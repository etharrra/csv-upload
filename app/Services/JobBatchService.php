<?php

namespace App\Services;

use App\Jobs\ProcessUpdate;
use Illuminate\Support\Facades\Bus;

class JobBatchService
{
    /**
     * Dispatches the file processing.
     *
     * @param array $fileData The file data to be processed.
     * @throws \Some_Exception_Class If an error occurs during processing.
     * @return void
     */
    public function dispatchFileProcessing(array $fileData)
    {
        $chunks = array_chunk($fileData['data'], 1000);
        $header = [];
        $jobs = [];

        foreach ($chunks as $key => $chunk) {
            $rows = array_map('str_getcsv', $chunk);

            if ($key === 0) {
                $header = $rows[0];
                $header[0] = trim($header[0], "\xEF\xBB\xBF");
                unset($rows[0]);
            }
            $jobs[] = new ProcessUpdate($rows, $header);
        }

        return Bus::batch($jobs)->dispatch();
    }
}
