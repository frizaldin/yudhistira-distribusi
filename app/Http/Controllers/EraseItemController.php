<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\EraseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EraseItemController extends Controller
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
        $query = EraseItem::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                return $q->where('erase_code', 'like', '%' . $s . '%');
            })
            ->orderBy('erase_code', 'desc');

        $perPage = 20;
        $items = $query->paginate($perPage)->withQueryString();

        return view($this->callbackfolder . '.erase_item.index', [
            'items' => $items,
            'queryString' => $request->query(),
        ]);
    }

    public function create()
    {
        $branches = Branch::orderBy('branch_name')->get(['branch_code', 'branch_name']);

        return view($this->callbackfolder . '.erase_item.create', [
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'erase_code' => 'required|string|unique:m_hapus_barang,erase_code',
            'branch_code' => 'nullable|string',
            'edit_date' => 'nullable|date',
            'empl_code' => 'nullable|string',
            'info' => 'nullable|string',
            'trans_date' => 'nullable|date',
            'whouse_head' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.book_code' => 'required|string',
            'items.*.koli' => 'required|numeric|min:0',
            'items.*.volume' => 'required|numeric|min:0',
            'items.*.exemplar' => 'required|numeric|min:0',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $eraseItem = EraseItem::create([
                'erase_code' => $request->erase_code,
                'branch_code' => $request->branch_code,
                'edit_date' => $request->edit_date,
                'empl_code' => $request->empl_code,
                'info' => $request->info,
                'trans_date' => $request->trans_date,
                'whouse_head' => $request->whouse_head,
                'user_id'     => Auth::user()->username ?? Auth::id() ?? 'system',
                'status' => 0,
                'printed' => 0,
            ]);

            foreach ($request->items as $item) {
                $koli = $item['koli'];
                $volume = $item['volume'];
                $exemplar = $item['exemplar'];
                $total_exemplar = ($koli * $volume) + $exemplar;

                $eraseItem->items()->create([
                    'book_code' => $item['book_code'],
                    'branch_code' => $request->branch_code,
                    'koli' => $koli,
                    'volume' => $volume,
                    'exemplar' => $exemplar,
                    'total_exemplar' => $total_exemplar,
                ]);
            }
        });

        return redirect()->route('erase_item.index')->with('success', 'Data Nota Penghapusan berhasil ditambahkan.');
    }

    public function show($erase_code)
    {
        $data = EraseItem::with(['items'])
            ->where('erase_code', $erase_code)
            ->firstOrFail();

        return view($this->callbackfolder . '.erase_item.show', [
            'data' => $data,
        ]);
    }

    public function destroy($erase_code)
    {
        $data = EraseItem::where('erase_code', $erase_code)->firstOrFail();
        
        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $data->items()->delete();
            $data->delete();
        });

        return redirect()->route('erase_item.index')->with('success', 'Data Nota Penghapusan berhasil dihapus.');
    }
}