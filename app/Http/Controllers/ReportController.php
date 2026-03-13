<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\NppbCentral;
use App\Models\Periode;
use App\Models\Product;
use App\Models\SpBranch;
use App\Models\Target;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected $callbackfolder;

    public function __construct()
    {
        $this->callbackfolder = (Auth::check() && (Auth::user()->authority_id ?? null) == 2) ? 'branch' : 'superadmin';
    }

    /**
     * Report NASIONAL: tabel per Segment (jenjang) & Kategori (category_manual).
     * Segment dari books.jenjang, Kategori dari books.category_manual.
     */
    public function index(Request $request)
    {
        $year = (int) $request->input('year', date('Y'));
        $area = $request->input('area', ''); // '' = Nasional, else warehouse_code
        $branchCode = $request->input('branch', null);

        if (Auth::check() && (Auth::user()->authority_id ?? null) == 2) {
            $userBranch = Auth::user()->branch_code ?? null;
            if ($userBranch) {
                $branchCode = $userBranch;
            }
        }

        // Jika branch dipilih sama dengan area (mis. area=DY00, branch=DY00), kemungkinan DY00 adalah kode area
        // dan tidak ada baris sp_branches/targets dengan branch_code='DY00'. Pakai semua cabang di area itu.
        $filteredBranchCodes = $this->getFilteredBranchCodes($area, $branchCode);
        if ($filteredBranchCodes !== null && $branchCode !== null && $area !== '' && $branchCode === $area) {
            $branchesInArea = Branch::where('warehouse_code', $area)->pluck('branch_code')->toArray();
            if (count($branchesInArea) > 0) {
                $filteredBranchCodes = $branchesInArea;
            }
        }
        $scopeBranch = function ($query, $col = 'branch_code') use ($filteredBranchCodes) {
            if ($filteredBranchCodes === null) {
                return;
            }
            $query->whereIn($col, $filteredBranchCodes);
        };

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';
        $prevYear = $year - 1;
        $prevStart = $prevYear . '-01-01';
        $prevEnd = $prevYear . '-12-31';

        // Daftar segment (jenjang) dan kategori (category_manual) dari books
        $segmentKategori = Product::query()
            ->select('jenjang', 'category_manual')
            ->whereNotNull('jenjang')
            ->where('jenjang', '!=', '')
            ->whereNotNull('category_manual')
            ->where('category_manual', '!=', '')
            ->distinct()
            ->orderBy('jenjang')
            ->orderBy('category_manual')
            ->get();

        $segmentOrder = Product::query()
            ->whereNotNull('jenjang')
            ->where('jenjang', '!=', '')
            ->distinct()
            ->orderBy('jenjang')
            ->pluck('jenjang')
            ->values()
            ->toArray();
        $kategoriOrder = Product::query()
            ->whereNotNull('category_manual')
            ->where('category_manual', '!=', '')
            ->distinct()
            ->orderBy('category_manual')
            ->pluck('category_manual')
            ->values()
            ->toArray();

        // Logika: cari buku dulu berdasarkan segment (jenjang) & kategori (category_manual),
        // lalu ambil sp_branches/target hanya untuk book_code hasil pencarian itu.
        // Di bawah dipakai JOIN books + WHERE books.jenjang IN (...) AND books.category_manual IN (...).

        // Realisasi tahun lalu: sp_branches.ex_ftr untuk book yang jenjang+category_manual match
        $realisasiPrev = SpBranch::query()
            ->join('books', 'sp_branches.book_code', '=', 'books.book_code')
            ->whereIn('books.jenjang', $segmentOrder)
            ->whereIn('books.category_manual', $kategoriOrder)
            ->where('sp_branches.active_data', 'yes')
            ->whereBetween('sp_branches.trans_date', [$prevStart, $prevEnd])
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('sp_branches.branch_code', $filteredBranchCodes))
            ->groupBy('books.jenjang', 'books.category_manual')
            ->select(
                'books.jenjang as segment',
                'books.category_manual as kategori',
                DB::raw('COALESCE(SUM(sp_branches.ex_ftr), 0) as realisasi_prev')
            )
            ->get()
            ->keyBy(fn($r) => $r->segment . '|' . $r->kategori);

        // Target (tahun ini) — periode yang overlap dengan rentang tahun, atau yang tahunnya sama
        $periodCodes = Periode::where('from_date', '<=', $endDate)
            ->where('to_date', '>=', $startDate)
            ->pluck('period_code')
            ->toArray();
        if (empty($periodCodes)) {
            $periodCodes = Periode::whereYear('from_date', $year)
                ->orWhereYear('to_date', $year)
                ->pluck('period_code')
                ->toArray();
        }
        // Target: dari tabel targets, hanya untuk book yang jenjang+category_manual match
        $targetAgg = Target::query()
            ->join('books', 'targets.book_code', '=', 'books.book_code')
            ->whereIn('books.jenjang', $segmentOrder)
            ->whereIn('books.category_manual', $kategoriOrder)
            ->when(!empty($periodCodes), fn($q) => $q->whereIn('targets.period_code', $periodCodes))
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('targets.branch_code', $filteredBranchCodes))
            ->groupBy('books.jenjang', 'books.category_manual')
            ->select(
                'books.jenjang as segment',
                'books.category_manual as kategori',
                DB::raw('COALESCE(SUM(targets.exemplar), 0) as target_cabang')
            )
            ->get()
            ->keyBy(fn($r) => $r->segment . '|' . $r->kategori);

        // SP & Faktur tahun ini: dari sp_branches, hanya untuk book yang jenjang+category_manual match
        $spFaktur = SpBranch::query()
            ->join('books', 'sp_branches.book_code', '=', 'books.book_code')
            ->whereIn('books.jenjang', $segmentOrder)
            ->whereIn('books.category_manual', $kategoriOrder)
            ->where('sp_branches.active_data', 'yes')
            ->whereBetween('sp_branches.trans_date', [$startDate, $endDate])
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('sp_branches.branch_code', $filteredBranchCodes))
            ->groupBy('books.jenjang', 'books.category_manual')
            ->select(
                'books.jenjang as segment',
                'books.category_manual as kategori',
                DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0) as sp'),
                DB::raw('COALESCE(SUM(sp_branches.ex_ftr), 0) as faktur'),
                DB::raw('COALESCE(SUM(sp_branches.ex_stock), 0) as stock_cabang')
            )
            ->get()
            ->keyBy(fn($r) => $r->segment . '|' . $r->kategori);

        // NPPB Eks: hanya untuk book yang jenjang+category_manual match
        $nppbAgg = NppbCentral::query()
            ->join('books', 'nppb_centrals.book_code', '=', 'books.book_code')
            ->whereIn('books.jenjang', $segmentOrder)
            ->whereIn('books.category_manual', $kategoriOrder)
            ->whereNotNull('nppb_centrals.document_id')
            ->where('nppb_centrals.document_id', '!=', 0)
            ->whereBetween('nppb_centrals.date', [$startDate, $endDate])
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('nppb_centrals.branch_code', $filteredBranchCodes))
            ->groupBy('books.jenjang', 'books.category_manual')
            ->select(
                'books.jenjang as segment',
                'books.category_manual as kategori',
                DB::raw('COALESCE(SUM(nppb_centrals.exp), 0) as nppb_eks')
            )
            ->get()
            ->keyBy(fn($r) => $r->segment . '|' . $r->kategori);

        // Target Pusat: pakai target yang sama (cabang) untuk kolom pusat; bisa diganti sumber lain nanti
        $targetPusatAgg = $targetAgg;

        $rows = [];
        $segmentsUsed = [];
        foreach ($segmentOrder as $seg) {
            foreach ($kategoriOrder as $kat) {
                $key = $seg . '|' . $kat;
                $rp = $realisasiPrev->get($key);
                $ta = $targetAgg->get($key);
                $tp = $targetPusatAgg->get($key);
                $sf = $spFaktur->get($key);
                $np = $nppbAgg->get($key);

                $realisasiPrevVal = $rp ? (float) $rp->realisasi_prev : 0;
                $targetCabangVal = $ta ? (float) $ta->target_cabang : 0;
                $targetPusatVal = $tp ? (float) $tp->target_cabang : 0;
                $spVal = $sf ? (float) $sf->sp : 0;
                $fakturVal = $sf ? (float) $sf->faktur : 0;
                $stockCabangVal = $sf ? (float) $sf->stock_cabang : 0;
                // Stock B' SP: minimum antara SP dan Stock Cabang per segment+kategori
                // Contoh: SP=100, Stock Cabang=200 => Stock B' SP=100; SP=100, Stock Cabang=80 => Stock B' SP=80.
                $stockBSpVal = min($spVal, $stockCabangVal);
                $nppbEksVal = $np ? (float) $np->nppb_eks : 0;

                $pctSp = $targetCabangVal > 0 ? round(($spVal / $targetCabangVal) * 100, 0) : 0;
                $pctFaktur = $spVal > 0 ? round(($fakturVal / $spVal) * 100, 0) : 0;
                $pctStockFaktur = $spVal > 0 ? round((($stockBSpVal + $fakturVal) / $spVal) * 100, 0) : 0;
                // Stock thd SP Lebih/Kurang: dihitung dari selisih Stock Cabang vs SP per segment+kategori
                $lebih = max(0, $stockCabangVal - $spVal);
                $kurang = min(0, $stockCabangVal - $spVal);
                $pctNppb = $spVal > 0 ? round((($stockBSpVal + $fakturVal + $nppbEksVal) / $spVal) * 100, 0) : 0;

                $rows[] = [
                    'segment' => $seg,
                    'kategori' => $kat,
                    'realisasi_prev' => $realisasiPrevVal,
                    'target_cabang' => $targetCabangVal,
                    'target_pusat' => $targetPusatVal,
                    'sp' => $spVal,
                    'pct_sp' => $pctSp,
                    'faktur' => $fakturVal,
                    'pct_faktur' => $pctFaktur,
                    'stock_cabang' => $stockCabangVal,
                    'stock_b_sp' => $stockBSpVal,
                    'pct_stock_faktur' => $pctStockFaktur,
                    'lebih' => $lebih,
                    'kurang' => $kurang,
                    'nppb_eks' => $nppbEksVal,
                    'pct_nppb' => $pctNppb,
                ];
                $segmentsUsed[$seg] = true;
            }
        }

        // Totals per segment (SD Total, SMP Total, ...) dan Grand Total
        $totalsBySegment = [];
        $grand = [
            'realisasi_prev' => 0,
            'target_cabang' => 0,
            'target_pusat' => 0,
            'sp' => 0,
            'faktur' => 0,
            'stock_cabang' => 0,
            'stock_b_sp' => 0,
            'lebih' => 0,
            'kurang' => 0,
            'nppb_eks' => 0,
        ];
        foreach ($rows as $r) {
            $seg = $r['segment'];
            if (!isset($totalsBySegment[$seg])) {
                $totalsBySegment[$seg] = array_merge(array_fill_keys(array_keys($grand), 0), ['segment' => $seg]);
            }
            foreach (['realisasi_prev', 'target_cabang', 'target_pusat', 'sp', 'faktur', 'stock_cabang', 'stock_b_sp', 'lebih', 'kurang', 'nppb_eks'] as $k) {
                $totalsBySegment[$seg][$k] += $r[$k];
                $grand[$k] += $r[$k];
            }
        }
        foreach ($totalsBySegment as $seg => $t) {
            $t['pct_sp'] = $t['target_cabang'] > 0 ? round(($t['sp'] / $t['target_cabang']) * 100, 0) : 0;
            $t['pct_faktur'] = $t['sp'] > 0 ? round(($t['faktur'] / $t['sp']) * 100, 0) : 0;
            $t['pct_stock_faktur'] = $t['sp'] > 0 ? round((($t['stock_b_sp'] + $t['faktur']) / $t['sp']) * 100, 0) : 0;
            $t['pct_nppb'] = $t['sp'] > 0 ? round((($t['stock_b_sp'] + $t['faktur'] + $t['nppb_eks']) / $t['sp']) * 100, 0) : 0;
            $totalsBySegment[$seg] = $t;
        }
        $grandPctSp = $grand['target_cabang'] > 0 ? round(($grand['sp'] / $grand['target_cabang']) * 100, 0) : 0;
        $grandPctFaktur = $grand['sp'] > 0 ? round(($grand['faktur'] / $grand['sp']) * 100, 0) : 0;
        $grandPctStockFaktur = $grand['sp'] > 0 ? round((($grand['stock_b_sp'] + $grand['faktur']) / $grand['sp']) * 100, 0) : 0;
        $grandPctNppb = $grand['sp'] > 0 ? round((($grand['stock_b_sp'] + $grand['faktur'] + $grand['nppb_eks']) / $grand['sp']) * 100, 0) : 0;

        $areas = $this->getAreasList();
        $branchesQuery = Branch::orderBy('branch_code')->select('branch_code', 'branch_name', 'warehouse_code');
        if ($area !== '' && $area !== null) {
            $branchesQuery->where('warehouse_code', $area);
        }
        $branches = $branchesQuery->get();

        $selectedBranchName = null;
        if ($branchCode) {
            $selectedBranch = Branch::where('branch_code', $branchCode)->first();
            $selectedBranchName = $selectedBranch ? $selectedBranch->branch_name : $branchCode;
        }

        $chartSegmentLabels = $segmentOrder;
        $chartSegmentTarget = [];
        $chartSegmentSp = [];
        $chartSegmentFaktur = [];
        foreach ($segmentOrder as $s) {
            $t = $totalsBySegment[$s] ?? [];
            $chartSegmentTarget[] = $t['target_cabang'] ?? 0;
            $chartSegmentSp[] = $t['sp'] ?? 0;
            $chartSegmentFaktur[] = $t['faktur'] ?? 0;
        }
        $chartKategoriTarget = [];
        $chartKategoriSp = [];
        $chartKategoriFaktur = [];
        foreach ($kategoriOrder as $k) {
            $chartKategoriTarget[] = collect($rows)->where('kategori', $k)->sum('target_cabang');
            $chartKategoriSp[] = collect($rows)->where('kategori', $k)->sum('sp');
            $chartKategoriFaktur[] = collect($rows)->where('kategori', $k)->sum('faktur');
        }

        return view('superadmin.report.index', [
            'year' => $year,
            'area' => $area,
            'branchCode' => $branchCode,
            'selectedBranchName' => $selectedBranchName,
            'rows' => $rows,
            'totalsBySegment' => $totalsBySegment,
            'grand' => $grand,
            'grandPctSp' => $grandPctSp,
            'grandPctFaktur' => $grandPctFaktur,
            'grandPctStockFaktur' => $grandPctStockFaktur,
            'grandPctNppb' => $grandPctNppb,
            'segmentOrder' => $segmentOrder,
            'kategoriOrder' => $kategoriOrder,
            'areas' => $areas,
            'branches' => $branches,
            'chartSegmentLabels' => $chartSegmentLabels,
            'chartSegmentTarget' => $chartSegmentTarget,
            'chartSegmentSp' => $chartSegmentSp,
            'chartSegmentFaktur' => $chartSegmentFaktur,
            'chartKategoriTarget' => $chartKategoriTarget,
            'chartKategoriSp' => $chartKategoriSp,
            'chartKategoriFaktur' => $chartKategoriFaktur,
        ]);
    }

    private function getFilteredBranchCodes(?string $area, ?string $branchCode): ?array
    {
        if ($branchCode) {
            return [$branchCode];
        }
        if ($area === '' || $area === null || $area === 'Nasional') {
            return null;
        }
        return Branch::where('warehouse_code', $area)->pluck('branch_code')->toArray();
    }

    /**
     * Area = distinct warehouse_code dari branches, dengan code dan name (nama dari salah satu cabang).
     */
    private function getAreasList(): array
    {
        $rows = Branch::query()
            ->select('warehouse_code')
            ->selectRaw('MIN(branch_name) as name')
            ->whereNotNull('warehouse_code')
            ->where('warehouse_code', '!=', '')
            ->groupBy('warehouse_code')
            ->orderBy('warehouse_code')
            ->get();

        $list = [['code' => '', 'name' => 'Nasional']];
        foreach ($rows as $r) {
            $list[] = ['code' => $r->warehouse_code, 'name' => $r->name ?? $r->warehouse_code];
        }
        return $list;
    }
}
