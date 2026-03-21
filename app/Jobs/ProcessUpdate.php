<?php

namespace App\Jobs;

use App\Events\CsvUploadProgress;
use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessUpdate implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $header
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        foreach ($this->rows as $data) {
            $this->updateProduct($data);
        }

        CsvUploadProgress::dispatch(
            $this->batch()->id,
            $this->batch()->progress(),
            $this->batch()->failedJobs,
            $this->batch()->finished(),
            $this->batch()->pendingJobs
        );
    }

    /**
     * Updates a product with the given data.
     *
     * @param array $data The data to update the product with.
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
     * @return int|false The index of the column in the header array, or false if not found.
     */
    protected function headerIndex(string $columnName): int|false
    {
        return array_search($columnName, $this->header);
    }
}
