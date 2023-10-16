<?php

namespace App\Services;

use App\Jobs\ProcessUpdate;
use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use App\Services\JobBatchService;

class FileService
{
    protected $jobBatchService;

    public function __construct(JobBatchService $jobBatchService)
    {
        $this->jobBatchService = $jobBatchService;
    }

    public function processAndStoreFile(UploadedFile $uploadedFile)
    {
        $fileData = $this->processFile($uploadedFile);
        $batch = $this->jobBatchService->dispatchFileProcessing($fileData);
        $file = $this->storeFileRecord(
            $batch,
            $fileData['csv_name'],
            $fileData['csv_name_og'],
            $fileData['csv_size']
        );

        return $file;
    }

    /**
     * Process the uploaded file.
     *
     * @param UploadedFile $uploadedFile The uploaded file to be processed
     * @throws Some_Exception_Class Description of exception
     * @return array Returns an array containing the processed data
     */
    protected function processFile(UploadedFile $uploadedFile)
    {
        $csv_name_og = $uploadedFile->getClientOriginalName();
        $csv_name = $csv_name_og . '_' . uniqid() . '_' . time();
        $csv_size = $uploadedFile->getSize();

        $data = file($uploadedFile->getPathname());
        $data = $this->removeNonUTF8Characters($data);

        return compact('csv_name', 'csv_name_og', 'csv_size', 'data');
    }

    /**
     * Stores the file record in the database.
     *
     * @param mixed $batch The batch object.
     * @param string $csv_name The name of the CSV file.
     * @param string $csv_name_og The original name of the CSV file.
     * @param int $csv_size The size of the CSV file.
     * @return File The saved file record.
     */
    protected function storeFileRecord($batch, $csv_name, $csv_name_og, $csv_size)
    {
        $file = new File();
        $file->name = $csv_name;
        $file->name_og = $csv_name_og;
        $file->file_size = $csv_size;
        $file->status = 0;
        $file->job_batch_id = $batch->id;
        $file->save();

        return $file;
    }

    /**
     * Removes any non-UTF8 characters from the given text.
     *
     * @param string $text The text to remove non-UTF8 characters from.
     * @return string The text with non-UTF8 characters removed.
     */
    private function removeNonUTF8Characters($text)
    {
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
}
