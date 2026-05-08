<?php

namespace App\Jobs;

use App\Models\EraseItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SynchronizeEraseItemsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 0;

    public function handle(): void
    {
        $cacheKey = 'sync_m_hapus_barang_progress';

        try {
            Log::info('SynchronizeEraseItemsJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('m_hapus_barang')->count();

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
                    ->table('m_hapus_barang')
                    ->orderBy('erase_code')
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

                        $primaryId = isset($row['erase_code']) ? $removeQuotes($row['erase_code']) : null;

                        if (empty($primaryId)) {
                            continue;
                        }

                        $existing = EraseItem::where('erase_code', $primaryId)->first();

                        $payload = [
                            'erase_code' => $primaryId,
                            'branch_code' => isset($row['branch_code']) ? $removeQuotes($row['branch_code']) : null,
                            'edit_date' => isset($row['edit_date']) && !empty($row['edit_date']) ? $row['edit_date'] : null,
                            'empl_code' => isset($row['empl_code']) ? $removeQuotes($row['empl_code']) : null,
                            'info' => isset($row['info']) ? $removeQuotes($row['info']) : null,
                            'printed' => isset($row['printed']) ? (int) $row['printed'] : 0,
                            'status' => isset($row['status']) ? (int) $row['status'] : 0,
                            'trans_date' => isset($row['trans_date']) && !empty($row['trans_date']) ? $row['trans_date'] : null,
                            'user_id' => isset($row['user_id']) ? $removeQuotes($row['user_id']) : null,
                            'whouse_head' => isset($row['whouse_head']) ? $removeQuotes($row['whouse_head']) : null,
                        ];

                        if ($existing) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            EraseItem::create($payload);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $code = $row['erase_code'] ?? 'unknown';
                        $errors[] = "Error pada erase_code {$code}: " . $e->getMessage();
                        Log::error("Sync EraseItem erase_code {$code}: " . $e->getMessage());
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
                    Log::info("SynchronizeEraseItemsJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
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

            Cache::forget('sync_m_hapus_barang_lock');

            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeEraseItemsJob: Synchronization completed');
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

            Cache::forget('sync_m_hapus_barang_lock');
            throw $e;
        }
    }
}