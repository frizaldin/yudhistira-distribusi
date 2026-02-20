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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Display rekapitulasi report
     */
    public function index(Request $request)
    {
        try {
            // Get year from request or use current year
            $year = $request->input('year', date('Y'));
            $filterBookCode = $request->input('book_code', '');
            $filterBookCode = trim($filterBookCode);

            // Check if there's an active cutoff_data
            $activeCutoff = CutoffData::where('status', 'active')->first();

            // Determine date range: use cutoff_datas if active (hanya end_date required; start_date null = data <= end_date)
            $startDate = null;
            $endDate = null;
            if ($activeCutoff) {
                $endDate = $activeCutoff->end_date
                    ? \Carbon\Carbon::parse($activeCutoff->end_date)->format('Y-m-d')
                    : null;

                $startDate = $activeCutoff->start_date
                    ? \Carbon\Carbon::parse($activeCutoff->start_date)->format('Y-m-d')
                    : null;
            }

            // Filter cabang: role 2 = satu cabang, role 3 (ADP) = array cabang dari user->branch
            $userBranchCode = null;
            $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
            if ($this->role == 2 && Auth::check()) {
                $userBranchCode = Auth::user()->branch_code ?? null;
            }

            // Get branches: untuk ADP/superadmin filter by $filteredBranchCodes
            $branchesQuery = Branch::orderBy('branch_name');
            if ($filteredBranchCodes !== null) {
                $branchesQuery->whereIn('branch_code', $filteredBranchCodes);
            }
            $branches = $branchesQuery->get();

            // Get targets from targets table (sum per branch_code)
            // Filter by cutoff_datas if active, otherwise by year
            $targetsQuery = Target::select([
                'targets.branch_code',
                DB::raw('SUM(targets.exemplar) as total_target'),
            ])
                ->join('periods', 'targets.period_code', '=', 'periods.period_code');

            if ($activeCutoff) {
                if ($startDate !== null) {
                    $targetsQuery->where(function ($q) use ($startDate, $endDate) {
                        $q->where('periods.from_date', '<=', $endDate)
                            ->where('periods.to_date', '>=', $startDate);
                    });
                } else {
                    $targetsQuery->where('periods.to_date', '<=', $endDate);
                }
            } else {
                // Filter by year
                $targetsQuery->where(function ($query) use ($year) {
                    $query->whereYear('periods.from_date', $year)
                        ->orWhereYear('periods.to_date', $year);
                });
            }

            $targets = $targetsQuery
                ->when($filterBookCode !== '', function ($q) use ($filterBookCode) {
                    return $q->where('targets.book_code', $filterBookCode);
                })
                ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                    return $q->whereIn('targets.branch_code', $filteredBranchCodes);
                })
                ->groupBy('targets.branch_code')
                ->get()
                ->keyBy('branch_code');

            // Get NPPB Central (Rencana NPPB Pusat Ciawi) - sum per branch_code
            // Filter by cutoff_datas if active
            $nppbCentralsQuery = NppbCentral::select([
                'branch_code',
                DB::raw('SUM(koli) as total_koli'),
                DB::raw('SUM(pls) as total_pls'),
                DB::raw('SUM(exp) as total_exp'),
            ]);

            if ($activeCutoff) {
                if ($startDate !== null) {
                    $nppbCentralsQuery->whereBetween('date', [$startDate, $endDate]);
                } else {
                    $nppbCentralsQuery->where('date', '<=', $endDate);
                }
            }
            $nppbCentralsQuery->when($filterBookCode !== '', function ($q) use ($filterBookCode) {
                return $q->where('book_code', $filterBookCode);
            });
            $nppbCentralsQuery->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->whereIn('branch_code', $filteredBranchCodes);
            });
            $nppbCentrals = $nppbCentralsQuery
                ->groupBy('branch_code')
                ->get()
                ->keyBy('branch_code');

            // If branch role, get detailed data per book for that branch
            if ($this->role == 2 && $userBranchCode) {
                // Get detailed data per book for the branch - menggunakan field terbaru
                $branchBooksQuery = SpBranch::select([
                    'sp_branches.book_code',
                    DB::raw('SUM(sp_branches.ex_sp) as sp'), // SP = ex_sp
                    DB::raw('SUM(sp_branches.ex_ftr) as faktur'), // faktur = ex_ftr
                    DB::raw('SUM(sp_branches.ex_ret) as total_ret'), // ret = ex_ret
                    DB::raw('SUM(sp_branches.ex_sp) - SUM(sp_branches.ex_ftr) as sisa_sp'), // sisa sp = sp - faktur
                    DB::raw('SUM(sp_branches.ex_stock) as stok_cabang'), // stok cabang = ex_stock
                    DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as nkb_pusat'), // NKB dari pusat = ex_rec_pst
                ])
                    ->where('sp_branches.active_data', 'yes')
                    ->where('sp_branches.branch_code', $userBranchCode);

                // Filter by cutoff_datas if active
                if ($activeCutoff) {
                    if ($startDate !== null) {
                        $branchBooksQuery->whereBetween('sp_branches.trans_date', [$startDate, $endDate]);
                    } else {
                        $branchBooksQuery->where('sp_branches.trans_date', '<=', $endDate);
                    }
                }

                $branchBooks = $branchBooksQuery
                    ->groupBy('sp_branches.book_code')
                    ->orderBy('sp_branches.book_code')
                    ->get();

                // Get product info to add book_title
                $products = Product::whereIn('book_code', $branchBooks->pluck('book_code'))->get()->keyBy('book_code');

                // Add book_title and realisasi data
                foreach ($branchBooks as $book) {
                    $product = $products->get($book->book_code);
                    $book->book_title = $product->book_title ?? '';
                    $book->realisasi_2024 = 0; // Placeholder - data historis
                    $book->realisasi_2025 = $book->faktur ?? 0;
                }

                // Get target per book for this branch
                $bookTargetsQuery = Target::select([
                    'targets.book_code',
                    DB::raw('SUM(targets.exemplar) as target'),
                ])
                    ->join('periods', 'targets.period_code', '=', 'periods.period_code')
                    ->where('targets.branch_code', $userBranchCode);

                if ($activeCutoff) {
                    if ($startDate !== null) {
                        $bookTargetsQuery->where(function ($q) use ($startDate, $endDate) {
                            $q->where('periods.from_date', '<=', $endDate)
                                ->where('periods.to_date', '>=', $startDate);
                        });
                    } else {
                        $bookTargetsQuery->where('periods.to_date', '<=', $endDate);
                    }
                } else {
                    $bookTargetsQuery->where(function ($query) use ($year) {
                        $query->whereYear('periods.from_date', $year)
                            ->orWhereYear('periods.to_date', $year);
                    });
                }

                $bookTargets = $bookTargetsQuery
                    ->whereNotNull('targets.book_code')
                    ->groupBy('targets.book_code')
                    ->get()
                    ->keyBy('book_code');

                // Get NPPB Central per book for this branch
                $bookNppbQuery = NppbCentral::select([
                    'book_code',
                    DB::raw('SUM(koli) as koli'),
                    DB::raw('SUM(pls) as pls'),
                    DB::raw('SUM(exp) as exp'),
                ])
                    ->where('branch_code', $userBranchCode)
                    ->whereNotNull('book_code');

                // Filter by cutoff_datas if active
                if ($activeCutoff) {
                    if ($startDate !== null) {
                        $bookNppbQuery->whereBetween('date', [$startDate, $endDate]);
                    } else {
                        $bookNppbQuery->where('date', '<=', $endDate);
                    }
                }

                $bookNppb = $bookNppbQuery
                    ->groupBy('book_code')
                    ->get()
                    ->keyBy('book_code');

                // Get branch info
                $branchInfo = Branch::where('branch_code', $userBranchCode)->first();

                // Merge data
                foreach ($branchBooks as $book) {
                    $target = $bookTargets->get($book->book_code);
                    $book->target = $target->target ?? 0;

                    $nppb = $bookNppb->get($book->book_code);
                    $book->nppb_koli = $nppb->koli ?? 0;
                    $book->nppb_pls = $nppb->pls ?? 0;
                    $book->nppb_exp = $nppb->exp ?? 0;

                    // Calculate stock availability
                    $diffTarget = $book->stok_cabang - $book->target;
                    $book->stok_thd_target_lebih = $diffTarget > 0 ? $diffTarget : 0;
                    $book->stok_thd_target_kurang = $diffTarget < 0 ? $diffTarget : 0;

                    $diffSp = $book->stok_cabang - $book->sp;
                    $book->stok_thd_sp_lebih = $diffSp > 0 ? $diffSp : 0;
                    $book->stok_thd_sp_kurang = $diffSp < 0 ? $diffSp : 0;

                    // Calculate percentages
                    $book->pct_stok_thd_real = $book->realisasi_2025 > 0 ? round(($book->stok_cabang / $book->realisasi_2025) * 100, 0) : 0;
                    $book->pct_stok_thd_target = $book->target > 0 ? round(($book->stok_cabang / $book->target) * 100, 0) : 0;
                    $book->pct_stok_thd_sp = $book->sp > 0 ? round(($book->stok_cabang / $book->sp) * 100, 0) : 0;
                }

                // Get product info for grouping
                $products = Product::whereIn('book_code', $branchBooks->pluck('book_code'))->get()->keyBy('book_code');

                // Calculate totals
                $branchTotals = [
                    'realisasi_2024' => $branchBooks->sum('realisasi_2024'),
                    'realisasi_2025' => $branchBooks->sum('realisasi_2025'),
                    'target' => $branchBooks->sum('target'),
                    'sp' => $branchBooks->sum('sp'),
                    'faktur' => $branchBooks->sum('faktur'),
                    'sisa_sp' => $branchBooks->sum('sisa_sp'),
                    'stok_cabang' => $branchBooks->sum('stok_cabang'),
                    'nkb_pusat' => $branchBooks->sum('nkb_pusat'),
                    'stok_thd_target_lebih' => $branchBooks->sum('stok_thd_target_lebih'),
                    'stok_thd_target_kurang' => $branchBooks->sum('stok_thd_target_kurang'),
                    'stok_thd_sp_lebih' => $branchBooks->sum('stok_thd_sp_lebih'),
                    'stok_thd_sp_kurang' => $branchBooks->sum('stok_thd_sp_kurang'),
                    'nppb_koli' => $branchBooks->sum('nppb_koli'),
                    'nppb_pls' => $branchBooks->sum('nppb_pls'),
                    'nppb_exp' => $branchBooks->sum('nppb_exp'),
                ];

                // Calculate total percentages
                $branchTotals['pct_stok_thd_real'] = $branchTotals['realisasi_2025'] > 0 ? round(($branchTotals['stok_cabang'] / $branchTotals['realisasi_2025']) * 100, 0) : 0;
                $branchTotals['pct_stok_thd_target'] = $branchTotals['target'] > 0 ? round(($branchTotals['stok_cabang'] / $branchTotals['target']) * 100, 0) : 0;
                $branchTotals['pct_stok_thd_sp'] = $branchTotals['sp'] > 0 ? round(($branchTotals['stok_cabang'] / $branchTotals['sp']) * 100, 0) : 0;

                $data = [
                    'title' => $this->title,
                    'base_url' => $this->base_url,
                    'year' => $year,
                    'branchInfo' => $branchInfo,
                    'branchBooks' => $branchBooks,
                    'products' => $products,
                    'branchTotals' => $branchTotals,
                    'activeCutoff' => $activeCutoff,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ];

                return view($this->callbackfolder . '.rekapitulasi.index', $data);
            }

            // Original superadmin logic
            $spBranchesQuery = SpBranch::select([
                'sp_branches.branch_code',
                'branches.branch_name',
                DB::raw('SUM(sp_branches.ex_sp) as total_sp'), // SP = ex_sp
                DB::raw('SUM(sp_branches.ex_ftr) as total_faktur'), // faktur = ex_ftr
                DB::raw('SUM(sp_branches.ex_ret) as total_ret'), // ret = ex_ret
                DB::raw('SUM(sp_branches.ex_ftr) - COALESCE(SUM(sp_branches.ex_ret), 0) as netto'), // netto = faktur - retur
                DB::raw('SUM(sp_branches.ex_sp) - SUM(sp_branches.ex_ftr) as sisa_sp'), // sisa sp = sp - faktur
                DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nk'), // Rec Pusat = ex_rec_pst
                DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nt'), // Rec Pusat = ex_rec_pst
                DB::raw('COALESCE(SUM(sp_branches.ex_rec_pst), 0) as total_nkb'), // Rec Pusat = ex_rec_pst
                DB::raw('SUM(sp_branches.ex_stock) as total_stok_cabang'), // stok cabang = ex_stock
                DB::raw('SUM(sp_branches.ex_sp) as total_sp_1'), // SP dari gudang area
                DB::raw('SUM(sp_branches.ex_ftr) as total_faktur_1'), // faktur dari gudang area
                DB::raw('SUM(sp_branches.ex_ret) as total_ret_1'), // ret
                DB::raw('COALESCE(SUM(sp_branches.ex_rec_gdg), 0) as total_nt_1'), // Rec Gudang = ex_rec_gdg
                DB::raw('COALESCE(SUM(sp_branches.ex_rec_gdg), 0) as total_nkb_1'), // Rec Gudang = ex_rec_gdg
                DB::raw('COALESCE(SUM(sp_branches.ex_rec_gdg), 0) as total_ntb_1'), // Rec Gudang = ex_rec_gdg
            ])
                ->leftJoin('branches', 'sp_branches.branch_code', '=', 'branches.branch_code')
                ->where('sp_branches.active_data', 'yes');

            // Filter by cutoff_datas if active
            if ($activeCutoff) {
                if ($startDate !== null) {
                    $spBranchesQuery->whereBetween('sp_branches.trans_date', [$startDate, $endDate]);
                } else {
                    $spBranchesQuery->where('sp_branches.trans_date', '<=', $endDate);
                }
            }
            $spBranchesQuery->when($filterBookCode !== '', function ($q) use ($filterBookCode) {
                return $q->where('sp_branches.book_code', $filterBookCode);
            });

            $spBranches = $spBranchesQuery
                ->when($userBranchCode, function ($query) use ($userBranchCode) {
                    return $query->where('sp_branches.branch_code', $userBranchCode);
                })
                ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                    return $query->whereIn('sp_branches.branch_code', $filteredBranchCodes);
                })
                ->groupBy('sp_branches.branch_code', 'branches.branch_name')
                ->get();

            // Per-book data untuk THD SP & THD Target (LEBIH/KURANG per buku lalu dijumlah)
            $branchBooksQuery = SpBranch::select([
                'sp_branches.branch_code',
                'sp_branches.book_code',
                DB::raw('SUM(sp_branches.ex_stock) as stok'),
                DB::raw('SUM(sp_branches.ex_sp) as sp'),
            ])
                ->where('sp_branches.active_data', 'yes')
                ->whereNotNull('sp_branches.book_code');

            if ($activeCutoff) {
                if ($startDate !== null) {
                    $branchBooksQuery->whereBetween('sp_branches.trans_date', [$startDate, $endDate]);
                } else {
                    $branchBooksQuery->where('sp_branches.trans_date', '<=', $endDate);
                }
            }
            $branchBooksQuery->when($filterBookCode !== '', function ($q) use ($filterBookCode) {
                return $q->where('sp_branches.book_code', $filterBookCode);
            });
            $branchBooksQuery->when($userBranchCode, function ($q) use ($userBranchCode) {
                return $q->where('sp_branches.branch_code', $userBranchCode);
            });
            $branchBooksQuery->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->whereIn('sp_branches.branch_code', $filteredBranchCodes);
            });

            $branchBooks = $branchBooksQuery
                ->groupBy('sp_branches.branch_code', 'sp_branches.book_code')
                ->get();

            // Target per book per branch
            $bookTargetsQuery = Target::select([
                'targets.branch_code',
                'targets.book_code',
                DB::raw('SUM(targets.exemplar) as target'),
            ])
                ->join('periods', 'targets.period_code', '=', 'periods.period_code')
                ->whereNotNull('targets.book_code');

            if ($activeCutoff) {
                if ($startDate !== null) {
                    $bookTargetsQuery->where(function ($q) use ($startDate, $endDate) {
                        $q->where('periods.from_date', '<=', $endDate)
                            ->where('periods.to_date', '>=', $startDate);
                    });
                } else {
                    $bookTargetsQuery->where('periods.to_date', '<=', $endDate);
                }
            } else {
                $bookTargetsQuery->where(function ($q) use ($year) {
                    $q->whereYear('periods.from_date', $year)
                        ->orWhereYear('periods.to_date', $year);
                });
            }

            $bookTargetsQuery->when($filterBookCode !== '', function ($q) use ($filterBookCode) {
                return $q->where('targets.book_code', $filterBookCode);
            });
            $bookTargetsQuery->when($userBranchCode, function ($q) use ($userBranchCode) {
                return $q->where('targets.branch_code', $userBranchCode);
            });
            $bookTargetsQuery->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->whereIn('targets.branch_code', $filteredBranchCodes);
            });

            $bookTargets = $bookTargetsQuery
                ->groupBy('targets.branch_code', 'targets.book_code')
                ->get();

            $targetByBranchBook = $bookTargets->groupBy('branch_code')->map(function ($items) {
                return $items->keyBy('book_code');
            });

            // Hitung THD SP & THD Target per branch (per buku: LEBIH = stock - sp/target jika > 0, KURANG = sp/target - stock jika < 0)
            $thdSpByBranch = [];
            $thdTargetByBranch = [];
            foreach ($branchBooks->groupBy('branch_code') as $branchCode => $books) {
                $thdSpLebih = 0;
                $thdSpKurang = 0;
                $thdTargetLebih = 0;
                $thdTargetKurang = 0;
                foreach ($books as $b) {
                    $stok = (float)($b->stok ?? 0);
                    $sp = (float)($b->sp ?? 0);
                    $diffSp = $stok - $sp;
                    if ($diffSp > 0) {
                        $thdSpLebih += $diffSp;
                    } else {
                        $thdSpKurang += abs($diffSp);
                    }
                    $target = (float)($targetByBranchBook->get($branchCode)?->get($b->book_code)?->target ?? 0);
                    $diffTarget = $stok - $target;
                    if ($diffTarget > 0) {
                        $thdTargetLebih += $diffTarget;
                    } else {
                        $thdTargetKurang += abs($diffTarget);
                    }
                }
                $thdSpByBranch[$branchCode] = ['lebih' => $thdSpLebih, 'kurang' => $thdSpKurang];
                $thdTargetByBranch[$branchCode] = ['lebih' => $thdTargetLebih, 'kurang' => $thdTargetKurang];
            }

            // Merge Target dan NPPB Central ke spBranches
            foreach ($spBranches as $branch) {
                $branch->target = $targets->get($branch->branch_code)->total_target ?? 0;

                $nppbData = $nppbCentrals->get($branch->branch_code);
                $branch->nppb_koli = $nppbData->total_koli ?? 0;
                $branch->nppb_pls = $nppbData->total_pls ?? 0;
                $branch->nppb_exp = $nppbData->total_exp ?? 0;

                $thdSp = $thdSpByBranch[$branch->branch_code] ?? ['lebih' => 0, 'kurang' => 0];
                $thdTarget = $thdTargetByBranch[$branch->branch_code] ?? ['lebih' => 0, 'kurang' => 0];
                $branch->thd_sp_lebih = $thdSp['lebih'];
                $branch->thd_sp_kurang = $thdSp['kurang'];
                $branch->thd_target_lebih = $thdTarget['lebih'];
                $branch->thd_target_kurang = $thdTarget['kurang'];
            }

            // Calculate totals for NASIONAL
            // NKB dari pusat = sum dari sp_branches.nkb_1 (total_nkb)
            $totalNkbPusat = $spBranches->sum('total_nkb');

            $nasional = [
                'realisasi_2024' => 0,
                'realisasi_2025' => 0,
                'target' => $spBranches->sum('target'),
                'total_sp' => $spBranches->sum('total_sp'),
                'total_faktur' => $spBranches->sum('total_faktur'),
                'total_ret' => $spBranches->sum('total_ret'),
                'netto' => $spBranches->sum('netto'),
                'sisa_sp' => $spBranches->sum('sisa_sp'),
                'total_nk' => $spBranches->sum('total_nk'),
                'total_nt' => $spBranches->sum('total_nt'),
                'total_nkb' => $totalNkbPusat,
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

            // Group branches by area (extract area from branch_name)
            // Assuming area is part of branch name or we need to add area field
            $areas = [];
            foreach ($spBranches as $branch) {
                // Extract area from branch_name (e.g., "CAB. PALEMBANG" -> "AREA SUMATERA UTARA")
                // For now, we'll group by first part of branch name or create area mapping
                $areaName = $this->extractAreaFromBranch($branch->branch_name);

                if (!isset($areas[$areaName])) {
                    $areas[$areaName] = [
                        'name' => $areaName,
                        'branches' => [],
                        'totals' => [
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
                        ]
                    ];
                }

                $areas[$areaName]['branches'][] = $branch;
                $areas[$areaName]['totals']['target'] += $branch->target ?? 0;
                $areas[$areaName]['totals']['total_sp'] += $branch->total_sp ?? 0;
                $areas[$areaName]['totals']['total_faktur'] += $branch->total_faktur ?? 0;
                $areas[$areaName]['totals']['total_ret'] += $branch->total_ret ?? 0;
                $areas[$areaName]['totals']['netto'] += $branch->netto ?? 0;
                $areas[$areaName]['totals']['sisa_sp'] += $branch->sisa_sp ?? 0;
                $areas[$areaName]['totals']['total_nk'] += $branch->total_nk ?? 0;
                $areas[$areaName]['totals']['total_nt'] += $branch->total_nt ?? 0;
                $areas[$areaName]['totals']['total_nkb'] += $branch->total_nkb ?? 0;
                $areas[$areaName]['totals']['total_stok_cabang'] += $branch->total_stok_cabang ?? 0;
                $areas[$areaName]['totals']['total_sp_1'] += $branch->total_sp_1 ?? 0;
                $areas[$areaName]['totals']['total_faktur_1'] += $branch->total_faktur_1 ?? 0;
                $areas[$areaName]['totals']['total_ret_1'] += $branch->total_ret_1 ?? 0;
                $areas[$areaName]['totals']['total_nt_1'] += $branch->total_nt_1 ?? 0;
                $areas[$areaName]['totals']['total_nkb_1'] += $branch->total_nkb_1 ?? 0;
                $areas[$areaName]['totals']['total_ntb_1'] += $branch->total_ntb_1 ?? 0;
                $areas[$areaName]['totals']['nppb_koli'] += $branch->nppb_koli ?? 0;
                $areas[$areaName]['totals']['nppb_pls'] += $branch->nppb_pls ?? 0;
                $areas[$areaName]['totals']['nppb_exp'] += $branch->nppb_exp ?? 0;
                $areas[$areaName]['totals']['thd_target_lebih'] += $branch->thd_target_lebih ?? 0;
                $areas[$areaName]['totals']['thd_target_kurang'] += $branch->thd_target_kurang ?? 0;
                $areas[$areaName]['totals']['thd_sp_lebih'] += $branch->thd_sp_lebih ?? 0;
                $areas[$areaName]['totals']['thd_sp_kurang'] += $branch->thd_sp_kurang ?? 0;
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

            return view($this->callbackfolder . '.rekapitulasi.index', $data);
        } catch (\Throwable $e) {
            Log::error('RekapController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
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
