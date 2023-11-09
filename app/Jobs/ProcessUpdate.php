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
    // public $batch;

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
        // $this->batch = $this->batch();
    }

    /**
     * Handle the function.
     *
     * @return void
     */
    public function handle(): void
    {
        if (!$this->batch()->failedJobs && !$this->batch()->cancelled()) {
            $this->updateProducts();

            if ($this->batch()->pendingJobs == 1) {
                $this->handleFinishedBatch();
            }
        } else {
            Log::info("Batch {$this->batch()->id} failed with {$this->batch()->failedJobs} failed jobs and {$this->batch()->pendingJobs} pending jobs.");
            $this->handleFailedBatch();
        }
    }

    /**
     * Handles a failed batch().
     *
     * @return void
     */
    protected function handleFailedBatch(): void
    {
        $this->batch()->cancel();
        CsvUploadFailed::dispatch(
            $this->batch()->id,
            $this->batch()->progress(),
            $this->batch()->failedJobs,
            $this->batch()->finished(),
            $this->batch()->pendingJobs
        );
    }

    /**
     * Updates the products.
     *
     * Dispatches a CsvUploadProgress event with the batch() ID, progress, failed jobs, 
     * finished flag, and pending jobs.
     * 
     * Iterates over each row in the rows array and calls the updateProduct function 
     * with the row data.
     * 
     * Dispatches a CsvUploadProgress event again with the same parameters.
     *
     * @return void
     */
    protected function updateProducts(): void
    {
        $batchId = $this->batch()->id;
        $progress = $this->batch()->progress();
        $failedJobs = $this->batch()->failedJobs;
        $finished = $this->batch()->finished();
        $pendingJobs = $this->batch()->pendingJobs;

        CsvUploadProgress::dispatch($batchId, $progress, $failedJobs, $finished, $pendingJobs);

        foreach ($this->rows as $data) {
            $this->updateProduct($data);
        }

        CsvUploadProgress::dispatch($batchId, $progress, $failedJobs, $finished, $pendingJobs);
    }

    /**
     * Updates a product with the given data.
     *
     * @param array $data The data to update the product with.
     * @throws \Some_Exception_Class Description of the exception that can be thrown.
     * @return void
     */
    protected function updateProduct(array $data): void
    {
        $productData = [
            'unique_key' => $data[$this->headerIndex('UNIQUE_KEY')],
            'product_title' => $data[$this->headerIndex('PRODUCT_TITLE')],
            'product_description' => $data[$this->headerIndex('PRODUCT_DESCRIPTION')],
            'style' => $data[$this->headerIndex('STYLE#')],
            'sanmar_mainframe_color' => $data[$this->headerIndex('SANMAR_MAINFRAME_COLOR')],
            'size' => $data[$this->headerIndex('SIZE')],
            'color_name' => $data[$this->headerIndex('COLOR_NAME')],
            'piece_price' => $data[$this->headerIndex('PIECE_PRICE')],
        ];

        Product::updateOrCreate(['unique_key' => $productData['unique_key']], $productData);
    }

    /**
     * Retrieves the index of the given column name in the header array.
     *
     * @param string $columnName The name of the column to search for.
     * @return int The index of the column in the header array, or false if not found.
     */
    protected function headerIndex(string $columnName): int
    {
        return array_search($columnName, $this->header);
    }

    /**
     * Handle the finished batch.
     *
     * @return void
     */
    protected function handleFinishedBatch(): void
    {
        CsvUploadFinished::dispatch(
            $this->batch()->id,
            $this->batch()->progress(),
            $this->batch()->failedJobs,
            $this->batch()->finished(),
            $this->batch()->pendingJobs
        );
    }
}
