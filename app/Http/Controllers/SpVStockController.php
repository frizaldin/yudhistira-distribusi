<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\CentralStock;
use App\Models\SpBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SpVStockController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/sp_v_stock');
        $this->title = 'Sp Terhadap Stok';

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
        // Get all products
        $productsQuery = Product::select('book_code', 'book_title')
            ->orderBy('book_code');

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $productsQuery->where(function ($query) use ($search) {
                $query->where('book_code', 'like', '%' . $search . '%')
                    ->orWhere('book_title', 'like', '%' . $search . '%');
            });
        }

        $products = $productsQuery->get();

        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        // Get stock pusat per book_code (SUM dari CentralStock)
        $centralStocksQuery = CentralStock::select([
            'book_code',
            DB::raw('SUM(exemplar) as total_stock_pusat')
        ])->groupBy('book_code');
        if ($filteredBranchCodes !== null) {
            $centralStocksQuery->whereIn('branch_code', $filteredBranchCodes);
        }
        $centralStocks = $centralStocksQuery->get()->keyBy('book_code');

        // Get SP per book_code (SUM dari SpBranch ex_sp, hanya active_data = 'yes')
        $spDataQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_sp) as total_sp')
        ])
            ->where('active_data', 'yes')
            ->groupBy('book_code');
        if ($filteredBranchCodes !== null) {
            $spDataQuery->whereIn('branch_code', $filteredBranchCodes);
        }
        $spData = $spDataQuery->get()->keyBy('book_code');

        // Calculate percentage and status for each product
        $data = [];
        foreach ($products as $product) {
            $stockPusat = $centralStocks->get($product->book_code)->total_stock_pusat ?? 0;
            $sp = $spData->get($product->book_code)->total_sp ?? 0;

            // Persentase = (Stock - SP) / SP * 100
            // Jika Stock < SP → minus (kurang), Stock > SP → plus (lebih), Stock = SP → 0%
            $persentase = 0;
            if ($sp > 0) {
                $persentase = (($stockPusat - $sp) / $sp) * 100;
            } else {
                // SP = 0: tidak ada pesanan, kalau ada stock anggap 100%
                $persentase = $stockPusat > 0 ? 100 : 0;
            }

            // Determine status
            // Logika: Kurang = < 70% (termasuk minus), Cukup = == 70%, Lebih = > 70%
            $status = 'cukup';
            $statusClass = 'warning';
            if ($persentase < 70) {
                $status = 'kurang';
                $statusClass = 'danger';
            } elseif ($persentase == 70) {
                $status = 'cukup';
                $statusClass = 'warning';
            } else {
                // persentase > 70
                $status = 'lebih';
                $statusClass = 'success';
            }

            $data[] = [
                'book_code' => $product->book_code,
                'book_name' => $product->book_title,
                'stock_pusat' => $stockPusat,
                'sp' => $sp,
                'persentase' => round($persentase, 2),
                'status' => $status,
                'status_class' => $statusClass,
            ];
        }

        // Sort by SP descending
        usort($data, function ($a, $b) {
            return $b['sp'] <=> $a['sp'];
        });

        // Pagination
        $perPage = $request->get('per_page', 50);
        // Validate per_page value (only allow 50, 100, 250)
        if (!in_array($perPage, [50, 100, 250])) {
            $perPage = 50;
        }
        $currentPage = $request->get('page', 1);
        $total = count($data);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedData = array_slice($data, $offset, $perPage);
        $lastPage = ceil($total / $perPage);

        // Build query parameters for pagination
        $queryParams = $request->query();
        if (isset($queryParams['page'])) {
            unset($queryParams['page']);
        }
        
        $result = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedData,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $queryParams
            ]
        );

        return view($this->callbackfolder . '.sp-v-stock.index', [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'data' => $result,
            'search' => $request->get('search', ''),
            'perPage' => $perPage,
        ]);
    }
}
