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

            $chunkSize = 500;
            $created = 0;
            $updated = 0;
            $errors = [];

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

                foreach ($targetRecords as $targetData) {
                    try {
                        $target = (array) $targetData;

                        $removeQuotes = function ($value) {
                            if (empty($value)) return $value;
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value);
                        };

                        $branchCode = isset($target['branch_code']) ? $removeQuotes($target['branch_code']) : null;
                        $bookCode = isset($target['book_code']) ? $removeQuotes($target['book_code']) : null;
                        $periodCode = isset($target['period_code']) ? $removeQuotes($target['period_code']) : null;

                        if (empty($branchCode) || empty($bookCode) || empty($periodCode)) {
                            continue;
                        }

                        // Skip jika referensi tidak ada (hindari FK violation)
                        if (!Branch::where('branch_code', $branchCode)->exists()) {
                            Log::warning("Sync Target skip: branch_code {$branchCode} tidak ada di tabel branches");
                            continue;
                        }
                        if (!Product::where('book_code', $bookCode)->exists()) {
                            Log::warning("Sync Target skip: book_code {$bookCode} tidak ada di tabel books");
                            continue;
                        }
                        if (!Periode::where('period_code', $periodCode)->exists()) {
                            Log::warning("Sync Target skip: period_code {$periodCode} tidak ada di tabel periods");
                            continue;
                        }

                        $existingTarget = Target::where('branch_code', $branchCode)
                            ->where('book_code', $bookCode)
                            ->where('period_code', $periodCode)
                            ->first();

                        $targetDataArray = [
                            'branch_code' => $branchCode,
                            'book_code' => $bookCode,
                            'period_code' => $periodCode,
                            'exemplar' => $target['exemplar'] ?? 0,
                        ];

                        if ($existingTarget) {
                            $existingTarget->update($targetDataArray);
                            $updated++;
                        } else {
                            Target::create($targetDataArray);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $branchCodeError = $target['branch_code'] ?? 'unknown';
                        $bookCodeError = $target['book_code'] ?? 'unknown';
                        $periodCodeError = $target['period_code'] ?? 'unknown';
                        $errors[] = "Error pada branch_code {$branchCodeError}, book_code {$bookCodeError}, period_code {$periodCodeError}: " . $e->getMessage();
                        Log::error("Sync Error untuk branch_code {$branchCodeError}, book_code {$bookCodeError}, period_code {$periodCodeError}: " . $e->getMessage());
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

            Log::error('SynchronizeTargetsJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
