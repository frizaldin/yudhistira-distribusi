<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NkbPenyesuaianController extends Controller
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

    /**
     * Daftar NKB Penyesuaian
     */
    public function index(Request $request)
    {
        // Hanya yang branch_code = GIP dan branch_sender = PS00
        $query = DeliveryNote::query()
            ->where('branch_code', 'GIP')
            ->where('branch_sender', 'PS00')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                return $q->where(function ($q2) use ($s) {
                    $q2->where('nota_kirim_cab', 'like', '%' . $s . '%')
                        ->orWhere('nppb', 'like', '%' . $s . '%')
                        ->orWhere('sj', 'like', '%' . $s . '%');
                });
            })
            ->orderBy('send_date', 'desc');

        $perPage = 20;
        $items = $query->paginate($perPage)->withQueryString();

        return view($this->callbackfolder . '.nkb-penyesuaian.index', [
            'items' => $items,
            'queryString' => $request->query(),
        ]);
    }

    /**
     * Detail NKB Penyesuaian
     */
    public function show($nota_kirim_cab)
    {
        $nkb = DeliveryNote::with(['details'])
            ->where('nota_kirim_cab', $nota_kirim_cab)
            ->where('branch_code', 'GIP')
            ->where('branch_sender', 'PS00')
            ->firstOrFail();

        return view($this->callbackfolder . '.nkb-penyesuaian.show', [
            'nkb' => $nkb,
        ]);
    }

    /**
     * Tampilkan form pembuatan NKB Penyesuaian
     */
    public function create()
    {
        return view($this->callbackfolder . '.nkb-penyesuaian.create');
    }

    /**
     * Simpan data NKB Penyesuaian baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'nota_kirim_cab' => 'required|string|unique:delivery_notes,nota_kirim_cab',
            'nppb' => 'nullable|string',
            'sj' => 'nullable|string',
            'send_date' => 'nullable|date',
            'info' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.book_code' => 'required|string',
            'items.*.koli' => 'required|numeric|min:0',
            'items.*.volume' => 'required|numeric|min:0',
            'items.*.exemplar' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            DeliveryNote::create([
                'nota_kirim_cab' => $request->nota_kirim_cab,
                'branch_code' => 'GIP',
                'branch_sender' => 'PS00',
                'send_date' => $request->send_date,
                'info' => $request->info,
                'nppb' => $request->nppb,
                'sj' => $request->sj,
            ]);

            foreach ($request->items as $item) {
                $total_exemplar = ($item['koli'] * $item['volume']) + $item['exemplar'];
                DeliveryNoteDetail::create([
                    'nota_kirim_cab' => $request->nota_kirim_cab,
                    'book_code' => $item['book_code'],
                    'book_price' => null,
                    'koli' => $item['koli'],
                    'volume' => $item['volume'],
                    'exemplar' => $item['exemplar'],
                    'total_exemplar' => $total_exemplar,
                    'branch_sender' => 'PS00',
                ]);
            }
        });

        return redirect()->route('nkb_penyesuaian.index')
            ->with('success', 'Data NKB Penyesuaian berhasil ditambahkan.');
    }

    /**
     * Hapus NKB Penyesuaian
     */
    public function destroy($nota_kirim_cab)
    {
        $nkb = DeliveryNote::where('nota_kirim_cab', $nota_kirim_cab)
            ->where('branch_code', 'GIP')
            ->where('branch_sender', 'PS00')
            ->firstOrFail();

        DB::transaction(function () use ($nkb) {
            DeliveryNoteDetail::where('nota_kirim_cab', $nkb->nota_kirim_cab)->delete();
            $nkb->delete();
        });

        return redirect()->route('nkb_penyesuaian.index')
            ->with('success', 'Data NKB Penyesuaian berhasil dihapus.');
    }
}
