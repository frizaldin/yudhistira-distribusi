<?php

namespace App\Http\Controllers;

use App\Models\Nkb;
use App\Models\NkbItem;
use App\Models\NppbCentral;
use App\Models\CentralStockDeduction;
use App\Models\NppbDocument;
use App\Models\Branch;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PreparationNotesController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/preparation-notes');
        $this->title = 'Preparation Notes (NPPB Centrals)';

        if (Auth::check()) {
            $this->role = Auth::user()->authority_id ?? 1;
            $this->callbackfolder = match ($this->role) {
                1 => 'superadmin',
                2 => 'branch',
                default => 'superadmin',
            };
        } else {
            $this->callbackfolder = 'superadmin';
        }
    }

    /**
     * Display a listing of nppb_centrals, grouped by stack.
     */
    public function index(Request $request)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        $perPage = 20;

        $baseQuery = NppbCentral::query()
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->whereIn('branch_code', $filteredBranchCodes);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                return $q->where(function ($q2) use ($s) {
                    $q2->where('branch_code', 'like', '%' . $s . '%')
                        ->orWhere('branch_name', 'like', '%' . $s . '%')
                        ->orWhere('book_code', 'like', '%' . $s . '%')
                        ->orWhere('book_name', 'like', '%' . $s . '%');
                });
            })
            ->when($request->filled('branch_code'), function ($q) use ($request) {
                return $q->where('branch_code', $request->branch_code);
            });

        // Paginasi per stack: ambil daftar stack (urut terbaru dulu)
        $stackQuery = (clone $baseQuery)->select('stack')
            ->whereNotNull('stack')
            ->where('stack', '!=', '')
            ->groupBy('stack')
            ->orderByRaw('MAX(date) DESC')
            ->orderBy('stack', 'desc');

        $totalStacks = (clone $stackQuery)->get()->count();
        $page = max(1, (int) $request->get('page', 1));
        $lastPage = (int) ceil($totalStacks / $perPage) ?: 1;
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $stacksForPage = (clone $stackQuery)->offset($offset)->limit($perPage)->pluck('stack');

        // Ambil semua baris untuk stack di halaman ini, lalu group by stack (dengan relasi creator)
        $rows = (clone $baseQuery)
            ->with('creator:id,name')
            ->whereIn('stack', $stacksForPage->toArray())
            ->orderBy('date', 'desc')
            ->orderBy('book_code')
            ->get();

        $groupedByStack = $rows->groupBy('stack');

        // Doc id → nkb_cancelled_at (untuk baris merah: NKB dari NPPB ini pernah dibatalkan)
        $documentIds = $rows->pluck('document_id')->filter()->unique()->values()->all();
        $docNkbCancelled = collect();
        if (!empty($documentIds)) {
            $docNkbCancelled = NppbDocument::whereIn('id', $documentIds)
                ->get()
                ->keyBy('id')
                ->map(fn ($d) => $d->nkb_cancelled_at);
        }

        // List ringkas per stack: stack, tanggal, jumlah, pembuat, sudah disetujui, NKB pernah dibatalkan
        $stackList = $stacksForPage->map(function ($stack) use ($groupedByStack, $docNkbCancelled) {
            $rowsInStack = $groupedByStack[$stack] ?? collect();
            $firstRow = $rowsInStack->first();
            $documentId = $firstRow && $firstRow->document_id ? $firstRow->document_id : null;
            $nkbCancelledAt = $documentId ? ($docNkbCancelled->get($documentId) ?? null) : null;
            return (object)[
                'stack' => $stack,
                'date' => $rowsInStack->max('date'),
                'count' => $rowsInStack->count(),
                'creator_name' => $firstRow && $firstRow->creator ? $firstRow->creator->name : '-',
                'has_document' => $rowsInStack->contains(fn ($r) => !empty($r->document_id)),
                'nkb_was_cancelled' => $nkbCancelledAt !== null,
            ];
        });

        $branches = Branch::query()
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->whereIn('branch_code', $filteredBranchCodes);
            })
            ->orderBy('branch_name')
            ->get(['branch_code', 'branch_name']);

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'stackList' => $stackList,
            'totalStacks' => $totalStacks,
            'currentPage' => $page,
            'lastPage' => $lastPage,
            'perPage' => $perPage,
            'branches' => $branches,
            'queryString' => $request->query(),
        ];

        return view($this->callbackfolder . '.preparation-notes.index', $data);
    }

    /**
     * Detail satu stack: full list baris NPPB untuk stack tersebut.
     */
    public function detail(Request $request)
    {
        $stack = $request->get('stack');
        if ($stack === null || $stack === '') {
            return redirect()->route('preparation_notes.index')->with('error', 'Stack tidak valid.');
        }

        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $rows = NppbCentral::query()
            ->with('creator:id,name')
            ->where('stack', $stack)
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->whereIn('branch_code', $filteredBranchCodes);
            })
            ->orderBy('date', 'desc')
            ->orderBy('book_code')
            ->get();

        $creatorName = $rows->isNotEmpty() && $rows->first()->relationLoaded('creator') && $rows->first()->creator
            ? $rows->first()->creator->name
            : ($rows->isNotEmpty() ? \App\Models\User::find($rows->first()->created_by)?->name : null) ?? '-';

        $totalTypeBooks = $rows->pluck('book_code')->unique()->count();
        $totalExemplar = (int) $rows->sum('exp');
        $hasDocument = $rows->isNotEmpty() && $rows->first()->document_id !== null;

        $existingNkb = null;
        if ($hasDocument && $rows->isNotEmpty()) {
            $document = NppbDocument::find($rows->first()->document_id);
            if ($document) {
                $existingNkb = Nkb::where('nppb_code', $document->number)->first();
            }
        }

        $branches = Branch::query()
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->whereIn('branch_code', $filteredBranchCodes);
            })
            ->orderBy('branch_name')
            ->get(['branch_code', 'branch_name']);

        // Pengirim selalu PS00; penerima dari data stack (jika semua baris satu cabang = cabang itu, else cabang pertama)
        $defaultSenderCode = 'PS00';
        $branchCodesInStack = $rows->pluck('branch_code')->unique()->values();
        $defaultRecipientCode = $branchCodesInStack->count() === 1
            ? $branchCodesInStack->first()
            : $branchCodesInStack->first();
        $defaultRecipientName = $branches->firstWhere('branch_code', $defaultRecipientCode)?->branch_name ?? $defaultRecipientCode;

        $data = [
            'title' => $this->title . ' - Detail',
            'base_url' => $this->base_url,
            'stack' => $stack,
            'creator_name' => $creatorName,
            'rows' => $rows,
            'total_type_books' => $totalTypeBooks,
            'total_exemplar' => $totalExemplar,
            'has_document' => $hasDocument,
            'existing_nkb' => $existingNkb,
            'branches' => $branches,
            'default_sender_code' => $defaultSenderCode,
            'default_recipient_code' => $defaultRecipientCode,
            'default_recipient_name' => $defaultRecipientName,
        ];

        return view($this->callbackfolder . '.preparation-notes.detail', $data);
    }

    /**
     * Update baris detail (volume, koli, pls, exp) untuk stack yang dipilih.
     */
    public function updateDetail(Request $request)
    {
        $request->validate([
            'stack' => 'required|string|max:50',
            'rows' => 'required|array',
            'rows.*.id' => 'required|integer|exists:nppb_centrals,id',
            'rows.*.volume' => 'nullable|numeric|min:0',
            'rows.*.koli' => 'nullable|numeric|min:0',
            'rows.*.pls' => 'nullable|numeric|min:0',
            'rows.*.exp' => 'nullable|numeric|min:0',
        ], [], [
            'stack' => 'Stack',
            'rows.*.volume' => 'Isi',
            'rows.*.koli' => 'Koli',
            'rows.*.pls' => 'Eceran',
            'rows.*.exp' => 'Total',
        ]);

        $stack = $request->input('stack');
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        // Jika stack sudah punya dokumen (sudah approve), tidak boleh edit
        $stackHasDocument = NppbCentral::where('stack', $stack)
            ->when($filteredBranchCodes !== null, fn($q) => $q->whereIn('branch_code', $filteredBranchCodes))
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->exists();
        if ($stackHasDocument) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Data rencana ini sudah disetujui. Perubahan tidak dapat disimpan.');
        }

        $updated = 0;
        foreach ($request->input('rows') as $item) {
            $query = NppbCentral::where('id', $item['id'])
                ->where('stack', $stack);
            if ($filteredBranchCodes !== null) {
                $query->whereIn('branch_code', $filteredBranchCodes);
            }
            $row = $query->first();
            if ($row && $row->document_id === null) {
                $row->volume = (float) ($item['volume'] ?? 0);
                $row->koli = (float) ($item['koli'] ?? 0);
                $row->pls = (float) ($item['pls'] ?? 0);
                $row->exp = (float) ($item['exp'] ?? 0);
                $row->save();
                $updated++;
            }
        }

        $redirect = redirect()->route('preparation_notes.detail', ['stack' => $stack]);
        return $updated > 0
            ? $redirect->with('success', $updated . ' baris berhasil diperbarui.')
            : $redirect->with('error', 'Tidak ada data yang diperbarui.');
    }

    /**
     * Approve rencana: buat nppb_document dan isi document_id di semua nppb_centrals stack tersebut.
     */
    public function approveRencana(Request $request)
    {
        $request->validate([
            'stack' => 'required|string|max:255',
            'note' => 'required|string',
            'send_date' => 'required|date',
            'total_type_books' => 'required|integer|min:0',
            'total_exemplar' => 'required|integer|min:0',
            'note_more' => 'required|string',
            'creator_name' => 'required|string|max:255',
            'known_name' => 'required|string|max:255',
        ], [], [
            'note' => 'Catatan',
            'send_date' => 'Tanggal Kirim',
            'total_type_books' => 'Total Jenis Buku',
            'total_exemplar' => 'Total Eksemplar',
            'note_more' => 'Catatan Tambahan',
            'creator_name' => 'Nama Pembuat',
            'known_name' => 'Nama Dikenal',
        ]);

        $stack = $request->input('stack');
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $query = NppbCentral::where('stack', $stack);
        if ($filteredBranchCodes !== null) {
            $query->whereIn('branch_code', $filteredBranchCodes);
        }
        $rows = $query->get();
        if ($rows->isEmpty()) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Tidak ada data rencana untuk stack ini.');
        }

        // Pengirim selalu PS00; penerima dari data stack (satu cabang unik atau cabang pertama)
        $senderCode = 'PS00';
        $branchCodesInStack = $rows->pluck('branch_code')->unique()->values();
        $recipientCode = $branchCodesInStack->count() >= 1 ? $branchCodesInStack->first() : null;
        if (!$recipientCode) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Tidak dapat menentukan cabang penerima dari data stack.');
        }

        $doc = DB::transaction(function () use ($request, $stack, $filteredBranchCodes, $senderCode, $recipientCode) {
            $number = NppbDocument::generateNextNumber();
            $doc = NppbDocument::create([
                'number' => $number,
                'note' => $request->input('note'),
                'sender_code' => $senderCode,
                'recipient_code' => $recipientCode,
                'send_date' => $request->input('send_date'),
                'total_type_books' => (int) $request->input('total_type_books'),
                'total_exemplar' => (int) $request->input('total_exemplar'),
                'note_more' => $request->input('note_more', ''),
                'creator_name' => $request->input('creator_name', ''),
                'known_name' => $request->input('known_name', ''),
                'created_by' => Auth::id(),
            ]);

            $updateQuery = NppbCentral::where('stack', $stack);
            if ($filteredBranchCodes !== null) {
                $updateQuery->whereIn('branch_code', $filteredBranchCodes);
            }
            $updateQuery->update(['document_id' => $doc->id]);

            return $doc;
        });

        return redirect()->route('preparation_notes.detail', ['stack' => $stack])
            ->with('success', 'Rencana berhasil disetujui. Dokumen NPPB ' . $doc->number . ' telah dibuat.');
    }

    /**
     * Hapus item (baris NPPB) yang dipilih dari detail stack.
     * Hanya boleh jika stack belum punya dokumen (belum disetujui).
     */
    public function deleteDetailRows(Request $request)
    {
        $request->validate([
            'stack' => 'required|string|max:255',
            'rows' => 'required|array',
            'rows.*' => 'required|integer|exists:nppb_centrals,id',
        ], [], [
            'stack' => 'Stack',
            'rows' => 'Item',
        ]);

        $stack = $request->input('stack');
        $ids = $request->input('rows');
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $query = NppbCentral::where('stack', $stack)
            ->whereIn('id', $ids)
            ->whereNull('document_id');
        if ($filteredBranchCodes !== null) {
            $query->whereIn('branch_code', $filteredBranchCodes);
        }
        $deleted = $query->delete();

        return redirect()->route('preparation_notes.detail', ['stack' => $stack])
            ->with('success', $deleted . ' item berhasil dihapus.');
    }

    /**
     * Batalkan/hapus rencana (seluruh stack). Boleh untuk stack belum disetujui maupun sudah disetujui.
     * Jika sudah disetujui: dokumen NPPB ikut dihapus.
     */
    public function cancelRencana(Request $request)
    {
        $request->validate([
            'stack' => 'required|string|max:255',
        ], [], ['stack' => 'Stack']);

        $stack = $request->input('stack');
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $query = NppbCentral::where('stack', $stack);
        if ($filteredBranchCodes !== null) {
            $query->whereIn('branch_code', $filteredBranchCodes);
        }
        $rows = $query->get();
        if ($rows->isEmpty()) {
            return redirect()->route('preparation_notes.index')
                ->with('error', 'Rencana tidak ditemukan.');
        }

        $documentId = $rows->first()->document_id;

        DB::transaction(function () use ($query, $documentId) {
            $query->delete();
            if ($documentId) {
                NppbDocument::where('id', $documentId)->delete();
            }
        });

        $deleted = $rows->count();
        $msg = 'Rencana ' . $stack . ' berhasil dibatalkan (' . $deleted . ' baris dihapus).';
        if ($documentId) {
            $msg .= ' Dokumen NPPB terkait juga telah dihapus.';
        }
        return redirect()->route('preparation_notes.index')->with('success', $msg);
    }

    /**
     * Export / tampilkan Nota Permintaan Penyiapan Barang (hanya jika stack sudah punya dokumen).
     */
    public function exportNota(Request $request)
    {
        $stack = $request->get('stack');
        if ($stack === null || $stack === '') {
            return redirect()->route('preparation_notes.index')->with('error', 'Stack tidak valid.');
        }

        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        // Cari salah satu baris di stack ini yang sudah punya document_id (stack sudah disetujui)
        $firstRow = NppbCentral::where('stack', $stack)
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->first();

        if (!$firstRow || !$firstRow->document_id) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Data belum disetujui. Export nota hanya untuk rencana yang sudah disetujui.');
        }

        $document = NppbDocument::with(['nppbCentrals' => function ($q) use ($filteredBranchCodes) {
            $q->orderBy('book_code');
            if ($filteredBranchCodes !== null) {
                $q->whereIn('branch_code', $filteredBranchCodes);
            }
        }, 'senderBranch', 'recipientBranch'])
            ->find($firstRow->document_id);

        if (!$document) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Dokumen tidak ditemukan.');
        }

        $rows = $document->nppbCentrals;
        $totalKoli = (int) $rows->sum('koli');
        $totalPls = (int) $rows->sum('pls');
        $totalEx = (int) $rows->sum('exp');

        $bookCodes = $rows->pluck('book_code')->unique()->filter()->values()->all();
        $pricesByBook = Product::whereIn('book_code', $bookCodes)
            ->get(['book_code', 'sale_price'])
            ->keyBy('book_code');

        $totalRp = 0;
        foreach ($rows as $row) {
            $price = $pricesByBook->get($row->book_code);
            $unitPrice = $price ? (float) $price->sale_price : 0;
            $totalRp += $row->exp * $unitPrice;
        }

        $data = [
            'document' => $document,
            'rows' => $rows,
            'total_koli' => $totalKoli,
            'total_pls' => $totalPls,
            'total_ex' => $totalEx,
            'total_rp' => $totalRp,
            'prices_by_book' => $pricesByBook,
            'stack' => $stack,
        ];

        return view('pdf.nppb', $data);
    }

    /**
     * Halaman preview NKB — pilih item lalu submit ke jadikanNkb.
     */
    public function previewNkbPage(Request $request)
    {
        $stack = $request->get('stack');
        if ($stack === null || $stack === '') {
            return redirect()->route('preparation_notes.index')->with('error', 'Stack tidak valid.');
        }

        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        $firstRow = NppbCentral::where('stack', $stack)
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->first();

        if (!$firstRow || !$firstRow->document_id) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Data belum disetujui. NKB hanya dapat dibuat dari rencana yang sudah disetujui.');
        }

        $document = NppbDocument::with(['nppbCentrals' => function ($q) use ($filteredBranchCodes) {
            $q->orderBy('book_code');
            if ($filteredBranchCodes !== null) {
                $q->whereIn('branch_code', $filteredBranchCodes);
            }
        }, 'senderBranch', 'recipientBranch'])->find($firstRow->document_id);

        if (!$document) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Dokumen tidak ditemukan.');
        }

        $existingNkb = Nkb::where('nppb_code', $document->number)->first();
        if ($existingNkb) {
            return redirect()->route('preparation_notes.view_nkb', ['number' => $existingNkb->number]);
        }

        $rows = $document->nppbCentrals;
        $bookCodes = $rows->pluck('book_code')->unique()->filter()->values()->all();
        $pricesByBook = Product::whereIn('book_code', $bookCodes)->get(['book_code', 'sale_price'])->keyBy('book_code');

        $items = $rows->map(function ($row) use ($pricesByBook) {
            $price = $pricesByBook->get($row->book_code);
            $harga = $price ? (float) $price->sale_price : 0;
            $exp = (int) $row->exp;
            return (object) [
                'id' => $row->id,
                'book_code' => $row->book_code,
                'book_name' => $row->book_name,
                'koli' => (int) $row->koli,
                'volume' => (int) $row->volume,
                'exp' => $exp,
                'harga_buku' => $harga,
                'subtotal' => $exp * $harga,
            ];
        });

        return view($this->callbackfolder . '.preparation-notes.preview-nkb', [
            'stack' => $stack,
            'document' => $document,
            'items' => $items,
            'from' => $request->get('from'),
        ]);
    }

    /**
     * Halaman Lihat NKB (read-only) — dipakai ketika NKB untuk rencana ini sudah pernah dibuat.
     */
    public function viewNkb($number)
    {
        $nkb = Nkb::with(['items', 'senderBranch', 'recipientBranch'])
            ->where('number', $number)
            ->firstOrFail();

        $stack = null;
        $doc = NppbDocument::where('number', $nkb->nppb_code)->first();
        if ($doc) {
            $stack = NppbCentral::where('document_id', $doc->id)->value('stack');
        }

        return view($this->callbackfolder . '.preparation-notes.view-nkb', [
            'nkb' => $nkb,
            'stack' => $stack,
        ]);
    }

    /**
     * Preview data untuk modal "Jadikan NKB" (JSON).
     */
    public function previewNkb(Request $request)
    {
        $stack = $request->get('stack');
        if ($stack === null || $stack === '') {
            return response()->json(['error' => 'Stack tidak valid.'], 400);
        }

        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        $firstRow = NppbCentral::where('stack', $stack)
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->first();

        if (!$firstRow || !$firstRow->document_id) {
            return response()->json(['error' => 'Data belum disetujui.'], 400);
        }

        $document = NppbDocument::with(['nppbCentrals' => function ($q) use ($filteredBranchCodes) {
            $q->orderBy('book_code');
            if ($filteredBranchCodes !== null) {
                $q->whereIn('branch_code', $filteredBranchCodes);
            }
        }, 'senderBranch', 'recipientBranch'])->find($firstRow->document_id);

        if (!$document) {
            return response()->json(['error' => 'Dokumen tidak ditemukan.'], 404);
        }

        if (Nkb::where('nppb_code', $document->number)->exists()) {
            return response()->json(['error' => 'NKB untuk rencana ini sudah pernah dibuat.'], 400);
        }

        $rows = $document->nppbCentrals;
        $bookCodes = $rows->pluck('book_code')->unique()->filter()->values()->all();
        $pricesByBook = Product::whereIn('book_code', $bookCodes)->get(['book_code', 'sale_price'])->keyBy('book_code');

        $items = $rows->map(function ($row) use ($pricesByBook) {
            $price = $pricesByBook->get($row->book_code);
            $harga = $price ? (float) $price->sale_price : 0;
            $exp = (int) $row->exp;
            return [
                'id' => $row->id,
                'book_code' => $row->book_code,
                'book_name' => $row->book_name,
                'koli' => (int) $row->koli,
                'volume' => (int) $row->volume,
                'exp' => $exp,
                'harga_buku' => $harga,
                'subtotal' => $exp * $harga,
            ];
        })->values()->all();

        return response()->json([
            'document' => [
                'number' => $document->number,
                'note' => $document->note,
                'note_more' => $document->note_more,
                'sender_code' => $document->sender_code,
                'recipient_code' => $document->recipient_code,
                'send_date' => $document->send_date ? $document->send_date->format('Y-m-d') : null,
                'sender_name' => $document->senderBranch->branch_name ?? $document->sender_code,
                'recipient_name' => $document->recipientBranch->branch_name ?? $document->recipient_code,
            ],
            'items' => $items,
        ]);
    }

    /**
     * Jadikan NKB: buat record NKB + nkb_items dari dokumen NPPB yang sudah disetujui.
     * Bisa hanya item yang dipilih (row_ids); kalau tidak dikirim, semua item dipakai.
     */
    public function jadikanNkb(Request $request)
    {
        $request->validate([
            'stack' => 'required|string|max:255',
            'creator_name' => 'required|string|max:255',
            'known_name' => 'required|string|max:255',
            'row_ids' => 'sometimes|array',
            'row_ids.*' => 'integer|exists:nppb_centrals,id',
        ], [], [
            'creator_name' => 'Nama Pembuat',
            'known_name' => 'Nama Dikenal',
        ]);

        $stack = $request->input('stack');
        $rowIds = $request->input('row_ids', []);
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $firstRow = NppbCentral::where('stack', $stack)
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->first();

        if (!$firstRow || !$firstRow->document_id) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Data belum disetujui. NKB hanya dapat dibuat dari rencana yang sudah disetujui.');
        }

        $document = NppbDocument::with(['nppbCentrals' => function ($q) use ($filteredBranchCodes, $rowIds) {
            $q->orderBy('book_code');
            if ($filteredBranchCodes !== null) {
                $q->whereIn('branch_code', $filteredBranchCodes);
            }
            if (!empty($rowIds)) {
                $q->whereIn('id', $rowIds);
            }
        }])->find($firstRow->document_id);

        if (!$document) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Dokumen tidak ditemukan.');
        }

        $existingNkb = Nkb::where('nppb_code', $document->number)->first();
        if ($existingNkb) {
            return redirect()->route('preparation_notes.view_nkb', ['number' => $existingNkb->number]);
        }

        $rows = $document->nppbCentrals;
        if ($rows->isEmpty()) {
            return redirect()->route('preparation_notes.detail', ['stack' => $stack])
                ->with('error', 'Pilih minimal satu item untuk dibuat NKB.');
        }

        // Ambil nilai koli, volume, exp dari form (bisa diedit); fallback ke data NPPB
        $rowsWithEdits = $rows->map(function ($row) use ($request) {
            $koli = (int) $request->input('items.' . $row->id . '.koli', $row->koli);
            $volume = (int) $request->input('items.' . $row->id . '.volume', $row->volume);
            $exp = (int) $request->input('items.' . $row->id . '.exp', $row->exp);
            $pls = max(0, $exp - $koli * $volume);
            return (object) [
                'row' => $row,
                'koli' => $koli,
                'volume' => $volume,
                'exp' => $exp,
                'pls' => $pls,
            ];
        });

        $totalTypeBooks = $rowsWithEdits->pluck('row.book_code')->unique()->count();
        $totalExemplar = (int) $rowsWithEdits->sum('exp');

        $creatorName = $request->input('creator_name', '');
        $knownName = $request->input('known_name', '');

        $nkb = DB::transaction(function () use ($document, $rowsWithEdits, $totalTypeBooks, $totalExemplar, $creatorName, $knownName) {
            $number = Nkb::generateNextNumber($document->sender_code ?? '');
            $nkb = Nkb::create([
                'number' => $number,
                'nppb_code' => $document->number,
                'note' => $document->note ?? '',
                'creator_name' => $creatorName,
                'known_name' => $knownName,
                'sender_code' => $document->sender_code,
                'recipient_code' => $document->recipient_code,
                'send_date' => $document->send_date,
                'total_type_books' => $totalTypeBooks,
                'total_exemplar' => $totalExemplar,
                'note_more' => $document->note_more ?? '',
                'created_by' => Auth::id(),
            ]);

            foreach ($rowsWithEdits as $edit) {
                $row = $edit->row;
                NkbItem::create([
                    'nkb_code' => $nkb->number,
                    'book_code' => $row->book_code ?? '',
                    'book_name' => $row->book_name ?? '',
                    'koli' => $edit->koli,
                    'pls' => $edit->pls,
                    'exp' => $edit->exp,
                    'volume' => $edit->volume,
                ]);
            }

            // NKB baru dibuat → hilangkan tanda "NKB dibatalkan" agar baris di preparation-notes tidak merah lagi
            $document->update(['nkb_cancelled_at' => null]);

            // Kurangi stock pusat: catat deduction per book_code (eksemplar NKB) agar tampil di nppb-central/nppb-warehouse
            $expByBook = $rowsWithEdits->groupBy('row.book_code')->map(fn ($items) => $items->sum('exp'));
            foreach ($expByBook as $bookCode => $qty) {
                if ($bookCode !== '' && $qty > 0) {
                    CentralStockDeduction::create([
                        'book_code' => $bookCode,
                        'quantity' => (int) $qty,
                        'source_type' => CentralStockDeduction::SOURCE_NKB,
                        'source_id' => $nkb->number,
                    ]);
                }
            }

            return $nkb;
        });

        if ($request->input('from') === 'nkb') {
            return redirect()->route('nkb.show', ['number' => $nkb->number])
                ->with('success', 'NKB ' . $nkb->number . ' berhasil dibuat dari NPPB ' . $document->number . '.');
        }
        return redirect()->route('preparation_notes.detail', ['stack' => $stack])
            ->with('success', 'NKB ' . $nkb->number . ' berhasil dibuat dari NPPB ' . $document->number . '.');
    }
}
