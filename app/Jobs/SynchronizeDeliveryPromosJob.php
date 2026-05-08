<?php

namespace App\Jobs;

use App\Models\DeliveryPromo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SynchronizeDeliveryPromosJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 0;

    public function handle(): void
    {
        $cacheKey = 'sync_m_kirim_promosi_progress';

        try {
            Log::info('SynchronizeDeliveryPromosJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('m_kirim_promosi')->count();

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
                    ->table('m_kirim_promosi')
                    ->orderBy('nota_kirim_promo')
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

                        $primaryId = isset($row['nota_kirim_promo']) ? $removeQuotes($row['nota_kirim_promo']) : null;

                        if (empty($primaryId)) {
                            continue;
                        }

                        $existing = DeliveryPromo::where('nota_kirim_promo', $primaryId)->first();

                        $payload = [
                            'nota_kirim_promo' => $primaryId,
                            'approve_by' => isset($row['approve_by']) ? $removeQuotes($row['approve_by']) : null,
                            'branch_sender' => isset($row['branch_sender']) ? $removeQuotes($row['branch_sender']) : null,
                            'deliver_by' => isset($row['deliver_by']) ? $removeQuotes($row['deliver_by']) : null,
                            'info' => isset($row['info']) ? $removeQuotes($row['info']) : null,
                            'printed' => isset($row['printed']) ? (int) $row['printed'] : 0,
                            'sales_code' => isset($row['sales_code']) ? $removeQuotes($row['sales_code']) : null,
                            'send_date' => isset($row['send_date']) && !empty($row['send_date']) ? $row['send_date'] : null,
                            'status' => isset($row['status']) ? (int) $row['status'] : 0,
                            'user_id' => isset($row['user_id']) ? $removeQuotes($row['user_id']) : null,
                            'whouse_head' => isset($row['whouse_head']) ? $removeQuotes($row['whouse_head']) : null,
                        ];

                        if ($existing) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            DeliveryPromo::create($payload);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $code = $row['nota_kirim_promo'] ?? 'unknown';
                        $errors[] = "Error pada nota_kirim_promo {$code}: " . $e->getMessage();
                        Log::error("Sync DeliveryPromo nota_kirim_promo {$code}: " . $e->getMessage());
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
                    Log::info("SynchronizeDeliveryPromosJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
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

            Cache::forget('sync_m_kirim_promosi_lock');

            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeDeliveryPromosJob: Synchronization completed');
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

            Cache::forget('sync_m_kirim_promosi_lock');
            throw $e;
        }
    }
}