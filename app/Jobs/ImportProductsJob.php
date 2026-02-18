<?php

namespace App\Jobs;

use App\Imports\ProductsImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ImportProductsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected $filePath;
    protected $disk;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $disk = 'local')
    {
        $this->filePath = $filePath;
        $this->disk = $disk;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Import dengan chunking per 100 data
        Excel::import(new ProductsImport, $this->filePath, $this->disk);
    }
}
