<?php

namespace App\Jobs;

use App\Models\DeliveryPromoDetail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SynchronizeDeliveryPromoDetailsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 0;

    public function handle(): void
    {
        $cacheKey = 'sync_d_kirim_promosi_progress';

        $removeQuotes = function ($value) {
            if ($value === null || $value === '') return $value;
            $value = trim((string) $value, " '\`\"");
            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
            return trim($value);
        };

        try {
            Log::info('SynchronizeDeliveryPromoDetailsJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('d_kirim_promosi')->count();

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
                    ->table('d_kirim_promosi')
                    ->orderBy('nota_kirim_promo')
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

                        $primaryId = isset($row['nota_kirim_promo']) ? $removeQuotes($row['nota_kirim_promo']) : null;
                        $bookCode = isset($row['book_code']) ? $removeQuotes($row['book_code']) : null;

                        if (empty($primaryId) || empty($bookCode)) {
                            continue;
                        }

                        $existing = DeliveryPromoDetail::where('nota_kirim_promo', $primaryId)
                            ->where('book_code', $bookCode)
                            ->first();

                        $payload = [
                            'nota_kirim_promo' => $primaryId,
                            'book_code' => $bookCode,
                            'book_price' => isset($row['book_price']) ? $removeQuotes($row['book_price']) : null,
                            'branch_sender' => isset($row['branch_sender']) ? $removeQuotes($row['branch_sender']) : null,
                            'exemplar' => isset($row['exemplar']) ? (float) $row['exemplar'] : 0,
                            'koli' => isset($row['koli']) ? (float) $row['koli'] : 0,
                            'total_exemplar' => isset($row['total_exemplar']) ? (float) $row['total_exemplar'] : 0,
                            'volume' => isset($row['volume']) ? (float) $row['volume'] : 0,
                        ];

                        if ($existing) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            DeliveryPromoDetail::create($payload);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $pId = $row['nota_kirim_promo'] ?? 'unknown';
                        $bCode = $row['book_code'] ?? 'unknown';
                        $errors[] = "Error pada nota_kirim_promo {$pId}, book_code {$bCode}: " . $e->getMessage();
                        Log::error("Sync DeliveryPromoDetail {$pId} / {$bCode}: " . $e->getMessage());
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
                    Log::info("SynchronizeDeliveryPromoDetailsJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
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

            Cache::forget('sync_d_kirim_promosi_lock');

            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeDeliveryPromoDetailsJob: Synchronization completed');
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

            Cache::forget('sync_d_kirim_promosi_lock');
            throw $e;
        }
    }
}