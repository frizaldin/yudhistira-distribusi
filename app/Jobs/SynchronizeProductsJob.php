<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Staging\Master\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeProductsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $cacheKey = 'sync_products_progress';

        try {
            Log::info('SynchronizeProductsJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('m_book')->count();

            Cache::put($cacheKey, [
                'status' => 'running',
                'total' => $totalRecords,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'percentage' => 0
            ], now()->addHours(2));

            $chunkSize = 500;
            $created = 0;
            $updated = 0;
            $errors = [];

            $offset = 0;
            $totalProcessed = 0;

            while (true) {
                $bookRecords = DB::connection('pgsql')
                    ->table('m_book')
                    ->orderBy('book_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($bookRecords->isEmpty()) {
                    break;
                }

                foreach ($bookRecords as $bookData) {
                    try {
                        $book = (array) $bookData;

                        $removeQuotes = function ($value) {
                            if (empty($value)) return $value;
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value);
                        };

                        $bookCode = isset($book['book_code']) ? $removeQuotes($book['book_code']) : null;
                        $bookTitleRaw = isset($book['book_title']) ? $book['book_title'] : '';

                        $product = Product::where('book_code', $bookCode)->first();

                        $bookTitle = !empty(trim($bookTitleRaw)) ? $removeQuotes($bookTitleRaw) : 'Undefined';

                        $productData = [
                            'book_code' => $bookCode,
                            'book_title' => $bookTitle,
                            'pages' => $book['pages'] ?? null,
                            'paper_size' => $book['paper_size'] ?? null,
                            'paper_code' => $book['paper_code'] ?? null,
                            'c_color_code' => $book['c_color_code'] ?? null,
                            'sale_price' => $book['sale_price'] ?? null,
                            'writer' => $book['writer'] ?? null,
                            'book_tipe' => $book['book_tipe'] ?? null,
                            'isbn' => $book['isbn'] ?? null,
                            'mulok' => $book['mulok'] ?? 0,
                            'aktif' => $book['aktif'] ?? 1,
                            'jenjang' => $book['jenjang'] ?? null,
                            'category' => $book['category'] ?? null,
                        ];

                        if ($product) {
                            $product->update($productData);
                            $updated++;
                        } else {
                            Product::create($productData);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $bookCode = $book['book_code'] ?? 'unknown';
                        $errors[] = "Error pada book_code {$bookCode}: " . $e->getMessage();
                        Log::error("Sync Error untuk book_code {$bookCode}: " . $e->getMessage());
                    }
                }

                $offset += $chunkSize;

                $percentage = $totalRecords > 0 ? round(($totalProcessed / $totalRecords) * 100, 2) : 0;
                Cache::put($cacheKey, [
                    'status' => 'running',
                    'total' => $totalRecords,
                    'processed' => $totalProcessed,
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => count($errors),
                    'percentage' => $percentage
                ], now()->addHours(2));

                if ($totalProcessed % 1000 == 0) {
                    Log::info("SynchronizeProductsJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
                }
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'total' => $totalRecords,
                'processed' => $totalProcessed,
                'created' => $created,
                'updated' => $updated,
                'errors' => count($errors),
                'percentage' => 100,
                'completed_at' => now()->toDateTimeString()
            ], now()->addHours(2));
            
            // Save last sync timestamp
            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeProductsJob: Synchronization completed', [
                'created' => $created,
                'updated' => $updated,
                'errors_count' => count($errors)
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeProductsJob errors: ', $errors);
            }
        } catch (\Exception $e) {
            $currentProgress = Cache::get($cacheKey, []);
            Cache::put($cacheKey, [
                'status' => 'failed',
                'total' => $currentProgress['total'] ?? 0,
                'processed' => $currentProgress['processed'] ?? 0,
                'created' => $currentProgress['created'] ?? 0,
                'updated' => $currentProgress['updated'] ?? 0,
                'errors' => $currentProgress['errors'] ?? 0,
                'percentage' => $currentProgress['percentage'] ?? 0,
                'error_message' => $e->getMessage()
            ], now()->addHours(2));

            Log::error('SynchronizeProductsJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
