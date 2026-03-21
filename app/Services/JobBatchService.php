<?php

namespace App\Services;

use App\Events\CsvUploadFailed;
use App\Events\CsvUploadFinished;
use App\Jobs\ProcessUpdate;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class JobBatchService
{
    /**
     * Dispatches the file processing as a batch of jobs.
     *
     * @param array $fileData The file data to be processed.
     * @return Batch The dispatched batch.
     *
     * @throws \Throwable If an error occurs during batch dispatching.
     */
    public function dispatchFileProcessing(array $fileData): Batch
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

        return Bus::batch($jobs)
            ->name('csv-upload')
            ->then(function (Batch $batch) {
                CsvUploadFinished::dispatch(
                    $batch->id,
                    $batch->progress(),
                    $batch->failedJobs,
                    $batch->finished(),
                    $batch->pendingJobs
                );
            })
            ->catch(function (Batch $batch, \Throwable $e) {
                Log::error("Batch {$batch->id} failed: {$e->getMessage()}");
                CsvUploadFailed::dispatch(
                    $batch->id,
                    $batch->progress(),
                    $batch->failedJobs,
                    $batch->finished(),
                    $batch->pendingJobs
                );
            })
            ->dispatch();
    }
}
