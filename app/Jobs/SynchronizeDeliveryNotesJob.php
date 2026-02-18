<?php

namespace App\Jobs;

use App\Models\DeliveryNote;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeDeliveryNotesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $cacheKey = 'sync_delivery_notes_progress';

        try {
            Log::info('SynchronizeDeliveryNotesJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('m_kirim_cabang')->count();

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
                $deliveryNoteRecords = DB::connection('pgsql')
                    ->table('m_kirim_cabang')
                    ->orderBy('nota_kirim_cab')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($deliveryNoteRecords->isEmpty()) {
                    break;
                }

                foreach ($deliveryNoteRecords as $deliveryNoteData) {
                    try {
                        $deliveryNote = (array) $deliveryNoteData;

                        $removeQuotes = function ($value) {
                            if (empty($value)) return $value;
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);
                            return trim($value);
                        };

                        $notaKirimCab = isset($deliveryNote['nota_kirim_cab']) ? $removeQuotes($deliveryNote['nota_kirim_cab']) : null;

                        if (empty($notaKirimCab)) {
                            continue; // Skip jika nota_kirim_cab kosong
                        }

                        $existingDeliveryNote = DeliveryNote::where('nota_kirim_cab', $notaKirimCab)->first();

                        $deliveryNoteDataArray = [
                            'nota_kirim_cab' => $notaKirimCab,
                            'branch_code' => isset($deliveryNote['branch_code']) ? $removeQuotes($deliveryNote['branch_code']) : null,
                            'branch_sender' => isset($deliveryNote['branch_sender']) ? $removeQuotes($deliveryNote['branch_sender']) : null,
                            'send_date' => isset($deliveryNote['send_date']) && !empty($deliveryNote['send_date']) ? $deliveryNote['send_date'] : null,
                            'info' => isset($deliveryNote['info']) ? $removeQuotes($deliveryNote['info']) : null,
                            'nppb' => isset($deliveryNote['nppb']) ? $removeQuotes($deliveryNote['nppb']) : null,
                            'sj' => isset($deliveryNote['sj']) ? $removeQuotes($deliveryNote['sj']) : null,
                        ];

                        if ($existingDeliveryNote) {
                            $existingDeliveryNote->update($deliveryNoteDataArray);
                            $updated++;
                        } else {
                            DeliveryNote::create($deliveryNoteDataArray);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $notaError = $deliveryNote['nota_kirim_cab'] ?? 'unknown';
                        $errors[] = "Error pada nota_kirim_cab {$notaError}: " . $e->getMessage();
                        Log::error("Sync Error untuk nota_kirim_cab {$notaError}: " . $e->getMessage());
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
                    Log::info("SynchronizeDeliveryNotesJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
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

            Log::info('SynchronizeDeliveryNotesJob: Synchronization completed', [
                'created' => $created,
                'updated' => $updated,
                'errors_count' => count($errors)
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeDeliveryNotesJob errors: ', $errors);
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

            Log::error('SynchronizeDeliveryNotesJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
