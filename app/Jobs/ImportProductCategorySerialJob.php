<?php

namespace App\Jobs;

use App\Imports\ProductCategorySerialImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

/**
 * Job untuk import Excel identifikasi buku: update field category_manual dan serial
 * berdasarkan KODE (book_code). File path relative ke disk 'local'.
 */
class ImportProductCategorySerialJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * @var string Path file relatif dari storage (disk local), e.g. imports/product-category-serial/xxx.xlsx
     */
    protected string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function handle(): void
    {
        $fullPath = Storage::disk('local')->path($this->filePath);

        if (!file_exists($fullPath)) {
            Log::error('ImportProductCategorySerialJob: File not found', ['path' => $this->filePath]);
            return;
        }

        try {
            Log::info('ImportProductCategorySerialJob: Starting import', ['path' => $this->filePath]);

            Excel::import(new ProductCategorySerialImport(), $this->filePath, 'local');

            Log::info('ImportProductCategorySerialJob: Import completed', ['path' => $this->filePath]);
        } catch (\Throwable $e) {
            Log::error('ImportProductCategorySerialJob: ' . $e->getMessage(), [
                'path' => $this->filePath,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
