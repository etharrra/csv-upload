<?php

namespace App\Services;

use App\Enums\FileStatus;
use App\Models\File;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class FileService
{
    public function __construct(
        protected JobBatchService $jobBatchService
    ) {}

    /**
     * Retrieve paginated file records, newest first.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getFiles(int $perPage = 15): LengthAwarePaginator
    {
        return File::latest()->paginate($perPage);
    }

    /**
     * Process an uploaded CSV and dispatch batch jobs.
     *
     * @param UploadedFile $uploadedFile
     * @return File
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
     * @param UploadedFile $uploadedFile The uploaded file to be processed
     * @return array Returns an array containing csv_name, csv_name_og, csv_size, and data
     */
    protected function processFile(UploadedFile $uploadedFile): array
    {
        $csv_name_og = $uploadedFile->getClientOriginalName();
        $csv_name = $csv_name_og . '_' . uniqid() . '_' . time();
        $csv_size = $uploadedFile->getSize();

        $data = file($uploadedFile->getPathname());
        $data = $this->removeNonUTF8Characters($data);

        return compact('csv_name', 'csv_name_og', 'csv_size', 'data');
    }

    /**
     * Stores the file record in the database using mass assignment.
     *
     * @param Batch $batch The dispatched batch.
     * @param array $fileData Processed file metadata.
     * @return File The saved file record.
     */
    protected function storeFileRecord(Batch $batch, array $fileData): File
    {
        return File::create([
            'name' => $fileData['csv_name'],
            'name_og' => $fileData['csv_name_og'],
            'file_size' => $fileData['csv_size'],
            'status' => FileStatus::Pending,
            'job_batch_id' => $batch->id,
        ]);
    }

    /**
     * Removes any non-UTF8 characters from the given data.
     *
     * @param array|string $data The data to sanitize.
     * @return array|string The sanitized data.
     */
    private function removeNonUTF8Characters(array|string $data): array|string
    {
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }
}
