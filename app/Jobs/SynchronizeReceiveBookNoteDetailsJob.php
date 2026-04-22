<?php

namespace App\Jobs;

use App\Models\ReceiveBookNoteDetail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SynchronizeReceiveBookNoteDetailsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /** Tidak dibatasi waktu (0 = sampai selesai). */
    public int $timeout = 0;

    public function handle(): void
    {
        $cacheKey = 'sync_receive_book_note_details_progress';

        $removeQuotes = function ($value) {
            if ($value === null || $value === '') {
                return $value;
            }
            $value = trim((string) $value, " '\"\`");
            $value = preg_replace('/^[\'"`]+|[\'"`]+$/u', '', $value);

            return trim($value);
        };

        try {
            Log::info('SynchronizeReceiveBookNoteDetailsJob: Starting synchronization from PostgreSQL');

            $totalRecords = DB::connection('pgsql')->table('d_terima_buku')->count();

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
                    ->table('d_terima_buku')
                    ->orderBy('receive_code')
                    ->orderBy('book_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($records->isEmpty()) {
                    break;
                }

                $receiveCodesForLookup = [];
                foreach ($records as $rowData) {
                    $row = (array) $rowData;
                    $nota = isset($row['nota_kirim_cab']) ? $removeQuotes($row['nota_kirim_cab']) : null;
                    $recv = isset($row['receive_code']) ? $removeQuotes($row['receive_code']) : null;
                    if (($nota === null || $nota === '') && $recv) {
                        $receiveCodesForLookup[] = $recv;
                    }
                }
                $receiveCodesForLookup = array_values(array_unique($receiveCodesForLookup));

                $notaByReceive = [];
                if ($receiveCodesForLookup !== []) {
                    $rowsMaster = DB::connection('pgsql')
                        ->table('m_terima_buku')
                        ->whereIn('receive_code', $receiveCodesForLookup)
                        ->get(['receive_code', 'nota_kirim_cab']);
                    foreach ($rowsMaster as $m) {
                        $mArr = (array) $m;
                        $rc = isset($mArr['receive_code']) ? $removeQuotes($mArr['receive_code']) : null;
                        if ($rc) {
                            $notaByReceive[$rc] = isset($mArr['nota_kirim_cab']) ? $removeQuotes($mArr['nota_kirim_cab']) : null;
                        }
                    }
                }

                foreach ($records as $rowData) {
                    $row = [];
                    try {
                        $row = (array) $rowData;

                        $notaKirimCab = isset($row['nota_kirim_cab']) ? $removeQuotes($row['nota_kirim_cab']) : null;
                        $receiveCode = isset($row['receive_code']) ? $removeQuotes($row['receive_code']) : null;
                        if (($notaKirimCab === null || $notaKirimCab === '') && $receiveCode) {
                            $notaKirimCab = $notaByReceive[$receiveCode] ?? null;
                            if ($notaKirimCab !== null && $notaKirimCab !== '') {
                                $notaKirimCab = $removeQuotes($notaKirimCab);
                            }
                        }

                        $bookCode = isset($row['book_code']) ? $removeQuotes($row['book_code']) : null;

                        if (empty($notaKirimCab) || $bookCode === null || $bookCode === '') {
                            continue;
                        }

                        $existing = ReceiveBookNoteDetail::where('nota_kirim_cab', $notaKirimCab)
                            ->where('book_code', $bookCode)
                            ->first();

                        $payload = [
                            'nota_kirim_cab' => $notaKirimCab,
                            'book_code' => $bookCode,
                            'book_price' => isset($row['book_price']) ? $removeQuotes($row['book_price']) : null,
                            'koli' => isset($row['koli']) ? (float) $row['koli'] : 0,
                            'exemplar' => isset($row['exemplar']) ? (float) $row['exemplar'] : 0,
                            'total_exemplar' => isset($row['total_exemplar']) ? (float) $row['total_exemplar'] : 0,
                            'volume' => isset($row['volume']) ? (float) $row['volume'] : 0,
                            'branch_sender' => isset($row['branch_sender']) ? $removeQuotes($row['branch_sender']) : null,
                        ];

                        if ($existing) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            ReceiveBookNoteDetail::create($payload);
                            $created++;
                        }
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $n = $row['nota_kirim_cab'] ?? 'unknown';
                        $b = $row['book_code'] ?? 'unknown';
                        $errors[] = "Error pada nota_kirim_cab {$n}, book_code {$b}: " . $e->getMessage();
                        Log::error("Sync ReceiveBookNoteDetail {$n} / {$b}: " . $e->getMessage());
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
                    Log::info("SynchronizeReceiveBookNoteDetailsJob: Processed {$totalProcessed}/{$totalRecords} records ({$percentage}%)");
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

            Cache::forget('sync_receive_book_note_details_lock');

            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeReceiveBookNoteDetailsJob: Synchronization completed', [
                'created' => $created,
                'updated' => $updated,
                'errors_count' => count($errors),
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeReceiveBookNoteDetailsJob errors: ', $errors);
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

            Cache::forget('sync_receive_book_note_details_lock');

            Log::error('SynchronizeReceiveBookNoteDetailsJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
