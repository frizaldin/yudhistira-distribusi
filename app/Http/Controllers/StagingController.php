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
use App\Models\CutoffData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StagingController extends Controller
{
    /** Cache count staging (detik) - kurangi query ke PostgreSQL tiap load */
    const STAGING_COUNT_CACHE_TTL = 120;

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
        ];

        // Get sync progress and last sync time for each item
        foreach ($stagingData as &$item) {
            // Special handling for delivery_notes: combine progress from both jobs
            if ($item['key'] === 'delivery_notes') {
                $progressNotes = Cache::get('sync_delivery_notes_progress', null);
                $progressDetails = Cache::get('sync_delivery_note_details_progress', null);

                // Combine progress from both jobs
                if ($progressNotes || $progressDetails) {
                    $combinedProgress = [
                        'status' => 'running',
                        'total' => 0,
                        'processed' => 0,
                        'created' => 0,
                        'updated' => 0,
                        'errors' => 0,
                        'percentage' => 0,
                    ];

                    // Sum totals first
                    $combinedProgress['total'] = ($progressNotes['total'] ?? 0) + ($progressDetails['total'] ?? 0);
                    $combinedProgress['processed'] = ($progressNotes['processed'] ?? 0) + ($progressDetails['processed'] ?? 0);
                    $combinedProgress['created'] = ($progressNotes['created'] ?? 0) + ($progressDetails['created'] ?? 0);
                    $combinedProgress['updated'] = ($progressNotes['updated'] ?? 0) + ($progressDetails['updated'] ?? 0);
                    $combinedProgress['errors'] = ($progressNotes['errors'] ?? 0) + ($progressDetails['errors'] ?? 0);

                    // Determine overall status
                    $notesStatus = $progressNotes['status'] ?? null;
                    $detailsStatus = $progressDetails['status'] ?? null;

                    // If both are null, status is running (jobs might be starting)
                    if ($notesStatus === null && $detailsStatus === null) {
                        $combinedProgress['status'] = 'running';
                    } else if ($notesStatus === 'running' || $detailsStatus === 'running') {
                        $combinedProgress['status'] = 'running';
                    } else if ($notesStatus === 'failed' || $detailsStatus === 'failed') {
                        $combinedProgress['status'] = 'failed';
                    } else {
                        // Both are completed or at least not running/failed
                        // If both jobs have status 'completed', mark as completed
                        if (($notesStatus === 'completed' || $notesStatus === null) &&
                            ($detailsStatus === 'completed' || $detailsStatus === null)
                        ) {
                            $combinedProgress['status'] = 'completed';
                            // Use the latest completed_at
                            $notesCompletedAt = $progressNotes['completed_at'] ?? null;
                            $detailsCompletedAt = $progressDetails['completed_at'] ?? null;
                            if ($notesCompletedAt && $detailsCompletedAt) {
                                $combinedProgress['completed_at'] = $notesCompletedAt > $detailsCompletedAt ? $notesCompletedAt : $detailsCompletedAt;
                            } else {
                                $combinedProgress['completed_at'] = $notesCompletedAt ?? $detailsCompletedAt;
                            }
                        } else {
                            $combinedProgress['status'] = 'running';
                        }
                    }

                    // Calculate percentage
                    // When completed, ensure processed includes errors (errors are also processed records)
                    if ($combinedProgress['status'] === 'completed' && $combinedProgress['total'] > 0) {
                        // When completed, all records have been processed (including errors)
                        // So processed + errors should equal total, or we set processed = total
                        $actualProcessed = $combinedProgress['processed'] + $combinedProgress['errors'];
                        if ($actualProcessed >= $combinedProgress['total']) {
                            $combinedProgress['processed'] = $combinedProgress['total'];
                        } else {
                            // If somehow processed + errors < total, use total as processed
                            $combinedProgress['processed'] = $combinedProgress['total'];
                        }
                        $combinedProgress['percentage'] = 100;
                    } else if ($combinedProgress['total'] > 0) {
                        // While running, calculate percentage based on processed + errors
                        $actualProcessed = $combinedProgress['processed'] + $combinedProgress['errors'];
                        $combinedProgress['percentage'] = round(($actualProcessed / $combinedProgress['total']) * 100, 2);
                    }

                    $item['progress'] = $combinedProgress;
                    $item['is_running'] = $combinedProgress['status'] === 'running';

                    // Get last sync timestamp (use the latest one)
                    $lastSyncNotes = Cache::get('sync_delivery_notes_progress_last_sync', null);
                    $lastSyncDetails = Cache::get('sync_delivery_note_details_progress_last_sync', null);
                    if ($lastSyncNotes && $lastSyncDetails) {
                        $item['last_sync'] = $lastSyncNotes > $lastSyncDetails ? $lastSyncNotes : $lastSyncDetails;
                    } else {
                        $item['last_sync'] = $lastSyncNotes ?? $lastSyncDetails;
                    }
                } else {
                    $item['progress'] = null;
                    $item['is_running'] = false;
                    $item['last_sync'] = null;
                }
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
     * API: ambil semua count staging (untuk lazy-load di halaman, dengan cache).
     */
    public function getStagingCounts()
    {
        $counts = [
            'product' => $this->getStagingCount('m_book'),
            'branch' => $this->getStagingCount('m_cabang'),
            'central_stock' => $this->getStagingCount('r_stock_pusat'),
            'target' => $this->getStagingCount('r_target_buku'),
            'period' => $this->getStagingCount('m_period'),
            'sp_branch' => $this->getStagingCount('r_sp_faktur_stok'),
            'delivery_notes' => $this->getStagingCount('m_kirim_cabang') + $this->getStagingCount('d_kirim_cabang'),
        ];
        return response()->json($counts);
    }

    /**
     * Get count from PostgreSQL staging table (dengan cache agar halaman tidak lambat).
     */
    private function getStagingCount($table)
    {
        $cacheKey = 'staging_count_' . str_replace(',', '_', $table);
        try {
            return Cache::remember($cacheKey, self::STAGING_COUNT_CACHE_TTL, function () use ($table) {
                return DB::connection('pgsql')->table($table)->count();
            });
        } catch (\Exception $e) {
            Cache::forget($cacheKey);
            return 0;
        }
    }

    /**
     * Hapus cache count staging untuk tabel tertentu (dipanggil setelah sync).
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
            default => [],
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
            $types = ['product', 'branch', 'period', 'central_stock', 'target', 'sp_branch', 'delivery_notes'];
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
                        SynchronizeCentralStocksJob::dispatch();
                        break;
                    case 'target':
                        SynchronizeTargetsJob::dispatch(false);
                        break;
                    case 'period':
                        SynchronizePeriodesJob::dispatch();
                        break;
                    case 'sp_branch':
                        SynchronizeSpBranchesJob::dispatch(false);
                        break;
                    case 'delivery_notes':
                        SynchronizeDeliveryNotesJob::dispatch();
                        SynchronizeDeliveryNoteDetailsJob::dispatch();
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
            'type' => 'required|string|in:product,branch,central_stock,target,period,sp_branch,delivery_notes',
        ]);

        $type = $request->input('type');
        $clearFirst = $request->input('clear_first', false);

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
                    SynchronizeCentralStocksJob::dispatch();
                    break;
                case 'target':
                    SynchronizeTargetsJob::dispatch($clearFirst);
                    break;
                case 'period':
                    SynchronizePeriodesJob::dispatch();
                    break;
                case 'sp_branch':
                    SynchronizeSpBranchesJob::dispatch($clearFirst);
                    break;
                case 'delivery_notes':
                    // Sinkron delivery_notes (m_kirim_cabang) dan delivery_note_details (d_kirim_cabang) sekaligus
                    SynchronizeDeliveryNotesJob::dispatch();
                    SynchronizeDeliveryNoteDetailsJob::dispatch();
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
            'type' => 'required|string|in:product,branch,central_stock,target,period,sp_branch,delivery_notes',
        ]);

        $type = $request->input('type');

        // Special handling for delivery_notes: combine progress from both delivery_notes and delivery_note_details
        if ($type === 'delivery_notes') {
            $progressNotes = Cache::get('sync_delivery_notes_progress', null);
            $progressDetails = Cache::get('sync_delivery_note_details_progress', null);

            // Combine progress from both jobs
            if ($progressNotes || $progressDetails) {
                $combinedProgress = [
                    'status' => 'running',
                    'total' => 0,
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'errors' => 0,
                    'percentage' => 0,
                ];

                // Sum totals first
                $combinedProgress['total'] = ($progressNotes['total'] ?? 0) + ($progressDetails['total'] ?? 0);
                $combinedProgress['processed'] = ($progressNotes['processed'] ?? 0) + ($progressDetails['processed'] ?? 0);
                $combinedProgress['created'] = ($progressNotes['created'] ?? 0) + ($progressDetails['created'] ?? 0);
                $combinedProgress['updated'] = ($progressNotes['updated'] ?? 0) + ($progressDetails['updated'] ?? 0);
                $combinedProgress['errors'] = ($progressNotes['errors'] ?? 0) + ($progressDetails['errors'] ?? 0);

                // Determine overall status
                $notesStatus = $progressNotes['status'] ?? null;
                $detailsStatus = $progressDetails['status'] ?? null;

                // If both are null, status is running (jobs might be starting)
                if ($notesStatus === null && $detailsStatus === null) {
                    $combinedProgress['status'] = 'running';
                } else if ($notesStatus === 'running' || $detailsStatus === 'running') {
                    $combinedProgress['status'] = 'running';
                } else if ($notesStatus === 'failed' || $detailsStatus === 'failed') {
                    $combinedProgress['status'] = 'failed';
                } else {
                    // Both are completed or at least not running/failed
                    // If both jobs have status 'completed', mark as completed
                    if (($notesStatus === 'completed' || $notesStatus === null) &&
                        ($detailsStatus === 'completed' || $detailsStatus === null)
                    ) {
                        $combinedProgress['status'] = 'completed';
                        // Use the latest completed_at
                        $notesCompletedAt = $progressNotes['completed_at'] ?? null;
                        $detailsCompletedAt = $progressDetails['completed_at'] ?? null;
                        if ($notesCompletedAt && $detailsCompletedAt) {
                            $combinedProgress['completed_at'] = $notesCompletedAt > $detailsCompletedAt ? $notesCompletedAt : $detailsCompletedAt;
                        } else {
                            $combinedProgress['completed_at'] = $notesCompletedAt ?? $detailsCompletedAt;
                        }
                    } else {
                        $combinedProgress['status'] = 'running';
                    }
                }

                // Calculate percentage
                // When completed, ensure processed includes errors (errors are also processed records)
                if ($combinedProgress['status'] === 'completed' && $combinedProgress['total'] > 0) {
                    // When completed, all records have been processed (including errors)
                    // So processed + errors should equal total, or we set processed = total
                    $actualProcessed = $combinedProgress['processed'] + $combinedProgress['errors'];
                    if ($actualProcessed >= $combinedProgress['total']) {
                        $combinedProgress['processed'] = $combinedProgress['total'];
                    } else {
                        // If somehow processed + errors < total, use total as processed
                        $combinedProgress['processed'] = $combinedProgress['total'];
                    }
                    $combinedProgress['percentage'] = 100;
                } else if ($combinedProgress['total'] > 0) {
                    // While running, calculate percentage based on processed + errors
                    $actualProcessed = $combinedProgress['processed'] + $combinedProgress['errors'];
                    $combinedProgress['percentage'] = round(($actualProcessed / $combinedProgress['total']) * 100, 2);
                }

                return response()->json([
                    'success' => true,
                    'progress' => $combinedProgress,
                ]);
            }

            // No progress data yet
            return response()->json([
                'success' => true,
                'progress' => null,
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
     * Get cache key for type
     */
    private function getCacheKey($type)
    {
        $cacheKeys = [
            'product' => 'sync_products_progress',
            'branch' => 'sync_branches_progress',
            'central_stock' => 'sync_central_stocks_progress',
            'target' => 'sync_targets_progress',
            'period' => 'sync_periodes_progress',
            'sp_branch' => 'sync_sp_branches_progress',
            'delivery_notes' => 'sync_delivery_notes_progress',
        ];

        return $cacheKeys[$type] ?? '';
    }

    /**
     * Get type name for display
     */
    private function getTypeName($type)
    {
        $names = [
            'product' => 'Product',
            'branch' => 'Branch',
            'central_stock' => 'Central Stock',
            'target' => 'Target',
            'period' => 'Periode',
            'sp_branch' => 'Pesanan (Sp Branch)',
            'delivery_notes' => 'Delivery Notes',
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
