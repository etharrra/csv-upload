<?php

namespace App\Services;

use App\Enums\FileStatus;
use App\Events\CsvFileUploaded;
use App\Models\File;
use Aws\S3\S3Client;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    public function __construct(
        protected JobBatchService $jobBatchService
    ) {
    }

    /**
     * Retrieve paginated file records, newest first.
     */
    public function getFiles(int $perPage = 15): LengthAwarePaginator
    {
        return File::latest()->paginate($perPage);
    }

    /**
     * Process an uploaded CSV and dispatch batch jobs.
     */
    public function processAndStoreFile(UploadedFile $uploadedFile): File
    {
        $fileData = $this->processFile($uploadedFile);
        $batch = $this->jobBatchService->dispatchFileProcessing($fileData);

        return $this->storeFileRecord($batch, $fileData);
    }

    /**
     * Process the uploaded file into structured data.
     *
     * @param  UploadedFile  $uploadedFile The uploaded file to be processed
     * @return array Returns an array containing csv_name, csv_name_og, csv_size, and data
     */
    protected function processFile(UploadedFile $uploadedFile): array
    {
        $csv_name_og = $uploadedFile->getClientOriginalName();
        $csv_name = $csv_name_og.'_'.uniqid().'_'.time();
        $csv_size = $uploadedFile->getSize();

        $data = file($uploadedFile->getPathname());
        $data = $this->removeNonUTF8Characters($data);

        return compact('csv_name', 'csv_name_og', 'csv_size', 'data');
    }

    public function createPresignedUpload(string $originalName, int $size, string $contentType = null): array
    {
        $file = File::create([
            'name' => $this->uniqueStoredName($originalName),
            'name_og' => $originalName,
            'file_size' => $size,
            'storage_disk' => 's3',
            'status' => FileStatus::Pending,
        ]);

        try {
            $path = $this->buildUploadPath($file, $originalName);
            $file->update(['storage_path' => $path]);

            $expiresAt = now()->addMinutes((int) config('services.aws.presign_ttl_minutes', 10));
            $contentType = $contentType ?: 'text/csv';
            $upload = $this->temporaryUploadUrl($path, $expiresAt, $contentType);
        } catch (\Throwable $e) {
            $file->delete();

            throw $e;
        }

        return [
            'fileId' => $file->id,
            'key' => $path,
            'url' => $upload['url'],
            'headers' => $upload['headers'],
            'expiresAt' => $expiresAt->toISOString(),
            'file' => [
                'id' => $file->id,
                'name' => $file->name_og,
                'status' => $file->status->label(),
                'createdAt' => $file->created_at->toISOString(),
            ],
        ];
    }

    public function processStoredFile(File $file): File
    {
        if (! $file->storage_path) {
            throw new \RuntimeException("File {$file->id} does not have a storage path.");
        }

        $contents = Storage::disk($file->storage_disk ?: 's3')->get($file->storage_path);

        if (! is_string($contents)) {
            throw new \RuntimeException("File {$file->id} could not be read from storage.");
        }

        $data = preg_split('/\r\n|\r|\n/', $contents);
        $data = array_values(array_filter($data, static fn (?string $line): bool => $line !== null && $line !== ''));
        $data = $this->removeNonUTF8Characters($data);

        $batch = $this->jobBatchService->dispatchFileProcessing([
            'csv_name' => $file->name,
            'csv_name_og' => $file->name_og,
            'csv_size' => $file->file_size,
            'data' => $data,
        ]);

        $file->update([
            'job_batch_id' => $batch->id,
            'status' => FileStatus::Processing,
        ]);

        CsvFileUploaded::dispatch(
            $file->name_og,
            $batch->id,
            (string) $file->id,
            $file->created_at->toISOString(),
            FileStatus::Processing->label()
        );

        return $file->refresh();
    }

    public function cancelPendingUpload(File $file): void
    {
        if ($file->status !== FileStatus::Pending || $file->job_batch_id !== null) {
            return;
        }

        $file->delete();
    }

    /**
     * Stores the file record in the database using mass assignment.
     *
     * @param  Batch  $batch The dispatched batch.
     * @param  array  $fileData Processed file metadata.
     * @return File The saved file record.
     */
    protected function storeFileRecord(Batch $batch, array $fileData): File
    {
        return File::create([
            'name' => $fileData['csv_name'],
            'name_og' => $fileData['csv_name_og'],
            'file_size' => $fileData['csv_size'],
            'storage_disk' => 'local',
            'status' => FileStatus::Pending,
            'job_batch_id' => $batch->id,
        ]);
    }

    private function uniqueStoredName(string $originalName): string
    {
        return $originalName.'_'.uniqid().'_'.time();
    }

    private function buildUploadPath(File $file, string $originalName): string
    {
        $prefix = trim((string) config('services.aws.upload_prefix', 'csv-uploads'), '/');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'csv';
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = Str::slug($baseName) ?: 'upload';

        return "{$prefix}/{$file->id}/{$safeName}.{$extension}";
    }

    private function temporaryUploadUrl(string $path, \DateTimeInterface $expiresAt, string $contentType): array
    {
        $disk = Storage::disk('s3');

        if (method_exists($disk, 'temporaryUploadUrl')) {
            return $disk->temporaryUploadUrl($path, $expiresAt);
        }

        $config = [
            'version' => 'latest',
            'region' => config('filesystems.disks.s3.region'),
            'use_path_style_endpoint' => (bool) config('filesystems.disks.s3.use_path_style_endpoint'),
        ];

        if (config('filesystems.disks.s3.key') && config('filesystems.disks.s3.secret')) {
            $config['credentials'] = [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ];
        }

        if (config('filesystems.disks.s3.endpoint')) {
            $config['endpoint'] = config('filesystems.disks.s3.endpoint');
        }

        $client = new S3Client($config);

        $command = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $path,
            'ContentType' => $contentType,
        ]);

        $request = $client->createPresignedRequest($command, $expiresAt);

        return [
            'url' => (string) $request->getUri(),
            'headers' => [
                'Content-Type' => $contentType,
            ],
        ];
    }

    /**
     * Removes any non-UTF8 characters from the given data.
     *
     * @param  array|string  $data The data to sanitize.
     * @return array|string The sanitized data.
     */
    private function removeNonUTF8Characters(array|string $data): array|string
    {
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }
}
