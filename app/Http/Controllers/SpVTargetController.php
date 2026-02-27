<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Target;
use App\Models\SpBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SpVTargetController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/sp_v_target');
        $this->title = 'Sp Terhadap Target';

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
        $productsQuery = Product::select('book_code', 'book_title')
            ->orderBy('book_code');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $productsQuery->where(function ($query) use ($search) {
                $query->where('book_code', 'like', '%' . $search . '%')
                    ->orWhere('book_title', 'like', '%' . $search . '%');
            });
        }

        $products = $productsQuery->get();

        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $targetDataQuery = Target::select([
            'book_code',
            DB::raw('SUM(exemplar) as total_target'),
        ])->groupBy('book_code');
        if ($filteredBranchCodes !== null) {
            $targetDataQuery->whereIn('branch_code', $filteredBranchCodes);
        }
        $targetData = $targetDataQuery->get()->keyBy('book_code');

        $spDataQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_sp) as total_sp'),
        ])
            ->where('active_data', 'yes')
            ->groupBy('book_code');
        if ($filteredBranchCodes !== null) {
            $spDataQuery->whereIn('branch_code', $filteredBranchCodes);
        }
        $spData = $spDataQuery->get()->keyBy('book_code');

        $data = [];
        foreach ($products as $product) {
            $target = $targetData->get($product->book_code)?->total_target ?? 0;
            $sp = $spData->get($product->book_code)?->total_sp ?? 0;

            // SP vs Target: terpenuhi = (SP/Target)*100%, belum terpenuhi = 100% - terpenuhi (min 0)
            $persentaseTerpenuhi = 0;
            $persentaseBelumTerpenuhi = 0;
            if ($target > 0) {
                $persentaseTerpenuhi = ($sp / $target) * 100;
                $persentaseBelumTerpenuhi = max(0, 100 - $persentaseTerpenuhi);
            } else {
                $persentaseTerpenuhi = $sp > 0 ? 100 : 0;
            }

            if ($persentaseTerpenuhi < 100) {
                $status = 'kurang';
                $statusClass = 'danger';
            } elseif ($persentaseTerpenuhi > 100) {
                $status = 'lebih';
                $statusClass = 'success';
            } else {
                $status = 'cukup';
                $statusClass = 'warning';
            }

            $data[] = [
                'book_code' => $product->book_code,
                'book_name' => $product->book_title,
                'target' => $target,
                'sp' => $sp,
                'persentase_terpenuhi' => round($persentaseTerpenuhi, 2),
                'persentase_belum_terpenuhi' => round($persentaseBelumTerpenuhi, 2),
                'status' => $status,
                'status_class' => $statusClass,
            ];
        }

        usort($data, function ($a, $b) {
            return $b['sp'] <=> $a['sp'];
        });

        $perPage = $request->get('per_page', 50);
        if (!in_array($perPage, [50, 100, 250])) {
            $perPage = 50;
        }
        $currentPage = $request->get('page', 1);
        $total = count($data);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedData = array_slice($data, $offset, $perPage);

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
                'query' => $queryParams,
            ]
        );

        return view($this->callbackfolder . '.sp-v-target.index', [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'data' => $result,
            'search' => $request->get('search', ''),
            'perPage' => $perPage,
        ]);
    }
}
