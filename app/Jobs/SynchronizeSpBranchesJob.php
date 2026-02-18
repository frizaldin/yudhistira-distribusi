<?php

namespace App\Jobs;

use App\Models\SpBranch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeSpBranchesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected $clearFirst;

    public function __construct($clearFirst = false)
    {
        $this->clearFirst = $clearFirst;
    }

    public function handle(): void
    {
        $cacheKey = 'sync_sp_branches_progress';
        $totalRecords = 0;
        $totalProcessed = 0;
        $created = 0;
        $errors = [];

        try {
            Log::info('SynchronizeSpBranchesJob: Starting synchronization from PostgreSQL', [
                'clear_first' => $this->clearFirst
            ]);

            // Get total records first and initialize progress immediately
            $totalRecords = DB::connection('pgsql')->table('r_sp_faktur_stok')->count();
            Log::info("SynchronizeSpBranchesJob: Total records to sync: {$totalRecords}");
            
            // Initialize progress immediately so progress bar appears right away
            Cache::put($cacheKey, [
                'status' => 'running',
                'total' => $totalRecords,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'percentage' => 0
            ], now()->addHours(2));

            // Clear all data first if requested
            if ($this->clearFirst) {
                Log::info('SynchronizeSpBranchesJob: Clearing all existing data');
                SpBranch::truncate();
            } else {
                // Set all existing data to active_data = 'no' before sync
                Log::info('SynchronizeSpBranchesJob: Setting all existing data to active_data = no');
                SpBranch::where('active_data', 'yes')->update(['active_data' => 'no']);
            }

            $chunkSize = 500;
            $offset = 0;

            while (true) {
                $spBranchRecords = DB::connection('pgsql')
                    ->table('r_sp_faktur_stok')
                    ->select('branch_code', 'book_code', 'ex_sp', 'ex_ftr', 'ex_ret', 'ex_rec_pst', 'ex_rec_gdg', 'ex_stock', 'trans_date')
                    ->orderBy('branch_code')
                    ->orderBy('book_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($spBranchRecords->isEmpty()) {
                    break;
                }

                foreach ($spBranchRecords as $spBranchData) {
                    try {
                        $data = (array) $spBranchData;

                        // Helper function to remove quotes and clean data
                        $removeQuotes = function ($value) {
                            if (empty($value) && $value !== '0') return null;
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value) === '' ? null : trim($value);
                        };

                        $branchCode = isset($data['branch_code']) ? $removeQuotes($data['branch_code']) : null;
                        $bookCode = isset($data['book_code']) ? $removeQuotes($data['book_code']) : null;

                        if (empty($branchCode) || empty($bookCode)) {
                            continue; // Skip if branch_code or book_code is empty
                        }

                        $convertNumeric = function ($value) {
                            if ($value === null || $value === '' || $value === 'null') {
                                return 0;
                            }
                            // Handle string numeric values
                            if (is_string($value)) {
                                $value = trim($value);
                                if ($value === '' || $value === 'null') {
                                    return 0;
                                }
                            }
                            return (float)$value;
                        };

                        // PostgreSQL uses 'ex_sp', MySQL also uses 'ex_sp'
                        $exSp = $convertNumeric($data['ex_sp'] ?? null);
                        $exFtr = $convertNumeric($data['ex_ftr'] ?? null);
                        $exRet = $convertNumeric($data['ex_ret'] ?? null);
                        $exRecPst = $convertNumeric($data['ex_rec_pst'] ?? null);
                        $exRecGdg = $convertNumeric($data['ex_rec_gdg'] ?? null);
                        $exStock = $convertNumeric($data['ex_stock'] ?? null);

                        $transDate = isset($data['trans_date']) && !empty($data['trans_date']) && $data['trans_date'] !== 'null'
                            ? $data['trans_date']
                            : null;

                        // Debug logging for first few records with non-zero ex_sp
                        if ($totalProcessed < 10 && $exSp > 0) {
                            Log::info("Sync Debug - branch_code: {$branchCode}, book_code: {$bookCode}", [
                                'ex_sp_raw' => $data['ex_sp'] ?? 'not_set',
                                'ex_sp_type' => gettype($data['ex_sp'] ?? null),
                                'ex_sp_converted' => $exSp,
                                'all_data_keys' => array_keys($data)
                            ]);
                        }

                        $spBranchDataArray = [
                            'branch_code' => $branchCode,
                            'book_code' => $bookCode,
                            'ex_sp' => $exSp,
                            'ex_ftr' => $exFtr,
                            'ex_ret' => $exRet,
                            'ex_rec_pst' => $exRecPst,
                            'ex_rec_gdg' => $exRecGdg,
                            'ex_stock' => $exStock,
                            'trans_date' => $transDate,
                            'active_data' => 'yes', // Set semua data baru sebagai active
                        ];

                        // Create semua data baru (tidak pakai upsert)
                        SpBranch::create($spBranchDataArray);
                        $created++;
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $branchCodeError = $data['branch_code'] ?? 'unknown';
                        $bookCodeError = $data['book_code'] ?? 'unknown';
                        $errors[] = "Error pada branch_code {$branchCodeError}, book_code {$bookCodeError}: " . $e->getMessage();
                        Log::error("Sync Error untuk branch_code {$branchCodeError}, book_code {$bookCodeError}: " . $e->getMessage());
                    }
                }

                $offset += $chunkSize;

                // Update progress after each chunk
                $percentage = $totalRecords > 0 ? round(($totalProcessed / $totalRecords) * 100, 2) : 0;
                Cache::put($cacheKey, [
                    'status' => 'running',
                    'total' => $totalRecords,
                    'processed' => $totalProcessed,
                    'created' => $created,
                    'updated' => 0, // SpBranch tidak pakai update, hanya create
                    'errors' => count($errors),
                    'percentage' => $percentage
                ], now()->addHours(2));

                if ($totalProcessed % 1000 == 0) {
                    Log::info("SynchronizeSpBranchesJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
                }
            }

            Log::info('SynchronizeSpBranchesJob: Synchronization completed', [
                'created' => $created,
                'errors_count' => count($errors),
                'total_processed' => $totalProcessed
            ]);

            // Mark as completed
            Cache::put($cacheKey, [
                'status' => 'completed',
                'total' => $totalRecords,
                'processed' => $totalProcessed,
                'created' => $created,
                'updated' => 0,
                'errors' => count($errors),
                'percentage' => 100,
                'completed_at' => now()->toDateTimeString()
            ], now()->addHours(2));

            // Save last sync timestamp
            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            if (count($errors) > 0) {
                Log::warning('SynchronizeSpBranchesJob errors: ', $errors);
            }
        } catch (\Exception $e) {
            // Mark as error in cache
            $cacheKey = 'sync_sp_branches_progress';
            Cache::put($cacheKey, [
                'status' => 'error',
                'total' => $totalRecords ?? 0,
                'processed' => $totalProcessed ?? 0,
                'created' => $created ?? 0,
                'updated' => 0,
                'errors' => count($errors ?? []),
                'percentage' => 0,
                'error_message' => $e->getMessage()
            ], now()->addHours(2));

            Log::error('SynchronizeSpBranchesJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }
}
