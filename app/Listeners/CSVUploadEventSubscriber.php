<?php

namespace App\Listeners;

use App\Events\CsvUploadFailed;
use App\Events\CsvUploadFinished;
use App\Events\CsvUploadProgress;
use App\Models\File;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class CSVUploadEventSubscriber
{
    /**
     * Handle user CsvUploadProgress events.
     */
    public function handleCsvUploadProgress($event): void
    {
        $batchId = $event->batch_arr['batchId'];
        $file = File::where('job_batch_id', $batchId)->first();
        if ($file && $file->status == 0) {
            $file->status = 1;
            $file->save();
        }
    }

    /**
     * Handle user CsvUploadFinished events.
     */
    public function handleCsvUploadFinished($event): void
    {
        $batchId = $event->batch_arr['batchId'];
        $file = File::where('job_batch_id', $batchId)->first();
        if ($file->status == 1) {
            $file->status = 3;
            $file->save();
        }
    }

    /**
     * Handle user CsvUploadFailed events.
     */
    public function handleCsvUploadFailed($event): void
    {
        $batchId = $event->batch_arr['batchId'];
        $file = File::where('job_batch_id', $batchId)->first();
        if ($file) {
            $file->status = 2;
            $file->save();
        }
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            CsvUploadProgress::class => 'handleCsvUploadProgress',
            CsvUploadFinished::class => 'handleCsvUploadFinished',
            CsvUploadFailed::class => 'handleCsvUploadFailed',
        ];
    }
}
