<?php

namespace App\Http\Controllers;

use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->with('product:book_code,book_title')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('book_code', 'like', '%' . $s . '%')
                    ->orWhere('nama_pt_produksi', 'like', '%' . $s . '%')
                    ->orWhere('nomor_surat_jalan', 'like', '%' . $s . '%')
                    ->orWhere('nomor_jo', 'like', '%' . $s . '%');
            });
        }

        $items = $query->paginate(20)->withQueryString();

        return view($this->callbackfolder . '.stock-mutation.index', [
            'items' => $items,
            'queryString' => $request->query(),
        ]);
    }

    public function create()
    {
        return view($this->callbackfolder . '.stock-mutation.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validatedMutation($request);

        $total = (int) $validated['koli'] * (int) $validated['isi_koli'] + (int) $validated['eceran'];

        StockMutation::create([
            'book_code' => $validated['book_code'],
            'koli' => (int) $validated['koli'],
            'isi_koli' => (int) $validated['isi_koli'],
            'eceran' => (int) $validated['eceran'],
            'total_eksemplar' => $total,
            'nama_pt_produksi' => $validated['nama_pt_produksi'] ?? null,
            'tanggal_penerimaan' => $validated['tanggal_penerimaan'] ?? null,
            'nama_penerima' => $validated['nama_penerima'] ?? null,
            'nomor_surat_jalan' => $validated['nomor_surat_jalan'] ?? null,
            'nomor_jo' => $validated['nomor_jo'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('mutasi.index')->with('success', 'Mutasi berhasil disimpan. Total eksemplar masuk ke stock pusat.');
    }

    public function edit(StockMutation $stock_mutation)
    {
        $stock_mutation->load('product:book_code,book_title');

        return view($this->callbackfolder . '.stock-mutation.edit', [
            'item' => $stock_mutation,
        ]);
    }

    public function update(Request $request, StockMutation $stock_mutation)
    {
        $validated = $this->validatedMutationUpdate($request);

        $total = (int) $validated['koli'] * (int) $validated['isi_koli'] + (int) $validated['eceran'];

        $stock_mutation->update([
            'book_code' => $validated['book_code'],
            'koli' => (int) $validated['koli'],
            'isi_koli' => (int) $validated['isi_koli'],
            'eceran' => (int) $validated['eceran'],
            'total_eksemplar' => $total,
        ]);

        return redirect()->route('mutasi.index')->with('success', 'Mutasi berhasil diperbarui.');
    }

    public function destroy(StockMutation $stock_mutation)
    {
        $stock_mutation->delete();

        return redirect()->route('mutasi.index')->with('success', 'Mutasi berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedMutation(Request $request): array
    {
        return $request->validate([
            'book_code' => ['required', 'string', 'max:100', 'exists:books,book_code'],
            'koli' => ['required', 'integer', 'min:0'],
            'isi_koli' => ['required', 'integer', 'min:0'],
            'eceran' => ['required', 'integer', 'min:0'],
            'nama_pt_produksi' => ['nullable', 'string', 'max:255'],
            'tanggal_penerimaan' => ['nullable', 'date'],
            'nama_penerima' => ['nullable', 'string', 'max:255'],
            'nomor_surat_jalan' => ['nullable', 'string', 'max:100'],
            'nomor_jo' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedMutationUpdate(Request $request): array
    {
        return $request->validate([
            'book_code' => ['required', 'string', 'max:100', 'exists:books,book_code'],
            'koli' => ['required', 'integer', 'min:0'],
            'isi_koli' => ['required', 'integer', 'min:0'],
            'eceran' => ['required', 'integer', 'min:0'],
        ]);
    }
}
