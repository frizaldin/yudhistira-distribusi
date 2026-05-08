<?php

namespace App\Jobs;

use App\Models\MoveWarehouse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SynchronizeMoveWarehousesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 0;

    public function handle(): void
    {
        $cacheKey = 'sync_m_pindah_gudang_progress';

        try {
            Log::info('SynchronizeMoveWarehousesJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('m_pindah_gudang')->count();

            Cache::put($cacheKey, [
                'status' => 'running',
                'total' => $totalRecords,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'percentage' => 0,
            ], now()->addHours(2));

            $chunkSize = 500;
            $created = 0;
            $updated = 0;
            $errors = [];

            $offset = 0;
            $totalProcessed = 0;

            while (true) {
                $records = DB::connection('pgsql')
                    ->table('m_pindah_gudang')
                    ->orderBy('move_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($records->isEmpty()) {
                    break;
                }

                foreach ($records as $rowData) {
                    try {
                        $row = (array) $rowData;

                        $removeQuotes = function ($value) {
                            if (empty($value)) return $value;
                            $value = trim($value, " '\`\"");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value);
                        };

                        $primaryId = isset($row['move_code']) ? $removeQuotes($row['move_code']) : null;

                        if (empty($primaryId)) {
                            continue;
                        }

                        $existing = MoveWarehouse::where('move_code', $primaryId)->first();

                        $payload = [
                            'move_code' => $primaryId,
                            'branch_code' => isset($row['branch_code']) ? $removeQuotes($row['branch_code']) : null,
                            'info' => isset($row['info']) ? $removeQuotes($row['info']) : null,
                            'mova_date' => isset($row['mova_date']) && !empty($row['mova_date']) ? $row['mova_date'] : null,
                            'officer' => isset($row['officer']) ? $removeQuotes($row['officer']) : null,
                            'printed' => isset($row['printed']) ? (int) $row['printed'] : 0,
                            'status' => isset($row['status']) ? (int) $row['status'] : 0,
                            'user_id' => isset($row['user_id']) ? $removeQuotes($row['user_id']) : null,
                            'whouse_head' => isset($row['whouse_head']) ? $removeQuotes($row['whouse_head']) : null,
                        ];

                        if ($existing) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            MoveWarehouse::create($payload);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $code = $row['move_code'] ?? 'unknown';
                        $errors[] = "Error pada move_code {$code}: " . $e->getMessage();
                        Log::error("Sync MoveWarehouse move_code {$code}: " . $e->getMessage());
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
                    'percentage' => $percentage,
                ], now()->addHours(2));

                if ($totalProcessed % 1000 == 0) {
                    Log::info("SynchronizeMoveWarehousesJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
                }
            }

            $finalProcessed = $totalProcessed + count($errors);
            if ($finalProcessed > $totalRecords) {
                $finalProcessed = $totalRecords;
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'total' => $totalRecords,
                'processed' => $finalProcessed,
                'created' => $created,
                'updated' => $updated,
                'errors' => count($errors),
                'percentage' => 100,
                'completed_at' => now()->toDateTimeString(),
            ], now()->addHours(2));

            Cache::forget('sync_m_pindah_gudang_lock');

            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeMoveWarehousesJob: Synchronization completed');
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
                'error_message' => $e->getMessage(),
            ], now()->addHours(2));

            Cache::forget('sync_m_pindah_gudang_lock');
            throw $e;
        }
    }
}