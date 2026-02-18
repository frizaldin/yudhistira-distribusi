<?php

namespace App\Jobs;

use App\Models\DeliveryNoteDetail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeDeliveryNoteDetailsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $cacheKey = 'sync_delivery_note_details_progress';

        try {
            Log::info('SynchronizeDeliveryNoteDetailsJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('d_kirim_cabang')->count();

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
                $deliveryNoteDetailRecords = DB::connection('pgsql')
                    ->table('d_kirim_cabang')
                    ->orderBy('nota_kirim_cab')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($deliveryNoteDetailRecords->isEmpty()) {
                    break;
                }

                foreach ($deliveryNoteDetailRecords as $deliveryNoteDetailData) {
                    try {
                        $deliveryNoteDetail = (array) $deliveryNoteDetailData;

                        $removeQuotes = function ($value) {
                            if (empty($value)) return $value;
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value);
                        };

                        $notaKirimCab = isset($deliveryNoteDetail['nota_kirim_cab']) ? $removeQuotes($deliveryNoteDetail['nota_kirim_cab']) : null;
                        $bookCode = isset($deliveryNoteDetail['book_code']) ? $removeQuotes($deliveryNoteDetail['book_code']) : null;

                        if (empty($notaKirimCab)) {
                            continue; // Skip jika nota_kirim_cab kosong
                        }

                        // Find existing record by nota_kirim_cab and book_code (composite key)
                        $existingDeliveryNoteDetail = DeliveryNoteDetail::where('nota_kirim_cab', $notaKirimCab)
                            ->where('book_code', $bookCode)
                            ->first();

                        $deliveryNoteDetailDataArray = [
                            'nota_kirim_cab' => $notaKirimCab,
                            'book_code' => $bookCode,
                            'book_price' => isset($deliveryNoteDetail['book_price']) ? $removeQuotes($deliveryNoteDetail['book_price']) : null,
                            'koli' => isset($deliveryNoteDetail['koli']) ? (float) $deliveryNoteDetail['koli'] : 0,
                            'exemplar' => isset($deliveryNoteDetail['exemplar']) ? (float) $deliveryNoteDetail['exemplar'] : 0,
                            'total_exemplar' => isset($deliveryNoteDetail['total_exemplar']) ? (float) $deliveryNoteDetail['total_exemplar'] : 0,
                            'volume' => isset($deliveryNoteDetail['volume']) ? (float) $deliveryNoteDetail['volume'] : 0,
                            'branch_sender' => isset($deliveryNoteDetail['branch_sender']) ? $removeQuotes($deliveryNoteDetail['branch_sender']) : null,
                        ];

                        if ($existingDeliveryNoteDetail) {
                            $existingDeliveryNoteDetail->update($deliveryNoteDetailDataArray);
                            $updated++;
                        } else {
                            DeliveryNoteDetail::create($deliveryNoteDetailDataArray);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $notaError = $deliveryNoteDetail['nota_kirim_cab'] ?? 'unknown';
                        $bookError = $deliveryNoteDetail['book_code'] ?? 'unknown';
                        $errors[] = "Error pada nota_kirim_cab {$notaError}, book_code {$bookError}: " . $e->getMessage();
                        Log::error("Sync Error untuk nota_kirim_cab {$notaError}, book_code {$bookError}: " . $e->getMessage());
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
                    Log::info("SynchronizeDeliveryNoteDetailsJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
                }
            }

            // When completed, processed should equal total (including errors as processed)
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
                'completed_at' => now()->toDateTimeString()
            ], now()->addHours(2));
            
            // Save last sync timestamp
            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeDeliveryNoteDetailsJob: Synchronization completed', [
                'created' => $created,
                'updated' => $updated,
                'errors_count' => count($errors)
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeDeliveryNoteDetailsJob errors: ', $errors);
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

            Log::error('SynchronizeDeliveryNoteDetailsJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
