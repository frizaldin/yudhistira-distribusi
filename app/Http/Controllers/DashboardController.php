<?php

namespace App\Http\Controllers;

use App\Models\SpBranch;
use App\Models\Branch;
use App\Models\CentralStock;
use App\Models\CentralStockKoli;
use App\Models\Target;
use App\Models\NppbCentral;
use App\Models\Product;
use App\Models\CutoffData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $base_url;

    protected $title;

    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('dashboard');
        $this->title = 'Dashboard';
        $this->role = Auth::user()->authority_id;

        $this->callbackfolder = match ($this->role) {
            1       => 'superadmin',
            2       => 'branch',
            default => 'superadmin',
        };
    }


    public function index(Request $request)
    {
        // Check if date range is set in session
        $dateRange = session('date_range_global');
        
        // Check if there's an active cutoff_data
        $activeCutoff = CutoffData::where('status', 'active')->first();
        $usingCutoffData = false;

        // Get date range priority:
        // 1. If date_range_global is set in session, use that (user override)
        // 2. Else if there's an active cutoff_data, use that
        // 3. Else use default (current month)
        if ($dateRange) {
            // User has set date_range_global, use that (override cutoff_datas)
            $startDate = $dateRange['start_date'];
            $endDate = $dateRange['end_date'];
        } elseif ($activeCutoff) {
            $endDate = $activeCutoff->end_date->format('Y-m-d');
            $startDate = $activeCutoff->start_date ? $activeCutoff->start_date->format('Y-m-d') : null;
            $usingCutoffData = true;
        } else {
            // Default: current month
            $startDate = date('Y-m-01'); // First day of current month
            $endDate = date('Y-m-t'); // Last day of current month
        }

        // Get year from request or use current year
        $year = $request->input('year', date('Y'));

        // Get branch filter from request
        $selectedBranchCode = $request->input('branch', null);

        $userBranchCode = null;
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        if ($this->role == 2 && Auth::check()) {
            $userBranchCode = Auth::user()->branch_code ?? null;
            $filteredBranchCodes = null;
        } else {
            $filteredBranchCodes = null; // superadmin & ADP: akses global
        }

        // Hanya role cabang dan superadmin yang bisa pilih branch dari request; ADP selalu global
        if ($this->role != 3) {
            if ($selectedBranchCode) {
                $filteredBranchCodes = [$selectedBranchCode];
                $userBranchCode = null;
            } elseif ($userBranchCode) {
                $filteredBranchCodes = [$userBranchCode];
            }
        }

        // Query untuk targets - mulai dari Branch, lalu left join dengan Target
        $targets = Branch::select([
            'branches.branch_code',
            DB::raw('COALESCE(SUM(targets.exemplar), 0) as total_target'),
        ])
            ->leftJoin('targets', 'branches.branch_code', '=', 'targets.branch_code')
            ->leftJoin('periods', function ($join) use ($startDate, $endDate) {
                $join->on('targets.period_code', '=', 'periods.period_code');
                if ($startDate !== null) {
                    $join->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
                } else {
                    $join->where('periods.to_date', '<=', $endDate);
                }
            })
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branches.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branches.branch_code', $filteredBranchCodes);
            })
            ->groupBy('branches.branch_code')
            ->get()
            ->keyBy('branch_code');

        $nppbCentralTotalQuery = NppbCentral::select([
            DB::raw('SUM(koli) as total_koli'),
            DB::raw('SUM(pls) as total_pls'),
            DB::raw('SUM(exp) as total_exp'),
        ]);
        if ($startDate !== null) {
            $nppbCentralTotalQuery->whereBetween('date', [$startDate, $endDate]);
        } else {
            $nppbCentralTotalQuery->where('date', '<=', $endDate);
        }
        $nppbCentralTotal = $nppbCentralTotalQuery
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->first();

        if (!$nppbCentralTotal) {
            $nppbCentralTotal = (object)[
                'total_koli' => 0,
                'total_pls' => 0,
                'total_exp' => 0,
            ];
        }

        $totalTargetForNppbQuery = Target::select([
            DB::raw('SUM(targets.exemplar) as total_target'),
        ])
            ->join('periods', 'targets.period_code', '=', 'periods.period_code');
        if ($startDate !== null) {
            $totalTargetForNppbQuery->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
        } else {
            $totalTargetForNppbQuery->where('periods.to_date', '<=', $endDate);
        }
        $totalTargetForNppb = $totalTargetForNppbQuery
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('targets.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('targets.branch_code', $filteredBranchCodes);
            })
            ->first();

        $totalTargetYear = $totalTargetForNppb->total_target ?? 0;

        // Query untuk pagination "Kebutuhan Kirim Cabang"
        // Mulai dari Branch (semua cabang), lalu left join dengan sp_branches
        $spBranchesQuery = Branch::select([
            'branches.branch_code',
            'branches.branch_name',
            DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0) as total_sp'),
            DB::raw('COALESCE(SUM(sp_branches.ex_ftr), 0) as total_faktur'),
            DB::raw('COALESCE(SUM(sp_branches.ex_ret), 0) as total_ret'),
            DB::raw('COALESCE(SUM(sp_branches.ex_ftr), 0) - COALESCE(SUM(sp_branches.ex_ret), 0) as netto'),
            DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0) - COALESCE(SUM(sp_branches.ex_ftr), 0) as sisa_sp'),
            DB::raw('COALESCE(SUM(sp_branches.ex_stock), 0) as total_stok_cabang'),
            DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nkb'),
        ])
            ->leftJoin('sp_branches', function ($join) use ($startDate, $endDate) {
                $join->on('branches.branch_code', '=', 'sp_branches.branch_code')
                    ->where('sp_branches.active_data', 'yes');
                if ($startDate !== null) {
                    $join->whereBetween('sp_branches.trans_date', [$startDate, $endDate]);
                } else {
                    $join->where('sp_branches.trans_date', '<=', $endDate);
                }
            })
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branches.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branches.branch_code', $filteredBranchCodes);
            })
            ->groupBy('branches.branch_code', 'branches.branch_name')
            ->orderByDesc(DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0)'));
        
        // Paginate untuk "Kebutuhan Kirim Cabang" table (infinite scroll: 15 per load)
        $perPage = 15;
        $spBranchesPaginated = $spBranchesQuery->paginate($perPage, ['*'], 'kebutuhan_page')->withQueryString();
        
        // Get all data for totals calculation (without pagination)
        $spBranches = SpBranch::select([
            'sp_branches.branch_code',
            'branches.branch_name',
            DB::raw('SUM(sp_branches.ex_sp) as total_sp'),
            DB::raw('SUM(sp_branches.ex_ftr) as total_faktur'),
            DB::raw('SUM(sp_branches.ex_ret) as total_ret'),
            DB::raw('SUM(sp_branches.ex_ftr) - COALESCE(SUM(sp_branches.ex_ret), 0) as netto'),
            DB::raw('SUM(sp_branches.ex_sp) - SUM(sp_branches.ex_ftr) as sisa_sp'),
            DB::raw('SUM(sp_branches.ex_stock) as total_stok_cabang'),
            DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nkb'),
        ])
            ->leftJoin('branches', 'sp_branches.branch_code', '=', 'branches.branch_code')
            ->where('sp_branches.active_data', 'yes');
        if ($startDate !== null) {
            $spBranches = $spBranches->whereBetween('sp_branches.trans_date', [$startDate, $endDate]);
        } else {
            $spBranches = $spBranches->where('sp_branches.trans_date', '<=', $endDate);
        }
        $spBranches = $spBranches
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('sp_branches.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('sp_branches.branch_code', $filteredBranchCodes);
            })
            ->groupBy('sp_branches.branch_code', 'branches.branch_name')
            ->orderByDesc(DB::raw('SUM(sp_branches.ex_sp)'))
            ->get();

        $pusatStocks = CentralStock::select('exemplar')
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->get();

        $totalStockPusat = $pusatStocks->sum('exemplar');
        
        // Total calculation - hanya dari cabang yang memiliki data (untuk akurasi)
        $totalTargetForCalculationQuery = Target::select([
            DB::raw('SUM(targets.exemplar) as total_target'),
        ])
            ->join('periods', 'targets.period_code', '=', 'periods.period_code');
        if ($startDate !== null) {
            $totalTargetForCalculationQuery->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
        } else {
            $totalTargetForCalculationQuery->where('periods.to_date', '<=', $endDate);
        }
        $totalTargetForCalculation = $totalTargetForCalculationQuery
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('targets.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('targets.branch_code', $filteredBranchCodes);
            })
            ->first();
        $totalTarget = $totalTargetForCalculation->total_target ?? 0;
        
        $totalSp = $spBranches->sum('total_sp');
        $totalFaktur = $spBranches->sum('total_faktur');
        $totalRet = $spBranches->sum('total_ret');
        $totalNetto = $spBranches->sum('netto');
        $totalSisaSp = $spBranches->sum('sisa_sp');
        $totalStokCabang = $spBranches->sum('total_stok_cabang');
        $totalNkb = $spBranches->sum('total_nkb');
        $totalNppbKoli = is_object($nppbCentralTotal) ? ($nppbCentralTotal->total_koli ?? 0) : 0;
        $totalNppbPls = is_object($nppbCentralTotal) ? ($nppbCentralTotal->total_pls ?? 0) : 0;
        $totalNppbExp = is_object($nppbCentralTotal) ? ($nppbCentralTotal->total_exp ?? 0) : 0;

        // Calculate achievement percentage
        $achievementPercent = $totalTarget > 0 ? round(($totalFaktur / $totalTarget) * 100, 2) : 0;

        // KPI tambahan: Persentase SP thd Target, Faktur thd SP, Stock thd SP (sebelum/sesudah rencana), Target vs (Faktur+Stock+Rencana)
        $pctSpThdTarget = $totalTarget > 0 ? round(($totalSp / $totalTarget) * 100, 2) : 0;
        $pctFakturThdSp = $totalSp > 0 ? round(($totalFaktur / $totalSp) * 100, 2) : 0;
        $pctStockThdSpSebelum = $totalSp > 0 ? round(($totalStokCabang / $totalSp) * 100, 2) : 0;
        $pctStockThdSpSesudah = $totalSp > 0 ? round((($totalStokCabang + $totalNppbExp) / $totalSp) * 100, 2) : 0;
        $totalFakturStockRencana = $totalFaktur + $totalStokCabang + $totalNppbExp;
        $pctTargetVsFakturStockRencana = $totalTarget > 0 ? round(($totalFakturStockRencana / $totalTarget) * 100, 2) : 0;

        // Query untuk nppbPerBranch - mulai dari Branch, lalu left join dengan NppbCentral
        $nppbPerBranch = Branch::select([
            'branches.branch_code',
            DB::raw('COALESCE(SUM(nppb_centrals.pls), 0) as total_pls'),
        ])
            ->leftJoin('nppb_centrals', function ($join) use ($startDate, $endDate) {
                $join->on('branches.branch_code', '=', 'nppb_centrals.branch_code');
                if ($startDate !== null) {
                    $join->whereBetween('nppb_centrals.date', [$startDate, $endDate]);
                } else {
                    $join->where('nppb_centrals.date', '<=', $endDate);
                }
            })
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branches.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branches.branch_code', $filteredBranchCodes);
            })
            ->groupBy('branches.branch_code')
            ->get()
            ->keyBy('branch_code');

        // Ambil semua cabang yang sudah diurutkan berdasarkan SP tertinggi
        // Pastikan data terurut berdasarkan total_sp (SP tertinggi)
        // For "Kebutuhan Kirim Cabang" table - use paginated data
        // Data sudah di-sort by total_sp desc di query
        $topBranches = $spBranchesPaginated;

        // Query untuk pagination "Penentuan Kirim (ADP)"
        // Mulai dari Branch (semua cabang), lalu left join dengan sp_branches untuk sisa_sp
        $adpBranchesQuery = Branch::select([
            'branches.branch_code',
            'branches.branch_name',
            DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0) - COALESCE(SUM(sp_branches.ex_ftr), 0) as sisa_sp'),
        ])
            ->leftJoin('sp_branches', function ($join) use ($startDate, $endDate) {
                $join->on('branches.branch_code', '=', 'sp_branches.branch_code')
                    ->where('sp_branches.active_data', 'yes');
                if ($startDate !== null) {
                    $join->whereBetween('sp_branches.trans_date', [$startDate, $endDate]);
                } else {
                    $join->where('sp_branches.trans_date', '<=', $endDate);
                }
            })
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branches.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branches.branch_code', $filteredBranchCodes);
            })
            ->groupBy('branches.branch_code', 'branches.branch_name')
            ->orderByDesc(DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0) - COALESCE(SUM(sp_branches.ex_ftr), 0)'));
        
        // Paginate untuk "Penentuan Kirim (ADP)" table (infinite scroll: 15 per load)
        $perPageAdp = 15;
        $adpBranchesPaginated = $adpBranchesQuery->paginate($perPageAdp, ['*'], 'adp_page')->withQueryString();

        $topProductsQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_ftr) as total_faktur'),
        ])
            ->where('active_data', 'yes')
            ->whereNotNull('book_code')
            ->where('book_code', '!=', '');
        if ($startDate !== null) {
            $topProductsQuery->whereBetween('trans_date', [$startDate, $endDate]);
        } else {
            $topProductsQuery->where('trans_date', '<=', $endDate);
        }
        $topProducts = $topProductsQuery
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->groupBy('book_code')
            ->orderByDesc('total_faktur')
            ->limit(5)
            ->get();

        $productTargetsQuery = Target::select([
            'targets.book_code',
            DB::raw('SUM(targets.exemplar) as total_target'),
        ])
            ->join('periods', 'targets.period_code', '=', 'periods.period_code')
            ->whereNotNull('targets.book_code');
        if ($startDate !== null) {
            $productTargetsQuery->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
        } else {
            $productTargetsQuery->where('periods.to_date', '<=', $endDate);
        }
        $productTargets = $productTargetsQuery
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('targets.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('targets.branch_code', $filteredBranchCodes);
            })
            ->groupBy('targets.book_code')
            ->get()
            ->keyBy('book_code');

        foreach ($topProducts as $product) {
            $target = $productTargets->get($product->book_code)->total_target ?? 0;
            $product->target = $target;
            $product->achievement = $target > 0 ? round(($product->total_faktur / $target) * 100, 1) : 0;
        }

        $segmentRankingQuery = DB::table('sp_branches')
            ->select([
                'books.category',
                DB::raw('SUM(sp_branches.ex_ftr) as total_faktur'),
            ])
            ->leftJoin('books', 'sp_branches.book_code', '=', 'books.book_code')
            ->where('sp_branches.active_data', 'yes')
            ->whereNotNull('books.category')
            ->where('books.category', '!=', '');
        if ($startDate !== null) {
            $segmentRankingQuery->whereBetween('sp_branches.trans_date', [$startDate, $endDate]);
        } else {
            $segmentRankingQuery->where('sp_branches.trans_date', '<=', $endDate);
        }
        $segmentRanking = $segmentRankingQuery
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('sp_branches.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('sp_branches.branch_code', $filteredBranchCodes);
            })
            ->groupBy('books.category')
            ->orderByDesc('total_faktur')
            ->get();

        $segmentData = [];
        foreach ($segmentRanking as $item) {
            $segment = $item->category ?? 'Lainnya';
            if ($segment && $segment != '') {
                $segmentData[$segment] = $item->total_faktur ?? 0;
            }
        }

        $monthlySalesQuery = SpBranch::select([
            DB::raw('MONTH(trans_date) as month'),
            DB::raw('SUM(ex_ftr) as total_faktur'),
        ])
            ->where('active_data', 'yes')
            ->whereNotNull('trans_date');
        if ($startDate !== null) {
            $monthlySalesQuery->whereBetween('trans_date', [$startDate, $endDate]);
        } else {
            $monthlySalesQuery->where('trans_date', '<=', $endDate);
        }
        $monthlySales = $monthlySalesQuery
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->groupBy(DB::raw('MONTH(trans_date)'))
            ->orderBy('month')
            ->get();

        $monthlyData = [];
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[$i] = 0;
        }

        foreach ($monthlySales as $sale) {
            if ($sale->month >= 1 && $sale->month <= 12) {
                $monthlyData[$sale->month] = $sale->total_faktur ?? 0;
            }
        }

        $chartData = [];
        $chartLabels = [];
        foreach ($monthlyData as $monthNum => $total) {
            $chartLabels[] = $monthNames[$monthNum - 1];
            $chartData[] = $total;
        }

        $currentYear = (int)$year;
        $yearsRange = [];
        for ($i = 4; $i >= 0; $i--) {
            $yearsRange[] = $currentYear - $i;
        }

        $yearlyTargetData = [];
        $allTargetsSumQuery = Target::select([
            DB::raw('SUM(targets.exemplar) as total_target'),
        ])
            ->join('periods', 'targets.period_code', '=', 'periods.period_code');
        if ($startDate !== null) {
            $allTargetsSumQuery->where('periods.from_date', '<=', $endDate)->where('periods.to_date', '>=', $startDate);
        } else {
            $allTargetsSumQuery->where('periods.to_date', '<=', $endDate);
        }
        $allTargetsSum = $allTargetsSumQuery
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('targets.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('targets.branch_code', $filteredBranchCodes);
            })
            ->first();

        $totalAllTargets = $allTargetsSum->total_target ?? 0;

        foreach ($yearsRange as $y) {
            $yearlyTargetData[$y] = $totalAllTargets;
        }

        $yearlyRealisasiKirimQuery = SpBranch::select([
            DB::raw('YEAR(trans_date) as year'),
            DB::raw('SUM(ex_sp) as total_pesanan'),
        ])
            ->where('active_data', 'yes')
            ->whereNotNull('trans_date');
        if ($startDate !== null) {
            $yearlyRealisasiKirimQuery->whereBetween('trans_date', [$startDate, $endDate]);
        } else {
            $yearlyRealisasiKirimQuery->where('trans_date', '<=', $endDate);
        }
        $yearlyRealisasiKirim = $yearlyRealisasiKirimQuery
            ->whereIn(DB::raw('YEAR(trans_date)'), $yearsRange)
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->groupBy(DB::raw('YEAR(trans_date)'))
            ->orderBy('year')
            ->get()
            ->keyBy('year');

        // Get SP per year from SpBranch (for Pemakaian Kuota NPPB chart)
        $yearlySpDataQuery = SpBranch::select([
            DB::raw('YEAR(trans_date) as year'),
            DB::raw('SUM(ex_sp) as total_sp'),
        ])
            ->where('active_data', 'yes')
            ->whereNotNull('trans_date');
        if ($startDate !== null) {
            $yearlySpDataQuery->whereBetween('trans_date', [$startDate, $endDate]);
        } else {
            $yearlySpDataQuery->where('trans_date', '<=', $endDate);
        }
        $yearlySpData = $yearlySpDataQuery
            ->whereIn(DB::raw('YEAR(trans_date)'), $yearsRange)
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->groupBy(DB::raw('YEAR(trans_date)'))
            ->orderBy('year')
            ->get()
            ->keyBy('year');

        // Initialize array for all years with 0
        $yearlySpChartData = [];
        foreach ($yearsRange as $y) {
            $yearlySpChartData[$y] = 0;
        }

        // Fill in actual data
        foreach ($yearlySpData as $sp) {
            if (in_array($sp->year, $yearsRange)) {
                $yearlySpChartData[$sp->year] = $sp->total_sp ?? 0;
            }
        }

        // Get Stok Pusat per year from CentralStock
        // Note: CentralStock doesn't have year field, so we'll use created_at or get all data
        // For now, we'll get all stok pusat and distribute evenly or use a different approach
        $yearlyStokPusatData = [];
        $totalStokPusatAll = CentralStock::select([
            DB::raw('SUM(exemplar) as total_stok_pusat'),
        ])
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->first();
        
        $totalStokPusatAllValue = $totalStokPusatAll->total_stok_pusat ?? 0;
        
        // For now, distribute total stok pusat evenly across years
        // You can modify this logic if CentralStock has date field
        foreach ($yearsRange as $y) {
            $yearlyStokPusatData[$y] = $totalStokPusatAllValue; // Use same total for all years
        }

        // Get Target per year (already calculated above, but need to format for chart)
        $yearlyTargetChartData = [];
        foreach ($yearsRange as $y) {
            $yearlyTargetChartData[$y] = $totalAllTargets; // Use same total for all years
        }

        $yearlyRealisasiData = [];
        foreach ($yearsRange as $y) {
            $yearlyRealisasiData[$y] = 0;
        }

        foreach ($yearlyRealisasiKirim as $realisasi) {
            if (in_array($realisasi->year, $yearsRange)) {
                $yearlyRealisasiData[$realisasi->year] = $realisasi->total_pesanan ?? 0;
            }
        }

        $chartTargetData = [];
        $chartRealisasiKirimData = [];
        $chartYearLabels = [];
        foreach ($yearsRange as $y) {
            $chartYearLabels[] = (string)$y;
            $chartTargetData[] = $yearlyTargetData[$y] ?? 0;
            $chartRealisasiKirimData[] = $yearlyRealisasiData[$y] ?? 0;
        }

        $branchInfo = null;
        if ($this->role == 2 && $userBranchCode) {
            $branchInfo = Branch::where('branch_code', $userBranchCode)->first();
        }

        $realisasi2024 = 0;
        $realisasi2025 = 0;
        if ($this->role == 2 && $userBranchCode) {
            $r24 = SpBranch::where('active_data', 'yes')->where('branch_code', $userBranchCode)->whereYear('trans_date', 2024);
            $r25 = SpBranch::where('active_data', 'yes')->where('branch_code', $userBranchCode)->whereYear('trans_date', 2025);
            if ($startDate !== null) {
                $r24->whereBetween('trans_date', [$startDate, $endDate]);
                $r25->whereBetween('trans_date', [$startDate, $endDate]);
            } else {
                $r24->where('trans_date', '<=', $endDate);
                $r25->where('trans_date', '<=', $endDate);
            }
            $realisasi2024 = $r24->sum('ex_ftr') ?? 0;
            $realisasi2025 = $r25->sum('ex_ftr') ?? 0;
        }

        $allBranches = Branch::select('branch_name')->distinct()->get();
        $areas = ['Nasional'];
        $areaSet = ['Nasional'];
        foreach ($allBranches as $branch) {
            $areaName = $this->extractAreaFromBranch($branch->branch_name);
            if (!in_array($areaName, $areaSet)) {
                $areaSet[] = $areaName;
                $areas[] = $areaName;
            }
        }
        $otherAreas = array_filter($areas, function ($a) {
            return $a !== 'Nasional';
        });
        sort($otherAreas);
        $areas = array_merge(['Nasional'], $otherAreas);

        // Distinct category_manual dari product (books) + jumlah per kategori
        $productCategoryManualCounts = Product::query()
            ->select('category_manual')
            ->selectRaw('count(*) as total')
            ->whereNotNull('category_manual')
            ->where('category_manual', '!=', '')
            ->groupBy('category_manual')
            ->orderByDesc('total')
            ->get();

        // Kategori Manual (books) + total SP dari sp_branches (periode & filter cabang sama)
        $categoryManualSpQuery = DB::table('sp_branches')
            ->select([
                'books.category_manual',
                DB::raw('SUM(sp_branches.ex_sp) as total_sp'),
                DB::raw('SUM(sp_branches.ex_ftr) as total_faktur'),
            ])
            ->leftJoin('books', 'sp_branches.book_code', '=', 'books.book_code')
            ->where('sp_branches.active_data', 'yes')
            ->whereNotNull('books.category_manual')
            ->where('books.category_manual', '!=', '');
        if ($startDate !== null) {
            $categoryManualSpQuery->whereBetween('sp_branches.trans_date', [$startDate, $endDate]);
        } else {
            $categoryManualSpQuery->where('sp_branches.trans_date', '<=', $endDate);
        }
        $categoryManualSp = $categoryManualSpQuery
            ->when($userBranchCode, function ($query) use ($userBranchCode) {
                return $query->where('sp_branches.branch_code', $userBranchCode);
            })
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('sp_branches.branch_code', $filteredBranchCodes);
            })
            ->groupBy('books.category_manual')
            ->orderByDesc('total_sp')
            ->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'year' => $year,
            'targets' => $targets, // KeyBy branch_code
            'totalTarget' => $totalTarget,
            'totalSp' => $totalSp,
            'totalFaktur' => $totalFaktur,
            'totalRet' => $totalRet,
            'totalNetto' => $totalNetto,
            'totalSisaSp' => $totalSisaSp,
            'totalStockPusat' => $totalStockPusat,
            'totalStokCabang' => $totalStokCabang,
            'totalNkb' => $totalNkb,
            'totalNppbKoli' => $totalNppbKoli,
            'totalNppbPls' => $totalNppbPls,
            'totalNppbExp' => $totalNppbExp,
            'totalTargetYear' => $totalTargetYear, // Total target tahun ini untuk perhitungan persentase NPPB
            'achievementPercent' => $achievementPercent,
            'pctSpThdTarget' => $pctSpThdTarget,
            'pctFakturThdSp' => $pctFakturThdSp,
            'pctStockThdSpSebelum' => $pctStockThdSpSebelum,
            'pctStockThdSpSesudah' => $pctStockThdSpSesudah,
            'totalFakturStockRencana' => $totalFakturStockRencana,
            'pctTargetVsFakturStockRencana' => $pctTargetVsFakturStockRencana,
            // Data untuk Sizem TNPP
            'totalSp' => $totalSp, // Total pesanan_1 dari sp_branches
            'totalTarget' => $totalTarget, // Total target dari tabel target
            'topBranches' => $topBranches,
            'adpBranches' => $adpBranchesPaginated, // Paginated data untuk Penentuan Kirim (ADP)
            'topProducts' => $topProducts,
            'nppbPerBranch' => $nppbPerBranch, // NPPB per branch untuk kolom plastik
            'segmentRanking' => $segmentData, // Array dengan key = segment name, value = total_faktur
            'monthlySalesLabels' => $chartLabels, // Labels untuk chart (Jan, Feb, etc.)
            'monthlySalesData' => $chartData, // Data penjualan per bulan
            'yearlyTargetData' => $chartTargetData, // Data target per tahun (5 tahun)
            'yearlyRealisasiKirimData' => $chartRealisasiKirimData, // Data realisasi kirim per tahun (pesanan_1)
            'yearlyLabels' => $chartYearLabels, // Labels tahun untuk chart
            'yearlySpData' => $yearlySpChartData, // Data SP per tahun untuk chart SP Per Tahun
            'yearlyStokPusatData' => $yearlyStokPusatData, // Data Stok Pusat per tahun
            'yearlyTargetChartData' => $yearlyTargetChartData, // Data Target per tahun untuk chart
            'totalBranches' => $spBranches->count(),
            'branchInfo' => $branchInfo,
            'realisasi2024' => $realisasi2024,
            'realisasi2025' => $realisasi2025,
            'areas' => $areas,
            'selectedBranchCode' => $selectedBranchCode,
            'dateRange' => $dateRange,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'usingCutoffData' => $usingCutoffData,
            'activeCutoff' => $activeCutoff,
            'productCategoryManualCounts' => $productCategoryManualCounts,
            'categoryManualSp' => $categoryManualSp,
        ];

        return view($this->callbackfolder . '.dashboard.index', $data);
    }

    /**
     * Get branch codes by area filter
     */
    private function getBranchCodesByArea($area)
    {
        // If area is "Nasional", return null (no filter)
        if ($area === 'Nasional' || empty($area)) {
            return null;
        }

        // Get all branches
        $allBranches = Branch::select('branch_code', 'branch_name')->get();
        
        // Filter branches by area
        $filteredBranchCodes = [];
        foreach ($allBranches as $branch) {
            $branchArea = $this->extractAreaFromBranch($branch->branch_name);
            if ($branchArea === $area) {
                $filteredBranchCodes[] = $branch->branch_code;
            }
        }

        // If no branches found, return empty array to show no data
        return !empty($filteredBranchCodes) ? $filteredBranchCodes : [];
    }

    /**
     * Extract area name from branch name
     */
    private function extractAreaFromBranch($branchName)
    {
        if (empty($branchName)) {
            return 'Nasional';
        }

        $branchNameUpper = strtoupper($branchName);

        // Area Sumatera Utara
        if (
            stripos($branchName, 'MEDAN') !== false ||
            stripos($branchName, 'PALEMBANG') !== false ||
            stripos($branchName, 'PEKANBARU') !== false ||
            stripos($branchName, 'BANDA ACEH') !== false ||
            stripos($branchName, 'SIBOLGA') !== false ||
            stripos($branchName, 'PADANG') !== false ||
            stripos($branchName, 'JAMBI') !== false ||
            stripos($branchName, 'BENGKULU') !== false ||
            stripos($branchName, 'LAMPUNG') !== false
        ) {
            return 'Area Sumatera';
        }

        // Area Jawa
        if (
            stripos($branchName, 'JAKARTA') !== false ||
            stripos($branchName, 'BANDUNG') !== false ||
            stripos($branchName, 'SURABAYA') !== false ||
            stripos($branchName, 'YOGYAKARTA') !== false ||
            stripos($branchName, 'SEMARANG') !== false ||
            stripos($branchName, 'MALANG') !== false ||
            stripos($branchName, 'BOGOR') !== false ||
            stripos($branchName, 'DEPOK') !== false ||
            stripos($branchName, 'TANGERANG') !== false ||
            stripos($branchName, 'BEKASI') !== false
        ) {
            return 'Area Jawa';
        }

        // Area Sulawesi
        if (
            stripos($branchName, 'MAKASSAR') !== false ||
            stripos($branchName, 'MANADO') !== false ||
            stripos($branchName, 'PALU') !== false ||
            stripos($branchName, 'KENDARI') !== false
        ) {
            return 'Area Sulawesi';
        }

        // Default
        return 'Nasional';
    }

    /**
     * Show branch detail page
     */
    public function branchDetail(Request $request, $branchCode)
    {
        // Check if date range is set in session
        $dateRange = session('date_range_global');
        
        // Check if there's an active cutoff_data
        $activeCutoff = CutoffData::where('status', 'active')->first();
        
        // Get date range priority:
        // 1. If date_range_global is set in session, use that (user override)
        // 2. Else if there's an active cutoff_data, use that
        // 3. Else use default (current month)
        if ($dateRange) {
            $startDate = $dateRange['start_date'];
            $endDate = $dateRange['end_date'];
        } elseif ($activeCutoff) {
            $endDate = $activeCutoff->end_date->format('Y-m-d');
            $startDate = $activeCutoff->start_date ? $activeCutoff->start_date->format('Y-m-d') : null;
        } else {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }

        // Get branch info
        $branch = Branch::where('branch_code', $branchCode)->first();
        if (!$branch) {
            abort(404, 'Cabang tidak ditemukan');
        }

        // Get all products
        $products = Product::select('book_code', 'book_title')
            ->orderBy('book_code')
            ->get();

        // Get central stocks (total stock pusat per book_code)
        $centralStocks = CentralStock::select([
            'book_code',
            DB::raw('SUM(exemplar) as total_stock_pusat')
        ])
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Get existing NPPB data for this branch
        $existingNppbQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(koli) as koli'),
            DB::raw('SUM(exp) as exp'),
            DB::raw('SUM(pls) as pls'),
        ])
            ->where('branch_code', $branchCode);
        if ($startDate !== null) {
            $existingNppbQuery->whereBetween('date', [$startDate, $endDate]);
        } else {
            $existingNppbQuery->where('date', '<=', $endDate);
        }
        $existingNppb = $existingNppbQuery
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Get SP, Faktur, and Stock Cabang from sp_branches
        $spBranchQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_sp) as sp'),
            DB::raw('SUM(ex_ftr) as faktur'),
            DB::raw('SUM(ex_stock) as stock_cabang'),
        ])
            ->where('active_data', 'yes')
            ->where('branch_code', $branchCode);
        if ($startDate !== null) {
            $spBranchQuery->whereBetween('trans_date', [$startDate, $endDate]);
        } else {
            $spBranchQuery->where('trans_date', '<=', $endDate);
        }
        $spBranchData = $spBranchQuery
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Pre-load CentralStockKoli data
        $allStockKolis = CentralStockKoli::select([
            'branch_code',
            'book_code',
            DB::raw('MAX(volume) as volume')
        ])
            ->whereIn('book_code', $products->pluck('book_code'))
            ->groupBy('branch_code', 'book_code')
            ->get();

        $stockKolisByBranch = $allStockKolis
            ->where('branch_code', $branchCode)
            ->keyBy('book_code');
        
        $stockKolisGeneral = $allStockKolis
            ->groupBy('book_code')
            ->map(function ($items) {
                return $items->first();
            });

        // Combine data per product
        $branchProducts = $products->map(function ($product) use ($centralStocks, $existingNppb, $spBranchData, $stockKolisByBranch, $stockKolisGeneral) {
            $stock = $centralStocks->get($product->book_code);
            $nppb = $existingNppb->get($product->book_code);
            $spBranch = $spBranchData->get($product->book_code);

            $sp = $spBranch->sp ?? 0;
            $faktur = $spBranch->faktur ?? 0;
            $stockCabang = $spBranch->stock_cabang ?? 0;
            $stockPusat = $stock->total_stock_pusat ?? 0;

            // Calculate Sisa SP
            $selisih = $sp - $faktur;
            if ($stockCabang >= $selisih) {
                $sisaSp = 0;
            } else {
                $sisaSp = max(0, $selisih - $stockCabang - $stockPusat);
            }

            // Get eksemplar, koli, plastik
            $exp = $nppb->exp ?? 0;
            $koli = $nppb->koli ?? 0;
            $pls = $nppb->pls ?? 0;
            $hasExistingData = ($nppb !== null);
            
            $stockKoli = $stockKolisByBranch->get($product->book_code);
            if (!$stockKoli) {
                $stockKoli = $stockKolisGeneral->get($product->book_code);
            }

            // If no existing data, calculate from sisa SP
            if (!$hasExistingData) {
                $exp = $sisaSp;
                
                if ($stockKoli && $stockKoli->volume > 0 && $exp > 0) {
                    $volume = (float)$stockKoli->volume;
                    $koli = floor($exp / $volume);
                    $pls = $exp % $volume;
                }
            }

            return [
                'book_code' => $product->book_code,
                'book_name' => $product->book_title,
                'sp' => $sp,
                'faktur' => $faktur,
                'sisa_sp' => $sisaSp,
                'stock_cabang' => $stockCabang,
                'eksemplar' => $exp,
                'koli' => $koli,
                'plastik' => $pls,
            ];
        })->values(); // Tampilkan semua produk, tidak perlu filter

        // Paginate
        $perPage = (int) $request->input('per_page', 25);
        if ($perPage < 10) {
            $perPage = 25;
        }
        $currentPage = (int) $request->input('page', 1);
        $total = $branchProducts->count();
        $offset = ($currentPage - 1) * $perPage;
        $paginatedProducts = $branchProducts->slice($offset, $perPage)->values();

        $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedProducts,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query()
            ]
        );

        $data = [
            'title' => 'Detail Cabang - ' . ($branch->branch_name ?? $branchCode),
            'base_url' => $this->base_url,
            'branch' => $branch,
            'branchCode' => $branchCode,
            'products' => $paginatedData,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'usingCutoffData' => $activeCutoff && !$dateRange,
            'activeCutoff' => $activeCutoff,
        ];

        return view($this->callbackfolder . '.dashboard.branch-detail', $data);
    }

    /**
     * Set date range for dashboard filter
     */
    public function setDateRange(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        session([
            'date_range_global' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Range tanggal berhasil disimpan',
            'redirect' => route('dashboard')
        ]);
    }

    /**
     * Infinite scroll: load more rows untuk tabel Penentuan Kirim (ADP). Return HTML fragment.
     */
    public function adpMore(Request $request)
    {
        $ctx = $this->getDashboardScrollContext($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15;

        $adpBranchesQuery = Branch::select([
            'branches.branch_code',
            'branches.branch_name',
            DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0) - COALESCE(SUM(sp_branches.ex_ftr), 0) as sisa_sp'),
        ])
            ->leftJoin('sp_branches', function ($join) use ($ctx) {
                $join->on('branches.branch_code', '=', 'sp_branches.branch_code')
                    ->where('sp_branches.active_data', 'yes');
                if ($ctx['startDate'] !== null) {
                    $join->whereBetween('sp_branches.trans_date', [$ctx['startDate'], $ctx['endDate']]);
                } else {
                    $join->where('sp_branches.trans_date', '<=', $ctx['endDate']);
                }
            })
            ->when($ctx['userBranchCode'], fn ($q) => $q->where('branches.branch_code', $ctx['userBranchCode']))
            ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('branches.branch_code', $ctx['filteredBranchCodes']))
            ->groupBy('branches.branch_code', 'branches.branch_name')
            ->orderByDesc(DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0) - COALESCE(SUM(sp_branches.ex_ftr), 0)'));

        $paginator = $adpBranchesQuery->paginate($perPage, ['*'], 'page', $page);
        $targets = $this->getTargetsKeyedByBranch($ctx);
        $nppbPerBranch = $this->getNppbPerBranch($ctx);
        $totalNppbKoli = $this->getTotalNppbKoli($ctx);
        $totalTarget = $this->getTotalTarget($ctx);
        $totalSisaSp = $this->getTotalSisaSp($ctx);
        $totalNppbPls = $this->getTotalNppbPls($ctx);

        $html = view($this->callbackfolder . '.dashboard._adp_rows', [
            'adpBranches' => $paginator->items(),
            'targets' => $targets,
            'nppbPerBranch' => $nppbPerBranch,
            'totalNppbKoli' => $totalNppbKoli,
            'totalTarget' => $totalTarget,
            'totalSisaSp' => $totalSisaSp,
            'totalNppbPls' => $totalNppbPls,
            'hasMore' => $paginator->hasMorePages(),
        ])->render();
        return response()->json(['html' => $html, 'hasMore' => $paginator->hasMorePages()]);
    }

    /**
     * Infinite scroll: load more rows untuk tabel Kebutuhan Kirim Cabang. Return HTML fragment.
     */
    public function kebutuhanMore(Request $request)
    {
        $ctx = $this->getDashboardScrollContext($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15;

        $spBranchesQuery = Branch::select([
            'branches.branch_code',
            'branches.branch_name',
            DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0) as total_sp'),
            DB::raw('COALESCE(SUM(sp_branches.ex_ftr), 0) as total_faktur'),
            DB::raw('COALESCE(SUM(sp_branches.ex_ret), 0) as total_ret'),
            DB::raw('COALESCE(SUM(sp_branches.ex_ftr), 0) - COALESCE(SUM(sp_branches.ex_ret), 0) as netto'),
            DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0) - COALESCE(SUM(sp_branches.ex_ftr), 0) as sisa_sp'),
            DB::raw('COALESCE(SUM(sp_branches.ex_stock), 0) as total_stok_cabang'),
            DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nkb'),
        ])
            ->leftJoin('sp_branches', function ($join) use ($ctx) {
                $join->on('branches.branch_code', '=', 'sp_branches.branch_code')
                    ->where('sp_branches.active_data', 'yes');
                if ($ctx['startDate'] !== null) {
                    $join->whereBetween('sp_branches.trans_date', [$ctx['startDate'], $ctx['endDate']]);
                } else {
                    $join->where('sp_branches.trans_date', '<=', $ctx['endDate']);
                }
            })
            ->when($ctx['userBranchCode'], fn ($q) => $q->where('branches.branch_code', $ctx['userBranchCode']))
            ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('branches.branch_code', $ctx['filteredBranchCodes']))
            ->groupBy('branches.branch_code', 'branches.branch_name')
            ->orderByDesc(DB::raw('COALESCE(SUM(sp_branches.ex_sp), 0)'));

        $paginator = $spBranchesQuery->paginate($perPage, ['*'], 'page', $page);
        $totalSp = $this->getTotalSp($ctx);
        $totalSisaSp = $this->getTotalSisaSp($ctx);
        $totalStokCabang = $this->getTotalStokCabang($ctx);

        $html = view($this->callbackfolder . '.dashboard._kebutuhan_rows', [
            'topBranches' => $paginator->items(),
            'totalSp' => $totalSp,
            'totalSisaSp' => $totalSisaSp,
            'totalStokCabang' => $totalStokCabang,
            'hasMore' => $paginator->hasMorePages(),
        ])->render();
        return response()->json(['html' => $html, 'hasMore' => $paginator->hasMorePages()]);
    }

    private function getDashboardScrollContext(Request $request): array
    {
        $dateRange = session('date_range_global');
        $activeCutoff = CutoffData::where('status', 'active')->first();
        if ($dateRange) {
            $startDate = $dateRange['start_date'];
            $endDate = $dateRange['end_date'];
        } elseif ($activeCutoff) {
            $endDate = $activeCutoff->end_date->format('Y-m-d');
            $startDate = $activeCutoff->start_date ? $activeCutoff->start_date->format('Y-m-d') : null;
        } else {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }
        $userBranchCode = null;
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        if ($this->role == 2 && Auth::check()) {
            $userBranchCode = Auth::user()->branch_code ?? null;
            $filteredBranchCodes = null;
        } else {
            $filteredBranchCodes = null; // superadmin & ADP: akses global
        }
        $selectedBranchCode = $request->input('branch', null);
        if ($this->role != 3) {
            if ($selectedBranchCode) {
                $filteredBranchCodes = [$selectedBranchCode];
                $userBranchCode = null;
            } elseif ($userBranchCode) {
                $filteredBranchCodes = [$userBranchCode];
            }
        }
        return compact('startDate', 'endDate', 'userBranchCode', 'filteredBranchCodes');
    }

    private function getTargetsKeyedByBranch(array $ctx)
    {
        return Branch::select(['branches.branch_code', DB::raw('COALESCE(SUM(targets.exemplar), 0) as total_target')])
            ->leftJoin('targets', 'branches.branch_code', '=', 'targets.branch_code')
            ->leftJoin('periods', function ($join) use ($ctx) {
                $join->on('targets.period_code', '=', 'periods.period_code');
                if ($ctx['startDate'] !== null) {
                    $join->where('periods.from_date', '<=', $ctx['endDate'])->where('periods.to_date', '>=', $ctx['startDate']);
                } else {
                    $join->where('periods.to_date', '<=', $ctx['endDate']);
                }
            })
            ->when($ctx['userBranchCode'], fn ($q) => $q->where('branches.branch_code', $ctx['userBranchCode']))
            ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('branches.branch_code', $ctx['filteredBranchCodes']))
            ->groupBy('branches.branch_code')
            ->get()
            ->keyBy('branch_code');
    }

    private function getNppbPerBranch(array $ctx)
    {
        return Branch::select(['branches.branch_code', DB::raw('COALESCE(SUM(nppb_centrals.pls), 0) as total_pls')])
            ->leftJoin('nppb_centrals', function ($join) use ($ctx) {
                $join->on('branches.branch_code', '=', 'nppb_centrals.branch_code');
                if ($ctx['startDate'] !== null) {
                    $join->whereBetween('nppb_centrals.date', [$ctx['startDate'], $ctx['endDate']]);
                } else {
                    $join->where('nppb_centrals.date', '<=', $ctx['endDate']);
                }
            })
            ->when($ctx['userBranchCode'], fn ($q) => $q->where('branches.branch_code', $ctx['userBranchCode']))
            ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('branches.branch_code', $ctx['filteredBranchCodes']))
            ->groupBy('branches.branch_code')
            ->get()
            ->keyBy('branch_code');
    }

    private function getTotalNppbKoli(array $ctx)
    {
        $r = NppbCentral::selectRaw('COALESCE(SUM(koli), 0) as total_koli');
        if ($ctx['startDate'] !== null) {
            $r->whereBetween('date', [$ctx['startDate'], $ctx['endDate']]);
        } else {
            $r->where('date', '<=', $ctx['endDate']);
        }
        $r->when($ctx['userBranchCode'], fn ($q) => $q->where('branch_code', $ctx['userBranchCode']))
          ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('branch_code', $ctx['filteredBranchCodes']));
        return (int) ($r->first()->total_koli ?? 0);
    }

    private function getTotalTarget(array $ctx)
    {
        $q = Target::selectRaw('COALESCE(SUM(targets.exemplar), 0) as total_target')
            ->join('periods', 'targets.period_code', '=', 'periods.period_code');
        if ($ctx['startDate'] !== null) {
            $q->where('periods.from_date', '<=', $ctx['endDate'])->where('periods.to_date', '>=', $ctx['startDate']);
        } else {
            $q->where('periods.to_date', '<=', $ctx['endDate']);
        }
        $q->when($ctx['userBranchCode'], fn ($q) => $q->where('targets.branch_code', $ctx['userBranchCode']))
          ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('targets.branch_code', $ctx['filteredBranchCodes']));
        return (int) ($q->first()->total_target ?? 0);
    }

    private function getTotalSisaSp(array $ctx)
    {
        $q = SpBranch::selectRaw('(COALESCE(SUM(ex_sp), 0) - COALESCE(SUM(ex_ftr), 0)) as sisa')
            ->where('active_data', 'yes');
        if ($ctx['startDate'] !== null) {
            $q->whereBetween('trans_date', [$ctx['startDate'], $ctx['endDate']]);
        } else {
            $q->where('trans_date', '<=', $ctx['endDate']);
        }
        $q->when($ctx['userBranchCode'], fn ($q) => $q->where('branch_code', $ctx['userBranchCode']))
          ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('branch_code', $ctx['filteredBranchCodes']));
        $row = $q->first();
        return (int) ($row->sisa ?? 0);
    }

    private function getTotalNppbPls(array $ctx)
    {
        $r = NppbCentral::selectRaw('COALESCE(SUM(pls), 0) as total_pls');
        if ($ctx['startDate'] !== null) {
            $r->whereBetween('date', [$ctx['startDate'], $ctx['endDate']]);
        } else {
            $r->where('date', '<=', $ctx['endDate']);
        }
        $r->when($ctx['userBranchCode'], fn ($q) => $q->where('branch_code', $ctx['userBranchCode']))
          ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('branch_code', $ctx['filteredBranchCodes']));
        return (int) ($r->first()->total_pls ?? 0);
    }

    private function getTotalSp(array $ctx)
    {
        $q = SpBranch::selectRaw('COALESCE(SUM(ex_sp), 0) as total_sp')->where('active_data', 'yes');
        if ($ctx['startDate'] !== null) {
            $q->whereBetween('trans_date', [$ctx['startDate'], $ctx['endDate']]);
        } else {
            $q->where('trans_date', '<=', $ctx['endDate']);
        }
        $q->when($ctx['userBranchCode'], fn ($q) => $q->where('branch_code', $ctx['userBranchCode']))
          ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('branch_code', $ctx['filteredBranchCodes']));
        return (int) ($q->first()->total_sp ?? 0);
    }

    private function getTotalStokCabang(array $ctx)
    {
        $q = SpBranch::selectRaw('COALESCE(SUM(ex_stock), 0) as total_stok')->where('active_data', 'yes');
        if ($ctx['startDate'] !== null) {
            $q->whereBetween('trans_date', [$ctx['startDate'], $ctx['endDate']]);
        } else {
            $q->where('trans_date', '<=', $ctx['endDate']);
        }
        $q->when($ctx['userBranchCode'], fn ($q) => $q->where('branch_code', $ctx['userBranchCode']))
          ->when($ctx['filteredBranchCodes'] !== null, fn ($q) => $q->whereIn('branch_code', $ctx['filteredBranchCodes']));
        return (int) ($q->first()->total_stok ?? 0);
    }
}
