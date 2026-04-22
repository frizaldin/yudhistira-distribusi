<?php

namespace App\Jobs;

use App\Models\StockMutation;
use App\Models\StockMutationItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeStockMutationItemsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /** Tidak dibatasi waktu (0 = sampai selesai). */
    public int $timeout = 0;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $cacheKey = 'sync_stock_mutation_items_progress';
        $lockKey  = 'sync_stock_mutation_items_lock';

        try {
            Log::info('SynchronizeStockMutationItemsJob: Starting synchronization from PostgreSQL (d_mutasi_buku)');

            $totalRecords = DB::connection('pgsql')->table('d_mutasi_buku')->count();

            Cache::put($cacheKey, [
                'status'     => 'running',
                'total'      => $totalRecords,
                'processed'  => 0,
                'created'    => 0,
                'updated'    => 0,
                'errors'     => 0,
                'percentage' => 0,
            ], now()->addHours(2));

            // Pre-load semua no_mutasi → id dari MySQL agar tidak N+1 query di loop
            $mutationMap = StockMutation::pluck('id', 'no_mutasi')->toArray();

            $chunkSize      = 500;
            $created        = 0;
            $updated        = 0;
            $errors         = [];
            $offset         = 0;
            $totalProcessed = 0;

            while (true) {
                $records = DB::connection('pgsql')
                    ->table('d_mutasi_buku')
                    ->orderBy('no_mutasi')
                    ->orderBy('book_code')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($records->isEmpty()) {
                    break;
                }

                foreach ($records as $row) {
                    try {
                        $data     = (array) $row;
                        $noMutasi = trim($data['no_mutasi']  ?? '');
                        $bookCode = trim($data['book_code']  ?? '');

                        if (empty($noMutasi) || empty($bookCode)) {
                            continue;
                        }

                        // Cari mutation_id; jika master belum sync, skip dan catat error
                        $mutationId = $mutationMap[$noMutasi] ?? null;
                        if (!$mutationId) {
                            // Coba fetch langsung (master bisa baru saja dibuat di job sebelumnya)
                            $master = StockMutation::where('no_mutasi', $noMutasi)->first();
                            if ($master) {
                                $mutationId = $master->id;
                                $mutationMap[$noMutasi] = $mutationId;
                            } else {
                                $errors[] = "no_mutasi {$noMutasi} tidak ditemukan di stock_mutations (master belum sync?)";
                                Log::warning("SynchronizeStockMutationItemsJob: no_mutasi {$noMutasi} tidak ada di stock_mutations");
                                $totalProcessed++;
                                continue;
                            }
                        }

                        $payload = [
                            'mutation_id'     => $mutationId,
                            'book_code'       => $bookCode,
                            'koli'            => (int) ($data['koli']            ?? 0),
                            'isi_koli'        => (int) ($data['isi_koli']        ?? 0),
                            'eceran'          => (int) ($data['eceran']          ?? 0),
                            'total_eksemplar'  => (int) ($data['total_eksemplar'] ?? 0),
                        ];

                        // Composite key: mutation_id + book_code
                        $existing = StockMutationItem::where('mutation_id', $mutationId)
                            ->where('book_code', $bookCode)
                            ->first();

                        if ($existing) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            StockMutationItem::create($payload);
                            $created++;
                        }

                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $key1 = $data['no_mutasi']  ?? 'unknown';
                        $key2 = $data['book_code']  ?? 'unknown';
                        $errors[] = "Error pada no_mutasi {$key1}, book_code {$key2}: " . $e->getMessage();
                        Log::error("SynchronizeStockMutationItemsJob: Error no_mutasi {$key1}, book_code {$key2}: " . $e->getMessage());
                    }
                }

                $offset += $chunkSize;

                $percentage = $totalRecords > 0 ? round(($totalProcessed / $totalRecords) * 100, 2) : 0;
                Cache::put($cacheKey, [
                    'status'     => 'running',
                    'total'      => $totalRecords,
                    'processed'  => $totalProcessed,
                    'created'    => $created,
                    'updated'    => $updated,
                    'errors'     => count($errors),
                    'percentage' => $percentage,
                ], now()->addHours(2));

                if ($totalProcessed % 1000 === 0) {
                    Log::info("SynchronizeStockMutationItemsJob: Processed {$totalProcessed}/{$totalRecords} ({$percentage}%)");
                }
            }

            $finalProcessed = min($totalProcessed + count($errors), $totalRecords);

            Cache::put($cacheKey, [
                'status'       => 'completed',
                'total'        => $totalRecords,
                'processed'    => $finalProcessed,
                'created'      => $created,
                'updated'      => $updated,
                'errors'       => count($errors),
                'percentage'   => 100,
                'completed_at' => now()->toDateTimeString(),
            ], now()->addHours(2));

            Cache::forget($lockKey);
            Cache::put($cacheKey . '_last_sync', now()->toDateTimeString(), now()->addDays(30));

            Log::info('SynchronizeStockMutationItemsJob: Completed', [
                'created'      => $created,
                'updated'      => $updated,
                'errors_count' => count($errors),
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeStockMutationItemsJob errors:', $errors);
            }
        } catch (\Exception $e) {
            $currentProgress = Cache::get($cacheKey, []);
            Cache::put($cacheKey, [
                'status'        => 'failed',
                'total'         => $currentProgress['total']      ?? 0,
                'processed'     => $currentProgress['processed']  ?? 0,
                'created'       => $currentProgress['created']    ?? 0,
                'updated'       => $currentProgress['updated']    ?? 0,
                'errors'        => $currentProgress['errors']     ?? 0,
                'percentage'    => $currentProgress['percentage'] ?? 0,
                'error_message' => $e->getMessage(),
            ], now()->addHours(2));

            Cache::forget($lockKey);

            Log::error('SynchronizeStockMutationItemsJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
