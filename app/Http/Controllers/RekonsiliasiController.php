<?php

namespace App\Http\Controllers;

use App\Models\Nkb;
use App\Models\NkbItem;
use App\Models\Branch;
use App\Models\ReceiveBookNoteDetail;
use App\Imports\NtbImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class RekonsiliasiController extends Controller
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
        $legacy = DB::table('delivery_notes')
            ->select(
                DB::raw('nota_kirim_cab COLLATE utf8mb4_unicode_ci as number'),
                DB::raw('branch_sender COLLATE utf8mb4_unicode_ci as sender_code'),
                DB::raw('branch_code COLLATE utf8mb4_unicode_ci as recipient_code'),
                'send_date',
                DB::raw("'legacy' COLLATE utf8mb4_unicode_ci as source"),
                DB::raw('(SELECT COALESCE(SUM(total_exemplar), 0) FROM delivery_note_details WHERE delivery_note_details.nota_kirim_cab = delivery_notes.nota_kirim_cab) as total_exemplar')
            );

        $new = DB::table('nkbs')
            ->select(
                DB::raw('number COLLATE utf8mb4_unicode_ci as number'),
                DB::raw('sender_code COLLATE utf8mb4_unicode_ci as sender_code'),
                DB::raw('recipient_code COLLATE utf8mb4_unicode_ci as recipient_code'),
                'send_date',
                DB::raw("'new' COLLATE utf8mb4_unicode_ci as source"),
                'total_exemplar'
            );

        $union = $legacy->union($new);

        $query = DB::table(DB::raw("({$union->toSql()}) as combined"))
            ->mergeBindings($union)
            ->leftJoin('m_terima_buku', DB::raw('m_terima_buku.nota_kirim_cab COLLATE utf8mb4_unicode_ci'), '=', DB::raw('combined.number COLLATE utf8mb4_unicode_ci'))
            ->select(
                'combined.*',
                'm_terima_buku.retur_date',
                'm_terima_buku.receive_code'
            )
            ->addSelect([
                'total_terima' => DB::table('d_terima_buku')
                    ->selectRaw('COALESCE(SUM(total_exemplar), 0)')
                    ->whereRaw('d_terima_buku.nota_kirim_cab COLLATE utf8mb4_unicode_ci = combined.number COLLATE utf8mb4_unicode_ci')
            ])
            ->orderBy('total_terima', 'asc')
            ->orderByDesc('combined.send_date');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('combined.number', 'like', '%' . $s . '%')
                  ->orWhere('combined.recipient_code', 'like', '%' . $s . '%')
                  ->orWhere('combined.sender_code', 'like', '%' . $s . '%');
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status == 'match') {
                $query->havingRaw('combined.total_exemplar = total_terima')
                      ->having('total_terima', '>', 0);
            } elseif ($status == 'selisih') {
                $query->havingRaw('combined.total_exemplar != total_terima')
                      ->having('total_terima', '>', 0);
            } elseif ($status == 'belum') {
                $query->having('total_terima', '=', 0);
            }
        }

        if ($request->filled('branch')) {
            $b = $request->branch;
            $query->where(function ($q) use ($b) {
                $q->where('combined.sender_code', $b)
                  ->orWhere('combined.recipient_code', $b);
            });
        }

        $items = $query->paginate(20)->withQueryString();
        $branches = Branch::orderBy('branch_code')->get();

        return view($this->callbackfolder . '.rekonsiliasi.index', [
            'items'       => $items,
            'branches'    => $branches,
            'queryString' => $request->query(),
        ]);
    }

    public function show($number)
    {
        $nkbNew = Nkb::with(['items', 'receiveNote'])->where('number', $number)->first();
        $nkbLegacy = null;
        
        if (!$nkbNew) {
            $nkbLegacy = \App\Models\DeliveryNote::with(['details.product:book_code,book_title', 'receiveNote'])->where('nota_kirim_cab', $number)->firstOrFail();
        }
        
        $ntbItemsQuery = DB::table('d_terima_buku')
            ->whereRaw('nota_kirim_cab COLLATE utf8mb4_unicode_ci = ?', [$number])
            ->get();
            
        // Extract array of products from d_terima_buku explicitly to fetch their titles
        $ntbBookCodes = $ntbItemsQuery->pluck('book_code')->unique()->toArray();
        $products = DB::table('books')->whereIn('book_code', $ntbBookCodes)->pluck('book_title', 'book_code');

        // Compile combined list by grouping by book_code
        $comparison = [];

        // 1. Process NKB
        if ($nkbNew) {
            foreach ($nkbNew->items as $detail) {
                $comp = [
                    'book_code' => $detail->book_code,
                    'book_title' => $detail->book_name ?: 'Unknown Title',
                    'nkb_koli' => (int) $detail->koli,
                    'nkb_eceran' => (int) $detail->pls,
                    'nkb_total' => (int) $detail->exp,
                    'ntb_koli' => 0,
                    'ntb_eceran' => 0,
                    'ntb_total' => 0,
                ];
                $comparison[$detail->book_code] = $comp;
            }
        } else {
            foreach ($nkbLegacy->details as $detail) {
                $comp = [
                    'book_code' => $detail->book_code,
                    'book_title' => $detail->product ? $detail->product->book_title : 'Unknown Title',
                    'nkb_koli' => (int) $detail->koli,
                    'nkb_eceran' => (int) $detail->exemplar,
                    'nkb_total' => (int) $detail->total_exemplar,
                    'ntb_koli' => 0,
                    'ntb_eceran' => 0,
                    'ntb_total' => 0,
                ];
                $comparison[$detail->book_code] = $comp;
            }
        }

        // 2. Process NTB 
        foreach ($ntbItemsQuery as $item) {
            if (!isset($comparison[$item->book_code])) {
                $comparison[$item->book_code] = [
                    'book_code' => $item->book_code,
                    'book_title' => $products[$item->book_code] ?? 'Unknown Title',
                    'nkb_koli' => 0,
                    'nkb_eceran' => 0,
                    'nkb_total' => 0,
                    'ntb_koli' => 0,
                    'ntb_eceran' => 0,
                    'ntb_total' => 0,
                ];
            }
            $comparison[$item->book_code]['ntb_koli'] += (int) $item->koli;
            $comparison[$item->book_code]['ntb_eceran'] += (int) $item->exemplar;
            $comparison[$item->book_code]['ntb_total'] += (int) $item->total_exemplar;
        }

        // 3. Compute Selisih
        foreach ($comparison as $code => &$data) {
            $data['diff_koli'] = $data['ntb_koli'] - $data['nkb_koli'];
            $data['diff_eceran'] = $data['ntb_eceran'] - $data['nkb_eceran'];
            $data['diff_total'] = $data['ntb_total'] - $data['nkb_total'];
        }
        unset($data);

        $itemData = (object)[
            'number' => $nkbNew ? $nkbNew->number : $nkbLegacy->nota_kirim_cab,
            'sender_code' => $nkbNew ? $nkbNew->sender_code : $nkbLegacy->branch_sender,
            'recipient_code' => $nkbNew ? $nkbNew->recipient_code : $nkbLegacy->branch_code,
            'send_date' => $nkbNew ? $nkbNew->send_date : $nkbLegacy->send_date,
            'receiveNote' => $nkbNew ? $nkbNew->receiveNote : $nkbLegacy->receiveNote,
            'source' => $nkbNew ? 'new' : 'legacy'
        ];

        return view($this->callbackfolder . '.rekonsiliasi.show', [
            'item' => $itemData,
            'comparison' => collect($comparison)->values(),
        ]);
    }

    public function importNtb(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'file.required' => 'File wajib dipilih.',
            'file.mimes'    => 'Format file harus xlsx, xls, atau csv.',
            'file.max'      => 'Ukuran file maksimal 5 MB.',
        ]);

        try {
            $import = new NtbImport();
            Excel::import($import, $request->file('file'));

            $msg = "Import berhasil! {$import->inserted} detail buku berhasil diimport.";
            if ($import->skipped > 0) {
                $msg .= " {$import->skipped} baris dilewati (sudah ada).";
            }
            if (!empty($import->errors)) {
                $msg .= ' Namun ada beberapa error: ' . implode(', ', array_slice($import->errors, 0, 5));
                return redirect()->route('rekonsiliasi.index')->with('warning', $msg);
            }

            return redirect()->route('rekonsiliasi.index')->with('success', $msg);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = collect($e->failures())->map(fn($f) => "Baris {$f->row()}: " . implode(', ', $f->errors()))->implode(' | ');
            return redirect()->back()->with('error', 'Validasi file gagal: ' . $failures);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal mengimport file: ' . $e->getMessage());
        }
    }
}
