<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\Product;
use App\Models\SpBranch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeSpBranchesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /** Tidak dibatasi waktu (0 = sampai selesai). Default 60 detik bikin job sync 80k+ data terpotong. */
    public int $timeout = 0;

    /** Hanya satu job ini yang boleh ada di queue; mencegah double saat user sinkron berkali-kali sebelum worker jalan. */
    public function uniqueId(): string
    {
        return 'sync_sp_branches';
    }

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

            $chunkSize = 5000;
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
                    try {
                        DB::transaction(function () use ($batch, &$created, &$updated) {
                            foreach ($batch as $row) {
                                // 1) sp_branches: update/create (setelah truncate)
                                $query = DB::table('sp_branches')
                                    ->where('branch_code', $row['branch_code'])
                                    ->where('book_code', $row['book_code']);
                                if ($row['trans_date'] === null) {
                                    $query->whereNull('trans_date');
                                } else {
                                    $query->where('trans_date', $row['trans_date']);
                                }

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

                                if ($affected > 0) {
                                    $updated++;
                                } else {
                                    DB::table('sp_branches')->insert($row);
                                    $created++;
                                }

                                // 2) sp_branche_mains: tanpa truncate, pakai update/create rule yang sama
                                $mainQuery = DB::table('sp_branche_mains')
                                    ->where('branch_code', $row['branch_code'])
                                    ->where('book_code', $row['book_code']);
                                if ($row['trans_date'] === null) {
                                    $mainQuery->whereNull('trans_date');
                                } else {
                                    $mainQuery->where('trans_date', $row['trans_date']);
                                }

                                $mainAffected = $mainQuery->update([
                                    'ex_sp' => $row['ex_sp'],
                                    'ex_ftr' => $row['ex_ftr'],
                                    'ex_ret' => $row['ex_ret'],
                                    'ex_rec_pst' => $row['ex_rec_pst'],
                                    'ex_rec_gdg' => $row['ex_rec_gdg'],
                                    'ex_stock' => $row['ex_stock'],
                                    'active_data' => $row['active_data'],
                                    'updated_at' => $row['updated_at'],
                                ]);

                                if ($mainAffected <= 0) {
                                    DB::table('sp_branche_mains')->insert($row);
                                }
                            }
                        });
                    } catch (\Exception $e) {
                        Log::error('SynchronizeSpBranchesJob chunk error: ' . $e->getMessage());
                        $errors[] = $e->getMessage();
                    }
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
}
