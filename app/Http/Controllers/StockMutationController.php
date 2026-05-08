<?php

namespace App\Http\Controllers;

use App\Models\StockMutation;
use App\Models\StockMutationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockMutationController extends Controller
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
        $query = StockMutation::query()
            ->with(['items.product:book_code,book_title'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama_pt_produksi', 'like', '%' . $s . '%')
                    ->orWhere('nomor_surat_jalan', 'like', '%' . $s . '%')
                    ->orWhere('nomor_jo', 'like', '%' . $s . '%')
                    ->orWhereHas('items', function ($qi) use ($s) {
                        $qi->where('book_code', 'like', '%' . $s . '%');
                    });
            });
        }

        $items = $query->paginate(20)->withQueryString();

        return view($this->callbackfolder . '.stock-mutation.index', [
            'items'       => $items,
            'queryString' => $request->query(),
        ]);
    }

    public function create()
    {
        return view($this->callbackfolder . '.stock-mutation.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_pt_produksi'   => ['nullable', 'string', 'max:255'],
            'tanggal_penerimaan' => ['nullable', 'date'],
            'nama_penerima'      => ['nullable', 'string', 'max:255'],
            'nomor_surat_jalan'  => ['nullable', 'string', 'max:100'],
            'nomor_jo'           => ['nullable', 'string', 'max:100'],
            'keterangan'         => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1', 'max:25'],
            'items.*.book_code'  => ['required', 'string', 'max:100', 'exists:books,book_code'],
            'items.*.koli'       => ['required', 'integer', 'min:0'],
            'items.*.isi_koli'   => ['required', 'integer', 'min:0'],
            'items.*.eceran'     => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($request) {
            $mutation = StockMutation::create([
                'nama_pt_produksi'   => $request->nama_pt_produksi,
                'tanggal_penerimaan' => $request->tanggal_penerimaan,
                'nama_penerima'      => $request->nama_penerima,
                'nomor_surat_jalan'  => $request->nomor_surat_jalan,
                'nomor_jo'           => $request->nomor_jo,
                'keterangan'         => $request->keterangan,
                'created_by'         => $request->user()?->id,
            ]);

            foreach ($request->items as $row) {
                $total = (int) $row['koli'] * (int) $row['isi_koli'] + (int) $row['eceran'];
                StockMutationItem::create([
                    'mutation_id'    => $mutation->id,
                    'book_code'      => $row['book_code'],
                    'koli'           => (int) $row['koli'],
                    'isi_koli'       => (int) $row['isi_koli'],
                    'eceran'         => (int) $row['eceran'],
                    'total_eksemplar' => $total,
                ]);
            }
        });

        return redirect()->route('mutasi.index')->with('success', 'Mutasi berhasil disimpan. Total eksemplar masuk ke stock pusat.');
    }

    public function edit(StockMutation $stock_mutation)
    {
        $stock_mutation->load('items.product:book_code,book_title');

        return view($this->callbackfolder . '.stock-mutation.edit', [
            'item' => $stock_mutation,
        ]);
    }

    public function update(Request $request, StockMutation $stock_mutation)
    {
        $request->validate([
            'nama_pt_produksi'   => ['nullable', 'string', 'max:255'],
            'tanggal_penerimaan' => ['nullable', 'date'],
            'nama_penerima'      => ['nullable', 'string', 'max:255'],
            'nomor_surat_jalan'  => ['nullable', 'string', 'max:100'],
            'nomor_jo'           => ['nullable', 'string', 'max:100'],
            'keterangan'         => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1', 'max:25'],
            'items.*.book_code'  => ['required', 'string', 'max:100', 'exists:books,book_code'],
            'items.*.koli'       => ['required', 'integer', 'min:0'],
            'items.*.isi_koli'   => ['required', 'integer', 'min:0'],
            'items.*.eceran'     => ['required', 'integer', 'min:0'],
        ]);

        $new_book_codes = collect($request->items)->pluck('book_code')->toArray();
        $deleted_items = $stock_mutation->items->whereNotIn('book_code', $new_book_codes);

        foreach ($deleted_items as $item) {
            $book_code = $item->book_code;
            $qty_mutasi = $item->total_eksemplar;

            $cs = \App\Models\CentralStock::where('book_code', $book_code)->sum('exemplar');
            $mut = \App\Models\StockMutationItem::where('book_code', $book_code)->sum('total_eksemplar');
            $rawStockPusat = (float) $cs + (float) $mut;
            
            $deducted = \App\Models\CentralStockDeduction::where('book_code', $book_code)->sum('quantity');
            $stockPusat = $rawStockPusat - $deducted;

            if ($stockPusat < $qty_mutasi) {
                return redirect()->back()->with('error', "Penghapusan baris gagal! Stock Pusat untuk buku {$book_code} tidak mencukupi (Tersedia: {$stockPusat}, Yang akan ditarik/hapus: {$qty_mutasi}).");
            }
        }

        DB::transaction(function () use ($request, $stock_mutation) {
            $stock_mutation->update([
                'nama_pt_produksi'   => $request->nama_pt_produksi,
                'tanggal_penerimaan' => $request->tanggal_penerimaan,
                'nama_penerima'      => $request->nama_penerima,
                'nomor_surat_jalan'  => $request->nomor_surat_jalan,
                'nomor_jo'           => $request->nomor_jo,
                'keterangan'         => $request->keterangan,
            ]);

            // Hapus semua items lama lalu insert ulang
            $stock_mutation->items()->delete();

            foreach ($request->items as $row) {
                $total = (int) $row['koli'] * (int) $row['isi_koli'] + (int) $row['eceran'];
                StockMutationItem::create([
                    'mutation_id'    => $stock_mutation->id,
                    'book_code'      => $row['book_code'],
                    'koli'           => (int) $row['koli'],
                    'isi_koli'       => (int) $row['isi_koli'],
                    'eceran'         => (int) $row['eceran'],
                    'total_eksemplar' => $total,
                ]);
            }
        });

        return redirect()->route('mutasi.index')->with('success', 'Mutasi berhasil diperbarui.');
    }

    public function destroy(StockMutation $stock_mutation)
    {
        // Validasi: jumlah stock pusat harus sama atau lebih dengan mutasi yang akan dihapus
        foreach ($stock_mutation->items as $item) {
            $book_code = $item->book_code;
            $qty_mutasi = $item->total_eksemplar;

            $cs = \App\Models\CentralStock::where('book_code', $book_code)->sum('exemplar');
            $mut = \App\Models\StockMutationItem::where('book_code', $book_code)->sum('total_eksemplar');
            $rawStockPusat = (float) $cs + (float) $mut;
            
            $deducted = \App\Models\CentralStockDeduction::where('book_code', $book_code)->sum('quantity');
            $stockPusat = $rawStockPusat - $deducted;

            if ($stockPusat < $qty_mutasi) {
                return redirect()->back()->with('error', "Pembatalan gagal! Stock Pusat untuk buku {$book_code} tidak mencukupi (Tersedia: {$stockPusat}, Yang akan ditarik/hapus: {$qty_mutasi}).");
            }
        }

        // items akan ikut terhapus karena ON DELETE CASCADE di DB
        $stock_mutation->delete();

        return redirect()->route('mutasi.index')->with('success', 'Mutasi berhasil dibatalkan.');
    }
}
