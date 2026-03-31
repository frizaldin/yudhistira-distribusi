<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\Product;
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

    /** Tidak dibatasi waktu (0 = sampai selesai). Default 60 detik bikin job sync 80k+ data terpotong. */
    public int $timeout = 0;

    /**
     * Tanpa ShouldBeUnique: di server, middleware unique bisa bikin job antri tapi tidak pernah dieksekusi
     * (lock cache unik bentrok / stale). Pencegahan double sync sudah dari StagingController + sync_sp_branches_lock.
     */

    /**
     * @param bool $clearFirst Tidak dipakai lagi: job selalu hapus semua data dulu lalu isi pakai upsert.
     */
    public function __construct($clearFirst = true)
    {
        // Legacy: parameter diabaikan
    }

    public function handle(): void
    {
        $cacheKey = 'sync_sp_branches_progress';
        $totalRecords = 0;
        $totalProcessed = 0;
        $created = 0;
        $updated = 0;
        $errors = [];
        $missingBranchCodes = [];
        $missingBookCodes = [];

        try {
            Log::info('SynchronizeSpBranchesJob: Starting synchronization from PostgreSQL (sp_branches truncate + update/create, sp_branche_mains update/create)');

            // Sesuai kebutuhan: sp_branches di-truncate dulu.
            Log::info('SynchronizeSpBranchesJob: Clearing sp_branches data');
            SpBranch::truncate();

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

            $validBranches = Branch::pluck('branch_code')->flip()->all();
            $validBooks = Product::pluck('book_code')->flip()->all();
            Log::info('SynchronizeSpBranchesJob: Pre-loaded referensi', [
                'branches' => count($validBranches),
                'books' => count($validBooks),
            ]);

            $removeQuotes = function ($value) {
                if (empty($value) && $value !== '0') return null;
                $value = trim($value, " '\"\`");
                $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                return trim($value) === '' ? null : trim($value);
            };
            $convertNumeric = function ($value) {
                if ($value === null || $value === '' || $value === 'null') return 0;
                if (is_string($value)) {
                    $value = trim($value);
                    if ($value === '' || $value === 'null') return 0;
                }
                return (float) $value;
            };

            $chunkSize = 1000;
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

                $now = now();
                $batch = [];
                foreach ($spBranchRecords as $spBranchData) {
                    $data = (array) $spBranchData;
                    $branchCode = isset($data['branch_code']) ? $removeQuotes($data['branch_code']) : null;
                    $bookCode = isset($data['book_code']) ? $removeQuotes($data['book_code']) : null;

                    if (empty($branchCode) || empty($bookCode)) {
                        continue;
                    }
                    if (!isset($validBranches[$branchCode]) || !isset($validBooks[$bookCode])) {
                        if (!isset($validBranches[$branchCode])) {
                            $missingBranchCodes[$branchCode] = true;
                        }
                        if (!isset($validBooks[$bookCode])) {
                            $missingBookCodes[$bookCode] = true;
                        }
                        $totalProcessed++;
                        continue;
                    }

                    $transDate = isset($data['trans_date']) && !empty($data['trans_date']) && $data['trans_date'] !== 'null'
                        ? $data['trans_date']
                        : null;

                    $batch[] = [
                        'branch_code' => $branchCode,
                        'book_code' => $bookCode,
                        'ex_sp' => $convertNumeric($data['ex_sp'] ?? null),
                        'ex_ftr' => $convertNumeric($data['ex_ftr'] ?? null),
                        'ex_ret' => $convertNumeric($data['ex_ret'] ?? null),
                        'ex_rec_pst' => $convertNumeric($data['ex_rec_pst'] ?? null),
                        'ex_rec_gdg' => $convertNumeric($data['ex_rec_gdg'] ?? null),
                        'ex_stock' => $convertNumeric($data['ex_stock'] ?? null),
                        'trans_date' => $transDate,
                        'active_data' => 'yes',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($batch)) {
                    // Optimisasi: bulk upsert per chunk (lebih cepat & lebih sedikit lock)
                    $this->runWithDeadlockRetry(function () use ($batch, &$updated) {
                        DB::transaction(function () use ($batch, &$updated) {
                            $batchWithDate = [];
                            $batchNullDate = [];

                            foreach ($batch as $row) {
                                if ($row['trans_date'] === null) {
                                    $batchNullDate[] = $row;
                                } else {
                                    $batchWithDate[] = $row;
                                }
                            }

                            // 1) sp_branches: upsert jika trans_date tidak null (butuh unique key (branch_code, book_code, trans_date))
                            if (!empty($batchWithDate)) {
                                DB::table('sp_branches')->upsert(
                                    $batchWithDate,
                                    ['branch_code', 'book_code', 'trans_date'],
                                    ['ex_sp', 'ex_ftr', 'ex_ret', 'ex_rec_pst', 'ex_rec_gdg', 'ex_stock', 'active_data', 'updated_at']
                                );
                            }

                            // Fallback rare-case: trans_date null (MySQL UNIQUE memperbolehkan banyak NULL, jadi tidak aman untuk upsert)
                            if (!empty($batchNullDate)) {
                                foreach ($batchNullDate as $row) {
                                    $query = DB::table('sp_branches')
                                        ->where('branch_code', $row['branch_code'])
                                        ->where('book_code', $row['book_code'])
                                        ->whereNull('trans_date');

                                    $affected = $query->update([
                                        'ex_sp' => $row['ex_sp'],
                                        'ex_ftr' => $row['ex_ftr'],
                                        'ex_ret' => $row['ex_ret'],
                                        'ex_rec_pst' => $row['ex_rec_pst'],
                                        'ex_rec_gdg' => $row['ex_rec_gdg'],
                                        'ex_stock' => $row['ex_stock'],
                                        'active_data' => $row['active_data'],
                                        'updated_at' => $row['updated_at'],
                                    ]);

                                    if ($affected <= 0) {
                                        DB::table('sp_branches')->insert($row);
                                    }
                                }
                            }

                            // 2) sp_branche_mains: unique (branch_code, book_code)
                            // Ambil 1 row terakhir per key (branch_code, book_code) agar upsert tidak bolak-balik di chunk yang sama.
                            $mains = [];
                            foreach ($batch as $row) {
                                $k = $row['branch_code'] . '|' . $row['book_code'];
                                $mains[$k] = $row;
                            }
                            $mains = array_values($mains);

                            if (!empty($mains)) {
                                DB::table('sp_branche_mains')->upsert(
                                    $mains,
                                    ['branch_code', 'book_code'],
                                    ['ex_sp', 'ex_ftr', 'ex_ret', 'ex_rec_pst', 'ex_rec_gdg', 'ex_stock', 'active_data', 'updated_at', 'trans_date']
                                );
                            }

                            // upsert tidak memberi angka created/updated yang akurat, jadi treat sebagai updated untuk progres.
                            $updated += count($batch);
                        });
                    }, $errors);
                }
                $totalProcessed += count($spBranchRecords);

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

                if ($totalProcessed > 0 && $totalProcessed % 10000 == 0) {
                    Log::info("SynchronizeSpBranchesJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
                }
            }

            $missingBranchList = array_keys($missingBranchCodes);
            $missingBookList = array_keys($missingBookCodes);
            if (count($missingBranchList) > 0 || count($missingBookList) > 0) {
                Log::warning('SynchronizeSpBranchesJob: Kode tidak ditemukan di referensi', [
                    'missing_branch_codes' => $missingBranchList,
                    'missing_book_codes' => $missingBookList,
                ]);
            }

            Log::info('SynchronizeSpBranchesJob: Synchronization completed', [
                'created' => $created,
                'errors_count' => count($errors),
                'total_processed' => $totalProcessed
            ]);

            // Mark as completed (termasuk list kode yang tidak ditemukan untuk alert di UI)
            Cache::put($cacheKey, [
                'status' => 'completed',
                'total' => $totalRecords,
                'processed' => $totalProcessed,
                'created' => $created,
                'updated' => $updated,
                'errors' => count($errors),
                'percentage' => 100,
                'completed_at' => now()->toDateTimeString(),
                'missing_branch_codes' => $missingBranchList,
                'missing_book_codes' => $missingBookList,
            ], now()->addHours(2));

            Cache::forget('sync_sp_branches_lock');

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
                'updated' => $updated ?? 0,
                'errors' => count($errors ?? []),
                'percentage' => 0,
                'error_message' => $e->getMessage()
            ], now()->addHours(2));

            Cache::forget('sync_sp_branches_lock');

            Log::error('SynchronizeSpBranchesJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Retry query saat deadlock (SQLSTATE 40001 / error 1213).
     *
     * @param callable():void $callback
     * @param array<int,string> $errors
     */
    protected function runWithDeadlockRetry(callable $callback, array &$errors): void
    {
        $maxAttempts = 5;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $callback();
                return;
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $isDeadlock = str_contains($message, 'Deadlock found when trying to get lock')
                    || str_contains($message, 'SQLSTATE[40001]')
                    || str_contains($message, '1213');

                if (!$isDeadlock || $attempt === $maxAttempts) {
                    Log::error('SynchronizeSpBranchesJob chunk error: ' . $message);
                    $errors[] = $message;
                    return;
                }

                // Exponential backoff ringan: 100ms, 200ms, 400ms, 800ms
                usleep((int) (100000 * (2 ** ($attempt - 1))));
            }
        }
    }
}
