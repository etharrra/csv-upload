<?php

namespace App\Jobs;

use App\Events\CsvUploadFailed;
use App\Events\CsvUploadFinished;
use App\Events\CsvUploadProgress;
use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUpdate implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $rows;
    private $header;
    public $batch;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct($rows, $header)
    {
        $this->rows = $rows;
        $this->header = $header;
        $this->batch = $this->batch();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batch = $this->batch();

        if ($batch->failedJobs) {
            $batch->cancel();
            CsvUploadFailed::dispatch($batch->id, $batch->progress(), $batch->failedJobs, $batch->finished(), $batch->pendingJobs);
        }
        if ($batch->cancelled()) {
            return;
        }

        CsvUploadProgress::dispatch($batch->id, $batch->progress(), $batch->failedJobs, $batch->finished(), $batch->pendingJobs);

        foreach ($this->rows as $data) {
            Product::updateOrCreate(
                [
                    'unique_key' => $data[array_search('UNIQUE_KEY', $this->header)]
                ],
                [
                    'unique_key' => $data[array_search('UNIQUE_KEY', $this->header)],
                    'product_title' => $data[array_search('PRODUCT_TITLE', $this->header)],
                    'product_description' => $data[array_search('PRODUCT_DESCRIPTION', $this->header)],
                    'style' => $data[array_search('STYLE#', $this->header)],
                    'sanmar_mainframe_color' => $data[array_search('SANMAR_MAINFRAME_COLOR', $this->header)],
                    'size' => $data[array_search('SIZE', $this->header)],
                    'color_name' => $data[array_search('COLOR_NAME', $this->header)],
                    'piece_price' => $data[array_search('PIECE_PRICE', $this->header)],
                ]
            );
        }

        CsvUploadProgress::dispatch($batch->id, $batch->progress(), $batch->failedJobs, $batch->finished(), $batch->pendingJobs);

        if ($batch->pendingJobs == 1) {
            CsvUploadFinished::dispatch($batch->id, $batch->progress(), $batch->failedJobs, $batch->finished(), $batch->pendingJobs);
        }
    }
}
