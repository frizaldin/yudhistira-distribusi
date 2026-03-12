<?php

namespace App\Http\Controllers;

use App\Models\CentralStockDeduction;
use App\Models\DeliveryOrderItem;
use App\Models\Nkb;
use App\Models\NkbItem;
use App\Models\NppbCentral;
use App\Models\NppbDocument;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NkbController extends Controller
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
     * Daftar NKB (list).
     */
    public function index(Request $request)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $query = Nkb::query()
            ->with(['senderBranch', 'recipientBranch', 'creator:id,name'])
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->whereIn('sender_code', $filteredBranchCodes)
                    ->orWhereIn('recipient_code', $filteredBranchCodes);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                return $q->where(function ($q2) use ($s) {
                    $q2->where('number', 'like', '%' . $s . '%')
                        ->orWhere('nppb_code', 'like', '%' . $s . '%')
                        ->orWhere('sender_code', 'like', '%' . $s . '%')
                        ->orWhere('recipient_code', 'like', '%' . $s . '%');
                });
            })
            ->orderBy('id', 'desc');

        $perPage = 20;
        $items = $query->paginate($perPage)->withQueryString();

        return view($this->callbackfolder . '.nkb.index', [
            'items' => $items,
            'queryString' => $request->query(),
        ]);
    }

    /**
     * Detail NKB (lihat satu NKB).
     */
    public function show($number)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $nkb = Nkb::with(['items', 'senderBranch', 'recipientBranch'])
            ->where('number', $number)
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->firstOrFail();

        $stack = null;
        $doc = NppbDocument::where('number', $nkb->nppb_code)->first();
        if ($doc) {
            $stack = NppbCentral::where('document_id', $doc->id)->value('stack');
        }

        $usedInDo = DeliveryOrderItem::where('nkb_id', $nkb->id)->exists();

        return view($this->callbackfolder . '.nkb.show', [
            'nkb' => $nkb,
            'stack' => $stack,
            'used_in_do' => $usedInDo,
        ]);
    }

    /**
     * Form edit NKB — edit koli, isi koli, eksemplar per item.
     */
    public function edit($number)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $nkb = Nkb::with(['items', 'senderBranch', 'recipientBranch'])
            ->where('number', $number)
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->firstOrFail();

        $usedInDo = DeliveryOrderItem::where('nkb_id', $nkb->id)->exists();
        if ($usedInDo) {
            return redirect()->route('nkb.show', ['number' => $nkb->number])
                ->with('error', 'NKB ini sudah dipakai di Surat Jalan. Tidak dapat diedit.');
        }

        $stack = null;
        $doc = NppbDocument::where('number', $nkb->nppb_code)->first();
        if ($doc) {
            $stack = NppbCentral::where('document_id', $doc->id)->value('stack');
        }

        return view($this->callbackfolder . '.nkb.edit', [
            'nkb' => $nkb,
            'stack' => $stack,
        ]);
    }

    /**
     * Update NKB — simpan perubahan koli, volume, eksemplar; perbarui deduction stock pusat.
     */
    public function update(Request $request, $number)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $nkb = Nkb::with('items')
            ->where('number', $number)
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->firstOrFail();

        $usedInDo = DeliveryOrderItem::where('nkb_id', $nkb->id)->exists();
        if ($usedInDo) {
            return redirect()->route('nkb.show', ['number' => $nkb->number])
                ->with('error', 'NKB ini sudah dipakai di Surat Jalan. Tidak dapat diedit.');
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.koli' => 'required|numeric|min:0',
            'items.*.volume' => 'required|numeric|min:0',
            'items.*.exp' => 'required|numeric|min:0',
        ], [], [
            'items.*.koli' => 'Koli',
            'items.*.volume' => 'Isi koli',
            'items.*.exp' => 'Eksemplar',
        ]);

        DB::transaction(function () use ($request, $nkb) {
            $totalExemplar = 0;
            $newExpByBook = [];

            foreach ($nkb->items as $item) {
                $koli = (int) $request->input('items.' . $item->id . '.koli', $item->koli);
                $volume = (int) $request->input('items.' . $item->id . '.volume', $item->volume);
                $exp = (int) $request->input('items.' . $item->id . '.exp', $item->exp);
                $pls = max(0, $exp - $koli * $volume);

                $item->update(['koli' => $koli, 'volume' => $volume, 'exp' => $exp, 'pls' => $pls]);

                $totalExemplar += $exp;
                $bookCode = $item->book_code ?? '';
                if ($bookCode !== '') {
                    $newExpByBook[$bookCode] = ($newExpByBook[$bookCode] ?? 0) + $exp;
                }
            }

            $nkb->update([
                'total_type_books' => $nkb->items->pluck('book_code')->unique()->filter()->count(),
                'total_exemplar' => $totalExemplar,
            ]);

            CentralStockDeduction::where('source_type', CentralStockDeduction::SOURCE_NKB)
                ->where('source_id', $nkb->number)
                ->delete();

            foreach ($newExpByBook as $bookCode => $qty) {
                if ($qty > 0) {
                    CentralStockDeduction::create([
                        'book_code' => $bookCode,
                        'quantity' => $qty,
                        'source_type' => CentralStockDeduction::SOURCE_NKB,
                        'source_id' => $nkb->number,
                    ]);
                }
            }
        });

        return redirect()->route('nkb.show', ['number' => $nkb->number])
            ->with('success', 'NKB berhasil diperbarui.');
    }

    /**
     * Halaman print NKB (HTML + tombol cetak / ?print=1 auto print). Layout mirip NPPB.
     */
    public function print(Request $request, $number)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $nkb = Nkb::with(['items', 'senderBranch', 'recipientBranch'])
            ->where('number', $number)
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->firstOrFail();

        $bookCodes = $nkb->items->pluck('book_code')->unique()->filter()->values()->all();
        $pricesByBook = Product::whereIn('book_code', $bookCodes)->get(['book_code', 'sale_price'])->keyBy('book_code');

        $totalKoli = 0;
        $totalPls = 0;
        $totalEx = 0;
        $totalRp = 0.0;
        $rows = $nkb->items->map(function ($row) use ($pricesByBook, &$totalKoli, &$totalPls, &$totalEx, &$totalRp) {
            $price = $pricesByBook->get($row->book_code);
            $unitPrice = $price ? (float) $price->sale_price : 0;
            $exp = (int) $row->exp;
            $totalKoli += (int) $row->koli;
            $totalPls += (int) $row->pls;
            $totalEx += $exp;
            $totalRp += $exp * $unitPrice;
            return (object) [
                'book_code' => $row->book_code,
                'book_name' => $row->book_name,
                'koli' => (int) $row->koli,
                'pls' => (int) $row->pls,
                'volume' => (int) $row->volume,
                'exp' => $exp,
                'unit_price' => $unitPrice,
                'jumlah_rp' => $exp * $unitPrice,
            ];
        });

        $print = $request->boolean('print');

        return view('pdf.nkb', [
            'nkb' => $nkb,
            'rows' => $rows,
            'prices_by_book' => $pricesByBook,
            'total_koli' => $totalKoli,
            'total_pls' => $totalPls,
            'total_ex' => $totalEx,
            'total_rp' => $totalRp,
            'print' => $print,
        ]);
    }

    /**
     * Pilih NPPB yang belum punya NKB → lalu ke halaman preview (pilih item) untuk buat NKB.
     */
    public function create(Request $request)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $documentIdsWithBranch = NppbCentral::query()
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->whereIn('branch_code', $filteredBranchCodes);
            })
            ->distinct()
            ->pluck('document_id');

        $nppbCodesAlreadyHaveNkb = Nkb::pluck('nppb_code');

        $documents = NppbDocument::with(['senderBranch', 'recipientBranch'])
            ->whereIn('id', $documentIdsWithBranch)
            ->whereNotIn('number', $nppbCodesAlreadyHaveNkb)
            ->orderBy('id', 'desc')
            ->get();

        $stackByDocId = NppbCentral::whereIn('document_id', $documents->pluck('id'))
            ->select('document_id', 'stack')
            ->get()
            ->groupBy('document_id')
            ->map(fn ($g) => $g->first()->stack);

        return view($this->callbackfolder . '.nkb.create', [
            'documents' => $documents,
            'stackByDocId' => $stackByDocId,
        ]);
    }

    /**
     * Batalkan/hapus NKB. Hapus juga deduction stock pusat agar stock kembali.
     * Tidak boleh jika NKB sudah dipakai di Surat Jalan (Delivery Order).
     */
    public function destroy($number)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $nkb = Nkb::where('number', $number)
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->first();

        if (!$nkb) {
            return redirect()->route('nkb.index')->with('error', 'NKB tidak ditemukan.');
        }

        $usedInDo = DeliveryOrderItem::where('nkb_id', $nkb->id)->exists();
        if ($usedInDo) {
            return redirect()->route('nkb.index')->with('error', 'NKB ini sudah dipakai di Surat Jalan (Delivery Order). Batalkan/hapus Surat Jalan terlebih dahulu.');
        }

        $nppbCode = $nkb->nppb_code;

        DB::transaction(function () use ($nkb, $nppbCode) {
            CentralStockDeduction::where('source_type', CentralStockDeduction::SOURCE_NKB)
                ->where('source_id', $nkb->number)
                ->delete();

            NkbItem::where('nkb_code', $nkb->number)->delete();
            $nkb->delete();

            // Tandai dokumen NPPB bahwa NKB-nya dibatalkan → baris di preparation-notes jadi merah
            if ($nppbCode) {
                NppbDocument::where('number', $nppbCode)->update(['nkb_cancelled_at' => now()]);
            }
        });

        return redirect()->route('nkb.index')->with('success', 'NKB ' . $nkb->number . ' berhasil dibatalkan. Pengurangan stock pusat telah dikembalikan.');
    }
}
