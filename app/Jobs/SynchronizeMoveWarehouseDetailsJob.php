<?php

namespace App\Jobs;

use App\Models\MoveWarehouseDetail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SynchronizeMoveWarehouseDetailsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 0;

    public function handle(): void
    {
        $cacheKey = 'sync_d_pindah_gudang_progress';

        $removeQuotes = function ($value) {
            if ($value === null || $value === '') return $value;
            $value = trim((string) $value, " '\`\"");
            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
            return trim($value);
        };

        try {
            Log::info('SynchronizeMoveWarehouseDetailsJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('d_pindah_gudang')->count();

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
                    ->table('d_pindah_gudang')
                    ->orderBy('move_code')
                    ->orderBy('book_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($records->isEmpty()) {
                    break;
                }

                foreach ($records as $rowData) {
                    try {
                        $row = (array) $rowData;

                        $primaryId = isset($row['move_code']) ? $removeQuotes($row['move_code']) : null;
                        $bookCode = isset($row['book_code']) ? $removeQuotes($row['book_code']) : null;

                        if (empty($primaryId) || empty($bookCode)) {
                            continue;
                        }

                        $existing = MoveWarehouseDetail::where('move_code', $primaryId)
                            ->where('book_code', $bookCode)
                            ->first();

                        $payload = [
                            'move_code' => $primaryId,
                            'book_code' => $bookCode,
                            'branch_code' => isset($row['branch_code']) ? $removeQuotes($row['branch_code']) : null,
                            'exemplar' => isset($row['exemplar']) ? (float) $row['exemplar'] : 0,
                            'koli' => isset($row['koli']) ? (float) $row['koli'] : 0,
                            'total_exemplar' => isset($row['total_exemplar']) ? (float) $row['total_exemplar'] : 0,
                            'volume' => isset($row['volume']) ? (float) $row['volume'] : 0,
                        ];

                        if ($existing) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            MoveWarehouseDetail::create($payload);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $pId = $row['move_code'] ?? 'unknown';
                        $bCode = $row['book_code'] ?? 'unknown';
                        $errors[] = "Error pada move_code {$pId}, book_code {$bCode}: " . $e->getMessage();
                        Log::error("Sync MoveWarehouseDetail {$pId} / {$bCode}: " . $e->getMessage());
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
                    Log::info("SynchronizeMoveWarehouseDetailsJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
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

            Cache::forget('sync_d_pindah_gudang_lock');

            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeMoveWarehouseDetailsJob: Synchronization completed');
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

            Cache::forget('sync_d_pindah_gudang_lock');
            throw $e;
        }
    }
}