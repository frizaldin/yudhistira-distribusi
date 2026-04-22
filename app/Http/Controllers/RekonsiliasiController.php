<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteDetail;
use App\Models\ReceiveBookNoteDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
        // Calculate totals via subqueries for performance
        $query = DeliveryNote::query()
            ->with(['receiveNote:nota_kirim_cab,retur_date'])
            ->select('delivery_notes.*')
            ->addSelect([
                'total_kirim' => DB::table('delivery_note_details')
                    ->selectRaw('COALESCE(SUM(total_exemplar), 0)')
                    ->whereColumn('delivery_note_details.nota_kirim_cab', 'delivery_notes.nota_kirim_cab'),

                'total_terima' => DB::table('d_terima_buku')
                    ->selectRaw('COALESCE(SUM(total_exemplar), 0)')
                    ->whereColumn('d_terima_buku.nota_kirim_cab', 'delivery_notes.nota_kirim_cab')
            ])
            ->orderByDesc('send_date');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('delivery_notes.nota_kirim_cab', 'like', '%' . $s . '%')
                  ->orWhere('branch_code', 'like', '%' . $s . '%')
                  ->orWhere('branch_sender', 'like', '%' . $s . '%');
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status == 'match') {
                $query->havingRaw('(SELECT COALESCE(SUM(total_exemplar), 0) FROM delivery_note_details WHERE delivery_note_details.nota_kirim_cab = delivery_notes.nota_kirim_cab) = (SELECT COALESCE(SUM(total_exemplar), 0) FROM d_terima_buku WHERE d_terima_buku.nota_kirim_cab = delivery_notes.nota_kirim_cab)')
                      ->havingRaw('(SELECT COALESCE(SUM(total_exemplar), 0) FROM d_terima_buku WHERE d_terima_buku.nota_kirim_cab = delivery_notes.nota_kirim_cab) > 0');
            } elseif ($status == 'selisih') {
                $query->havingRaw('(SELECT COALESCE(SUM(total_exemplar), 0) FROM delivery_note_details WHERE delivery_note_details.nota_kirim_cab = delivery_notes.nota_kirim_cab) != (SELECT COALESCE(SUM(total_exemplar), 0) FROM d_terima_buku WHERE d_terima_buku.nota_kirim_cab = delivery_notes.nota_kirim_cab)')
                      ->havingRaw('(SELECT COALESCE(SUM(total_exemplar), 0) FROM d_terima_buku WHERE d_terima_buku.nota_kirim_cab = delivery_notes.nota_kirim_cab) > 0');
            } elseif ($status == 'belum') {
                $query->havingRaw('(SELECT COALESCE(SUM(total_exemplar), 0) FROM d_terima_buku WHERE d_terima_buku.nota_kirim_cab = delivery_notes.nota_kirim_cab) = 0');
            }
        }

        $items = $query->paginate(20)->withQueryString();

        return view($this->callbackfolder . '.rekonsiliasi.index', [
            'items'       => $items,
            'queryString' => $request->query(),
        ]);
    }

    public function show($nota_kirim_cab)
    {
        $nkb = DeliveryNote::with(['details.product:book_code,book_title', 'receiveNote'])->where('nota_kirim_cab', $nota_kirim_cab)->firstOrFail();
        
        $ntbItemsQuery = DB::table('d_terima_buku')
            ->where('nota_kirim_cab', $nota_kirim_cab)
            ->get();
            
        // Extract array of products from d_terima_buku explicitly to fetch their titles
        $ntbBookCodes = $ntbItemsQuery->pluck('book_code')->unique()->toArray();
        $products = DB::table('books')->whereIn('book_code', $ntbBookCodes)->pluck('book_title', 'book_code');

        // Compile combined list by grouping by book_code
        $comparison = [];

        // 1. Process NKB
        foreach ($nkb->details as $detail) {
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

        return view($this->callbackfolder . '.rekonsiliasi.show', [
            'item' => $nkb,
            'comparison' => collect($comparison)->values(),
        ]);
    }
}
