<?php

namespace App\Jobs;

use App\Models\ReceiveBookNote;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SynchronizeReceiveBookNotesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /** Tidak dibatasi waktu (0 = sampai selesai). */
    public int $timeout = 0;

    public function handle(): void
    {
        $cacheKey = 'sync_receive_book_notes_progress';

        try {
            Log::info('SynchronizeReceiveBookNotesJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('m_terima_buku')->count();

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
                    ->table('m_terima_buku')
                    ->orderBy('receive_code')
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
                            if (empty($value)) {
                                return $value;
                            }
                            $value = trim($value, " '\"\`");
                            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);

                            return trim($value);
                        };

                        $receiveCode = isset($row['receive_code']) ? $removeQuotes($row['receive_code']) : null;

                        if (empty($receiveCode)) {
                            continue;
                        }

                        $existing = ReceiveBookNote::where('receive_code', $receiveCode)->first();

                        $payload = [
                            'nota_kirim_cab' => isset($row['nota_kirim_cab']) ? $removeQuotes($row['nota_kirim_cab']) : null,
                            'receive_code' => $receiveCode,
                            'branch_code' => isset($row['branch_code']) ? $removeQuotes($row['branch_code']) : null,
                            'retur_date' => isset($row['retur_date']) && !empty($row['retur_date']) ? $row['retur_date'] : null,
                            'send_date' => isset($row['send_date']) && !empty($row['send_date']) ? $row['send_date'] : null,
                            'info' => isset($row['info']) ? $removeQuotes($row['info']) : null,
                            'branch_sender' => isset($row['branch_sender']) ? $removeQuotes($row['branch_sender']) : null,
                        ];

                        if ($existing) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            ReceiveBookNote::create($payload);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $code = $row['receive_code'] ?? 'unknown';
                        $errors[] = "Error pada receive_code {$code}: " . $e->getMessage();
                        Log::error("Sync ReceiveBookNote receive_code {$code}: " . $e->getMessage());
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
                    Log::info("SynchronizeReceiveBookNotesJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
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

            Cache::forget('sync_receive_book_notes_lock');

            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeReceiveBookNotesJob: Synchronization completed', [
                'created' => $created,
                'updated' => $updated,
                'errors_count' => count($errors),
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeReceiveBookNotesJob errors: ', $errors);
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
                'error_message' => $e->getMessage(),
            ], now()->addHours(2));

            Cache::forget('sync_receive_book_notes_lock');

            Log::error('SynchronizeReceiveBookNotesJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
