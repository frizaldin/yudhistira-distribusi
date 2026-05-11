<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DeliveryPromo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeliveryPromoController extends Controller
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
        $query = DeliveryPromo::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                return $q->where('nota_kirim_promo', 'like', '%' . $s . '%');
            })
            ->orderBy('nota_kirim_promo', 'desc');

        $perPage = 20;
        $items = $query->paginate($perPage)->withQueryString();

        return view($this->callbackfolder . '.delivery_promo.index', [
            'items' => $items,
            'queryString' => $request->query(),
        ]);
    }

    public function create()
    {
        $branches = Branch::orderBy('branch_name')->get(['branch_code', 'branch_name']);

        return view($this->callbackfolder . '.delivery_promo.create', [
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nota_kirim_promo' => 'required|string|unique:m_kirim_promosi,nota_kirim_promo',
            'approve_by' => 'nullable|string',
            'branch_sender' => 'nullable|string',
            'deliver_by' => 'nullable|string',
            'info' => 'nullable|string',
            'sales_code' => 'nullable|string',
            'send_date' => 'nullable|date',
            'whouse_head' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.book_code' => 'required|string',
            'items.*.koli' => 'required|numeric|min:0',
            'items.*.volume' => 'required|numeric|min:0',
            'items.*.exemplar' => 'required|numeric|min:0',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $deliveryPromo = DeliveryPromo::create([
                'nota_kirim_promo' => $request->nota_kirim_promo,
                'approve_by' => $request->approve_by,
                'branch_sender' => $request->branch_sender,
                'deliver_by' => $request->deliver_by,
                'info' => $request->info,
                'sales_code' => $request->sales_code,
                'send_date' => $request->send_date,
                'whouse_head' => $request->whouse_head,
                'user_id'          => Auth::user()->username ?? Auth::id() ?? 'system',
                'status' => 0,
                'printed' => 0,
            ]);

            foreach ($request->items as $item) {
                $koli = $item['koli'];
                $volume = $item['volume'];
                $exemplar = $item['exemplar'];
                $total_exemplar = ($koli * $volume) + $exemplar;

                $deliveryPromo->items()->create([
                    'book_code' => $item['book_code'],
                    'branch_sender' => $request->branch_sender,
                    'koli' => $koli,
                    'volume' => $volume,
                    'exemplar' => $exemplar,
                    'total_exemplar' => $total_exemplar,
                ]);

                $centralStock = \App\Models\CentralStock::where('branch_code', $request->branch_sender)
                    ->where('book_code', $item['book_code'])
                    ->first();
                
                if ($centralStock) {
                    $centralStock->decrement('exemplar', $total_exemplar);
                } else {
                    \App\Models\CentralStock::create([
                        'branch_code' => $request->branch_sender,
                        'book_code' => $item['book_code'],
                        'exemplar' => -$total_exemplar,
                    ]);
                }
            }
        });

        return redirect()->route('delivery_promo.index')->with('success', 'Data Nota Promosi berhasil ditambahkan.');
    }

    public function show($nota_kirim_promo)
    {
        $data = DeliveryPromo::with(['items'])
            ->where('nota_kirim_promo', $nota_kirim_promo)
            ->firstOrFail();

        return view($this->callbackfolder . '.delivery_promo.show', [
            'data' => $data,
        ]);
    }

    public function destroy($nota_kirim_promo)
    {
        $data = DeliveryPromo::where('nota_kirim_promo', $nota_kirim_promo)->firstOrFail();
        
        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            foreach ($data->items as $item) {
                $centralStock = \App\Models\CentralStock::where('branch_code', $item->branch_sender)
                    ->where('book_code', $item->book_code)
                    ->first();
                
                if ($centralStock) {
                    $centralStock->increment('exemplar', $item->total_exemplar);
                } else {
                    \App\Models\CentralStock::create([
                        'branch_code' => $item->branch_sender,
                        'book_code' => $item->book_code,
                        'exemplar' => $item->total_exemplar,
                    ]);
                }
            }

            $data->items()->delete();
            $data->delete();
        });

        return redirect()->route('delivery_promo.index')->with('success', 'Data Nota Promosi berhasil dihapus.');
    }
}