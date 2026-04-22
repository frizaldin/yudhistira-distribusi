<?php

namespace App\Http\Controllers;

use App\Jobs\SynchronizeProductsJob;
use App\Jobs\SynchronizeBranchesJob;
use App\Jobs\SynchronizeCentralStocksJob;
use App\Jobs\SynchronizeTargetsJob;
use App\Jobs\SynchronizePeriodesJob;
use App\Jobs\SynchronizeSpBranchesJob;
use App\Jobs\SynchronizeDeliveryNotesJob;
use App\Jobs\SynchronizeDeliveryNoteDetailsJob;
use App\Jobs\SynchronizeReceiveBookNotesJob;
use App\Jobs\SynchronizeReceiveBookNoteDetailsJob;
use App\Jobs\SynchronizeStockMutationsJob;
use App\Jobs\SynchronizeStockMutationItemsJob;
use App\Models\CutoffData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StagingController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/staging');
        $this->title = 'Staging - Sinkronisasi Data';

        if (Auth::check()) {
            $this->role = Auth::user()->authority_id ?? 1;
            $this->callbackfolder = match ($this->role) {
                1 => 'superadmin',
                2 => 'branch',
                default => 'superadmin',
            };
        } else {
            $this->callbackfolder = 'superadmin';
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Count dari PostgreSQL di-load lazy via AJAX (staging/counts) agar halaman langsung tampil
        $stagingData = [
            [
                'name' => 'Staging Buku',
                'key' => 'product',
                'table' => 'm_book',
                'count' => null,
                'cache_key' => 'sync_products_progress',
                'icon' => 'bi-journals',
                'color' => 'primary',
            ],
            [
                'name' => 'Staging Cabang',
                'key' => 'branch',
                'table' => 'm_cabang',
                'count' => null,
                'cache_key' => 'sync_branches_progress',
                'icon' => 'bi-building',
                'color' => 'success',
            ],
            [
                'name' => 'Staging Stok Pusat',
                'key' => 'central_stock',
                'table' => 'r_stock_pusat',
                'count' => null,
                'cache_key' => 'sync_central_stocks_progress',
                'icon' => 'bi-box-seam',
                'color' => 'warning',
            ],
            [
                'name' => 'Staging Target',
                'key' => 'target',
                'table' => 'r_target_buku',
                'count' => null,
                'cache_key' => 'sync_targets_progress',
                'icon' => 'bi-bullseye',
                'color' => 'danger',
            ],
            [
                'name' => 'Staging Periode',
                'key' => 'period',
                'table' => 'm_period',
                'count' => null,
                'cache_key' => 'sync_periodes_progress',
                'icon' => 'bi-calendar',
                'color' => 'info',
            ],
            [
                'name' => 'Staging Pesanan (Sp Cabang)',
                'key' => 'sp_branch',
                'table' => 'r_sp_faktur_stok',
                'count' => null,
                'cache_key' => 'sync_sp_branches_progress',
                'icon' => 'bi-cart-check',
                'color' => 'secondary',
            ],
            [
                'name' => 'Staging Nota Kirim',
                'key' => 'delivery_notes',
                'table' => 'm_kirim_cabang, d_kirim_cabang',
                'count' => null,
                'cache_key' => 'sync_delivery_notes_progress',
                'icon' => 'bi-truck',
                'color' => 'dark',
            ],
            [
                'name'      => 'Staging Nota Terima',
                'key'       => 'receive_notes',
                'table'     => 'm_terima_buku, d_terima_buku',
                'count'     => null,
                'cache_key' => 'sync_receive_book_notes_progress',
                'icon'      => 'bi-box-arrow-in-down',
                'color'     => 'secondary',
            ],
            [
                'name'      => 'Staging Mutasi Buku',
                'key'       => 'stock_mutations',
                'table'     => 'm_mutasi_buku, d_mutasi_buku',
                'count'     => null,
                'cache_key' => 'sync_stock_mutations_progress',
                'icon'      => 'bi-arrow-left-right',
                'color'     => 'warning',
            ],
        ];

        // Get sync progress and last sync time for each item
        foreach ($stagingData as &$item) {
            if ($item['key'] === 'delivery_notes') {
                $state = $this->combinedMasterDetailSyncState(
                    'sync_delivery_notes_progress',
                    'sync_delivery_note_details_progress'
                );
                $item['progress'] = $state['progress'];
                $item['is_running'] = $state['is_running'];
                $item['last_sync'] = $state['last_sync'];
            } elseif ($item['key'] === 'receive_notes') {
                $state = $this->combinedMasterDetailSyncState(
                    'sync_receive_book_notes_progress',
                    'sync_receive_book_note_details_progress'
                );
                $item['progress'] = $state['progress'];
                $item['is_running'] = $state['is_running'];
                $item['last_sync'] = $state['last_sync'];
            } elseif ($item['key'] === 'stock_mutations') {
                $state = $this->combinedMasterDetailSyncState(
                    'sync_stock_mutations_progress',
                    'sync_stock_mutation_items_progress'
                );
                $item['progress']   = $state['progress'];
                $item['is_running'] = $state['is_running'];
                $item['last_sync']  = $state['last_sync'];
            } else {
                // Normal handling for other items
                $progress = Cache::get($item['cache_key'], null);
                $item['progress'] = $progress;
                $item['is_running'] = $progress && ($progress['status'] ?? '') === 'running';

                // Get last sync timestamp
                $lastSyncKey = $item['cache_key'] . '_last_sync';
                $lastSync = Cache::get($lastSyncKey, null);
                $item['last_sync'] = $lastSync;
            }
        }

        // Get cutoff_datas
        $cutoffDatas = CutoffData::orderBy('id', 'desc')->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'stagingData' => $stagingData,
            'cutoffDatas' => $cutoffDatas,
        ];

        return view($this->callbackfolder . '.staging.index', $data);
    }

    /**
     * API: ambil semua count staging (lazy-load; hitungan PostgreSQL real-time tanpa cache).
     */
    public function getStagingCounts()
    {
        $counts = [
            'product'         => $this->getStagingCount('m_book'),
            'branch'          => $this->getStagingCount('m_cabang'),
            'central_stock'   => $this->getStagingCount('r_stock_pusat'),
            'target'          => $this->getStagingCount('r_target_buku'),
            'period'          => $this->getStagingCount('m_period'),
            'sp_branch'       => $this->getStagingCount('r_sp_faktur_stok'),
            'delivery_notes'  => $this->getStagingCount('m_kirim_cabang') + $this->getStagingCount('d_kirim_cabang'),
            'receive_notes'   => $this->getStagingCount('m_terima_buku')  + $this->getStagingCount('d_terima_buku'),
            'stock_mutations' => $this->getStagingCount('m_mutasi_buku')  + $this->getStagingCount('d_mutasi_buku'),
        ];
        return response()->json($counts);
    }

    /**
     * Gabungkan progress cache dua job (master + detail), pola sama dengan Nota Kirim.
     *
     * @return array{progress: ?array, is_running: bool, last_sync: ?string}
     */
    private function combinedMasterDetailSyncState(string $masterProgressKey, string $detailProgressKey): array
    {
        $progressNotes = Cache::get($masterProgressKey, null);
        $progressDetails = Cache::get($detailProgressKey, null);

        if (!$progressNotes && !$progressDetails) {
            return [
                'progress' => null,
                'is_running' => false,
                'last_sync' => null,
            ];
        }

        $combinedProgress = [
            'status' => 'running',
            'total' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'percentage' => 0,
        ];

        $n = is_array($progressNotes) ? $progressNotes : [];
        $d = is_array($progressDetails) ? $progressDetails : [];

        $combinedProgress['total'] = ($n['total'] ?? 0) + ($d['total'] ?? 0);
        $combinedProgress['processed'] = ($n['processed'] ?? 0) + ($d['processed'] ?? 0);
        $combinedProgress['created'] = ($n['created'] ?? 0) + ($d['created'] ?? 0);
        $combinedProgress['updated'] = ($n['updated'] ?? 0) + ($d['updated'] ?? 0);
        $combinedProgress['errors'] = ($n['errors'] ?? 0) + ($d['errors'] ?? 0);

        $notesStatus = $n['status'] ?? null;
        $detailsStatus = $d['status'] ?? null;

        if ($notesStatus === null && $detailsStatus === null) {
            $combinedProgress['status'] = 'running';
        } elseif ($notesStatus === 'running' || $detailsStatus === 'running') {
            $combinedProgress['status'] = 'running';
        } elseif ($notesStatus === 'failed' || $detailsStatus === 'failed') {
            $combinedProgress['status'] = 'failed';
        } else {
            if (($notesStatus === 'completed' || $notesStatus === null) &&
                ($detailsStatus === 'completed' || $detailsStatus === null)
            ) {
                $combinedProgress['status'] = 'completed';
                $notesCompletedAt = $n['completed_at'] ?? null;
                $detailsCompletedAt = $d['completed_at'] ?? null;
                if ($notesCompletedAt && $detailsCompletedAt) {
                    $combinedProgress['completed_at'] = $notesCompletedAt > $detailsCompletedAt ? $notesCompletedAt : $detailsCompletedAt;
                } else {
                    $combinedProgress['completed_at'] = $notesCompletedAt ?? $detailsCompletedAt;
                }
            } else {
                $combinedProgress['status'] = 'running';
            }
        }

        if ($combinedProgress['status'] === 'completed' && $combinedProgress['total'] > 0) {
            $actualProcessed = $combinedProgress['processed'] + $combinedProgress['errors'];
            if ($actualProcessed >= $combinedProgress['total']) {
                $combinedProgress['processed'] = $combinedProgress['total'];
            } else {
                $combinedProgress['processed'] = $combinedProgress['total'];
            }
            $combinedProgress['percentage'] = 100;
        } elseif ($combinedProgress['total'] > 0) {
            $actualProcessed = $combinedProgress['processed'] + $combinedProgress['errors'];
            $combinedProgress['percentage'] = round(($actualProcessed / $combinedProgress['total']) * 100, 2);
        }

        $lastSyncNotes = Cache::get($masterProgressKey . '_last_sync', null);
        $lastSyncDetails = Cache::get($detailProgressKey . '_last_sync', null);
        if ($lastSyncNotes && $lastSyncDetails) {
            $lastSync = $lastSyncNotes > $lastSyncDetails ? $lastSyncNotes : $lastSyncDetails;
        } else {
            $lastSync = $lastSyncNotes ?? $lastSyncDetails;
        }

        return [
            'progress' => $combinedProgress,
            'is_running' => $combinedProgress['status'] === 'running',
            'last_sync' => $lastSync,
        ];
    }

    /**
     * Hitung baris tabel staging di PostgreSQL (selalu query langsung, tidak di-cache).
     */
    private function getStagingCount(string $table): int
    {
        try {
            return (int) DB::connection('pgsql')->table($table)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Dulu dipakai untuk forget cache count; count tidak di-cache lagi, tetap dipanggil agar kunci lama (deploy sebelumnya) ikut dibersihkan.
     */
    private function clearStagingCountCache(string $type): void
    {
        $tables = match ($type) {
            'product' => ['m_book'],
            'branch' => ['m_cabang'],
            'central_stock' => ['r_stock_pusat'],
            'target' => ['r_target_buku'],
            'period' => ['m_period'],
            'sp_branch' => ['r_sp_faktur_stok'],
            'delivery_notes' => ['m_kirim_cabang', 'd_kirim_cabang'],
            'receive_notes' => ['m_terima_buku', 'd_terima_buku'],
            'stock_mutations' => ['m_mutasi_buku', 'd_mutasi_buku'],
            default           => [],
        };
        foreach ($tables as $table) {
            Cache::forget('staging_count_' . $table);
        }
    }

    /**
     * Sinkron semua staging data sekaligus (dispatch semua job).
     */
    public function synchronizeAll(Request $request)
    {
        try {
            $types = ['product', 'branch', 'period', 'central_stock', 'target', 'sp_branch', 'delivery_notes', 'receive_notes', 'stock_mutations'];
            foreach ($types as $type) {
                $this->clearStagingCountCache($type);
                switch ($type) {
                    case 'product':
                        SynchronizeProductsJob::dispatch();
                        break;
                    case 'branch':
                        SynchronizeBranchesJob::dispatch();
                        break;
                    case 'central_stock':
                        $this->clearStaleSyncLock('sync_central_stocks_lock', 'sync_central_stocks_progress');
                        if (Cache::add('sync_central_stocks_lock', true, now()->addHours(2))) {
                            SynchronizeCentralStocksJob::dispatch();
                        }
                        break;
                    case 'target':
                        $this->clearStaleSyncLock('sync_targets_lock', 'sync_targets_progress');
                        if (Cache::add('sync_targets_lock', true, now()->addHours(2))) {
                            SynchronizeTargetsJob::dispatch();
                        }
                        break;
                    case 'period':
                        SynchronizePeriodesJob::dispatch();
                        break;
                    case 'sp_branch':
                        $this->clearStaleSyncLock('sync_sp_branches_lock', 'sync_sp_branches_progress');
                        if (Cache::add('sync_sp_branches_lock', true, now()->addHours(2))) {
                            SynchronizeSpBranchesJob::dispatch();
                        }
                        break;
                    case 'delivery_notes':
                        $this->clearStaleSyncLock('sync_delivery_notes_lock', 'sync_delivery_notes_progress');
                        $this->clearStaleSyncLock('sync_delivery_note_details_lock', 'sync_delivery_note_details_progress');
                        if (Cache::add('sync_delivery_notes_lock', true, now()->addHours(2))) {
                            SynchronizeDeliveryNotesJob::dispatch();
                        }
                        if (Cache::add('sync_delivery_note_details_lock', true, now()->addHours(2))) {
                            SynchronizeDeliveryNoteDetailsJob::dispatch();
                        }
                        break;
                    case 'receive_notes':
                        $this->clearStaleSyncLock('sync_receive_book_notes_lock', 'sync_receive_book_notes_progress');
                        $this->clearStaleSyncLock('sync_receive_book_note_details_lock', 'sync_receive_book_note_details_progress');
                        if (Cache::add('sync_receive_book_notes_lock', true, now()->addHours(2))) {
                            SynchronizeReceiveBookNotesJob::dispatch();
                        }
                        if (Cache::add('sync_receive_book_note_details_lock', true, now()->addHours(2))) {
                            SynchronizeReceiveBookNoteDetailsJob::dispatch();
                        }
                        break;
                    case 'stock_mutations':
                        $this->clearStaleSyncLock('sync_stock_mutations_lock', 'sync_stock_mutations_progress');
                        $this->clearStaleSyncLock('sync_stock_mutation_items_lock', 'sync_stock_mutation_items_progress');
                        if (Cache::add('sync_stock_mutations_lock', true, now()->addHours(2))) {
                            SynchronizeStockMutationsJob::dispatch();
                        }
                        if (Cache::add('sync_stock_mutation_items_lock', true, now()->addHours(2))) {
                            SynchronizeStockMutationItemsJob::dispatch();
                        }
                        break;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Sinkronisasi semua data telah dimulai. Silakan refresh halaman untuk melihat progress.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Synchronize specific staging data
     */
    public function synchronize(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:product,branch,central_stock,target,period,sp_branch,delivery_notes,receive_notes,stock_mutations',
        ]);

        $type = $request->input('type');

        try {
            $this->clearStagingCountCache($type);

            switch ($type) {
                case 'product':
                    SynchronizeProductsJob::dispatch();
                    break;
                case 'branch':
                    SynchronizeBranchesJob::dispatch();
                    break;
                case 'central_stock':
                    $this->clearStaleSyncLock('sync_central_stocks_lock', 'sync_central_stocks_progress');
                    if (!Cache::add('sync_central_stocks_lock', true, now()->addHours(2))) {
                        return $this->syncConflictResponse(
                            'Job sinkron stock pusat (r_stock_pusat) masih berjalan. Tunggu sampai selesai sebelum menjalankan lagi.',
                            'sync_central_stocks_progress'
                        );
                    }
                    SynchronizeCentralStocksJob::dispatch();
                    break;
                case 'target':
                    $this->clearStaleSyncLock('sync_targets_lock', 'sync_targets_progress');
                    if (!Cache::add('sync_targets_lock', true, now()->addHours(2))) {
                        return $this->syncConflictResponse(
                            'Job sinkron target masih berjalan. Tunggu sampai selesai sebelum menjalankan lagi.',
                            'sync_targets_progress'
                        );
                    }
                    SynchronizeTargetsJob::dispatch();
                    break;
                case 'period':
                    SynchronizePeriodesJob::dispatch();
                    break;
                case 'sp_branch':
                    $this->clearStaleSyncLock('sync_sp_branches_lock', 'sync_sp_branches_progress');
                    if (!Cache::add('sync_sp_branches_lock', true, now()->addHours(2))) {
                        return $this->syncConflictResponse(
                            'Job sinkron SP Branch masih berjalan. Tunggu sampai selesai sebelum menjalankan lagi.',
                            'sync_sp_branches_progress'
                        );
                    }
                    SynchronizeSpBranchesJob::dispatch();
                    break;
                case 'delivery_notes':
                    // Sinkron delivery_notes (m_kirim_cabang) dan delivery_note_details (d_kirim_cabang) sekaligus
                    $this->clearStaleSyncLock('sync_delivery_notes_lock', 'sync_delivery_notes_progress');
                    $this->clearStaleSyncLock('sync_delivery_note_details_lock', 'sync_delivery_note_details_progress');
                    if (!Cache::add('sync_delivery_notes_lock', true, now()->addHours(2))) {
                        return $this->syncConflictResponse(
                            'Job sinkron nota kirim (m_kirim_cabang) masih berjalan. Tunggu sampai selesai sebelum menjalankan lagi.',
                            'sync_delivery_notes_progress'
                        );
                    }
                    if (!Cache::add('sync_delivery_note_details_lock', true, now()->addHours(2))) {
                        Cache::forget('sync_delivery_notes_lock');
                        return $this->syncConflictResponse(
                            'Job sinkron detail kirim (d_kirim_cabang) masih berjalan. Tunggu sampai selesai sebelum menjalankan lagi.',
                            'sync_delivery_note_details_progress'
                        );
                    }
                    SynchronizeDeliveryNotesJob::dispatch();
                    SynchronizeDeliveryNoteDetailsJob::dispatch();
                    break;
                case 'receive_notes':
                    $this->clearStaleSyncLock('sync_receive_book_notes_lock', 'sync_receive_book_notes_progress');
                    $this->clearStaleSyncLock('sync_receive_book_note_details_lock', 'sync_receive_book_note_details_progress');
                    if (!Cache::add('sync_receive_book_notes_lock', true, now()->addHours(2))) {
                        return $this->syncConflictResponse(
                            'Job sinkron nota terima (m_terima_buku) masih berjalan. Tunggu sampai selesai sebelum menjalankan lagi.',
                            'sync_receive_book_notes_progress'
                        );
                    }
                    if (!Cache::add('sync_receive_book_note_details_lock', true, now()->addHours(2))) {
                        Cache::forget('sync_receive_book_notes_lock');
                        return $this->syncConflictResponse(
                            'Job sinkron detail terima (d_terima_buku) masih berjalan. Tunggu sampai selesai sebelum menjalankan lagi.',
                            'sync_receive_book_note_details_progress'
                        );
                    }
                    SynchronizeReceiveBookNotesJob::dispatch();
                    SynchronizeReceiveBookNoteDetailsJob::dispatch();
                    break;
                case 'stock_mutations':
                    $this->clearStaleSyncLock('sync_stock_mutations_lock', 'sync_stock_mutations_progress');
                    $this->clearStaleSyncLock('sync_stock_mutation_items_lock', 'sync_stock_mutation_items_progress');
                    if (!Cache::add('sync_stock_mutations_lock', true, now()->addHours(2))) {
                        return $this->syncConflictResponse(
                            'Job sinkron mutasi buku (m_mutasi_buku) masih berjalan. Tunggu sampai selesai sebelum menjalankan lagi.',
                            'sync_stock_mutations_progress'
                        );
                    }
                    if (!Cache::add('sync_stock_mutation_items_lock', true, now()->addHours(2))) {
                        Cache::forget('sync_stock_mutations_lock');
                        return $this->syncConflictResponse(
                            'Job sinkron detail mutasi (d_mutasi_buku) masih berjalan. Tunggu sampai selesai sebelum menjalankan lagi.',
                            'sync_stock_mutation_items_progress'
                        );
                    }
                    SynchronizeStockMutationsJob::dispatch();
                    SynchronizeStockMutationItemsJob::dispatch();
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Sinkronisasi ' . $this->getTypeName($type) . ' telah dimulai. Silakan refresh halaman untuk melihat progress.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get progress for specific type
     */
    public function getProgress(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:product,branch,central_stock,target,period,sp_branch,delivery_notes,receive_notes,stock_mutations',
        ]);

        $type = $request->input('type');

        if ($type === 'delivery_notes') {
            $state = $this->combinedMasterDetailSyncState(
                'sync_delivery_notes_progress',
                'sync_delivery_note_details_progress'
            );

            return response()->json([
                'success' => true,
                'progress' => $state['progress'],
            ]);
        }

        if ($type === 'receive_notes') {
            $state = $this->combinedMasterDetailSyncState(
                'sync_receive_book_notes_progress',
                'sync_receive_book_note_details_progress'
            );

            return response()->json([
                'success' => true,
                'progress' => $state['progress'],
            ]);
        }

        if ($type === 'stock_mutations') {
            $state = $this->combinedMasterDetailSyncState(
                'sync_stock_mutations_progress',
                'sync_stock_mutation_items_progress'
            );
            return response()->json([
                'success'  => true,
                'progress' => $state['progress'],
            ]);
        }

        // For other types, use normal progress
        $cacheKey = $this->getCacheKey($type);
        $progress = Cache::get($cacheKey, null);

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    /**
     * Hapus lock yang tertinggal jika job sudah selesai/error (progress status completed/error/failed).
     * Jadi kalau job SP Branch/Target/dll. dulu gagal atau worker mati tanpa sempat clear lock,
     * user tetap bisa sinkron lagi dari halaman staging.
     */
    private function clearStaleSyncLock(string $lockKey, string $progressKey): void
    {
        $progress = Cache::get($progressKey);
        if (!is_array($progress ?? null)) {
            return;
        }
        $status = $progress['status'] ?? null;
        if (in_array($status, ['completed', 'error', 'failed'], true)) {
            Cache::forget($lockKey);
        }
    }

    /**
     * Respons 409 saat lock sync masih aktif: sertakan progress dari cache + hint untuk UI.
     */
    private function syncConflictResponse(string $message, string $progressCacheKey): JsonResponse
    {
        $progress = Cache::get($progressCacheKey);
        $progressArr = is_array($progress) ? $progress : null;

        return response()->json([
            'success' => false,
            'message' => $message,
            'progress' => $progressArr,
            'progress_hint' => $this->syncProgressHint($progressArr),
        ], 409);
    }

    /**
     * Teks tambahan untuk user: sudah berapa %, status job, atau kemungkinan worker queue belum jalan.
     */
    private function syncProgressHint(?array $progress): string
    {
        if ($progress === null) {
            return 'Belum ada data progress di cache. Jika baru memulai sync, pastikan worker antrian jalan '
                . '(mis. `php artisan queue:work` atau Horizon). Cron `schedule:run` saja tidak memproses job kecuali '
                . 'Anda memang menjadwalkan `queue:work` di dalamnya.';
        }

        $status = $progress['status'] ?? 'unknown';
        $pct = isset($progress['percentage']) ? (float) $progress['percentage'] : 0.0;
        $processed = (int) ($progress['processed'] ?? 0);
        $total = (int) ($progress['total'] ?? 0);

        $line = sprintf(
            'Status: %s — %s / %s (%.1f%%).',
            $status,
            number_format($processed, 0, ',', '.'),
            number_format($total, 0, ',', '.'),
            $pct
        );

        if ($status === 'running' && $total > 0 && $processed === 0) {
            return $line . ' Job sudah antri / baru mulai. Jika lama tetap 0%, cek worker queue di server.';
        }

        if ($status === 'running') {
            return $line . ' Sinkronisasi sedang berjalan.';
        }

        if (in_array($status, ['completed', 'error', 'failed'], true)) {
            return $line . ' Progress menunjuk selesai/error; coba refresh halaman. Jika masih terkunci, lock akan dibersihkan otomatis.';
        }

        return $line;
    }

    /**
     * Get cache key for type
     */
    private function getCacheKey($type)
    {
        $cacheKeys = [
            'product'         => 'sync_products_progress',
            'branch'          => 'sync_branches_progress',
            'central_stock'   => 'sync_central_stocks_progress',
            'target'          => 'sync_targets_progress',
            'period'          => 'sync_periodes_progress',
            'sp_branch'       => 'sync_sp_branches_progress',
            'delivery_notes'  => 'sync_delivery_notes_progress',
            'receive_notes'   => 'sync_receive_book_notes_progress',
            'stock_mutations' => 'sync_stock_mutations_progress',
        ];

        return $cacheKeys[$type] ?? '';
    }

    /**
     * Get type name for display
     */
    private function getTypeName($type)
    {
        $names = [
            'product'         => 'Product',
            'branch'          => 'Branch',
            'central_stock'   => 'Central Stock',
            'target'          => 'Target',
            'period'          => 'Periode',
            'sp_branch'       => 'Pesanan (Sp Branch)',
            'delivery_notes'  => 'Delivery Notes',
            'receive_notes'   => 'Nota Terima',
            'stock_mutations' => 'Mutasi Buku',
        ];

        return $names[$type] ?? $type;
    }

    /**
     * Store cutoff data
     */
    public function storeCutoffData(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'required|date',
        ]);
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $request->validate(['end_date' => 'after_or_equal:start_date']);
        }

        try {
            // Set all existing data to inactive
            CutoffData::where('status', 'active')->update(['status' => 'inactive']);

            // Create new cutoff data with active status (start_date optional: null = data <= end_date)
            $cutoffData = CutoffData::create([
                'start_date' => $request->filled('start_date') ? $request->start_date : null,
                'end_date' => $request->end_date,
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cutoff data berhasil disimpan.',
                'data' => $cutoffData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update cutoff data
     */
    public function updateCutoffData(Request $request, $id)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'required|date',
        ]);
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $request->validate(['end_date' => 'after_or_equal:start_date']);
        }

        try {
            $cutoffData = CutoffData::findOrFail($id);
            $cutoffData->start_date = $request->filled('start_date') ? $request->start_date : null;
            $cutoffData->end_date = $request->end_date;
            $cutoffData->save();

            return response()->json([
                'success' => true,
                'message' => 'Cutoff data berhasil diubah.',
                'data' => $cutoffData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete cutoff data
     */
    public function destroyCutoffData(Request $request, $id)
    {
        try {
            $cutoffData = CutoffData::findOrFail($id);
            $cutoffData->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cutoff data berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle cutoff data status
     */
    public function toggleCutoffData(Request $request, $id)
    {
        try {
            $cutoffData = CutoffData::findOrFail($id);

            if ($cutoffData->status === 'active') {
                // If deactivating, just set to inactive
                $cutoffData->status = 'inactive';
            } else {
                // If activating, set all others to inactive first
                CutoffData::where('status', 'active')->update(['status' => 'inactive']);
                $cutoffData->status = 'active';
            }

            $cutoffData->save();

            return response()->json([
                'success' => true,
                'message' => 'Status cutoff data berhasil diubah.',
                'data' => $cutoffData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
