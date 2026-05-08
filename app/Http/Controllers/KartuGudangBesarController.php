<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class KartuGudangBesarController extends Controller
{
    protected string $callbackfolder;

    public function __construct()
    {
        if (Auth::check()) {
            $role = Auth::user()->authority_id ?? 1;
            $this->callbackfolder = match ($role) {
                2 => 'branch',
                default => 'superadmin',
            };
        } else {
            $this->callbackfolder = 'superadmin';
        }
    }

    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->search_book_code, function ($query, $code) {
                return $query->where('book_code', 'like', '%' . $code . '%');
            })
            ->when($request->search, function ($query, $search) {
                return $query->where('book_title', 'like', '%' . $search . '%');
            })
            ->orderBy('book_code')
            ->paginate(15);

        return view($this->callbackfolder . '.kartu_gudang_besar.index', [
            'products' => $products,
        ]);
    }

    public function show(Request $request, $book_code)
    {
        $product = Product::where('book_code', $book_code)->firstOrFail();

        $dateFrom = $request->get('date_from', Carbon::now()->subYear()->toDateString());
        $dateTo = $request->get('date_to', Carbon::now()->toDateString());

        $parsedDateFrom = Carbon::parse($dateFrom)->startOfDay();
        $parsedDateTo = Carbon::parse($dateTo)->endOfDay();

        $pg = DB::connection('pgsql');

        // Mutasi
        $mutasiRows = $pg->table('m_mutasi_buku as m')
            ->join('d_mutasi_buku as d', 'm.mutation_code', '=', 'd.mutation_code')
            ->where('d.book_code', $book_code)
            ->whereRaw('COALESCE(m.send_date, m.receive_date) >= ?', [$parsedDateFrom])
            ->whereRaw('COALESCE(m.send_date, m.receive_date) <= ?', [$parsedDateTo])
            ->select([
                DB::raw("'Mutasi' as type"),
                'm.mutation_code as ref_no',
                DB::raw('COALESCE(m.send_date, m.receive_date) as action_date'),
                'm.branch_code as branch_sender',
                'm.receiver as branch_receiver',
                'd.total_exemplar as qty',
                'm.info'
            ])
            ->get();

        // NKB
        $nkbRows = $pg->table('m_kirim_cabang as m')
            ->join('d_kirim_cabang as d', 'm.nota_kirim_cab', '=', 'd.nota_kirim_cab')
            ->where('d.book_code', $book_code)
            ->whereBetween('m.send_date', [$parsedDateFrom, $parsedDateTo])
            ->select([
                DB::raw("'NKB' as type"),
                'm.nota_kirim_cab as ref_no',
                'm.send_date as action_date',
                'm.branch_sender as branch_sender',
                'm.branch_code as branch_receiver',
                'd.total_exemplar as qty',
                'm.info'
            ])
            ->get();

        // NTB
        $ntbRows = $pg->table('m_terima_buku as m')
            ->join('d_terima_buku as d', 'm.receive_code', '=', 'd.receive_code')
            ->where('d.book_code', $book_code)
            ->whereRaw('COALESCE(m.retur_date, m.send_date) >= ?', [$parsedDateFrom])
            ->whereRaw('COALESCE(m.retur_date, m.send_date) <= ?', [$parsedDateTo])
            ->select([
                DB::raw("'NTB' as type"),
                'm.receive_code as ref_no',
                DB::raw('COALESCE(m.retur_date, m.send_date) as action_date'),
                'm.branch_sender as branch_sender',
                'm.branch_code as branch_receiver',
                'd.total_exemplar as qty',
                'm.info'
            ])
            ->get();

        // Combine and sort
        $history = collect()
            ->merge($mutasiRows)
            ->merge($nkbRows)
            ->merge($ntbRows)
            ->sortByDesc('action_date')
            ->values();

        // Get branch names mapping
        $branchCodes = $history->pluck('branch_sender')
            ->merge($history->pluck('branch_receiver'))
            ->filter()
            ->unique();
            
        $branchNames = Branch::whereIn('branch_code', $branchCodes)
            ->pluck('branch_name', 'branch_code')
            ->all();

        return view($this->callbackfolder . '.kartu_gudang_besar.show', [
            'product' => $product,
            'history' => $history,
            'branchNames' => $branchNames,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]
        ]);
    }
}
