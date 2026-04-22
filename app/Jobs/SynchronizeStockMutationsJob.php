<?php

namespace App\Jobs;

use App\Models\StockMutation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SynchronizeStockMutationsJob implements ShouldQueue
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
        $cacheKey  = 'sync_stock_mutations_progress';
        $lockKey   = 'sync_stock_mutations_lock';

        try {
            Log::info('SynchronizeStockMutationsJob: Starting synchronization from PostgreSQL (m_mutasi_buku)');

            $totalRecords = DB::connection('pgsql')->table('m_mutasi_buku')->count();

            Cache::put($cacheKey, [
                'status'     => 'running',
                'total'      => $totalRecords,
                'processed'  => 0,
                'created'    => 0,
                'updated'    => 0,
                'errors'     => 0,
                'percentage' => 0,
            ], now()->addHours(2));

            $chunkSize     = 500;
            $created       = 0;
            $updated       = 0;
            $errors        = [];
            $offset        = 0;
            $totalProcessed = 0;

            while (true) {
                $records = DB::connection('pgsql')
                    ->table('m_mutasi_buku')
                    ->orderBy('no_mutasi')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->get();

                if ($records->isEmpty()) {
                    break;
                }

                foreach ($records as $row) {
                    try {
                        $data = (array) $row;

                        $noMutasi = trim($data['no_mutasi'] ?? '');
                        if (empty($noMutasi)) {
                            continue;
                        }

                        $payload = [
                            'no_mutasi'          => $noMutasi,
                            'nama_pt_produksi'   => trim($data['nama_pt_produksi']   ?? '') ?: null,
                            'tanggal_penerimaan' => !empty($data['tanggal_penerimaan']) ? $data['tanggal_penerimaan'] : null,
                            'nama_penerima'      => trim($data['nama_penerima']      ?? '') ?: null,
                            'nomor_surat_jalan'  => trim($data['nomor_surat_jalan']  ?? '') ?: null,
                            'nomor_jo'           => trim($data['nomor_jo']           ?? '') ?: null,
                            'keterangan'         => trim($data['keterangan']         ?? '') ?: null,
                        ];

                        $existing = StockMutation::where('no_mutasi', $noMutasi)->first();

                        if ($existing) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            StockMutation::create($payload);
                            $created++;
                        }

                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $key = $data['no_mutasi'] ?? 'unknown';
                        $errors[] = "Error pada no_mutasi {$key}: " . $e->getMessage();
                        Log::error("SynchronizeStockMutationsJob: Error pada no_mutasi {$key}: " . $e->getMessage());
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
                    Log::info("SynchronizeStockMutationsJob: Processed {$totalProcessed}/{$totalRecords} ({$percentage}%)");
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

            Log::info('SynchronizeStockMutationsJob: Completed', [
                'created'      => $created,
                'updated'      => $updated,
                'errors_count' => count($errors),
            ]);

            if (count($errors) > 0) {
                Log::warning('SynchronizeStockMutationsJob errors:', $errors);
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

            Log::error('SynchronizeStockMutationsJob Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
