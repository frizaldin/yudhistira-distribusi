<?php

namespace App\Http\Controllers;

use App\Models\SpBranch;
use App\Models\Branch;
use App\Models\CentralStock;
use App\Models\Target;
use App\Models\NppbCentral;
use App\Models\Periode;
use App\Models\Product;
use App\Models\CutoffData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\BuildRekapJob;

class RekapController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/recap');
        $this->title = 'Rekapitulasi';
        $this->role = 1;
        $this->callbackfolder = 'superadmin';
    }

    /**
     * Display rekapitulasi report
     */
    public function index(Request $request)
    {
        // Debug: pastikan controller terpanggil di server (cek dengan ?ping=1)
        if ($request->query('ping') === '1') {
            return response()->json(['status' => 'ok', 'message' => 'RekapController reached'], 200);
        }

        try {
            // Batas waktu & memory agar rekap tidak 500 (tanpa queue, proses di request)
            @set_time_limit(500);
            $memoryLimit = ini_get('memory_limit');
            if ($memoryLimit !== '-1' && (int) $memoryLimit < 1024) {
                @ini_set('memory_limit', '1024M');
            }
            if (!config('app.debug')) {
                DB::disableQueryLog();
            }

            // Set role & view folder dari Auth (setelah controller aman di-instance)
            try {
                if (Auth::check()) {
                    $user = Auth::user();
                    $this->role = (int) ($user->authority_id ?? 1);
                    $this->callbackfolder = ($this->role === 2) ? 'branch' : 'superadmin';
                }
            } catch (\Throwable $e) {
                $this->role = 1;
                $this->callbackfolder = 'superadmin';
            }

            // Get year from request or use current year
            $year = $request->input('year', date('Y'));
            $filterBookCode = $request->input('book_code', '');
            $filterBookCode = trim($filterBookCode);

            $userBranchCode = null;
            $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
            if ($this->role == 2 && Auth::check()) {
                $userBranchCode = Auth::user()->branch_code ?? null;
            }

            // Mode AJAX: load halaman ringan, data diisi per bagian via API (menghindari 500/timeout)
            $filterBranchCode = trim((string) $request->input('branch_code', ''));
            $activeCutoff = CutoffData::where('status', 'active')->first();
            $allBranchesQuery = Branch::select(['branch_code', 'branch_name'])->orderBy('branch_code');
            if ($filteredBranchCodes !== null) {
                $allBranchesQuery->whereIn('branch_code', $filteredBranchCodes);
            }
            $allBranchesForFilter = $allBranchesQuery->get();

            $branchesQuery = Branch::select(['branch_code', 'branch_name'])->orderBy('branch_code');
            if ($filterBranchCode !== '') {
                $allowed = $filteredBranchCodes === null || in_array($filterBranchCode, $filteredBranchCodes);
                if ($allowed) {
                    $branchesQuery->where('branch_code', $filterBranchCode);
                } else {
                    $branchesQuery->whereRaw('1 = 0');
                }
            } elseif ($filteredBranchCodes !== null) {
                $branchesQuery->whereIn('branch_code', $filteredBranchCodes);
            }
            $branches = $branchesQuery->get();

            $filterBookTitle = '';
            if ($filterBookCode !== '') {
                $product = Product::where('book_code', $filterBookCode)->first();
                $filterBookTitle = $product->book_title ?? '';
            }
            return view($this->callbackfolder . '.rekapitulasi.index', [
                'title' => $this->title,
                'base_url' => $this->base_url,
                'year' => $year,
                'use_ajax' => true,
                'branches' => $branches,
                'allBranchesForFilter' => $allBranchesForFilter,
                'nasional' => null,
                'areas' => [],
                'activeCutoff' => $activeCutoff,
                'filterBookCode' => $filterBookCode,
                'filterBookTitle' => $filterBookTitle,
                'filterBranchCode' => $filterBranchCode,
            ]);

            } catch (\Throwable $e) {
            Log::error('RekapController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $message = config('app.env') === 'production'
                ? 'Terjadi kesalahan saat memuat rekapitulasi. Silakan coba lagi atau hubungi administrator.'
                : 'Terjadi kesalahan: ' . $e->getMessage();

            // Debug: tampilkan error di response agar bisa dilihat di server (APP_DEBUG atau ?debug=1)
            $showError = config('app.debug') || $request->query('debug') === '1';
            if ($showError) {
                $body = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error Rekap</title></head><body>';
                $body .= '<h2>Error RekapController</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                $body .= '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' (line ' . $e->getLine() . ')</p>';
                $body .= '<h3>Trace</h3><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                $body .= '<p><a href="/recap">Kembali ke Rekapitulasi</a></p></body></html>';
                return response()->make($body, 500, ['Content-Type' => 'text/html; charset=utf-8']);
            }

            // Redirect ke /recap dengan flash error
            $url = '/recap';
            try {
                $prev = url()->previous();
                if ($prev && $prev !== url()->current()) {
                    $url = $prev;
                }
            } catch (\Throwable $ignored) {
            }
            try {
                return redirect()->to($url)->with('error', $message);
            } catch (\Throwable $redirectEx) {
                Log::error('RekapController@index redirect failed: ' . $redirectEx->getMessage());
                return response()->make(
                    '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body><p>Terjadi kesalahan saat memuat rekapitulasi.</p><p><a href="/recap">Kembali ke Rekapitulasi</a></p></body></html>',
                    500,
                    ['Content-Type' => 'text/html; charset=utf-8']
                );
            }
        }
    }

    /**
     * Export data rekap ke CSV (NASIONAL + semua cabang).
     */
    public function export(Request $request)
    {
        try {
            @set_time_limit(300);
            if (Auth::check()) {
                $user = Auth::user();
                $this->role = (int) ($user->authority_id ?? 1);
                $this->callbackfolder = ($this->role === 2) ? 'branch' : 'superadmin';
            } else {
                $this->role = 1;
                $this->callbackfolder = 'superadmin';
            }

            $year = $request->input('year', date('Y'));
            $filterBookCode = trim((string) $request->input('book_code', ''));
            $userBranchCode = $this->role == 2 && Auth::check() ? (Auth::user()->branch_code ?? null) : null;
            $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
            $filterBranchCode = trim((string) $request->input('branch_code', ''));
            if ($filterBranchCode !== '') {
                $filteredBranchCodes = $filteredBranchCodes !== null
                    ? array_values(array_intersect([$filterBranchCode], $filteredBranchCodes))
                    : [$filterBranchCode];
            }

            $result = $this->buildRecapDataForCache($year, $filterBookCode, $this->role, $userBranchCode, $filteredBranchCodes, $this->callbackfolder);
            $data = $result['data'];
            $nasional = $data['nasional'] ?? [];
            $areas = $data['areas'] ?? [];

            $headers = [
                'NO',
                'CABANG',
                'TARGET',
                'SP',
                'FAKTUR',
                'SISA SP',
                'NKB DARI PUSAT A',
                'STOCK CABANG',
                'THD TARGET LEBIH',
                'THD TARGET KURANG',
                'THD SP LEBIH',
                'THD SP KURANG',
                'KOLI',
                'PLS',
                'EXP',
                '% REAL',
                '% TARGET',
                '% SP',
            ];

            $rows = [];
            $no = 1;
            $rows[] = [
                $no++,
                'NASIONAL',
                $nasional['target'] ?? 0,
                $nasional['total_sp'] ?? 0,
                $nasional['total_faktur'] ?? 0,
                $nasional['sisa_sp'] ?? 0,
                $nasional['total_nkb'] ?? 0,
                $nasional['total_stok_cabang'] ?? 0,
                $nasional['thd_target_lebih'] ?? 0,
                $nasional['thd_target_kurang'] ?? 0,
                $nasional['thd_sp_lebih'] ?? 0,
                $nasional['thd_sp_kurang'] ?? 0,
                $nasional['total_nppb_koli'] ?? 0,
                $nasional['total_nppb_pls'] ?? 0,
                $nasional['total_nppb_exp'] ?? 0,
                '-',
                '-',
                '-',
            ];

            foreach ($areas as $area) {
                foreach ($area['branches'] as $branch) {
                    $pctTarget = ($branch->target ?? 0) > 0
                        ? round((($branch->total_stok_cabang ?? 0) / ($branch->target ?? 1)) * 100)
                        : 0;
                    $pctSp = ($branch->total_sp ?? 0) > 0
                        ? round((($branch->total_stok_cabang ?? 0) / ($branch->total_sp ?? 1)) * 100)
                        : 0;
                    $rows[] = [
                        $no++,
                        $branch->branch_name ?? $branch->branch_code,
                        $branch->target ?? 0,
                        $branch->total_sp ?? 0,
                        $branch->total_faktur ?? 0,
                        $branch->sisa_sp ?? 0,
                        $branch->total_nkb ?? 0,
                        $branch->total_stok_cabang ?? 0,
                        $branch->thd_target_lebih ?? 0,
                        $branch->thd_target_kurang ?? 0,
                        $branch->thd_sp_lebih ?? 0,
                        $branch->thd_sp_kurang ?? 0,
                        $branch->nppb_koli ?? 0,
                        $branch->nppb_pls ?? 0,
                        $branch->nppb_exp ?? 0,
                        '-',
                        $pctTarget > 0 ? $pctTarget . '%' : '-',
                        $pctSp > 0 ? $pctSp . '%' : '-',
                    ];
                }
            }

            $filename = 'rekapitulasi-' . now()->format('Y-m-d-His') . '.csv';
            $callback = function () use ($headers, $rows) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($out, $headers);
                foreach ($rows as $r) {
                    fputcsv($out, $r);
                }
                fclose($out);
            };

            return response()->stream($callback, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            Log::error('RekapController@export: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('recap.index')->with('error', 'Gagal mengekspor data rekap.');
        }
    }

    /**
     * API: Summary (penjualan SMT + NKB + stock cabang) per cabang.
     */
    public function apiSummary(Request $request)
    {
        try {
            $ctx = $this->getRecapApiContext($request);
            if ($ctx instanceof \Illuminate\Http\JsonResponse) {
                return $ctx;
            }
            ['startDate' => $startDate, 'endDate' => $endDate, 'filterBookCode' => $filterBookCode, 'userBranchCode' => $userBranchCode, 'filteredBranchCodes' => $filteredBranchCodes, 'activeCutoff' => $activeCutoff] = $ctx;

            $targetsQuery = Target::select(['targets.branch_code', DB::raw('SUM(targets.exemplar) as total_target')])
                ->join('periods', 'targets.period_code', '=', 'periods.period_code');
            $this->applyRecapDateFilter($targetsQuery, $activeCutoff, $startDate, $endDate, $ctx['year']);
            $targets = $targetsQuery->when($filterBookCode !== '', fn($q) => $q->where('targets.book_code', $filterBookCode))
                ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('targets.branch_code', $filteredBranchCodes))
                ->groupBy('targets.branch_code')->get()->keyBy('branch_code');

            $spQuery = SpBranch::select([
                'sp_branches.branch_code',
                DB::raw('SUM(sp_branches.ex_sp) as total_sp'),
                DB::raw('SUM(sp_branches.ex_ftr) as total_faktur'),
                DB::raw('SUM(sp_branches.ex_sp) - SUM(sp_branches.ex_ftr) as sisa_sp'),
                DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nkb'),
                DB::raw('SUM(sp_branches.ex_stock) as total_stok_cabang'),
            ])->where('sp_branches.active_data', 'yes')->whereNotNull('sp_branches.branch_code');
            $this->applyRecapSpDateFilter($spQuery, $activeCutoff, $startDate, $endDate);
            $spQuery->when($filterBookCode !== '', fn($q) => $q->where('sp_branches.book_code', $filterBookCode))
                ->when($userBranchCode, fn($q) => $q->where('sp_branches.branch_code', $userBranchCode))
                ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('sp_branches.branch_code', $filteredBranchCodes));
            $spRows = $spQuery->groupBy('sp_branches.branch_code')->get();

            $nasional = ['target' => 0, 'total_sp' => 0, 'total_faktur' => 0, 'sisa_sp' => 0, 'total_nkb' => 0, 'total_stok_cabang' => 0];
            $branches = [];
            foreach ($spRows as $row) {
                $target = (float) ($targets->get($row->branch_code)?->total_target ?? 0);
                $nasional['target'] += $target;
                $nasional['total_sp'] += (float) $row->total_sp;
                $nasional['total_faktur'] += (float) $row->total_faktur;
                $nasional['sisa_sp'] += (float) $row->sisa_sp;
                $nasional['total_nkb'] += (float) $row->total_nkb;
                $nasional['total_stok_cabang'] += (float) $row->total_stok_cabang;
                $branches[$row->branch_code] = [
                    'target' => $target,
                    'total_sp' => (float) $row->total_sp,
                    'total_faktur' => (float) $row->total_faktur,
                    'sisa_sp' => (float) $row->sisa_sp,
                    'total_nkb' => (float) $row->total_nkb,
                    'total_stok_cabang' => (float) $row->total_stok_cabang,
                ];
            }
            return response()->json(['nasional' => $nasional, 'branches' => $branches]);
        } catch (\Throwable $e) {
            Log::error('RekapController@apiSummary: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Ketersediaan stock (THD target & THD SP lebih/kurang).
     * Di-cache 3 menit per parameter; agregasi per cabang dilakukan di DB agar load ringan.
     */
    public function apiKetersediaan(Request $request)
    {
        try {
            $ctx = $this->getRecapApiContext($request);
            if ($ctx instanceof \Illuminate\Http\JsonResponse) {
                return $ctx;
            }
            ['startDate' => $startDate, 'endDate' => $endDate, 'filterBookCode' => $filterBookCode, 'userBranchCode' => $userBranchCode, 'filteredBranchCodes' => $filteredBranchCodes] = $ctx;

            $cacheKey = 'recap:ketersediaan:' . $ctx['year'] . ':' . $filterBookCode . ':' . ($userBranchCode ?? '') . ':' . (is_array($filteredBranchCodes) ? implode(',', $filteredBranchCodes) : 'all');
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }

            $spSub = SpBranch::select(['sp_branches.branch_code', 'sp_branches.book_code', DB::raw('SUM(sp_branches.ex_stock) as stok'), DB::raw('SUM(sp_branches.ex_sp) as sp')])
                ->where('sp_branches.active_data', 'yes')->whereNotNull('sp_branches.book_code');
            $this->applyRecapSpDateFilter($spSub, $ctx['activeCutoff'], $startDate, $endDate);
            $spSub->when($filterBookCode !== '', fn($q) => $q->where('sp_branches.book_code', $filterBookCode))
                ->when($userBranchCode, fn($q) => $q->where('sp_branches.branch_code', $userBranchCode))
                ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('sp_branches.branch_code', $filteredBranchCodes))
                ->groupBy('sp_branches.branch_code', 'sp_branches.book_code');

            $targetSub = Target::select(['targets.branch_code', 'targets.book_code', DB::raw('SUM(targets.exemplar) as target')])
                ->join('periods', 'targets.period_code', '=', 'periods.period_code')->whereNotNull('targets.book_code');
            $this->applyRecapDateFilter($targetSub, $ctx['activeCutoff'], $startDate, $endDate, $ctx['year']);
            $targetSub->when($filterBookCode !== '', fn($q) => $q->where('targets.book_code', $filterBookCode))
                ->when($userBranchCode, fn($q) => $q->where('targets.branch_code', $userBranchCode))
                ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('targets.branch_code', $filteredBranchCodes))
                ->groupBy('targets.branch_code', 'targets.book_code');

            $spSql = $spSub->toSql();
            $targetSql = $targetSub->toSql();
            $bindings = array_merge($spSub->getBindings(), $targetSub->getBindings());

            $rows = DB::select(
                "SELECT s.branch_code, " .
                "SUM(GREATEST(0, s.stok - s.sp)) AS thd_sp_lebih, " .
                "SUM(GREATEST(0, s.sp - s.stok)) AS thd_sp_kurang, " .
                "SUM(GREATEST(0, s.stok - IFNULL(t.target, 0))) AS thd_target_lebih, " .
                "SUM(GREATEST(0, IFNULL(t.target, 0) - s.stok)) AS thd_target_kurang " .
                "FROM ({$spSql}) s " .
                "LEFT JOIN ({$targetSql}) t ON s.branch_code = t.branch_code AND s.book_code = t.book_code " .
                "GROUP BY s.branch_code",
                $bindings
            );

            $thdByBranch = [];
            $nasional = ['thd_target_lebih' => 0, 'thd_target_kurang' => 0, 'thd_sp_lebih' => 0, 'thd_sp_kurang' => 0];
            foreach ($rows as $r) {
                $bc = $r->branch_code;
                $thdByBranch[$bc] = [
                    'thd_target_lebih' => (float) $r->thd_target_lebih,
                    'thd_target_kurang' => (float) $r->thd_target_kurang,
                    'thd_sp_lebih' => (float) $r->thd_sp_lebih,
                    'thd_sp_kurang' => (float) $r->thd_sp_kurang,
                ];
                $nasional['thd_target_lebih'] += $thdByBranch[$bc]['thd_target_lebih'];
                $nasional['thd_target_kurang'] += $thdByBranch[$bc]['thd_target_kurang'];
                $nasional['thd_sp_lebih'] += $thdByBranch[$bc]['thd_sp_lebih'];
                $nasional['thd_sp_kurang'] += $thdByBranch[$bc]['thd_sp_kurang'];
            }

            $payload = ['nasional' => $nasional, 'branches' => $thdByBranch];
            Cache::put($cacheKey, $payload, now()->addMinutes(3));

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('RekapController@apiKetersediaan: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Rencana NPPB (koli, pls, exp) per cabang.
     */
    public function apiNppb(Request $request)
    {
        try {
            $ctx = $this->getRecapApiContext($request);
            if ($ctx instanceof \Illuminate\Http\JsonResponse) {
                return $ctx;
            }
            ['startDate' => $startDate, 'endDate' => $endDate, 'filterBookCode' => $filterBookCode, 'userBranchCode' => $userBranchCode, 'filteredBranchCodes' => $filteredBranchCodes] = $ctx;

            $nppbQuery = NppbCentral::select(['branch_code', DB::raw('SUM(koli) as total_koli'), DB::raw('SUM(pls) as total_pls'), DB::raw('SUM(exp) as total_exp')]);
            if ($ctx['activeCutoff']) {
                $startDate !== null ? $nppbQuery->whereBetween('date', [$startDate, $endDate]) : $nppbQuery->where('date', '<=', $endDate);
            }
            $nppbRows = $nppbQuery->when($filterBookCode !== '', fn($q) => $q->where('book_code', $filterBookCode))
                ->when($userBranchCode, fn($q) => $q->where('branch_code', $userBranchCode))
                ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('branch_code', $filteredBranchCodes))
                ->groupBy('branch_code')->get();

            $nasional = ['total_nppb_koli' => 0, 'total_nppb_pls' => 0, 'total_nppb_exp' => 0];
            $branches = [];
            foreach ($nppbRows as $row) {
                $nasional['total_nppb_koli'] += (float) $row->total_koli;
                $nasional['total_nppb_pls'] += (float) $row->total_pls;
                $nasional['total_nppb_exp'] += (float) $row->total_exp;
                $branches[$row->branch_code] = ['nppb_koli' => (float) $row->total_koli, 'nppb_pls' => (float) $row->total_pls, 'nppb_exp' => (float) $row->total_exp];
            }
            return response()->json(['nasional' => $nasional, 'branches' => $branches]);
        } catch (\Throwable $e) {
            Log::error('RekapController@apiNppb: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getRecapApiContext(Request $request): array|\Illuminate\Http\JsonResponse
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();
                $this->role = (int) ($user->authority_id ?? 1);
            } else {
                $this->role = 1;
            }
            $userBranchCode = $this->role == 2 && Auth::check() ? (Auth::user()->branch_code ?? null) : null;
            $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
            $filterBranchCode = trim((string) $request->input('branch_code', ''));
            if ($filterBranchCode !== '') {
                $filteredBranchCodes = $filteredBranchCodes !== null
                    ? array_values(array_intersect([$filterBranchCode], $filteredBranchCodes))
                    : [$filterBranchCode];
            }
            $year = $request->input('year', date('Y'));
            $filterBookCode = trim((string) $request->input('book_code', ''));
            $activeCutoff = CutoffData::where('status', 'active')->first();
            $startDate = $endDate = null;
            if ($activeCutoff) {
                $endDate = $activeCutoff->end_date ? \Carbon\Carbon::parse($activeCutoff->end_date)->format('Y-m-d') : null;
                $startDate = $activeCutoff->start_date ? \Carbon\Carbon::parse($activeCutoff->start_date)->format('Y-m-d') : null;
            }
            return [
                'year' => $year,
                'filterBookCode' => $filterBookCode,
                'userBranchCode' => $userBranchCode,
                'filteredBranchCodes' => $filteredBranchCodes,
                'activeCutoff' => $activeCutoff,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ];
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function applyRecapDateFilter($query, $activeCutoff, $startDate, $endDate, $year): void
    {
        if ($activeCutoff) {
            if ($startDate !== null) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
                });
            } else {
                $query->where('periods.to_date', '<=', $endDate);
            }
        } else {
            $query->where(function ($q) use ($year) {
                $q->whereYear('periods.from_date', $year)->orWhereYear('periods.to_date', $year);
            });
        }
    }

    private function applyRecapSpDateFilter($query, $activeCutoff, $startDate, $endDate): void
    {
        if ($activeCutoff) {
            $startDate !== null ? $query->whereBetween('sp_branches.trans_date', [$startDate, $endDate]) : $query->where('sp_branches.trans_date', '<=', $endDate);
        }
    }

    /**
     * Build recap data for cache/queue (dipanggil oleh BuildRekapJob dan ?sync=1).
     */
    public function buildRecapDataForCache(
        string $year,
        string $filterBookCode,
        int $role,
        ?string $userBranchCode,
        ?array $filteredBranchCodes,
        string $callbackfolder = 'superadmin'
    ): array {
        $activeCutoff = CutoffData::where('status', 'active')->first();
        $startDate = null;
        $endDate = null;
        if ($activeCutoff) {
            $endDate = $activeCutoff->end_date ? \Carbon\Carbon::parse($activeCutoff->end_date)->format('Y-m-d') : null;
            $startDate = $activeCutoff->start_date ? \Carbon\Carbon::parse($activeCutoff->start_date)->format('Y-m-d') : null;
        }

        $branchesQuery = Branch::select(['branch_code', 'branch_name'])->orderBy('branch_code');
        if ($filteredBranchCodes !== null) {
            $branchesQuery->whereIn('branch_code', $filteredBranchCodes);
        }
        $branches = $branchesQuery->get();

        $targetsQuery = Target::select(['targets.branch_code', DB::raw('SUM(targets.exemplar) as total_target')])
            ->join('periods', 'targets.period_code', '=', 'periods.period_code');
        if ($activeCutoff) {
            if ($startDate !== null) {
                $targetsQuery->where(function ($q) use ($startDate, $endDate) {
                    $q->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
                });
            } else {
                $targetsQuery->where('periods.to_date', '<=', $endDate);
            }
        } else {
            $targetsQuery->where(function ($query) use ($year) {
                $query->whereYear('periods.from_date', $year)->orWhereYear('periods.to_date', $year);
            });
        }
        $targets = $targetsQuery
            ->when($filterBookCode !== '', fn($q) => $q->where('targets.book_code', $filterBookCode))
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('targets.branch_code', $filteredBranchCodes))
            ->groupBy('targets.branch_code')->get()->keyBy('branch_code');

        $nppbCentralsQuery = NppbCentral::select(['branch_code', DB::raw('SUM(koli) as total_koli'), DB::raw('SUM(pls) as total_pls'), DB::raw('SUM(exp) as total_exp')]);
        if ($activeCutoff) {
            $startDate !== null ? $nppbCentralsQuery->whereBetween('date', [$startDate, $endDate]) : $nppbCentralsQuery->where('date', '<=', $endDate);
        }
        $nppbCentrals = $nppbCentralsQuery
            ->when($filterBookCode !== '', fn($q) => $q->where('book_code', $filterBookCode))
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('branch_code', $filteredBranchCodes))
            ->groupBy('branch_code')->get()->keyBy('branch_code');

        $spBranchesQuery = SpBranch::select([
            'sp_branches.branch_code',
            'branches.branch_name',
            DB::raw('SUM(sp_branches.ex_sp) as total_sp'),
            DB::raw('SUM(sp_branches.ex_ftr) as total_faktur'),
            DB::raw('SUM(sp_branches.ex_ret) as total_ret'),
            DB::raw('SUM(sp_branches.ex_ftr) - COALESCE(SUM(sp_branches.ex_ret), 0) as netto'),
            DB::raw('SUM(sp_branches.ex_sp) - SUM(sp_branches.ex_ftr) as sisa_sp'),
            DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nk'),
            DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nt'),
            DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nkb'),
            DB::raw('SUM(sp_branches.ex_stock) as total_stok_cabang'),
            DB::raw('SUM(sp_branches.ex_sp) as total_sp_1'),
            DB::raw('SUM(sp_branches.ex_ftr) as total_faktur_1'),
            DB::raw('SUM(sp_branches.ex_ret) as total_ret_1'),
            DB::raw('COALESCE(SUM(sp_branches.ex_rec_gdg), 0) as total_nt_1'),
            DB::raw('COALESCE(SUM(sp_branches.ex_rec_gdg), 0) as total_nkb_1'),
            DB::raw('COALESCE(SUM(sp_branches.ex_rec_gdg), 0) as total_ntb_1'),
        ])->leftJoin('branches', 'sp_branches.branch_code', '=', 'branches.branch_code')
            ->where('sp_branches.active_data', 'yes')->whereNotNull('sp_branches.branch_code');
        if ($activeCutoff) {
            $startDate !== null ? $spBranchesQuery->whereBetween('sp_branches.trans_date', [$startDate, $endDate]) : $spBranchesQuery->where('sp_branches.trans_date', '<=', $endDate);
        }
        $spBranches = $spBranchesQuery->when($filterBookCode !== '', fn($q) => $q->where('sp_branches.book_code', $filterBookCode))
            ->when($userBranchCode, fn($q) => $q->where('sp_branches.branch_code', $userBranchCode))
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('sp_branches.branch_code', $filteredBranchCodes))
            ->groupBy('sp_branches.branch_code', 'branches.branch_name')->get();

        $branchBooksQuery = SpBranch::select(['sp_branches.branch_code', 'sp_branches.book_code', DB::raw('SUM(sp_branches.ex_stock) as stok'), DB::raw('SUM(sp_branches.ex_sp) as sp')])
            ->where('sp_branches.active_data', 'yes')->whereNotNull('sp_branches.book_code');
        if ($activeCutoff) {
            $startDate !== null ? $branchBooksQuery->whereBetween('sp_branches.trans_date', [$startDate, $endDate]) : $branchBooksQuery->where('sp_branches.trans_date', '<=', $endDate);
        }
        $branchBooks = $branchBooksQuery->when($filterBookCode !== '', fn($q) => $q->where('sp_branches.book_code', $filterBookCode))
            ->when($userBranchCode, fn($q) => $q->where('sp_branches.branch_code', $userBranchCode))
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('sp_branches.branch_code', $filteredBranchCodes))
            ->groupBy('sp_branches.branch_code', 'sp_branches.book_code')->get();

        $bookTargetsQuery = Target::select(['targets.branch_code', 'targets.book_code', DB::raw('SUM(targets.exemplar) as target')])
            ->join('periods', 'targets.period_code', '=', 'periods.period_code')->whereNotNull('targets.book_code');
        if ($activeCutoff) {
            if ($startDate !== null) {
                $bookTargetsQuery->where(function ($q) use ($startDate, $endDate) {
                    $q->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
                });
            } else {
                $bookTargetsQuery->where('periods.to_date', '<=', $endDate);
            }
        } else {
            $bookTargetsQuery->where(function ($q) use ($year) {
                $q->whereYear('periods.from_date', $year)->orWhereYear('periods.to_date', $year);
            });
        }
        $bookTargets = $bookTargetsQuery->when($filterBookCode !== '', fn($q) => $q->where('targets.book_code', $filterBookCode))
            ->when($userBranchCode, fn($q) => $q->where('targets.branch_code', $userBranchCode))
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('targets.branch_code', $filteredBranchCodes))
            ->groupBy('targets.branch_code', 'targets.book_code')->get();
        $targetByBranchBook = $bookTargets->groupBy('branch_code')->map(fn($items) => $items->keyBy('book_code'));

        $thdSpByBranch = [];
        $thdTargetByBranch = [];
        foreach ($branchBooks->groupBy('branch_code') as $branchCode => $books) {
            $thdSpLebih = $thdSpKurang = $thdTargetLebih = $thdTargetKurang = 0;
            foreach ($books as $b) {
                $stok = (float)($b->stok ?? 0);
                $sp = (float)($b->sp ?? 0);
                $diffSp = $stok - $sp;
                $thdSpLebih += $diffSp > 0 ? $diffSp : 0;
                $thdSpKurang += $diffSp < 0 ? abs($diffSp) : 0;
                $target = (float)($targetByBranchBook->get($branchCode)?->get($b->book_code)?->target ?? 0);
                $diffTarget = $stok - $target;
                $thdTargetLebih += $diffTarget > 0 ? $diffTarget : 0;
                $thdTargetKurang += $diffTarget < 0 ? abs($diffTarget) : 0;
            }
            $thdSpByBranch[$branchCode] = ['lebih' => $thdSpLebih, 'kurang' => $thdSpKurang];
            $thdTargetByBranch[$branchCode] = ['lebih' => $thdTargetLebih, 'kurang' => $thdTargetKurang];
        }

        foreach ($spBranches as $branch) {
            $branch->target = $targets->get($branch->branch_code)?->total_target ?? 0;
            $nppbData = $nppbCentrals->get($branch->branch_code);
            $branch->nppb_koli = $nppbData?->total_koli ?? 0;
            $branch->nppb_pls = $nppbData?->total_pls ?? 0;
            $branch->nppb_exp = $nppbData?->total_exp ?? 0;
            $thdSp = $thdSpByBranch[$branch->branch_code] ?? ['lebih' => 0, 'kurang' => 0];
            $thdTarget = $thdTargetByBranch[$branch->branch_code] ?? ['lebih' => 0, 'kurang' => 0];
            $branch->thd_sp_lebih = $thdSp['lebih'];
            $branch->thd_sp_kurang = $thdSp['kurang'];
            $branch->thd_target_lebih = $thdTarget['lebih'];
            $branch->thd_target_kurang = $thdTarget['kurang'];
        }

        $nasional = [
            'target' => $spBranches->sum('target'),
            'total_sp' => $spBranches->sum('total_sp'),
            'total_faktur' => $spBranches->sum('total_faktur'),
            'total_ret' => $spBranches->sum('total_ret'),
            'netto' => $spBranches->sum('netto'),
            'sisa_sp' => $spBranches->sum('sisa_sp'),
            'total_nk' => $spBranches->sum('total_nk'),
            'total_nt' => $spBranches->sum('total_nt'),
            'total_nkb' => $spBranches->sum('total_nkb'),
            'total_stok_cabang' => $spBranches->sum('total_stok_cabang'),
            'total_sp_1' => $spBranches->sum('total_sp_1'),
            'total_faktur_1' => $spBranches->sum('total_faktur_1'),
            'total_ret_1' => $spBranches->sum('total_ret_1'),
            'total_nt_1' => $spBranches->sum('total_nt_1'),
            'total_nkb_1' => $spBranches->sum('total_nkb_1'),
            'total_ntb_1' => $spBranches->sum('total_ntb_1'),
            'total_nppb_koli' => $spBranches->sum('nppb_koli'),
            'total_nppb_pls' => $spBranches->sum('nppb_pls'),
            'total_nppb_exp' => $spBranches->sum('nppb_exp'),
            'thd_target_lebih' => $spBranches->sum('thd_target_lebih'),
            'thd_target_kurang' => $spBranches->sum('thd_target_kurang'),
            'thd_sp_lebih' => $spBranches->sum('thd_sp_lebih'),
            'thd_sp_kurang' => $spBranches->sum('thd_sp_kurang'),
        ];

        $areas = [];
        foreach ($spBranches as $branch) {
            $areaName = $this->extractAreaFromBranch($branch->branch_name);
            if (!isset($areas[$areaName])) {
                $areas[$areaName] = ['name' => $areaName, 'branches' => [], 'totals' => [
                    'target' => 0,
                    'total_sp' => 0,
                    'total_faktur' => 0,
                    'total_ret' => 0,
                    'netto' => 0,
                    'sisa_sp' => 0,
                    'total_nk' => 0,
                    'total_nt' => 0,
                    'total_nkb' => 0,
                    'total_stok_cabang' => 0,
                    'total_sp_1' => 0,
                    'total_faktur_1' => 0,
                    'total_ret_1' => 0,
                    'total_nt_1' => 0,
                    'total_nkb_1' => 0,
                    'total_ntb_1' => 0,
                    'nppb_koli' => 0,
                    'nppb_pls' => 0,
                    'nppb_exp' => 0,
                    'thd_target_lebih' => 0,
                    'thd_target_kurang' => 0,
                    'thd_sp_lebih' => 0,
                    'thd_sp_kurang' => 0,
                ]];
            }
            $areas[$areaName]['branches'][] = $branch;
            foreach (['target', 'total_sp', 'total_faktur', 'total_ret', 'netto', 'sisa_sp', 'total_nk', 'total_nt', 'total_nkb', 'total_stok_cabang', 'total_sp_1', 'total_faktur_1', 'total_ret_1', 'total_nt_1', 'total_nkb_1', 'total_ntb_1', 'nppb_koli', 'nppb_pls', 'nppb_exp', 'thd_target_lebih', 'thd_target_kurang', 'thd_sp_lebih', 'thd_sp_kurang'] as $k) {
                $areas[$areaName]['totals'][$k] += $branch->{$k} ?? 0;
            }
        }

        $filterBookTitle = '';
        if ($filterBookCode !== '') {
            $product = Product::where('book_code', $filterBookCode)->first();
            $filterBookTitle = $product->book_title ?? '';
        }
        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'year' => $year,
            'nasional' => $nasional,
            'areas' => $areas,
            'branches' => $branches,
            'activeCutoff' => $activeCutoff,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filterBookCode' => $filterBookCode,
            'filterBookTitle' => $filterBookTitle,
        ];
        return ['view' => $callbackfolder . '.rekapitulasi.index', 'data' => $data];
    }

    /**
     * Detail rekapitulasi per cabang: list buku beserta stock, SP, target, dll berdasarkan cutoff.
     */
    public function detail(Request $request, string $branch_code)
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();
                $this->role = (int) ($user->authority_id ?? 1);
                $this->callbackfolder = ($this->role === 2) ? 'branch' : 'superadmin';
            } else {
                $this->role = 1;
                $this->callbackfolder = 'superadmin';
            }

            $userBranchCode = $this->role == 2 && Auth::check() ? (Auth::user()->branch_code ?? null) : null;
            if ($userBranchCode !== null && $userBranchCode !== $branch_code) {
                return redirect()->route('recap.index')->with('error', 'Anda hanya dapat melihat detail cabang Anda sendiri.');
            }

            $branch = Branch::where('branch_code', $branch_code)->first();
            if (!$branch) {
                return redirect()->route('recap.index')->with('error', 'Cabang tidak ditemukan.');
            }

            $activeCutoff = CutoffData::where('status', 'active')->first();
            $startDate = null;
            $endDate = null;
            if ($activeCutoff) {
                $endDate = $activeCutoff->end_date ? \Carbon\Carbon::parse($activeCutoff->end_date)->format('Y-m-d') : null;
                $startDate = $activeCutoff->start_date ? \Carbon\Carbon::parse($activeCutoff->start_date)->format('Y-m-d') : null;
            }

            $spBooks = SpBranch::select([
                'sp_branches.book_code',
                DB::raw('SUM(sp_branches.ex_stock) as stok'),
                DB::raw('SUM(sp_branches.ex_sp) as sp'),
                DB::raw('SUM(sp_branches.ex_ftr) as faktur'),
                DB::raw('COALESCE(SUM(sp_branches.ex_ret), 0) as ret'),
            ])
                ->where('sp_branches.branch_code', $branch_code)
                ->where('sp_branches.active_data', 'yes')
                ->whereNotNull('sp_branches.book_code');
            if ($activeCutoff) {
                $startDate !== null
                    ? $spBooks->whereBetween('sp_branches.trans_date', [$startDate, $endDate])
                    : $spBooks->where('sp_branches.trans_date', '<=', $endDate);
            }
            $spBooks = $spBooks->groupBy('sp_branches.book_code')->get()->keyBy('book_code');

            $targetsQuery = Target::select(['targets.book_code', DB::raw('SUM(targets.exemplar) as target')])
                ->join('periods', 'targets.period_code', '=', 'periods.period_code')
                ->where('targets.branch_code', $branch_code);
            if ($activeCutoff) {
                if ($startDate !== null) {
                    $targetsQuery->where(function ($q) use ($startDate, $endDate) {
                        $q->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
                    });
                } else {
                    $targetsQuery->where('periods.to_date', '<=', $endDate);
                }
            }
            $targets = $targetsQuery->groupBy('targets.book_code')->get()->keyBy('book_code');

            $nppbQuery = NppbCentral::select([
                'book_code',
                DB::raw('SUM(koli) as total_koli'),
                DB::raw('SUM(pls) as total_pls'),
                DB::raw('SUM(exp) as total_exp'),
            ])
                ->where('branch_code', $branch_code);
            if ($activeCutoff) {
                $startDate !== null
                    ? $nppbQuery->whereBetween('date', [$startDate, $endDate])
                    : $nppbQuery->where('date', '<=', $endDate);
            }
            $nppbByBook = $nppbQuery->groupBy('book_code')->get()->keyBy('book_code');

            $filterBookCode = trim((string) $request->input('book_code', ''));
            $filterBookName = trim((string) $request->input('book_name', ''));

            $productsQuery = Product::select('book_code', 'book_title')->orderBy('book_code');
            if ($filterBookCode !== '') {
                $productsQuery->where('book_code', 'like', '%' . $filterBookCode . '%');
            }
            if ($filterBookName !== '') {
                $productsQuery->where('book_title', 'like', '%' . $filterBookName . '%');
            }
            $productsPaginator = $productsQuery->paginate(100)->withQueryString();

            $rows = [];
            foreach ($productsPaginator as $product) {
                $bookCode = $product->book_code;
                $sp = $spBooks->get($bookCode);
                $stok = $sp !== null ? (float) ($sp->stok ?? 0) : 0;
                $spVal = $sp !== null ? (float) ($sp->sp ?? 0) : 0;
                $faktur = $sp !== null ? (float) ($sp->faktur ?? 0) : 0;
                $sisaSp = $spVal - $faktur;

                $targetRow = $targets->get($bookCode);
                $target = (float) ($targetRow->target ?? 0);

                $diffTarget = $stok - $target;
                $thdTargetLebih = $diffTarget > 0 ? $diffTarget : 0;
                $thdTargetKurang = $diffTarget < 0 ? abs($diffTarget) : 0;

                $diffSp = $stok - $spVal;
                $thdSpLebih = $diffSp > 0 ? $diffSp : 0;
                $thdSpKurang = $diffSp < 0 ? abs($diffSp) : 0;

                $nppb = $nppbByBook->get($bookCode);
                $nppbKoli = (float) ($nppb->total_koli ?? 0);
                $nppbPls = (float) ($nppb->total_pls ?? 0);
                $nppbExp = (float) ($nppb->total_exp ?? 0);

                $pctTarget = $target > 0 ? round(($stok / $target) * 100) : 0;
                $pctSp = $spVal > 0 ? round(($stok / $spVal) * 100) : 0;

                $rows[] = (object) [
                    'book_code' => $bookCode,
                    'book_title' => $product->book_title ?? '-',
                    'target' => $target,
                    'sp' => $spVal,
                    'faktur' => $faktur,
                    'sisa_sp' => $sisaSp,
                    'stock_cabang' => $stok,
                    'thd_target_lebih' => $thdTargetLebih,
                    'thd_target_kurang' => $thdTargetKurang,
                    'thd_sp_lebih' => $thdSpLebih,
                    'thd_sp_kurang' => $thdSpKurang,
                    'nppb_koli' => $nppbKoli,
                    'nppb_pls' => $nppbPls,
                    'nppb_exp' => $nppbExp,
                    'pct_stock_target' => $pctTarget,
                    'pct_stock_sp' => $pctSp,
                ];
            }

            return view($this->callbackfolder . '.rekapitulasi.detail', [
                'branch' => $branch,
                'rows' => $rows,
                'paginator' => $productsPaginator,
                'activeCutoff' => $activeCutoff,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'filterBookCode' => $filterBookCode,
                'filterBookName' => $filterBookName,
            ]);
        } catch (\Throwable $e) {
            Log::error('RekapController@detail: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'branch_code' => $branch_code,
            ]);
            return redirect()->route('recap.index')->with('error', 'Gagal memuat detail cabang.');
        }
    }

    /**
     * Export detail rekapitulasi cabang ke CSV (semua buku dari master sesuai filter).
     */
    public function detailExport(Request $request, string $branch_code)
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();
                $this->role = (int) ($user->authority_id ?? 1);
            } else {
                $this->role = 1;
            }
            $userBranchCode = $this->role == 2 && Auth::check() ? (Auth::user()->branch_code ?? null) : null;
            if ($userBranchCode !== null && $userBranchCode !== $branch_code) {
                return redirect()->route('recap.index')->with('error', 'Anda hanya dapat mengekspor detail cabang Anda sendiri.');
            }

            $branch = Branch::where('branch_code', $branch_code)->first();
            if (!$branch) {
                return redirect()->route('recap.index')->with('error', 'Cabang tidak ditemukan.');
            }

            $activeCutoff = CutoffData::where('status', 'active')->first();
            $startDate = null;
            $endDate = null;
            if ($activeCutoff) {
                $endDate = $activeCutoff->end_date ? \Carbon\Carbon::parse($activeCutoff->end_date)->format('Y-m-d') : null;
                $startDate = $activeCutoff->start_date ? \Carbon\Carbon::parse($activeCutoff->start_date)->format('Y-m-d') : null;
            }

            $spBooks = SpBranch::select([
                'sp_branches.book_code',
                DB::raw('SUM(sp_branches.ex_stock) as stok'),
                DB::raw('SUM(sp_branches.ex_sp) as sp'),
                DB::raw('SUM(sp_branches.ex_ftr) as faktur'),
            ])
                ->where('sp_branches.branch_code', $branch_code)
                ->where('sp_branches.active_data', 'yes')
                ->whereNotNull('sp_branches.book_code');
            if ($activeCutoff) {
                $startDate !== null
                    ? $spBooks->whereBetween('sp_branches.trans_date', [$startDate, $endDate])
                    : $spBooks->where('sp_branches.trans_date', '<=', $endDate);
            }
            $spBooks = $spBooks->groupBy('sp_branches.book_code')->get()->keyBy('book_code');

            $targetsQuery = Target::select(['targets.book_code', DB::raw('SUM(targets.exemplar) as target')])
                ->join('periods', 'targets.period_code', '=', 'periods.period_code')
                ->where('targets.branch_code', $branch_code);
            if ($activeCutoff) {
                if ($startDate !== null) {
                    $targetsQuery->where(function ($q) use ($startDate, $endDate) {
                        $q->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
                    });
                } else {
                    $targetsQuery->where('periods.to_date', '<=', $endDate);
                }
            }
            $targets = $targetsQuery->groupBy('targets.book_code')->get()->keyBy('book_code');

            $nppbQuery = NppbCentral::select([
                'book_code',
                DB::raw('SUM(koli) as total_koli'),
                DB::raw('SUM(pls) as total_pls'),
                DB::raw('SUM(exp) as total_exp'),
            ])->where('branch_code', $branch_code);
            if ($activeCutoff) {
                $startDate !== null
                    ? $nppbQuery->whereBetween('date', [$startDate, $endDate])
                    : $nppbQuery->where('date', '<=', $endDate);
            }
            $nppbByBook = $nppbQuery->groupBy('book_code')->get()->keyBy('book_code');

            $filterBookCode = trim((string) $request->input('book_code', ''));
            $filterBookName = trim((string) $request->input('book_name', ''));

            $productsQuery = Product::select('book_code', 'book_title')->orderBy('book_code');
            if ($filterBookCode !== '') {
                $productsQuery->where('book_code', 'like', '%' . $filterBookCode . '%');
            }
            if ($filterBookName !== '') {
                $productsQuery->where('book_title', 'like', '%' . $filterBookName . '%');
            }
            $products = $productsQuery->get();

            $rows = [];
            foreach ($products as $product) {
                $bookCode = $product->book_code;
                $sp = $spBooks->get($bookCode);
                $stok = $sp !== null ? (float) ($sp->stok ?? 0) : 0;
                $spVal = $sp !== null ? (float) ($sp->sp ?? 0) : 0;
                $faktur = $sp !== null ? (float) ($sp->faktur ?? 0) : 0;
                $sisaSp = $spVal - $faktur;
                $targetRow = $targets->get($bookCode);
                $target = $targetRow !== null ? (float) ($targetRow->target ?? 0) : 0;
                $diffTarget = $stok - $target;
                $thdTargetLebih = $diffTarget > 0 ? $diffTarget : 0;
                $thdTargetKurang = $diffTarget < 0 ? abs($diffTarget) : 0;
                $diffSp = $stok - $spVal;
                $thdSpLebih = $diffSp > 0 ? $diffSp : 0;
                $thdSpKurang = $diffSp < 0 ? abs($diffSp) : 0;
                $nppb = $nppbByBook->get($bookCode);
                $nppbKoli = (float) ($nppb->total_koli ?? 0);
                $nppbPls = (float) ($nppb->total_pls ?? 0);
                $nppbExp = (float) ($nppb->total_exp ?? 0);
                $pctTarget = $target > 0 ? round(($stok / $target) * 100) : 0;
                $pctSp = $spVal > 0 ? round(($stok / $spVal) * 100) : 0;
                $rows[] = [
                    $bookCode,
                    $product->book_title ?? '-',
                    $target,
                    $spVal,
                    $faktur,
                    $sisaSp,
                    $stok,
                    $thdTargetLebih,
                    $thdTargetKurang,
                    $thdSpLebih,
                    $thdSpKurang,
                    $nppbKoli,
                    $nppbPls,
                    $nppbExp,
                    $pctTarget,
                    $pctSp,
                ];
            }

            $filename = 'recap-detail-' . $branch_code . '-' . now()->format('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            $callback = function () use ($rows) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
                fputcsv($out, [
                    'Kode Buku', 'Nama Buku', 'TARGET', 'SP', 'FAKTUR', 'SISA SP', 'STOCK CABANG',
                    'THD TARGET LEBIH', 'THD TARGET KURANG', 'THD SP LEBIH', 'THD SP KURANG',
                    'KOLI', 'PLS', 'EXP', '% THD TARGET', '% THD SP',
                ]);
                foreach ($rows as $r) {
                    fputcsv($out, $r);
                }
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Throwable $e) {
            Log::error('RekapController@detailExport: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'branch_code' => $branch_code,
            ]);
            return redirect()->route('recap.index')->with('error', 'Gagal mengekspor data.');
        }
    }

    /**
     * Extract area name from branch name
     * This is a placeholder - you may need to adjust based on your actual data structure
     */
    private function extractAreaFromBranch($branchName)
    {
        // For now, return a default area or extract from branch name
        // You may need to add an 'area' field to Branch model or create a mapping
        if (
            stripos($branchName, 'MEDAN') !== false ||
            stripos($branchName, 'PALEMBANG') !== false ||
            stripos($branchName, 'PEKANBARU') !== false
        ) {
            return 'AREA SUMATERA UTARA';
        }

        if (
            stripos($branchName, 'JAKARTA') !== false ||
            stripos($branchName, 'BANDUNG') !== false
        ) {
            return 'AREA JAWA';
        }

        // Default area
        return 'AREA SUMATERA';
    }
}
