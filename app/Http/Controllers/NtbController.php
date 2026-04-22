<?php

namespace App\Http\Controllers;

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
        ]);

        DB::transaction(function () use ($request) {
            ReceiveBookNote::create([
                'receive_code'   => $request->receive_code,
                'nota_kirim_cab' => $request->nota_kirim_cab,
                'branch_code'    => $request->branch_code,
                'branch_sender'  => $request->branch_sender,
                'send_date'      => $request->send_date,
                'retur_date'     => $request->retur_date,
                'info'           => $request->info,
            ]);

            foreach ($request->items as $row) {
                $total = (int) $row['koli'] * (int) $row['volume'] + (int) $row['exemplar'];
                ReceiveBookNoteDetail::create([
                    'nota_kirim_cab' => $request->nota_kirim_cab,
                    'book_code'      => $row['book_code'],
                    'book_price'     => null,
                    'koli'           => (int) $row['koli'],
                    'volume'         => (int) $row['volume'],
                    'exemplar'       => (int) $row['exemplar'],
                    'total_exemplar' => $total,
                    'branch_sender'  => $request->branch_sender,
                ]);
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
        ]);

        DB::transaction(function () use ($request, $ntb) {
            $oldNota = $ntb->nota_kirim_cab;

            $ntb->update([
                'receive_code'   => $request->receive_code,
                'nota_kirim_cab' => $request->nota_kirim_cab,
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
                ReceiveBookNoteDetail::create([
                    'nota_kirim_cab' => $request->nota_kirim_cab,
                    'book_code'      => $row['book_code'],
                    'book_price'     => null,
                    'koli'           => (int) $row['koli'],
                    'volume'         => (int) $row['volume'],
                    'exemplar'       => (int) $row['exemplar'],
                    'total_exemplar' => $total,
                    'branch_sender'  => $request->branch_sender,
                ]);
            }
        });

        return redirect()->route('ntb.index')->with('success', 'NTB berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $ntb = ReceiveBookNote::findOrFail($id);
        
        DB::transaction(function () use ($ntb) {
            ReceiveBookNoteDetail::where('nota_kirim_cab', $ntb->nota_kirim_cab)->delete();
            $ntb->delete();
        });

        return redirect()->route('ntb.index')->with('success', 'NTB berhasil dibatalkan.');
    }
}
