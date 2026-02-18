<?php

namespace App\Jobs;

use App\Models\Periode;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizePeriodesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $cacheKey = 'sync_periodes_progress';

        try {
            Log::info('SynchronizePeriodesJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('m_period')->count();

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
                $periodRecords = DB::connection('pgsql')
                    ->table('m_period')
                    ->orderBy('period_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($periodRecords->isEmpty()) {
                    break;
                }

                foreach ($periodRecords as $periodData) {
                    try {
                        $period = (array) $periodData;

                        $removeQuotes = function ($value) {
                            if (empty($value)) return $value;
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value);
                        };

                        $periodCode = isset($period['period_code']) ? $removeQuotes($period['period_code']) : null;

                        $existingPeriod = Periode::where('period_code', $periodCode)->first();

                        $periodDataArray = [
                            'period_code' => $periodCode,
                            'period_name' => isset($period['period_name']) ? $removeQuotes($period['period_name']) : null,
                            'from_date' => isset($period['from_date']) && !empty($period['from_date']) ? $period['from_date'] : null,
                            'to_date' => isset($period['to_date']) && !empty($period['to_date']) ? $period['to_date'] : null,
                            'period_before' => isset($period['period_before']) ? $removeQuotes($period['period_before']) : null,
                            'status' => isset($period['status']) ? ($period['status'] ? 1 : 0) : 1,
                            'period_codes' => isset($period['period_codes']) ? $removeQuotes($period['period_codes']) : null,
                            'branch_code' => isset($period['branch_code']) ? $removeQuotes($period['branch_code']) : null,
                            'tanggal_aktif' => isset($period['tanggal_aktif']) && !empty($period['tanggal_aktif']) ? $period['tanggal_aktif'] : null,
                        ];

                        if ($existingPeriod) {
                            $existingPeriod->update($periodDataArray);
                            $updated++;
                        } else {
                            Periode::create($periodDataArray);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $periodCodeError = $period['period_code'] ?? 'unknown';
                        $errors[] = "Error pada period_code {$periodCodeError}: " . $e->getMessage();
                        Log::error("Sync Error untuk period_code {$periodCodeError}: " . $e->getMessage());
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
                    Log::info("SynchronizePeriodesJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
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

            Log::info('SynchronizePeriodesJob: Synchronization completed', [
                'created' => $created,
                'updated' => $updated,
                'errors_count' => count($errors)
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizePeriodesJob errors: ', $errors);
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

            Log::error('SynchronizePeriodesJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
