<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\MoveWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MoveWarehouseController extends Controller
{
    protected $callbackfolder;

    public function __construct()
    {
        if (Auth::check()) {
            $role = Auth::user()->authority_id ?? 1;
            $this->callbackfolder = match ($role) {
                1 => 'superadmin',
                2 => 'branch',
                default => 'superadmin',
            };
        } else {
            $this->callbackfolder = 'superadmin';
        }
    }

    public function index(Request $request)
    {
        $query = MoveWarehouse::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                return $q->where('move_code', 'like', '%' . $s . '%');
            })
            ->orderBy('move_code', 'desc');

        $perPage = 20;
        $items = $query->paginate($perPage)->withQueryString();

        return view($this->callbackfolder . '.move_warehouse.index', [
            'items' => $items,
            'queryString' => $request->query(),
        ]);
    }

    public function create()
    {
        $branches  = Branch::orderBy('branch_name')->get(['branch_code', 'branch_name']);
        $employees = Employee::orderBy('empl_name')
            ->get(['empl_code', 'empl_name']);

        return view($this->callbackfolder . '.move_warehouse.create', [
            'branches'  => $branches,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'move_code' => 'required|string|unique:m_pindah_gudang,move_code',
            'branch_code' => 'nullable|string',
            'info' => 'nullable|string',
            'mova_date' => 'nullable|date',
            'officer' => 'nullable|string',
            'whouse_head' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.book_code' => 'required|string',
            'items.*.koli' => 'required|numeric|min:0',
            'items.*.volume' => 'required|numeric|min:0',
            'items.*.exemplar' => 'required|numeric|min:0',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $moveWarehouse = MoveWarehouse::create([
                'move_code' => $request->move_code,
                'branch_code' => $request->branch_code,
                'info' => $request->info,
                'mova_date' => $request->mova_date,
                'officer' => $request->officer,
                'whouse_head' => $request->whouse_head,
                'user_id' => Auth::user()->username ?? 'system',
                'status' => 0,
                'printed' => 0,
            ]);

            foreach ($request->items as $item) {
                $koli = $item['koli'];
                $volume = $item['volume'];
                $exemplar = $item['exemplar'];
                $total_exemplar = ($koli * $volume) + $exemplar;

                $moveWarehouse->items()->create([
                    'book_code' => $item['book_code'],
                    'branch_code' => $request->branch_code,
                    'koli' => $koli,
                    'volume' => $volume,
                    'exemplar' => $exemplar,
                    'total_exemplar' => $total_exemplar,
                ]);

                $centralStock = \App\Models\CentralStock::where('branch_code', $request->branch_code)
                    ->where('book_code', $item['book_code'])
                    ->first();

                if ($centralStock) {
                    $centralStock->decrement('exemplar', $total_exemplar);
                } else {
                    \App\Models\CentralStock::create([
                        'branch_code' => $request->branch_code,
                        'book_code' => $item['book_code'],
                        'exemplar' => -$total_exemplar,
                    ]);
                }
            }
        });

        return redirect()->route('move_warehouse.index')->with('success', 'Data Gudang Isolasi berhasil ditambahkan.');
    }

    public function show($move_code)
    {
        $data = MoveWarehouse::with(['items'])
            ->where('move_code', $move_code)
            ->firstOrFail();

        return view($this->callbackfolder . '.move_warehouse.show', [
            'data' => $data,
        ]);
    }

    public function destroy($move_code)
    {
        $data = MoveWarehouse::where('move_code', $move_code)->firstOrFail();

        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            foreach ($data->items as $item) {
                $centralStock = \App\Models\CentralStock::where('branch_code', $item->branch_code)
                    ->where('book_code', $item->book_code)
                    ->first();

                if ($centralStock) {
                    $centralStock->increment('exemplar', $item->total_exemplar);
                } else {
                    \App\Models\CentralStock::create([
                        'branch_code' => $item->branch_code,
                        'book_code' => $item->book_code,
                        'exemplar' => $item->total_exemplar,
                    ]);
                }
            }

            $data->items()->delete();
            $data->delete();
        });

        return redirect()->route('move_warehouse.index')->with('success', 'Data Gudang Isolasi berhasil dihapus.');
    }
}
