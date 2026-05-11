<?php

namespace App\Http\Controllers;

use App\Models\NppbCentral;
use App\Models\NkbItem;
use App\Models\DeliveryOrderItem;
use App\Models\ReceiveBookNoteDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LaporanDistribusiController extends Controller
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
        $startDate = $request->input('start_date', date('Y-m-01'));
        $endDate   = $request->input('end_date', date('Y-m-t'));
        $filterBook = trim($request->input('book_code', ''));

        // ---------------------------------------------------------------
        // 1) NPPB: nppb_centrals, total eksemplar = (koli * pls) + exp
        // ---------------------------------------------------------------
        $nppbQuery = NppbCentral::select([
            'book_code',
            'book_name',
            DB::raw('SUM((koli * pls) + exp) as total_eks'),
        ])->whereBetween('date', [$startDate, $endDate]);
        if ($filterBook !== '') {
            $nppbQuery->where('book_code', $filterBook);
        }
        $nppbByBook = $nppbQuery->groupBy('book_code', 'book_name')->get()->keyBy('book_code');
        $totalNppb  = (int) $nppbByBook->sum('total_eks');

        // ---------------------------------------------------------------
        // 2) NKB: nkb_items join nkbs, total eksemplar = (koli * pls) + exp
        // ---------------------------------------------------------------
        $nkbQuery = NkbItem::select([
            'nkb_items.book_code',
            'nkb_items.book_name',
            DB::raw('SUM((nkb_items.koli * nkb_items.pls) + nkb_items.exp) as total_eks'),
        ])->join('nkbs', 'nkb_items.nkb_code', '=', 'nkbs.number')
          ->whereBetween('nkbs.send_date', [$startDate, $endDate]);
        if ($filterBook !== '') {
            $nkbQuery->where('nkb_items.book_code', $filterBook);
        }
        $nkbByBook = $nkbQuery->groupBy('nkb_items.book_code', 'nkb_items.book_name')->get()->keyBy('book_code');
        $totalNkb  = (int) $nkbByBook->sum('total_eks');

        // ---------------------------------------------------------------
        // 3) Surat Jalan: delivery_order_items join delivery_orders
        //    total eksemplar = SUM(total_ex)
        //    Tidak memiliki book_code langsung — hanya total keseluruhan
        // ---------------------------------------------------------------
        $sjQuery = DeliveryOrderItem::select([
            DB::raw('SUM(delivery_order_items.total_ex) as total_eks'),
        ])->join('delivery_orders', 'delivery_order_items.delivery_order_id', '=', 'delivery_orders.id')
          ->whereBetween('delivery_orders.date', [$startDate, $endDate]);
        $totalSj = (int) ($sjQuery->first()->total_eks ?? 0);

        // ---------------------------------------------------------------
        // 4) NTB: d_terima_buku join m_terima_buku, total eksemplar
        // ---------------------------------------------------------------
        $ntbQuery = ReceiveBookNoteDetail::select([
            'd_terima_buku.book_code',
            DB::raw('SUM(d_terima_buku.total_exemplar) as total_eks'),
        ])->join('m_terima_buku', 'm_terima_buku.nota_kirim_cab', '=', 'd_terima_buku.nota_kirim_cab')
          ->whereBetween('m_terima_buku.send_date', [$startDate, $endDate]);
        if ($filterBook !== '') {
            $ntbQuery->where('d_terima_buku.book_code', $filterBook);
        }
        $ntbByBook = $ntbQuery->groupBy('d_terima_buku.book_code')->get()->keyBy('book_code');
        $totalNtb  = (int) $ntbByBook->sum('total_eks');

        // ---------------------------------------------------------------
        // 5) Gabungkan semua book_code untuk tabel per-buku
        // ---------------------------------------------------------------
        $allBookCodes = collect()
            ->merge($nppbByBook->keys())
            ->merge($nkbByBook->keys())
            ->merge($ntbByBook->keys())
            ->unique()->sort()->values();

        $bookRows = $allBookCodes->map(function ($code) use ($nppbByBook, $nkbByBook, $ntbByBook) {
            $nppb = $nppbByBook->get($code);
            $nkb  = $nkbByBook->get($code);
            $ntb  = $ntbByBook->get($code);
            $bookName = $nppb?->book_name ?? $nkb?->book_name ?? $code;
            return [
                'book_code' => $code,
                'book_name' => $bookName,
                'nppb'      => (int) ($nppb?->total_eks ?? 0),
                'nkb'       => (int) ($nkb?->total_eks ?? 0),
                'ntb'       => (int) ($ntb?->total_eks ?? 0),
            ];
        });

        return view($this->callbackfolder . '.laporan-distribusi.index', [
            'title'      => 'Laporan Distribusi',
            'startDate'  => $startDate,
            'endDate'    => $endDate,
            'filterBook' => $filterBook,
            'totalNppb'  => $totalNppb,
            'totalNkb'   => $totalNkb,
            'totalSj'    => $totalSj,
            'totalNtb'   => $totalNtb,
            'bookRows'   => $bookRows,
        ]);
    }
}
