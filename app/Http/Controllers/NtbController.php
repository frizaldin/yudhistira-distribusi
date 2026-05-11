<?php

namespace App\Http\Controllers;

use App\Models\CentralStockDeduction;
use App\Models\ReceiveBookNote;
use App\Models\ReceiveBookNoteDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NtbController extends Controller
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
        $query = ReceiveBookNote::query()
            ->with(['items.product:book_code,book_title'])
            ->orderByDesc('id');

        if ($request->filled('type')) {
            if ($request->type === 'retur') {
                $query->where('receive_type', 1);
            } elseif ($request->type === 'non_retur') {
                $query->where(function ($q) {
                    $q->whereNull('receive_type')->orWhere('receive_type', 0);
                });
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('receive_code', 'like', '%' . $s . '%')
                    ->orWhere('nota_kirim_cab', 'like', '%' . $s . '%')
                    ->orWhere('branch_code', 'like', '%' . $s . '%')
                    ->orWhere('branch_sender', 'like', '%' . $s . '%')
                    ->orWhereHas('items', function ($qi) use ($s) {
                        $qi->where('book_code', 'like', '%' . $s . '%');
                    });
            });
        }

        $items = $query->paginate(20)->withQueryString();

        return view($this->callbackfolder . '.ntb.index', [
            'items'       => $items,
            'queryString' => $request->query(),
        ]);
    }

    public function create()
    {
        return view($this->callbackfolder . '.ntb.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'receive_code'       => ['required', 'string', 'max:100', 'unique:m_terima_buku,receive_code'],
            'nota_kirim_cab'     => ['required', 'string', 'max:100'],
            'receive_type'       => ['required', 'in:0,1'],
            'branch_code'        => ['nullable', 'string', 'max:100'],
            'branch_sender'      => ['nullable', 'string', 'max:100'],
            'send_date'          => ['nullable', 'date'],
            'retur_date'         => ['nullable', 'date'],
            'info'               => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.book_code'  => ['required', 'string', 'max:100'],
            'items.*.koli'       => ['required', 'integer', 'min:0'],
            'items.*.volume'     => ['required', 'integer', 'min:0'],
            'items.*.exemplar'   => ['required', 'integer', 'min:0'],
            'items.*.skip_central_stock_deduction' => ['nullable', 'in:0,1'],
        ]);

        DB::transaction(function () use ($request) {
            ReceiveBookNote::create([
                'receive_code'   => $request->receive_code,
                'nota_kirim_cab' => $request->nota_kirim_cab,
                'receive_type'   => (int) $request->receive_type,
                'branch_code'    => $request->branch_code,
                'branch_sender'  => $request->branch_sender,
                'send_date'      => $request->send_date,
                'retur_date'     => $request->retur_date,
                'info'           => $request->info,
            ]);

            foreach ($request->items as $row) {
                $total = (int) $row['koli'] * (int) $row['volume'] + (int) $row['exemplar'];
                $skipDeduction = $this->itemSkipsCentralStockDeduction($row);
                $detail = ReceiveBookNoteDetail::create([
                    'nota_kirim_cab' => $request->nota_kirim_cab,
                    'book_code'      => $row['book_code'],
                    'book_price'     => null,
                    'koli'           => (int) $row['koli'],
                    'volume'         => (int) $row['volume'],
                    'exemplar'       => (int) $row['exemplar'],
                    'total_exemplar' => $total,
                    'branch_sender'  => $request->branch_sender,
                    'skip_central_stock_deduction' => $skipDeduction,
                ]);
                $this->createCentralStockDeductionForDetail($detail, $skipDeduction);
            }
        });

        return redirect()->route('ntb.index')->with('success', 'NTB berhasil disimpan.');
    }

    public function edit($id)
    {
        $ntb = ReceiveBookNote::with('items.product')->findOrFail($id);

        return view($this->callbackfolder . '.ntb.edit', [
            'item' => $ntb,
        ]);
    }

    public function update(Request $request, $id)
    {
        $ntb = ReceiveBookNote::findOrFail($id);

        $request->validate([
            'receive_code'       => ['required', 'string', 'max:100', 'unique:m_terima_buku,receive_code,' . $id],
            'nota_kirim_cab'     => ['required', 'string', 'max:100'],
            'receive_type'       => ['required', 'in:0,1'],
            'branch_code'        => ['nullable', 'string', 'max:100'],
            'branch_sender'      => ['nullable', 'string', 'max:100'],
            'send_date'          => ['nullable', 'date'],
            'retur_date'         => ['nullable', 'date'],
            'info'               => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.book_code'  => ['required', 'string', 'max:100'],
            'items.*.koli'       => ['required', 'integer', 'min:0'],
            'items.*.volume'     => ['required', 'integer', 'min:0'],
            'items.*.exemplar'   => ['required', 'integer', 'min:0'],
            'items.*.skip_central_stock_deduction' => ['nullable', 'in:0,1'],
        ]);

        DB::transaction(function () use ($request, $ntb) {
            $oldNota = $ntb->nota_kirim_cab;

            $this->deleteCentralStockDeductionsForNota($oldNota);

            $ntb->update([
                'receive_code'   => $request->receive_code,
                'nota_kirim_cab' => $request->nota_kirim_cab,
                'receive_type'   => (int) $request->receive_type,
                'branch_code'    => $request->branch_code,
                'branch_sender'  => $request->branch_sender,
                'send_date'      => $request->send_date,
                'retur_date'     => $request->retur_date,
                'info'           => $request->info,
            ]);

            // Delete old items based on old nota
            ReceiveBookNoteDetail::where('nota_kirim_cab', $oldNota)->delete();

            // Insert new items
            foreach ($request->items as $row) {
                $total = (int) $row['koli'] * (int) $row['volume'] + (int) $row['exemplar'];
                $skipDeduction = $this->itemSkipsCentralStockDeduction($row);
                $detail = ReceiveBookNoteDetail::create([
                    'nota_kirim_cab' => $request->nota_kirim_cab,
                    'book_code'      => $row['book_code'],
                    'book_price'     => null,
                    'koli'           => (int) $row['koli'],
                    'volume'         => (int) $row['volume'],
                    'exemplar'       => (int) $row['exemplar'],
                    'total_exemplar' => $total,
                    'branch_sender'  => $request->branch_sender,
                    'skip_central_stock_deduction' => $skipDeduction,
                ]);
                $this->createCentralStockDeductionForDetail($detail, $skipDeduction);
            }
        });

        return redirect()->route('ntb.index')->with('success', 'NTB berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $ntb = ReceiveBookNote::findOrFail($id);
        
        DB::transaction(function () use ($ntb) {
            $this->deleteCentralStockDeductionsForNota($ntb->nota_kirim_cab);
            ReceiveBookNoteDetail::where('nota_kirim_cab', $ntb->nota_kirim_cab)->delete();
            $ntb->delete();
        });

        return redirect()->route('ntb.index')->with('success', 'NTB berhasil dibatalkan.');
    }

    /** Centang form = jangan kurangi stock pusat (tidak buat central_stock_deductions). */
    protected function itemSkipsCentralStockDeduction(array $row): bool
    {
        return isset($row['skip_central_stock_deduction'])
            && (string) $row['skip_central_stock_deduction'] === '1';
    }

    protected function deleteCentralStockDeductionsForNota(string $notaKirimCab): void
    {
        $ids = ReceiveBookNoteDetail::where('nota_kirim_cab', $notaKirimCab)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        $idStrings = $ids->map(fn ($id) => (string) $id)->all();
        CentralStockDeduction::where('source_type', CentralStockDeduction::SOURCE_NTB)
            ->whereIn('source_id', $idStrings)
            ->delete();
    }

    protected function createCentralStockDeductionForDetail(ReceiveBookNoteDetail $detail, bool $skipDeduction): void
    {
        if ($skipDeduction) {
            return;
        }
        $qty = (int) $detail->total_exemplar;
        if ($qty <= 0 || $detail->book_code === '') {
            return;
        }
        CentralStockDeduction::create([
            'book_code' => $detail->book_code,
            'quantity' => $qty,
            'source_type' => CentralStockDeduction::SOURCE_NTB,
            'source_id' => (string) $detail->id,
        ]);
    }
}
