<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\CentralStock;
use App\Models\CentralStockKoli;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeCentralStocksJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $cacheKey = 'sync_central_stocks_progress';

        try {
            Log::info('SynchronizeCentralStocksJob: Starting synchronization from PostgreSQL');

            // Count total records from both tables
            $totalStockRecords = DB::connection('pgsql')->table('r_stock_pusat')->count();
            $totalKoliRecords = DB::connection('pgsql')->table('r_stock_pusat_koli')->count();
            $totalRecords = $totalStockRecords + $totalKoliRecords;

            Cache::put($cacheKey, [
                'status' => 'running',
                'total' => $totalRecords,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'koli_created' => 0,
                'koli_updated' => 0,
                'errors' => 0,
                'percentage' => 0
            ], now()->addHours(2));

            $chunkSize = 500;
            $created = 0;
            $updated = 0;
            $koliCreated = 0;
            $koliUpdated = 0;
            $errors = [];

            $offset = 0;
            $totalProcessed = 0;

            while (true) {
                $stockRecords = DB::connection('pgsql')
                    ->table('r_stock_pusat')
                    ->orderBy('branch_code')
                    ->orderBy('book_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($stockRecords->isEmpty()) {
                    break;
                }

                foreach ($stockRecords as $stockData) {
                    try {
                        $stock = (array) $stockData;

                        $removeQuotes = function ($value) {
                            if (empty($value)) return $value;
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value);
                        };

                        $convertNumeric = function ($value) {
                            if ($value === null || $value === '' || $value === 'null') {
                                return 0;
                            }
                            if (is_string($value)) {
                                $value = trim($value);
                                if ($value === '' || $value === 'null') {
                                    return 0;
                                }
                            }
                            return (float)$value;
                        };

                        $branchCode = isset($stock['branch_code']) ? $removeQuotes($stock['branch_code']) : null;
                        $bookCode = isset($stock['book_code']) ? $removeQuotes($stock['book_code']) : null;

                        if (empty($branchCode) || empty($bookCode)) {
                            continue; // Skip if branch_code or book_code is empty
                        }

                        // Skip jika book_code atau branch_code tidak ada di tabel referensi (hindari FK violation)
                        if (!Product::where('book_code', $bookCode)->exists()) {
                            Log::warning("Sync Stock skip: book_code {$bookCode} tidak ada di tabel books");
                            continue;
                        }
                        if (!Branch::where('branch_code', $branchCode)->exists()) {
                            Log::warning("Sync Stock skip: branch_code {$branchCode} tidak ada di tabel branches");
                            continue;
                        }

                        // Sync to central_stocks
                        $existingStock = CentralStock::where('branch_code', $branchCode)
                            ->where('book_code', $bookCode)
                            ->first();

                        $stockDataArray = [
                            'branch_code' => $branchCode,
                            'book_code' => $bookCode,
                            'exemplar' => $convertNumeric($stock['exemplar'] ?? 0),
                        ];

                        if ($existingStock) {
                            $existingStock->update($stockDataArray);
                            $updated++;
                        } else {
                            CentralStock::create($stockDataArray);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $branchCodeError = $stock['branch_code'] ?? 'unknown';
                        $bookCodeError = $stock['book_code'] ?? 'unknown';
                        $errors[] = "Error pada branch_code {$branchCodeError}, book_code {$bookCodeError}: " . $e->getMessage();
                        Log::error("Sync Error untuk branch_code {$branchCodeError}, book_code {$bookCodeError}: " . $e->getMessage());
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
                    'koli_created' => $koliCreated,
                    'koli_updated' => $koliUpdated,
                    'errors' => count($errors),
                    'percentage' => $percentage
                ], now()->addHours(2));

                if ($totalProcessed % 1000 == 0) {
                    Log::info("SynchronizeCentralStocksJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
                }
            }

            // Sync r_stock_pusat_koli to central_stock_kolis
            Log::info('SynchronizeCentralStocksJob: Starting synchronization of r_stock_pusat_koli');

            $koliOffset = 0;
            while (true) {
                $koliRecords = DB::connection('pgsql')
                    ->table('r_stock_pusat_koli')
                    ->orderBy('branch_code')
                    ->orderBy('book_code')
                    ->offset($koliOffset)
                    ->limit($chunkSize)
                    ->get();

                if ($koliRecords->isEmpty()) {
                    break;
                }

                foreach ($koliRecords as $koliData) {
                    try {
                        $koli = (array) $koliData;

                        $removeQuotes = function ($value) {
                            if (empty($value)) return $value;
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value);
                        };

                        $convertNumeric = function ($value) {
                            if ($value === null || $value === '' || $value === 'null') {
                                return 0;
                            }
                            if (is_string($value)) {
                                $value = trim($value);
                                if ($value === '' || $value === 'null') {
                                    return 0;
                                }
                            }
                            return (float)$value;
                        };

                        $branchCode = isset($koli['branch_code']) ? $removeQuotes($koli['branch_code']) : null;
                        $bookCode = isset($koli['book_code']) ? $removeQuotes($koli['book_code']) : null;

                        if (empty($branchCode) || empty($bookCode)) {
                            continue; // Skip if branch_code or book_code is empty
                        }

                        // Skip jika book_code atau branch_code tidak ada di tabel referensi (hindari FK violation)
                        if (!Product::where('book_code', $bookCode)->exists()) {
                            Log::warning("Sync Koli skip: book_code {$bookCode} tidak ada di tabel books");
                            continue;
                        }
                        if (!Branch::where('branch_code', $branchCode)->exists()) {
                            Log::warning("Sync Koli skip: branch_code {$branchCode} tidak ada di tabel branches");
                            continue;
                        }

                        // Sync to central_stock_kolis
                        $existingKoli = CentralStockKoli::where('branch_code', $branchCode)
                            ->where('book_code', $bookCode)
                            ->where('volume', $convertNumeric($koli['volume'] ?? 0))
                            ->where('koli', $convertNumeric($koli['koli'] ?? 0))
                            ->first();

                        $koliDataArray = [
                            'branch_code' => $branchCode,
                            'book_code' => $bookCode,
                            'volume' => $convertNumeric($koli['volume'] ?? 0),
                            'koli' => $convertNumeric($koli['koli'] ?? 0),
                        ];

                        if ($existingKoli) {
                            $existingKoli->update($koliDataArray);
                            $koliUpdated++;
                        } else {
                            CentralStockKoli::create($koliDataArray);
                            $koliCreated++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $branchCodeError = $koli['branch_code'] ?? 'unknown';
                        $bookCodeError = $koli['book_code'] ?? 'unknown';
                        $errors[] = "Error pada koli branch_code {$branchCodeError}, book_code {$bookCodeError}: " . $e->getMessage();
                        Log::error("Sync Koli Error untuk branch_code {$branchCodeError}, book_code {$bookCodeError}: " . $e->getMessage());
                    }
                }

                $koliOffset += $chunkSize;

                $percentage = $totalRecords > 0 ? round(($totalProcessed / $totalRecords) * 100, 2) : 0;
                Cache::put($cacheKey, [
                    'status' => 'running',
                    'total' => $totalRecords,
                    'processed' => $totalProcessed,
                    'created' => $created,
                    'updated' => $updated,
                    'koli_created' => $koliCreated,
                    'koli_updated' => $koliUpdated,
                    'errors' => count($errors),
                    'percentage' => $percentage
                ], now()->addHours(2));

                if ($totalProcessed % 1000 == 0) {
                    Log::info("SynchronizeCentralStocksJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
                }
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'total' => $totalRecords,
                'processed' => $totalProcessed,
                'created' => $created,
                'updated' => $updated,
                'koli_created' => $koliCreated,
                'koli_updated' => $koliUpdated,
                'errors' => count($errors),
                'percentage' => 100,
                'completed_at' => now()->toDateTimeString()
            ], now()->addHours(2));
            
            // Save last sync timestamp
            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeCentralStocksJob: Synchronization completed', [
                'created' => $created,
                'updated' => $updated,
                'koli_created' => $koliCreated,
                'koli_updated' => $koliUpdated,
                'errors_count' => count($errors)
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeCentralStocksJob errors: ', $errors);
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

            Log::error('SynchronizeCentralStocksJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
