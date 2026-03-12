<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeTargetsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /** Tidak dibatasi waktu (0 = sampai selesai). Default 60 detik bikin job sync 170k+ data terpotong. */
    public int $timeout = 0;

    /** Jumlah percobaan ulang jika job gagal (timeout/error sementara). */
    public int $tries = 3;

    /** Detik jeda sebelum retry. */
    public int $backOff = 60;

    public function __construct() {}

    public function handle(): void
    {
        $cacheKey = 'sync_targets_progress';

        try {
            // Hanya error yang dicatat ke log; tidak ada log untuk alur sukses.
            // Hapus semua data di targets dulu, baru create dari sumber
            DB::table('targets')->truncate();

            $totalRecords = DB::connection('pgsql')->table('r_target_buku')->count();

            Cache::put($cacheKey, [
                'status' => 'running',
                'total' => $totalRecords,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'percentage' => 0
            ], now()->addHours(2));

            $chunkSize = 5000;
            $created = 0;
            $updated = 0;
            $errors = [];
            $missingBookCodesAll = [];
            $missingBranchCodesAll = [];

            $removeQuotes = function ($value) {
                if (empty($value)) return $value;
                $value = trim($value, " '\"\`");
                $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                return trim($value);
            };

            $offset = 0;
            $totalProcessed = 0;

            while (true) {
                $targetRecords = DB::connection('pgsql')
                    ->table('r_target_buku')
                    ->orderBy('branch_code')
                    ->orderBy('book_code')
                    ->orderBy('period_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($targetRecords->isEmpty()) {
                    break;
                }

                $now = now();
                $batch = [];
                foreach ($targetRecords as $targetData) {
                    $target = (array) $targetData;
                    $branchCode = isset($target['branch_code']) ? $removeQuotes($target['branch_code']) : null;
                    $bookCode = isset($target['book_code']) ? $removeQuotes($target['book_code']) : null;
                    $periodCode = isset($target['period_code']) ? $removeQuotes($target['period_code']) : null;

                    if (empty($branchCode) || empty($bookCode) || empty($periodCode)) {
                        continue;
                    }

                    $batch[] = [
                        'branch_code' => $branchCode,
                        'book_code' => $bookCode,
                        'period_code' => $periodCode,
                        'exemplar' => (int) ($target['exemplar'] ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($batch)) {
                    try {
                        DB::transaction(function () use ($batch, $now) {
                            DB::table('targets')->upsert(
                                $batch,
                                ['branch_code', 'book_code', 'period_code'],
                                ['exemplar', 'updated_at']
                            );
                        });
                        $totalProcessed += count($batch);
                        $created += count($batch);
                    } catch (\Exception $e) {
                        // Kalau batch gagal (FK constraint): list book_code & branch_code yang tidak ada, lalu fallback per-row
                        $bookCodesInBatch = array_unique(array_column($batch, 'book_code'));
                        $existingInBooks = DB::table('books')->whereIn('book_code', $bookCodesInBatch)->pluck('book_code')->toArray();
                        $missingBookCodes = array_values(array_diff($bookCodesInBatch, $existingInBooks));
                        if (!empty($missingBookCodes)) {
                            Log::warning('SynchronizeTargetsJob: book_codes not in books: ' . implode(',', $missingBookCodes));
                            $missingBookCodesAll = array_values(array_unique(array_merge($missingBookCodesAll, $missingBookCodes)));
                        }
                        $branchCodesInBatch = array_unique(array_column($batch, 'branch_code'));
                        $existingInBranches = DB::table('branches')->whereIn('branch_code', $branchCodesInBatch)->pluck('branch_code')->toArray();
                        $missingBranchCodes = array_values(array_diff($branchCodesInBatch, $existingInBranches));
                        if (!empty($missingBranchCodes)) {
                            Log::warning('SynchronizeTargetsJob: branch_codes not in branches: ' . implode(',', $missingBranchCodes));
                            $missingBranchCodesAll = array_values(array_unique(array_merge($missingBranchCodesAll, $missingBranchCodes)));
                        }
                        $errMsg = $e->getMessage();
                        if (!empty($missingBookCodes)) {
                            $errMsg = 'Chunk: book_codes not in books: ' . implode(',', $missingBookCodes);
                        } elseif (!empty($missingBranchCodes)) {
                            $errMsg = 'Chunk: branch_codes not in branches: ' . implode(',', $missingBranchCodes);
                        }
                        $errors[] = $errMsg;
                        foreach ($batch as $row) {
                            try {
                                DB::table('targets')->upsert(
                                    [$row],
                                    ['branch_code', 'book_code', 'period_code'],
                                    ['exemplar', 'updated_at']
                                );
                                $created++;
                            } catch (\Exception $eRow) {
                                $isFkBookCode = str_contains($eRow->getMessage(), 'targets_book_code_foreign');
                                $isFkBranchCode = str_contains($eRow->getMessage(), 'targets_branch_code_foreign');
                                if (!$isFkBookCode && !$isFkBranchCode) {
                                    $errors[] = $eRow->getMessage();
                                    Log::error('SynchronizeTargetsJob row error: ' . $eRow->getMessage(), [
                                        'branch_code' => $row['branch_code'] ?? null,
                                        'book_code' => $row['book_code'] ?? null,
                                        'period_code' => $row['period_code'] ?? null,
                                    ]);
                                }
                            } finally {
                                $totalProcessed++;
                            }
                        }
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
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'total' => $totalRecords,
                'processed' => $totalProcessed,
                'created' => $created,
                'updated' => $updated,
                'errors' => count($errors),
                'percentage' => 100,
                'completed_at' => now()->toDateTimeString(),
                'missing_book_codes' => array_values(array_unique($missingBookCodesAll)),
                'missing_branch_codes' => array_values(array_unique($missingBranchCodesAll)),
            ], now()->addHours(2));

            Cache::forget('sync_targets_lock');

            // Save last sync timestamp
            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            if (count($errors) > 0) {
                Log::warning('SynchronizeTargetsJob errors: ', $errors);
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

            Cache::forget('sync_targets_lock');

            Log::error('SynchronizeTargetsJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
