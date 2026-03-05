<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\Periode;
use App\Models\Product;
use App\Models\Target;
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

    protected $clearFirst;

    public function __construct($clearFirst = false)
    {
        $this->clearFirst = $clearFirst;
    }

    public function handle(): void
    {
        $cacheKey = 'sync_targets_progress';

        try {
            Log::info('SynchronizeTargetsJob: Starting synchronization from PostgreSQL');

            // Clear data first if requested
            if ($this->clearFirst) {
                Target::truncate();
                Log::info('SynchronizeTargetsJob: Cleared all targets data');
            }

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

            $removeQuotes = function ($value) {
                if (empty($value)) return $value;
                $value = trim($value, " '\"\`");
                $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                return trim($value);
            };

            $validBranches = Branch::pluck('branch_code')->flip()->all();
            $validBooks = Product::pluck('book_code')->flip()->all();
            $validPeriods = Periode::pluck('period_code')->flip()->all();
            Log::info('SynchronizeTargetsJob: Pre-loaded referensi', [
                'branches' => count($validBranches),
                'books' => count($validBooks),
                'periods' => count($validPeriods),
            ]);

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
                    if (!isset($validBranches[$branchCode]) || !isset($validBooks[$bookCode]) || !isset($validPeriods[$periodCode])) {
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
                        Log::error('SynchronizeTargetsJob chunk error: ' . $e->getMessage());
                        $errors[] = $e->getMessage();
                        foreach ($batch as $row) {
                            $totalProcessed++;
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

                if ($totalProcessed > 0 && $totalProcessed % 10000 == 0) {
                    Log::info("SynchronizeTargetsJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
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

            Cache::forget('sync_targets_lock');

            // Save last sync timestamp
            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeTargetsJob: Synchronization completed', [
                'created' => $created,
                'updated' => $updated,
                'errors_count' => count($errors)
            ]);

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
