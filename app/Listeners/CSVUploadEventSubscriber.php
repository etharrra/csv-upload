<?php

namespace App\Listeners;

use App\Enums\FileStatus;
use App\Events\CsvUploadFailed;
use App\Events\CsvUploadFinished;
use App\Events\CsvUploadProgress;
use App\Models\File;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class CSVUploadEventSubscriber
{
    /**
     * Handle CsvUploadProgress events.
     */
    public function handleCsvUploadProgress(CsvUploadProgress $event): void
    {
        $file = File::where('job_batch_id', $event->batch_arr['batchId'])->first();

        if ($file && $file->status === FileStatus::Pending) {
            $file->status = FileStatus::Processing;
            $file->save();
        }
    }

    /**
     * Handle CsvUploadFinished events.
     */
    public function handleCsvUploadFinished(CsvUploadFinished $event): void
    {
        $file = File::where('job_batch_id', $event->batch_arr['batchId'])->first();

        if ($file && $file->status === FileStatus::Processing) {
            $file->status = FileStatus::Completed;
            $file->save();
        }
    }

    /**
     * Handle CsvUploadFailed events.
     */
    public function handleCsvUploadFailed(CsvUploadFailed $event): void
    {
        $file = File::where('job_batch_id', $event->batch_arr['batchId'])->first();

        if ($file) {
            $file->status = FileStatus::Failed;
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
