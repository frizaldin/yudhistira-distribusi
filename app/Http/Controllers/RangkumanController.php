<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Branch;
use App\Models\CentralStock;
use App\Models\Target;
use App\Models\Periode;
use App\Models\SpBranch;
use App\Models\NppbCentral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RangkumanController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/rangkuman');
        $this->title = 'Rangkuman';

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
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        $scopeBranch = function ($q) use ($filteredBranchCodes) {
            if ($filteredBranchCodes !== null) {
                $q->whereIn('branch_code', $filteredBranchCodes);
            }
        };

        // Master Data
        $totalProduk = Product::count();
        $totalCabang = Branch::query()->when($filteredBranchCodes !== null, $scopeBranch)->count();
        $totalStockPusat = CentralStock::query()->when($filteredBranchCodes !== null, $scopeBranch)->sum('exemplar') ?? 0;
        $totalTarget = Target::query()->when($filteredBranchCodes !== null, $scopeBranch)->sum('exemplar') ?? 0;
        $totalPeriode = Periode::count();

        // Pesanan (SpBranch) - hanya data aktif
        $spBranchBase = SpBranch::where('active_data', 'yes')->when($filteredBranchCodes !== null, $scopeBranch);
        $totalPesanan = (clone $spBranchBase)->count();
        $totalSp = SpBranch::where('active_data', 'yes')->when($filteredBranchCodes !== null, $scopeBranch)->sum('ex_sp') ?? 0;
        $totalFaktur = SpBranch::where('active_data', 'yes')->when($filteredBranchCodes !== null, $scopeBranch)->sum('ex_ftr') ?? 0;
        $totalRet = SpBranch::where('active_data', 'yes')->when($filteredBranchCodes !== null, $scopeBranch)->sum('ex_ret') ?? 0;
        $totalStockCabang = SpBranch::where('active_data', 'yes')->when($filteredBranchCodes !== null, $scopeBranch)->sum('ex_stock') ?? 0;

        // Rencana Kirim (NppbCentral)
        $nppbBase = NppbCentral::query()->when($filteredBranchCodes !== null, $scopeBranch);
        $totalNppb = (clone $nppbBase)->count();
        $totalNppbKoli = NppbCentral::query()->when($filteredBranchCodes !== null, $scopeBranch)->sum('koli') ?? 0;
        $totalNppbPls = NppbCentral::query()->when($filteredBranchCodes !== null, $scopeBranch)->sum('pls') ?? 0;
        $totalNppbExp = NppbCentral::query()->when($filteredBranchCodes !== null, $scopeBranch)->sum('exp') ?? 0;

        // Statistik tambahan
        $totalBranchesWithTarget = Target::query()->when($filteredBranchCodes !== null, $scopeBranch)->distinct('branch_code')->count('branch_code');
        $totalBranchesWithPesanan = SpBranch::where('active_data', 'yes')->when($filteredBranchCodes !== null, $scopeBranch)->distinct('branch_code')->count('branch_code');
        $totalBranchesWithNppb = NppbCentral::query()->when($filteredBranchCodes !== null, $scopeBranch)->distinct('branch_code')->count('branch_code');
        $totalProductsWithTarget = Target::query()->when($filteredBranchCodes !== null, $scopeBranch)->distinct('book_code')->whereNotNull('book_code')->count('book_code');
        $totalProductsWithStock = CentralStock::query()->when($filteredBranchCodes !== null, $scopeBranch)->distinct('book_code')->whereNotNull('book_code')->count('book_code');

        // Ranking 10 Cabang dengan SP Terbanyak - hanya data aktif
        $topBranchesBySpQuery = SpBranch::select([
            'sp_branches.branch_code',
            'branches.branch_name',
            DB::raw('SUM(sp_branches.ex_sp) as total_sp'),
            DB::raw('SUM(sp_branches.ex_ftr) as total_faktur'),
        ])
            ->leftJoin('branches', 'sp_branches.branch_code', '=', 'branches.branch_code')
            ->where('sp_branches.active_data', 'yes');
        if ($filteredBranchCodes !== null) {
            $topBranchesBySpQuery->whereIn('sp_branches.branch_code', $filteredBranchCodes);
        }
        $topBranchesBySp = $topBranchesBySpQuery
            ->groupBy('sp_branches.branch_code', 'branches.branch_name')
            ->orderByDesc('total_sp')
            ->limit(10)
            ->get();

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            // Master Data
            'totalProduk' => $totalProduk,
            'totalCabang' => $totalCabang,
            'totalStockPusat' => $totalStockPusat,
            'totalTarget' => $totalTarget,
            'totalPeriode' => $totalPeriode,
            // Pesanan
            'totalPesanan' => $totalPesanan,
            'totalSp' => $totalSp,
            'totalFaktur' => $totalFaktur,
            'totalRet' => $totalRet,
            'totalStockCabang' => $totalStockCabang,
            // Rencana Kirim
            'totalNppb' => $totalNppb,
            'totalNppbKoli' => $totalNppbKoli,
            'totalNppbPls' => $totalNppbPls,
            'totalNppbExp' => $totalNppbExp,
            // Statistik
            'totalBranchesWithTarget' => $totalBranchesWithTarget,
            'totalBranchesWithPesanan' => $totalBranchesWithPesanan,
            'totalBranchesWithNppb' => $totalBranchesWithNppb,
            'totalProductsWithTarget' => $totalProductsWithTarget,
            'totalProductsWithStock' => $totalProductsWithStock,
            // Ranking
            'topBranchesBySp' => $topBranchesBySp,
        ];

        return view($this->callbackfolder . '.rangkuman.index', $data);
    }
}
