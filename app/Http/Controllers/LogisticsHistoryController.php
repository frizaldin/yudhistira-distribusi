<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LogisticsHistoryController extends Controller
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
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $branchesQuery = Branch::query()->orderBy('branch_name');
        if ($filteredBranchCodes !== null) {
            $branchesQuery->whereIn('branch_code', $filteredBranchCodes);
        }
        $branches = $branchesQuery->get(['branch_code', 'branch_name']);

        $defaults = [
            'book_code' => $request->get('book_code', ''),
            'branch_code' => $request->get('branch_code', ''),
            'recipient_code' => $request->get('recipient_code', ''),
            'date_from' => $request->get('date_from', Carbon::now()->startOfMonth()->toDateString()),
            'date_to' => $request->get('date_to', Carbon::now()->toDateString()),
        ];

        $mutasiRows = collect();
        $nkbRows = collect();
        $returRows = collect();
        $product = null;
        $searched = false;
        $errors = null;
        $branchNames = [];

        if ($request->filled('book_code')) {
            $searched = true;
            $validator = Validator::make(
                array_merge($defaults, $request->only(['book_code', 'branch_code', 'recipient_code', 'date_from', 'date_to'])),
                [
                    'book_code' => ['required', 'string', 'max:100', 'exists:books,book_code'],
                    'branch_code' => ['nullable', 'string', 'max:100', 'exists:branches,branch_code'],
                    'recipient_code' => ['nullable', 'string', 'max:100', 'exists:branches,branch_code'],
                    'date_from' => ['required', 'date'],
                    'date_to' => ['required', 'date', 'after_or_equal:date_from'],
                ]
            );

            if ($validator->fails()) {
                $errors = $validator->errors();
                $defaults = array_merge($defaults, $request->only(['book_code', 'branch_code', 'recipient_code', 'date_from', 'date_to']));
            } else {
                $data = $validator->validated();
                $bookCode = $data['book_code'];
                $branchCode = $data['branch_code'] ?: null;
                $recipientCode = $data['recipient_code'] ?: null;
                $dateFrom = Carbon::parse($data['date_from'])->startOfDay();
                $dateTo = Carbon::parse($data['date_to'])->endOfDay();

                if ($filteredBranchCodes !== null) {
                    if ($branchCode && ! in_array($branchCode, $filteredBranchCodes, true)) {
                        $branchCode = null;
                    }
                    if ($recipientCode && ! in_array($recipientCode, $filteredBranchCodes, true)) {
                        $recipientCode = null;
                    }
                }

                $product = Product::where('book_code', $bookCode)->first();

                $pg = DB::connection('pgsql');

                $mutasiRows = $pg->table('m_mutasi_buku as m')
                    ->join('d_mutasi_buku as d', 'm.mutation_code', '=', 'd.mutation_code')
                    ->where('d.book_code', $bookCode)
                    ->whereRaw('COALESCE(m.send_date, m.receive_date) >= ?', [$dateFrom])
                    ->whereRaw('COALESCE(m.send_date, m.receive_date) <= ?', [$dateTo])
                    ->when($branchCode, fn ($q) => $q->where('m.branch_code', $branchCode))
                    ->when($recipientCode, fn ($q) => $q->where('m.receiver', $recipientCode))
                    ->orderByDesc(DB::raw('COALESCE(m.send_date, m.receive_date)'))
                    ->orderByDesc('m.mutation_code')
                    ->select([
                        'm.mutation_code',
                        'm.jo_code',
                        'm.publish_code',
                        'm.send_date',
                        'm.receive_date',
                        'm.receiver',
                        'm.branch_code as master_branch_code',
                        'm.info',
                        'd.koli',
                        'd.exemplar',
                        'd.total_exemplar',
                        'd.branch_code as detail_branch_code',
                    ])
                    ->get();

                $nkbRows = $pg->table('m_kirim_cabang as m')
                    ->join('d_kirim_cabang as d', 'm.nota_kirim_cab', '=', 'd.nota_kirim_cab')
                    ->where('d.book_code', $bookCode)
                    ->whereBetween('m.send_date', [$dateFrom, $dateTo])
                    ->when($branchCode, fn ($q) => $q->where('m.branch_sender', $branchCode))
                    ->when($recipientCode, fn ($q) => $q->where('m.branch_code', $recipientCode))
                    ->orderByDesc('m.send_date')
                    ->orderByDesc('m.nota_kirim_cab')
                    ->select([
                        'm.nota_kirim_cab',
                        'm.branch_sender',
                        'm.branch_code as recipient_branch_code',
                        'm.send_date',
                        'm.info',
                        'm.nppb',
                        'm.sj',
                        'd.koli',
                        'd.exemplar',
                        'd.total_exemplar',
                    ])
                    ->get();

                $returRows = $pg->table('m_terima_buku as m')
                    ->join('d_terima_buku as d', 'm.receive_code', '=', 'd.receive_code')
                    ->where('d.book_code', $bookCode)
                    ->whereRaw('COALESCE(m.retur_date, m.send_date) >= ?', [$dateFrom])
                    ->whereRaw('COALESCE(m.retur_date, m.send_date) <= ?', [$dateTo])
                    ->when($branchCode, fn ($q) => $q->where('m.branch_sender', $branchCode))
                    ->when($recipientCode, fn ($q) => $q->where('m.branch_code', $recipientCode))
                    ->orderByDesc(DB::raw('COALESCE(m.retur_date, m.send_date)'))
                    ->orderByDesc('m.receive_code')
                    ->select([
                        'm.nota_kirim_cab',
                        'm.receive_code',
                        'm.branch_sender',
                        'm.branch_code as recipient_branch_code',
                        'm.retur_date',
                        'm.send_date',
                        'm.info',
                        'd.koli',
                        'd.exemplar',
                        'd.total_exemplar',
                    ])
                    ->get();

                $codes = collect();
                foreach ($mutasiRows as $r) {
                    $codes->push($r->master_branch_code, $r->detail_branch_code, $r->receiver);
                }
                foreach ($nkbRows as $r) {
                    $codes->push($r->branch_sender, $r->recipient_branch_code);
                }
                foreach ($returRows as $r) {
                    $codes->push($r->branch_sender, $r->recipient_branch_code);
                }
                $codes = $codes->filter(fn ($c) => $c !== null && $c !== '')->unique()->values();
                if ($codes->isNotEmpty()) {
                    $branchNames = Branch::query()
                        ->whereIn('branch_code', $codes)
                        ->pluck('branch_name', 'branch_code')
                        ->all();
                }

                $defaults = [
                    'book_code' => $bookCode,
                    'branch_code' => $branchCode ?? '',
                    'recipient_code' => $recipientCode ?? '',
                    'date_from' => $data['date_from'],
                    'date_to' => $data['date_to'],
                ];
            }
        }

        return view($this->callbackfolder . '.logistics-history.index', [
            'branches' => $branches,
            'filters' => $defaults,
            'product' => $product,
            'branchNames' => $branchNames,
            'mutasiRows' => $mutasiRows,
            'nkbRows' => $nkbRows,
            'returRows' => $returRows,
            'searched' => $searched,
            'validationErrors' => $errors,
        ]);
    }
}
