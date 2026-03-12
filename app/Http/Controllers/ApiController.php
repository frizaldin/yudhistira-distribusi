<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\NppbCentral;
use App\Models\CentralStock;
use App\Models\CentralStockDeduction;
use App\Models\CentralStockKoli;
use App\Models\Periode;
use App\Models\SpBranch;
use App\Models\Target;
use App\Models\CutoffData;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteDetail;
use App\Models\Nkb;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    /**
     * Get branches for Select2 AJAX
     */
    public function getBranches(Request $request): JsonResponse
    {
        $search = $request->get('q', '');
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $branches = Branch::query()
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->when($search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('branch_name', 'like', '%' . $search . '%')
                        ->orWhere('branch_code', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('branch_name')
            ->limit(100)
            ->get();

        $results = $branches->map(function ($branch) {
            return [
                'id' => $branch->branch_code,
                'text' => $branch->branch_code . ' - ' . $branch->branch_name,
                'branch_name' => $branch->branch_name,
            ];
        });

        return response()->json([
            'results' => $results
        ]);
    }

    /**
     * Get distinct warehouse_code from branches (untuk NPPB Warehouse / Rencana Kirim Cabang Area)
     */
    public function getWarehouseCodes(Request $request): JsonResponse
    {
        $warehouseCodes = Branch::select('warehouse_code')
            ->whereNotNull('warehouse_code')
            ->where('warehouse_code', '!=', '')
            ->distinct()
            ->orderBy('warehouse_code')
            ->pluck('warehouse_code');

        $results = $warehouseCodes->map(function ($code) {
            return [
                'id' => $code,
                'text' => $code,
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Get NKB detail (koli, ex, total_ex) untuk auto-fill form Surat Jalan.
     */
    public function getNkbDetail(Request $request, $id): JsonResponse
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $nkb = Nkb::with('items')
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                return $q->where(function ($q2) use ($filteredBranchCodes) {
                    $q2->whereIn('sender_code', $filteredBranchCodes)
                        ->orWhereIn('recipient_code', $filteredBranchCodes);
                });
            })
            ->find($id);

        if (!$nkb) {
            return response()->json(['success' => false, 'message' => 'NKB tidak ditemukan.'], 404);
        }

        $koli = (int) $nkb->items->sum('koli');
        $totalEx = (int) $nkb->items->sum('exp');
        if ($nkb->items->isEmpty() && $nkb->total_exemplar !== null) {
            $totalEx = (int) $nkb->total_exemplar;
        }
        return response()->json([
            'success' => true,
            'koli' => $koli,
            'ex' => $totalEx,
            'total_ex' => $totalEx,
        ]);
    }

    /**
     * Get branches filtered by warehouse_code (untuk NPPB Warehouse)
     */
    public function getBranchesByWarehouse(Request $request): JsonResponse
    {
        $search = $request->get('q', '');
        $warehouseCode = $request->get('warehouse_code', '');
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $branches = Branch::query()
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->when($warehouseCode, function ($query, $warehouseCode) {
                return $query->where('warehouse_code', $warehouseCode);
            })
            ->when($search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('branch_name', 'like', '%' . $search . '%')
                        ->orWhere('branch_code', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('branch_name')
            ->limit(100)
            ->get();

        $results = $branches->map(function ($branch) {
            return [
                'id' => $branch->branch_code,
                'text' => $branch->branch_code . ' - ' . $branch->branch_name,
                'branch_name' => $branch->branch_name,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Get products/books for Select2 AJAX
     */
    public function getProducts(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        $products = Product::query()
            ->when($search, function ($query, $search) {
                return $query->where('book_title', 'like', '%' . $search . '%')
                    ->orWhere('book_code', 'like', '%' . $search . '%');
            })
            ->orderBy('book_title')
            ->limit(50)
            ->get();

        $results = $products->map(function ($product) {
            return [
                'id' => $product->book_code,
                'text' => $product->book_code . ' - ' . $product->book_title,
                'book_name' => $product->book_title,
            ];
        });

        return response()->json([
            'results' => $results
        ]);
    }

    /**
     * Get NPPB Central products by branch_code
     * Returns list of products with editable fields and stock pusat
     */
    public function getNppbProducts(Request $request): JsonResponse
    {
        $branchCode = $request->get('branch_code');
        $currentYear = date('Y');
        $page = (int)$request->get('page', 1);
        $perPageRaw = (int)$request->get('per_page', 100);
        $allowedPerPage = [50, 100, 150, 250, 500];
        $perPage = in_array($perPageRaw, $allowedPerPage) ? $perPageRaw : 100;
        $perPage = min($perPage, 500); // batasi maksimal 500 per halaman agar response cepat
        $searchBookCode = $request->get('search_book_code', '');
        $searchBookName = $request->get('search_book_name', '');
        $percentageRaw = (int)$request->get('percentage', 100);
        $percentage = max(1, min(100, $percentageRaw));
        $skipTotals = $request->boolean('skip_totals');
        $totalsOnly = $request->boolean('totals_only');

        if (!$branchCode) {
            return response()->json([
                'results' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
                'per_page' => $perPage
            ]);
        }

        // User ADP (authority_id 3): akses global; filter branch hanya untuk role cabang (authority_id 2)
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        if ($filteredBranchCodes !== null && !in_array($branchCode, $filteredBranchCodes)) {
            return response()->json([
                'results' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
                'per_page' => $perPage
            ]);
        }

        // Totals only: kembalikan hanya totals untuk seluruh data (tanpa filter) — dipanggil sesi ke-2
        if ($totalsOnly) {
            $activeCutoff = CutoffData::where('status', 'active')->first();
            $productsQueryFull = Product::select('book_code', 'book_title')->orderBy('book_code');
            $totalFull = $productsQueryFull->count();
            $totals = $this->getNppbCentralTotals($branchCode, $activeCutoff, $currentYear, $percentage, $productsQueryFull);
            return response()->json([
                'results' => [],
                'totals' => $totals,
                'current_page' => 1,
                'last_page' => 1,
                'total' => $totalFull,
                'per_page' => $perPage
            ]);
        }

        // Get all products; optional filter: hanya list marketing (is_marketing_list = Y)
        $marketingListOnly = $request->boolean('marketing_list_only');
        $productsQuery = Product::select('book_code', 'book_title');
        if ($marketingListOnly) {
            $productsQuery->where('is_marketing_list', 'Y');
        }

        if (!empty($searchBookCode)) {
            $productsQuery->where('book_code', 'like', '%' . $searchBookCode . '%');
        }
        if (!empty($searchBookName)) {
            $productsQuery->where('book_title', 'like', '%' . $searchBookName . '%');
        }

        // Clone query untuk totals (seluruh halaman) sebelum dipakai untuk pagination — hanya dipakai jika !skip_totals
        $productsQueryForTotals = clone $productsQuery;

        // Paginate products: hanya ambil 1 halaman agar query lookup kecil (bukan load semua produk)
        $totalProducts = $productsQuery->count();
        $lastPage = $totalProducts > 0 ? (int) ceil($totalProducts / $perPage) : 1;
        $page = max(1, min($page, $lastPage));
        $products = $productsQuery->orderBy('book_code')->skip(($page - 1) * $perPage)->take($perPage)->get();

        // Check if there's an active cutoff_data (perlu sebelum empty return)
        $activeCutoff = CutoffData::where('status', 'active')->first();

        if ($products->isEmpty()) {
            $totals = $skipTotals ? null : $this->getNppbCentralTotals($branchCode, $activeCutoff, $currentYear, $percentage, $productsQueryForTotals);
            return response()->json([
                'results' => [],
                'totals' => $totals,
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => 0,
                'per_page' => $perPage
            ]);
        }

        $bookCodesPage = $products->pluck('book_code');

        // Get central stocks (total stock pusat per book_code) - hanya untuk book_code di halaman ini
        $centralStocks = CentralStock::select([
            'book_code',
            DB::raw('SUM(exemplar) as total_stock_pusat')
        ])
            ->whereIn('book_code', $bookCodesPage)
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Pengurangan stock pusat karena NKB / Delivery Order (eksemplar yang sudah keluar)
        $centralStockDeductions = CentralStockDeduction::select([
            'book_code',
            DB::raw('SUM(quantity) as total_deducted')
        ])->whereIn('book_code', $bookCodesPage)->groupBy('book_code')->get()->keyBy('book_code');

        $lastSpBranchSync = Cache::get('sync_sp_branches_progress_last_sync');

        // Get existing NPPB data for this branch and year - hanya untuk book di halaman ini
        $existingNppbQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(koli) as koli'),
            DB::raw('SUM(exp) as exp'),
            DB::raw('SUM(pls) as pls'),
            DB::raw('MAX(updated_at) as nppb_updated_at'),
        ])
            ->where('branch_code', $branchCode)
            ->whereIn('book_code', $bookCodesPage);

        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $existingNppbQuery->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $existingNppbQuery->where('date', '<=', $activeCutoff->end_date);
            }
        } else {
            $existingNppbQuery->whereYear('date', $currentYear);
        }

        $existingNppb = $existingNppbQuery
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Get SP, Faktur, and Stock Cabang from sp_branches - hanya book di halaman ini
        // Use the same $activeCutoff variable from above
        $spBranchQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_sp) as sp'),
            DB::raw('SUM(ex_ftr) as faktur'),
            DB::raw('SUM(ex_stock) as stock_cabang'),
        ])
            ->where('active_data', 'yes')
            ->where('branch_code', $branchCode)
            ->whereIn('book_code', $bookCodesPage);

        // Filter by trans_date if there's an active cutoff_data
        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $spBranchQuery->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $spBranchQuery->where('trans_date', '<=', $activeCutoff->end_date);
            }
        }

        $spBranchData = $spBranchQuery
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Eksemplar NPPB yang sudah diapprove (punya document_id) per cabang+book
        $nppbApprovedExpByBook = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as nppb_approved_exp'),
        ])
            ->where('branch_code', $branchCode)
            ->whereIn('book_code', $bookCodesPage)
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Stock Nasional & SP Nasional & Faktur Nasional - hanya book di halaman ini
        $spBranchNasionalQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_stock) as stock_nasional'),
            DB::raw('SUM(ex_sp) as sp_nasional'),
            DB::raw('SUM(ex_ftr) as faktur_nasional'),
        ])
            ->where('active_data', 'yes')
            ->whereIn('book_code', $bookCodesPage);
        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $spBranchNasionalQuery->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $spBranchNasionalQuery->where('trans_date', '<=', $activeCutoff->end_date);
            }
        }
        $spBranchNasional = $spBranchNasionalQuery
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Intransit nasional (seluruh cabang) untuk % (Ftr+Stk+Kirim vs Target)
        $intransitDataNasional = collect();
        if ($activeCutoff) {
            $dnNasionalQuery = DeliveryNote::query();
            if ($activeCutoff->start_date !== null) {
                $dnNasionalQuery->whereBetween('send_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $dnNasionalQuery->where('send_date', '<=', $activeCutoff->end_date);
            }
            $deliveryNotesNasional = $dnNasionalQuery->pluck('nota_kirim_cab');
            if ($deliveryNotesNasional->isNotEmpty()) {
                $intransitDataNasional = DeliveryNoteDetail::select([
                    'book_code',
                    DB::raw('SUM(exemplar) as total_intransit')
                ])
                    ->whereIn('nota_kirim_cab', $deliveryNotesNasional)
                    ->whereIn('book_code', $bookCodesPage)
                    ->whereNotNull('book_code')
                    ->groupBy('book_code')
                    ->get()
                    ->keyBy('book_code');
            }
        }

        // NPPB Central yang telah disetujui (nasional) - hanya book di halaman ini
        $nppbApprovedExpNasionalQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as nppb_approved_exp'),
        ])
            ->whereIn('book_code', $bookCodesPage)
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->groupBy('book_code');
        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $nppbApprovedExpNasionalQuery->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $nppbApprovedExpNasionalQuery->where('date', '<=', $activeCutoff->end_date);
            }
        } else {
            $nppbApprovedExpNasionalQuery->whereYear('date', $currentYear);
        }
        $nppbApprovedExpNasional = $nppbApprovedExpNasionalQuery->get()->keyBy('book_code');

        // Stock Teralokasikan: total eksemplar NPPB seluruh cabang - hanya book di halaman ini
        $stockTeralokasikanQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as stock_teralokasikan')
        ])->whereIn('book_code', $bookCodesPage)->groupBy('book_code');
        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $stockTeralokasikanQuery->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $stockTeralokasikanQuery->where('date', '<=', $activeCutoff->end_date);
            }
        } else {
            $stockTeralokasikanQuery->whereYear('date', $currentYear);
        }
        $stockTeralokasikanData = $stockTeralokasikanQuery->get()->keyBy('book_code');

        // Target Nasional: total target semua cabang untuk periode aktif (status aktif)
        $activePeriodCode = Periode::where('status', true)
            ->orderByDesc('from_date')
            ->value('period_code');

        $targetNasional = collect();
        if ($activePeriodCode) {
            $targetNasional = Target::select([
                'book_code',
                DB::raw('SUM(exemplar) as target_nasional')
            ])
                ->where('period_code', $activePeriodCode)
                ->whereIn('book_code', $products->pluck('book_code'))
                ->groupBy('book_code')
                ->get()
                ->keyBy('book_code');
        }

        // Pre-load all CentralStockKoli data untuk menghindari N+1 query problem
        // Group by branch_code dan book_code, ambil volume terbesar per book_code (untuk kalkulasi koli/pls)
        $allStockKolis = CentralStockKoli::select([
            'branch_code',
            'book_code',
            DB::raw('MAX(volume) as volume')
        ])
            ->whereIn('book_code', $products->pluck('book_code'))
            ->groupBy('branch_code', 'book_code')
            ->get();

        // Semua row central_stock_kolis per book_code untuk pilihan Isi (dropdown)
        $allStockKolisRows = CentralStockKoli::select(['branch_code', 'book_code', 'volume', 'koli'])
            ->whereIn('book_code', $products->pluck('book_code'))
            ->orderBy('book_code')
            ->orderByDesc('volume')
            ->get();
        $volumeOptionsByBook = $allStockKolisRows->groupBy('book_code');

        // Buat 2 lookup maps: satu untuk branch-specific, satu untuk general (tanpa branch)
        $stockKolisByBranch = $allStockKolis
            ->where('branch_code', $branchCode)
            ->keyBy('book_code');

        $stockKolisGeneral = $allStockKolis
            ->groupBy('book_code')
            ->map(function ($items) {
                return $items->first();
            });

        // Get intransit data: sum exemplar from delivery_note_details
        // Filter by delivery_notes where send_date matches active cutoff_datas and branch_code matches
        $intransitData = collect();
        if ($activeCutoff) {
            $deliveryNotesQuery = DeliveryNote::where('branch_code', $branchCode);
            if ($activeCutoff->start_date !== null) {
                $deliveryNotesQuery->whereBetween('send_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $deliveryNotesQuery->where('send_date', '<=', $activeCutoff->end_date);
            }
            $deliveryNotes = $deliveryNotesQuery->pluck('nota_kirim_cab');

            if ($deliveryNotes->isNotEmpty()) {
                // Get delivery_note_details and sum exemplar per book_code
                $intransitQuery = DeliveryNoteDetail::select([
                    'book_code',
                    DB::raw('SUM(exemplar) as total_intransit')
                ])
                    ->whereIn('nota_kirim_cab', $deliveryNotes)
                    ->whereIn('book_code', $bookCodesPage)
                    ->whereNotNull('book_code')
                    ->groupBy('book_code');

                $intransitData = $intransitQuery->get()->keyBy('book_code');
            }
        }

        // Combine data
        $results = $products->map(function ($product) use ($centralStocks, $centralStockDeductions, $existingNppb, $spBranchData, $spBranchNasional, $stockTeralokasikanData, $targetNasional, $branchCode, $stockKolisByBranch, $stockKolisGeneral, $volumeOptionsByBook, $intransitData, $nppbApprovedExpByBook, $intransitDataNasional, $nppbApprovedExpNasional, $percentage, $lastSpBranchSync) {
            $stock = $centralStocks->get($product->book_code);
            $nppb = $existingNppb->get($product->book_code);
            $spBranch = $spBranchData->get($product->book_code);
            $spNasionalRow = $spBranchNasional->get($product->book_code);
            $teralokasikanRow = $stockTeralokasikanData->get($product->book_code);
            $targetNasionalRow = $targetNasional->get($product->book_code);

            $sp = $spBranch->sp ?? 0;
            $faktur = $spBranch->faktur ?? 0;
            $stockCabang = $spBranch->stock_cabang ?? 0;
            $rawStockPusat = $stock->total_stock_pusat ?? 0;
            $deducted = $centralStockDeductions->get($product->book_code)?->total_deducted ?? 0;
            $stockPusat = max(0, $rawStockPusat - $deducted);
            $stockNasional = $spNasionalRow->stock_nasional ?? 0;
            $spNasional = $spNasionalRow->sp_nasional ?? 0;
            $stockTeralokasikan = $teralokasikanRow->stock_teralokasikan ?? 0;
            $sisaStockPusat = max(0, $stockPusat - $stockTeralokasikan);
            $targetNasionalVal = $targetNasionalRow->target_nasional ?? 0;

            // Akumulasi stock cabang: + intransit + exp NPPB yang sudah approve (document_id), dipakai juga untuk hitung Kurang SP
            $intransit = $intransitData->get($product->book_code);
            $totalIntransit = $intransit ? ($intransit->total_intransit ?? 0) : 0;
            $stockCabang += $totalIntransit;
            $nppbApproved = $nppbApprovedExpByBook->get($product->book_code);
            $stockCabang += $nppbApproved ? (int) ($nppbApproved->nppb_approved_exp ?? 0) : 0;

            // Nasional: Faktur + (Stock cabang + Intransit + NPPB disetujui) seluruh cabang → untuk % (Ftr+Stk+Kirim vs Target)
            $fakturNasional = $spNasionalRow->faktur_nasional ?? 0;
            $intransitNasional = $intransitDataNasional->get($product->book_code);
            $totalIntransitNasional = $intransitNasional ? ($intransitNasional->total_intransit ?? 0) : 0;
            $nppbApprovedNasional = $nppbApprovedExpNasional->get($product->book_code);
            $stockCabangNasional = ($spNasionalRow->stock_nasional ?? 0) + $totalIntransitNasional + ($nppbApprovedNasional ? (int)($nppbApprovedNasional->nppb_approved_exp ?? 0) : 0);

            // Persentase
            $pctStockPusatVsTargetNasional = $targetNasionalVal > 0 ? round(($stockPusat / $targetNasionalVal) * 100, 2) : 0;
            $pctStockPusatVsSp = $sp > 0 ? round(($stockPusat / $sp) * 100, 2) : 0;

            // Calculate Sisa SP (Kurang SP = (SP - Faktur) - stock_cabang - stock_pusat; stock_cabang sudah termasuk intransit + NPPB approve)
            // SP - Faktur
            $selisih = $sp - $faktur;

            // Jika stok cabang memenuhi (>= selisih), maka sisa SP = 0
            if ($stockCabang >= $selisih) {
                $sisaSp = 0;
            } else {
                // Jika stok cabang tidak memenuhi, maka sisa SP = SP - Faktur - Stok Cabang - Stok Pusat
                $sisaSp = max(0, $selisih - $stockCabang - $stockPusat);
            }

            // Jika di database sudah ada data NPPB, gunakan data dari database
            // Jangan melakukan perhitungan lagi jika data sudah tersimpan
            $exp = $nppb->exp ?? 0;
            $koli = $nppb->koli ?? 0;
            $pls = $nppb->pls ?? 0;
            $volumeUsed = 0;

            // Cek apakah sudah ada data di database (jika nppb tidak null, berarti sudah ada record)
            $hasExistingData = ($nppb !== null);
            $nppbUpdatedAt = $nppb && isset($nppb->nppb_updated_at) ? $nppb->nppb_updated_at : null;
            $rowHighlightYellow = $hasExistingData && ($lastSpBranchSync === null || ($nppbUpdatedAt && $nppbUpdatedAt > $lastSpBranchSync));

            // Ambil volume koli dari pre-loaded data: prioritaskan sesuai branch, jika tidak ada pakai volume umum book_code
            $stockKoli = $stockKolisByBranch->get($product->book_code);
            if (!$stockKoli) {
                $stockKoli = $stockKolisGeneral->get($product->book_code);
            }

            // Pilihan Isi: semua row central_stock_kolis untuk book ini; default = volume terbesar
            $opts = $volumeOptionsByBook->get($product->book_code) ?? collect();
            $volumeOptions = $opts->sortByDesc('volume')->values()->map(function ($r) {
                $v = (float) $r->volume;
                $koliVal = isset($r->koli) ? (int) $r->koli : null;
                $label = (string) (int) $v . ($koliVal !== null ? ' (' . $koliVal . ')' : '');
                return ['value' => $v, 'label' => $label];
            })->values()->toArray();
            $volumeUsed = $opts->isEmpty() ? 0 : (float) $opts->max('volume');
            if ($stockKoli && $stockKoli->volume > 0 && $volumeUsed <= 0) {
                $volumeUsed = (float)$stockKoli->volume;
            }

            // Jika belum ada data di database, lakukan perhitungan (seperti semula)
            if (!$hasExistingData) {
                // Eksemplar diambil dari sisa SP
                $exp = $sisaSp;
                $koli = 0;
                $pls = 0;

                // Hitung koli dari eksemplar dibagi volume (modulo); pakai volume_used (default = max dari central_stock_kolis)
                if ($volumeUsed > 0 && $exp > 0) {
                    $koli = (int) floor($exp / $volumeUsed);
                    $pls = (int) ($exp % $volumeUsed);
                }
            }

            // Kurang SP Nasional = max(0, SP Nasional - Faktur Nasional - Stock Cabang Nasional - Stock Pusat)
            $kurangSpNasional = max(0, $spNasional - $fakturNasional - $stockCabangNasional - $stockPusat);
            // Persentase SP (Stock Pusat vs Kurang SP) = sisa_sp / stock_pusat × 100 (per cabang)
            $pctSpVsStock = $stockPusat > 0 ? round(($sisaSp / $stockPusat) * 100, 2) : 0;
            // Hanya izinkan rencana kirim jika % Stk/Kur SP >= Persentase Penentuan Rencana Kirim
            $allowRencanaKirim = ($pctSpVsStock >= $percentage);
            if (!$allowRencanaKirim) {
                $koli = 0;
                $pls = 0;
                $exp = 0;
            }

            // Persentase Penentuan Rencana Kirim: maksimal total eksemplar nasional = percentage% × Stock Pusat
            $maksimalTotalEksemplarNasional = (int) floor(($percentage / 100) * $stockPusat);
            $sisaKuotaEksemplar = $maksimalTotalEksemplarNasional - $stockTeralokasikan;

            // (Faktur + Stock Cabang + NPPB yang telah disetujui) vs SP (per cabang) dan vs Target (nasional: seluruh cabang).
            $pctFakturStockTotalVsSp = $sp > 0 ? round((($faktur + $stockCabang) / $sp) * 100, 2) : 0;
            $pctFakturStockTotalVsTarget = $targetNasionalVal > 0 ? round((($fakturNasional + $stockCabangNasional) / $targetNasionalVal) * 100, 2) : 0;

            return [
                'book_code' => $product->book_code,
                'book_name' => $product->book_title,
                'koli' => $koli,
                'exp' => $exp,
                'pls' => $pls,
                'kurang_sp_nasional' => $kurangSpNasional,
                'pct_sp_vs_stock' => $pctSpVsStock,
                'allow_rencana_kirim' => $allowRencanaKirim,
                'stock_pusat' => $stockPusat,
                'stock_nasional' => $stockNasional,
                'sp_nasional' => $spNasional,
                'pct_stock_pusat_target_nasional' => $pctStockPusatVsTargetNasional,
                'pct_stock_pusat_sp' => $pctStockPusatVsSp,
                'stock_teralokasikan' => $stockTeralokasikan,
                'target_nasional' => $targetNasionalVal,
                'sisa_stock_pusat' => $sisaStockPusat,
                'sp' => $sp,
                'faktur' => $faktur,
                'stock_cabang' => $stockCabang,
                'sisa_sp' => $sisaSp,
                'pct_faktur_stock_total_vs_sp' => $pctFakturStockTotalVsSp,
                'pct_faktur_stock_total_vs_target' => $pctFakturStockTotalVsTarget,
                'intransit' => $totalIntransit,
                'volume_used' => $volumeUsed,
                'volume_options' => $volumeOptions,
                'maksimal_total_eksemplar_nasional' => $maksimalTotalEksemplarNasional,
                'sisa_kuota_eksemplar' => $sisaKuotaEksemplar,
                'row_highlight_yellow' => $rowHighlightYellow,
            ];
        });

        // Urutkan berdasarkan parameter sort (pakai numerik agar string dari DB tidak salah urut)
        $sort = $request->get('sort', '');
        if ($sort !== '') {
            switch ($sort) {
                case 'sp_desc':
                    $results = $results->sortByDesc(fn ($item) => (float)($item['sp'] ?? 0))->values();
                    break;
                case 'sp_asc':
                    $results = $results->sortBy(fn ($item) => (float)($item['sp'] ?? 0))->values();
                    break;
                case 'exp_desc':
                    $results = $results->sortByDesc(fn ($item) => (float)($item['exp'] ?? 0))->values();
                    break;
                case 'exp_asc':
                    $results = $results->sortBy(fn ($item) => (float)($item['exp'] ?? 0))->values();
                    break;
                case 'sisa_sp_desc':
                    $results = $results->sortByDesc(fn ($item) => (float)($item['sisa_sp'] ?? 0))->values();
                    break;
                case 'sisa_sp_asc':
                    $results = $results->sortBy(fn ($item) => (float)($item['sisa_sp'] ?? 0))->values();
                    break;
                default:
                    break;
            }
        }

        // Totals hanya dihitung jika tidak skip_totals (sesi 1 load rows saja; totals di sesi 2)
        $totals = $skipTotals ? null : $this->getNppbCentralTotals($branchCode, $activeCutoff, $currentYear, $percentage, $productsQueryForTotals);

        return response()->json([
            'results' => $results->values(),
            'totals' => $totals,
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $totalProducts,
            'per_page' => $perPage
        ]);
    }

    /**
     * Hitung totals NPPB Central (untuk baris Total) via query agregat - tanpa load semua baris ke memory
     */
    protected function getNppbCentralTotals(string $branchCode, ?object $activeCutoff, string $currentYear, int $percentage, $productsQuery): array
    {
        $allBookCodes = (clone $productsQuery)->orderBy('book_code')->pluck('book_code');
        if ($allBookCodes->isEmpty()) {
            return [
                'stock_pusat' => 0, 'stock_nasional' => 0, 'sp_nasional' => 0,
                'stock_teralokasikan' => 0, 'maksimal_total_eksemplar_nasional' => 0, 'sisa_kuota_eksemplar' => 0,
                'sisa_stock_pusat' => 0, 'sp' => 0, 'faktur' => 0, 'stock_cabang' => 0,
                'sisa_sp' => 0, 'sisa_sp_nasional' => 0, 'koli' => 0, 'pls' => 0, 'exp' => 0,
                'pct_stock_pusat_target_nasional_avg' => 0, 'pct_stock_pusat_sp_avg' => 0,
                'pct_faktur_stock_total_vs_sp_avg' => 0, 'pct_faktur_stock_total_vs_target_avg' => 0,
            ];
        }

        $totalStockPusat = CentralStock::whereIn('book_code', $allBookCodes)->sum('exemplar');
        $totalDeducted = CentralStockDeduction::whereIn('book_code', $allBookCodes)->sum('quantity');
        $stockPusatTotal = max(0, $totalStockPusat - $totalDeducted);

        $spBranchBase = SpBranch::where('active_data', 'yes')->where('branch_code', $branchCode)->whereIn('book_code', $allBookCodes);
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $spBranchBase->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $spBranchBase->where('trans_date', '<=', $activeCutoff->end_date);
        }
        $spTotal = (clone $spBranchBase)->sum('ex_sp');
        $fakturTotal = (clone $spBranchBase)->sum('ex_ftr');
        $stockCabangTotal = (clone $spBranchBase)->sum('ex_stock');

        $spNasionalBase = SpBranch::where('active_data', 'yes')->whereIn('book_code', $allBookCodes);
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $spNasionalBase->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $spNasionalBase->where('trans_date', '<=', $activeCutoff->end_date);
        }
        $stockNasionalTotal = (clone $spNasionalBase)->sum('ex_stock');
        $spNasionalTotal = (clone $spNasionalBase)->sum('ex_sp');

        $stockTeralokasikanQuery = NppbCentral::whereIn('book_code', $allBookCodes);
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $stockTeralokasikanQuery->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $stockTeralokasikanQuery->where('date', '<=', $activeCutoff->end_date);
        } else {
            $stockTeralokasikanQuery->whereYear('date', $currentYear);
        }
        $stockTeralokasikanTotal = (clone $stockTeralokasikanQuery)->sum('exp');

        $nppbCentralBranchQ = NppbCentral::where('branch_code', $branchCode)->whereIn('book_code', $allBookCodes);
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $nppbCentralBranchQ->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $nppbCentralBranchQ->where('date', '<=', $activeCutoff->end_date);
        } else {
            $nppbCentralBranchQ->whereYear('date', $currentYear);
        }
        $existingNppbSums = $nppbCentralBranchQ->selectRaw('book_code, SUM(koli) as koli, SUM(exp) as exp, SUM(pls) as pls')->groupBy('book_code')->get();

        // Sisa SP & Kurang SP Nasional: butuh per-book, dihitung via agregat ringan (tanpa load semua baris)
        $sisaSpTotal = 0;
        $kurangSpNasionalTotal = 0;

        // Rata-rata persen: hitung dari agregat per book (agar sesuai kolom di tabel)
        $pctStockTargetAvg = 0;
        $pctStockSpAvg = 0;
        $pctFtrSpAvg = 0; // avg dari % (Ftr+Stk+Kirim vs SP)
        $pctFtrTargetAvg = 0;

        // Avg % (Ftr+Stk+Kirim vs SP) per book: ((faktur + stock_cabang + intransit + nppb_approved) / sp) * 100
        $spBranchPerBookQ = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_sp) as sp'),
            DB::raw('SUM(ex_ftr) as faktur'),
            DB::raw('SUM(ex_stock) as stock_cabang'),
        ])->where('active_data', 'yes')
            ->where('branch_code', $branchCode)
            ->whereIn('book_code', $allBookCodes);
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $spBranchPerBookQ->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $spBranchPerBookQ->where('trans_date', '<=', $activeCutoff->end_date);
        }
        $spBranchPerBook = $spBranchPerBookQ->groupBy('book_code')->get()->keyBy('book_code');

        // Intransit per book untuk cabang (tujuan delivery_notes.branch_code)
        $intransitPerBook = collect();
        $intransitQuery = DeliveryNoteDetail::select([
            'delivery_note_details.book_code',
            DB::raw('SUM(exemplar) as total_intransit')
        ])->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_details.delivery_note_id')
            ->where('delivery_notes.branch_code', $branchCode)
            ->whereIn('delivery_note_details.book_code', $allBookCodes);
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $intransitQuery->whereBetween('delivery_notes.trans_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $intransitQuery->where('delivery_notes.trans_date', '<=', $activeCutoff->end_date);
        } else {
            $intransitQuery->whereYear('delivery_notes.trans_date', $currentYear);
        }
        $intransitPerBook = $intransitQuery->groupBy('delivery_note_details.book_code')->get()->keyBy('book_code');

        // NPPB Central yang sudah disetujui (punya document_id) per book cabang
        $nppbApprovedPerBook = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as nppb_approved_exp'),
        ])->where('branch_code', $branchCode)
            ->whereIn('book_code', $allBookCodes)
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0);
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $nppbApprovedPerBook->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $nppbApprovedPerBook->where('date', '<=', $activeCutoff->end_date);
        } else {
            $nppbApprovedPerBook->whereYear('date', $currentYear);
        }
        $nppbApprovedPerBook = $nppbApprovedPerBook->groupBy('book_code')->get()->keyBy('book_code');

        $pctVals = [];
        foreach ($allBookCodes as $bc) {
            $row = $spBranchPerBook->get($bc);
            if (!$row) continue;
            $sp = (float) ($row->sp ?? 0);
            if ($sp <= 0) continue;
            $ftr = (float) ($row->faktur ?? 0);
            $stk = (float) ($row->stock_cabang ?? 0);
            $intr = (float) (($intransitPerBook->get($bc)->total_intransit ?? 0) ?: 0);
            $apr = (float) (($nppbApprovedPerBook->get($bc)->nppb_approved_exp ?? 0) ?: 0);
            $pctVals[] = (($ftr + $stk + $intr + $apr) / $sp) * 100;
        }
        $pctFtrSpAvg = count($pctVals) ? round(array_sum($pctVals) / count($pctVals), 2) : 0;

        return [
            'stock_pusat' => $stockPusatTotal,
            'stock_nasional' => $stockNasionalTotal,
            'sp_nasional' => $spNasionalTotal,
            'stock_teralokasikan' => $stockTeralokasikanTotal,
            'maksimal_total_eksemplar_nasional' => (int) floor(($percentage / 100) * $stockPusatTotal),
            'sisa_kuota_eksemplar' => max(0, (int) floor(($percentage / 100) * $stockPusatTotal) - $stockTeralokasikanTotal),
            'sisa_stock_pusat' => max(0, $stockPusatTotal - $stockTeralokasikanTotal),
            'sp' => $spTotal,
            'faktur' => $fakturTotal,
            'stock_cabang' => $stockCabangTotal,
            'sisa_sp' => $sisaSpTotal,
            'sisa_sp_nasional' => $kurangSpNasionalTotal,
            'koli' => $existingNppbSums->sum('koli'),
            'pls' => $existingNppbSums->sum('pls'),
            'exp' => $existingNppbSums->sum('exp'),
            'pct_stock_pusat_target_nasional_avg' => $pctStockTargetAvg,
            'pct_stock_pusat_sp_avg' => $pctStockSpAvg,
            'pct_faktur_stock_total_vs_sp_avg' => $pctFtrSpAvg,
            'pct_faktur_stock_total_vs_target_avg' => $pctFtrTargetAvg,
        ];
    }

    /**
     * Get NPPB products aggregated by warehouse_code (untuk NPPB Warehouse)
     * Data SP, Faktur, Stock Cabang, Sisa SP, Intransit, Koli, Eksemplar, Plastik = akumulasi dari semua cabang di bawah warehouse
     */
    public function getNppbProductsByWarehouse(Request $request): JsonResponse
    {
        $warehouseCode = $request->get('warehouse_code');
        $currentYear = date('Y');
        $page = (int)$request->get('page', 1);
        $perPageRaw = (int)$request->get('per_page', 100);
        $allowedPerPage = [50, 100, 150, 250, 500];
        $perPage = in_array($perPageRaw, $allowedPerPage) ? $perPageRaw : 100;
        $search = $request->get('search', '');

        if (!$warehouseCode) {
            return response()->json([
                'results' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
                'per_page' => $perPage
            ]);
        }

        $branchCodes = Branch::where('warehouse_code', $warehouseCode)->pluck('branch_code');
        if ($branchCodes->isEmpty()) {
            return response()->json([
                'results' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
                'per_page' => $perPage
            ]);
        }

        $marketingListOnly = $request->boolean('marketing_list_only');
        $productsQuery = Product::select('book_code', 'book_title');
        if ($marketingListOnly) {
            $productsQuery->where('is_marketing_list', 'Y');
        }
        if (!empty($search)) {
            $productsQuery->where(function ($query) use ($search) {
                $query->where('book_code', 'like', '%' . $search . '%')
                    ->orWhere('book_title', 'like', '%' . $search . '%');
            });
        }
        $products = $productsQuery->orderBy('book_code')->get();

        $centralStocks = CentralStock::select(['book_code', DB::raw('SUM(exemplar) as total_stock_pusat')])
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        $centralStockDeductionsWarehouse = CentralStockDeduction::select([
            'book_code',
            DB::raw('SUM(quantity) as total_deducted')
        ])->groupBy('book_code')->get()->keyBy('book_code');

        $activeCutoff = CutoffData::where('status', 'active')->first();

        $lastSpBranchSync = Cache::get('sync_sp_branches_progress_last_sync');

        $existingNppbQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(koli) as koli'),
            DB::raw('SUM(exp) as exp'),
            DB::raw('SUM(pls) as pls'),
            DB::raw('MAX(updated_at) as nppb_updated_at'),
        ])
            ->whereIn('branch_code', $branchCodes);

        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $existingNppbQuery->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $existingNppbQuery->where('date', '<=', $activeCutoff->end_date);
            }
        } else {
            $existingNppbQuery->whereYear('date', $currentYear);
        }
        $existingNppb = $existingNppbQuery->groupBy('book_code')->get()->keyBy('book_code');

        $spBranchQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_sp) as sp'),
            DB::raw('SUM(ex_ftr) as faktur'),
            DB::raw('SUM(ex_stock) as stock_cabang'),
        ])
            ->where('active_data', 'yes')
            ->whereIn('branch_code', $branchCodes);

        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $spBranchQuery->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $spBranchQuery->where('trans_date', '<=', $activeCutoff->end_date);
            }
        }
        $spBranchData = $spBranchQuery->groupBy('book_code')->get()->keyBy('book_code');

        // Eksemplar NPPB yang sudah diapprove (punya document_id) per book (semua cabang di warehouse) → ditambahkan ke stock cabang
        $nppbApprovedExpByBook = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as nppb_approved_exp'),
        ])
            ->whereIn('branch_code', $branchCodes)
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        $intransitData = collect();
        if ($activeCutoff) {
            $dnQuery = DeliveryNote::whereIn('branch_code', $branchCodes);
            if ($activeCutoff->start_date !== null) {
                $dnQuery->whereBetween('send_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $dnQuery->where('send_date', '<=', $activeCutoff->end_date);
            }
            $deliveryNotes = $dnQuery->pluck('nota_kirim_cab');

            if ($deliveryNotes->isNotEmpty()) {
                $intransitData = DeliveryNoteDetail::select([
                    'book_code',
                    DB::raw('SUM(exemplar) as total_intransit')
                ])
                    ->whereIn('nota_kirim_cab', $deliveryNotes)
                    ->whereNotNull('book_code')
                    ->groupBy('book_code')
                    ->get()
                    ->keyBy('book_code');
            }
        }

        $allStockKolis = CentralStockKoli::select([
            'book_code',
            DB::raw('MAX(volume) as volume')
        ])
            ->whereIn('book_code', $products->pluck('book_code'))
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Semua row central_stock_kolis per book_code untuk pilihan Isi (dropdown)
        $allStockKolisRowsWarehouse = CentralStockKoli::select(['branch_code', 'book_code', 'volume', 'koli'])
            ->whereIn('book_code', $products->pluck('book_code'))
            ->orderBy('book_code')
            ->orderByDesc('volume')
            ->get();
        $volumeOptionsByBookWarehouse = $allStockKolisRowsWarehouse->groupBy('book_code');

        // Data nasional (seluruh cabang) untuk % (Ftr+Stk+Kirim vs Target) dan Kurang SP Nasional
        $spBranchNasionalQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_stock) as stock_nasional'),
            DB::raw('SUM(ex_sp) as sp_nasional'),
            DB::raw('SUM(ex_ftr) as faktur_nasional'),
        ])
            ->where('active_data', 'yes');
        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $spBranchNasionalQuery->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $spBranchNasionalQuery->where('trans_date', '<=', $activeCutoff->end_date);
            }
        }
        $spBranchNasional = $spBranchNasionalQuery->groupBy('book_code')->get()->keyBy('book_code');

        $percentage = (int) $request->get('percentage', 100);
        $percentage = max(1, min(100, $percentage));

        $intransitDataNasional = collect();
        if ($activeCutoff) {
            $dnNasionalQuery = DeliveryNote::query();
            if ($activeCutoff->start_date !== null) {
                $dnNasionalQuery->whereBetween('send_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $dnNasionalQuery->where('send_date', '<=', $activeCutoff->end_date);
            }
            $deliveryNotesNasional = $dnNasionalQuery->pluck('nota_kirim_cab');
            if ($deliveryNotesNasional->isNotEmpty()) {
                $intransitDataNasional = DeliveryNoteDetail::select([
                    'book_code',
                    DB::raw('SUM(exemplar) as total_intransit')
                ])
                    ->whereIn('nota_kirim_cab', $deliveryNotesNasional)
                    ->whereNotNull('book_code')
                    ->groupBy('book_code')
                    ->get()
                    ->keyBy('book_code');
            }
        }

        $nppbApprovedExpNasionalQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as nppb_approved_exp'),
        ])
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0)
            ->groupBy('book_code');
        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $nppbApprovedExpNasionalQuery->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $nppbApprovedExpNasionalQuery->where('date', '<=', $activeCutoff->end_date);
            }
        } else {
            $nppbApprovedExpNasionalQuery->whereYear('date', $currentYear);
        }
        $nppbApprovedExpNasional = $nppbApprovedExpNasionalQuery->get()->keyBy('book_code');

        // Target Nasional (periode aktif) dari table target per kode buku
        $activePeriodCode = Periode::where('status', true)->orderByDesc('from_date')->value('period_code');
        $targetNasional = collect();
        if ($activePeriodCode) {
            $targetNasional = Target::select(['book_code', DB::raw('SUM(exemplar) as target_nasional')])
                ->where('period_code', $activePeriodCode)
                ->whereIn('book_code', $products->pluck('book_code'))
                ->groupBy('book_code')
                ->get()
                ->keyBy('book_code');
        }

        $results = $products->map(function ($product) use ($centralStocks, $centralStockDeductionsWarehouse, $existingNppb, $spBranchData, $intransitData, $allStockKolis, $volumeOptionsByBookWarehouse, $nppbApprovedExpByBook, $targetNasional, $spBranchNasional, $intransitDataNasional, $nppbApprovedExpNasional, $percentage, $lastSpBranchSync) {
            $stock = $centralStocks->get($product->book_code);
            $nppb = $existingNppb->get($product->book_code);
            $hasExistingData = ($nppb !== null);
            $nppbUpdatedAt = $nppb && isset($nppb->nppb_updated_at) ? $nppb->nppb_updated_at : null;
            $rowHighlightYellow = $hasExistingData && ($lastSpBranchSync === null || ($nppbUpdatedAt && $nppbUpdatedAt > $lastSpBranchSync));
            $spBranch = $spBranchData->get($product->book_code);
            $targetNasionalRow = $targetNasional->get($product->book_code);

            $sp = $spBranch->sp ?? 0;
            $faktur = $spBranch->faktur ?? 0;
            $stockCabang = $spBranch->stock_cabang ?? 0;
            $rawStockPusatWarehouse = $stock->total_stock_pusat ?? 0;
            $deductedWarehouse = $centralStockDeductionsWarehouse->get($product->book_code)?->total_deducted ?? 0;
            $stockPusat = max(0, $rawStockPusatWarehouse - $deductedWarehouse);
            $targetNasionalVal = $targetNasionalRow->target_nasional ?? 0;

            // Tambahkan eksemplar dari NPPB yang sudah diapprove ke stock cabang
            $nppbApproved = $nppbApprovedExpByBook->get($product->book_code);
            $stockCabang += $nppbApproved ? (int) ($nppbApproved->nppb_approved_exp ?? 0) : 0;

            $selisih = $sp - $faktur;
            if ($stockCabang >= $selisih) {
                $sisaSp = 0;
            } else {
                $sisaSp = max(0, $selisih - $stockCabang - $stockPusat);
            }

            $exp = $nppb->exp ?? 0;
            $koli = $nppb->koli ?? 0;
            $pls = $nppb->pls ?? 0;
            // Pilihan Isi: semua row central_stock_kolis untuk book ini; default = volume terbesar
            $optsWh = $volumeOptionsByBookWarehouse->get($product->book_code) ?? collect();
            $volumeOptionsWh = $optsWh->sortByDesc('volume')->values()->map(function ($r) {
                $v = (float) $r->volume;
                $koliVal = isset($r->koli) ? (int) $r->koli : null;
                $label = (string) (int) $v . ($koliVal !== null ? ' (' . $koliVal . ')' : '');
                return ['value' => $v, 'label' => $label];
            })->values()->toArray();
            $volumeUsed = $optsWh->isEmpty() ? 0 : (float) $optsWh->max('volume');
            $stockKoli = $allStockKolis->get($product->book_code);
            if ($volumeUsed <= 0 && $stockKoli) {
                $volumeUsed = (float)$stockKoli->volume;
            }

            $intransit = $intransitData->get($product->book_code);
            $totalIntransit = $intransit ? ($intransit->total_intransit ?? 0) : 0;

            // % vs SP: per warehouse. % vs Target: nasional (faktur + stock cabang + intransit + nppb disetujui seluruh cabang) vs target dari table target.
            $spNasionalRow = $spBranchNasional->get($product->book_code);
            $spNasional = $spNasionalRow->sp_nasional ?? 0;
            $fakturNasional = $spNasionalRow->faktur_nasional ?? 0;
            $intransitNasional = $intransitDataNasional->get($product->book_code);
            $totalIntransitNasional = $intransitNasional ? ($intransitNasional->total_intransit ?? 0) : 0;
            $nppbApprovedNasional = $nppbApprovedExpNasional->get($product->book_code);
            $stockCabangNasional = ($spNasionalRow->stock_nasional ?? 0) + $totalIntransitNasional + ($nppbApprovedNasional ? (int)($nppbApprovedNasional->nppb_approved_exp ?? 0) : 0);

            $kurangSpNasional = max(0, $spNasional - $fakturNasional - $stockCabangNasional - $stockPusat);
            $pctSpVsStock = $stockPusat > 0 ? round(($kurangSpNasional / $stockPusat) * 100, 2) : 0;
            $allowRencanaKirim = ($pctSpVsStock >= $percentage);
            if (!$allowRencanaKirim) {
                $koli = 0;
                $pls = 0;
                $exp = 0;
            }

            $pctFakturStockTotalVsSp = $sp > 0 ? round((($faktur + $stockCabang) / $sp) * 100, 2) : 0;
            $pctFakturStockTotalVsTarget = $targetNasionalVal > 0 ? round((($fakturNasional + $stockCabangNasional) / $targetNasionalVal) * 100, 2) : 0;

            $stockNasional = $spNasionalRow->stock_nasional ?? 0;

            return [
                'book_code' => $product->book_code,
                'book_name' => $product->book_title,
                'koli' => $koli,
                'exp' => $exp,
                'pls' => $pls,
                'kurang_sp_nasional' => $kurangSpNasional,
                'pct_sp_vs_stock' => $pctSpVsStock,
                'allow_rencana_kirim' => $allowRencanaKirim,
                'stock_pusat' => $stockPusat,
                'stock_nasional' => $stockNasional,
                'sp_nasional' => $spNasional,
                'sp' => $sp,
                'faktur' => $faktur,
                'stock_cabang' => $stockCabang,
                'sisa_sp' => $sisaSp,
                'pct_faktur_stock_total_vs_sp' => $pctFakturStockTotalVsSp,
                'pct_faktur_stock_total_vs_target' => $pctFakturStockTotalVsTarget,
                'target_nasional' => $targetNasionalVal,
                'intransit' => $totalIntransit,
                'volume_used' => $volumeUsed,
                'volume_options' => $volumeOptionsWh,
                'row_highlight_yellow' => $rowHighlightYellow,
            ];
        });

        // Urutkan berdasarkan parameter sort (pakai numerik agar string dari DB tidak salah urut)
        $sort = $request->get('sort', '');
        if ($sort !== '') {
            switch ($sort) {
                case 'sp_desc':
                    $results = $results->sortByDesc(fn ($item) => (float)($item['sp'] ?? 0))->values();
                    break;
                case 'sp_asc':
                    $results = $results->sortBy(fn ($item) => (float)($item['sp'] ?? 0))->values();
                    break;
                case 'exp_desc':
                    $results = $results->sortByDesc(fn ($item) => (float)($item['exp'] ?? 0))->values();
                    break;
                case 'exp_asc':
                    $results = $results->sortBy(fn ($item) => (float)($item['exp'] ?? 0))->values();
                    break;
                case 'sisa_sp_desc':
                    $results = $results->sortByDesc(fn ($item) => (float)($item['sisa_sp'] ?? 0))->values();
                    break;
                case 'sisa_sp_asc':
                    $results = $results->sortBy(fn ($item) => (float)($item['sisa_sp'] ?? 0))->values();
                    break;
                default:
                    break;
            }
        }

        $total = $results->count();
        $lastPage = (int)ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedResults = $results->slice($offset, $perPage)->values();

        // Total keseluruhan (seluruh data di semua halaman) untuk baris total; kolom persen = rata-rata
        $totals = [
            'stock_pusat' => $results->sum('stock_pusat'),
            'stock_nasional' => $results->sum('stock_nasional'),
            'sp_nasional' => $results->sum('sp_nasional'),
            'stock_teralokasikan' => $results->sum('stock_teralokasikan'),
            'maksimal_total_eksemplar_nasional' => $results->sum('maksimal_total_eksemplar_nasional'),
            'sisa_kuota_eksemplar' => $results->sum('sisa_kuota_eksemplar'),
            'sisa_stock_pusat' => $results->sum('sisa_stock_pusat'),
            'sp' => $results->sum('sp'),
            'faktur' => $results->sum('faktur'),
            'stock_cabang' => $results->sum('stock_cabang'),
            'sisa_sp' => $results->sum('sisa_sp'),
            'sisa_sp_nasional' => $results->sum('kurang_sp_nasional'),
            'koli' => $results->sum('koli'),
            'pls' => $results->sum('pls'),
            'exp' => $results->sum('exp'),
            'pct_stock_pusat_target_nasional_avg' => round((float) $results->avg('pct_stock_pusat_target_nasional'), 2),
            'pct_stock_pusat_sp_avg' => round((float) $results->avg('pct_stock_pusat_sp'), 2),
            'pct_faktur_stock_total_vs_sp_avg' => round((float) $results->avg('pct_faktur_stock_total_vs_sp'), 2),
            'pct_faktur_stock_total_vs_target_avg' => round((float) $results->avg('pct_faktur_stock_total_vs_target'), 2),
        ];

        return response()->json([
            'results' => $paginatedResults,
            'totals' => $totals,
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
            'per_page' => $perPage
        ]);
    }

    /**
     * Get areas from branches for Select2 AJAX
     */
    public function getAreas(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        // Get all branches
        $allBranches = Branch::select('branch_name')->distinct()->get();

        // Extract areas from branch names
        $areaSet = ['Nasional'];
        $areas = ['Nasional'];

        foreach ($allBranches as $branch) {
            $areaName = $this->extractAreaFromBranch($branch->branch_name);
            if (!in_array($areaName, $areaSet)) {
                $areaSet[] = $areaName;
                $areas[] = $areaName;
            }
        }

        // Sort other areas (excluding Nasional)
        $otherAreas = array_filter($areas, function ($a) {
            return $a !== 'Nasional';
        });
        sort($otherAreas);
        $areas = array_merge(['Nasional'], $otherAreas);

        // Filter by search term
        if ($search) {
            $areas = array_filter($areas, function ($area) use ($search) {
                return stripos($area, $search) !== false;
            });
        }

        // Format for Select2
        $results = array_map(function ($area) {
            return [
                'id' => $area,
                'text' => $area,
            ];
        }, $areas);

        return response()->json([
            'results' => $results
        ]);
    }

    /**
     * Extract area name from branch name
     */
    private function extractAreaFromBranch($branchName)
    {
        if (empty($branchName)) {
            return 'Nasional';
        }

        $branchNameUpper = strtoupper($branchName);

        // Area Sumatera
        if (
            stripos($branchName, 'MEDAN') !== false ||
            stripos($branchName, 'PALEMBANG') !== false ||
            stripos($branchName, 'PEKANBARU') !== false ||
            stripos($branchName, 'BANDA ACEH') !== false ||
            stripos($branchName, 'SIBOLGA') !== false ||
            stripos($branchName, 'PADANG') !== false ||
            stripos($branchName, 'JAMBI') !== false ||
            stripos($branchName, 'BENGKULU') !== false ||
            stripos($branchName, 'LAMPUNG') !== false
        ) {
            return 'Area Sumatera';
        }

        // Area Jawa
        if (
            stripos($branchName, 'JAKARTA') !== false ||
            stripos($branchName, 'BANDUNG') !== false ||
            stripos($branchName, 'SURABAYA') !== false ||
            stripos($branchName, 'YOGYAKARTA') !== false ||
            stripos($branchName, 'SEMARANG') !== false ||
            stripos($branchName, 'MALANG') !== false ||
            stripos($branchName, 'BOGOR') !== false ||
            stripos($branchName, 'DEPOK') !== false ||
            stripos($branchName, 'TANGERANG') !== false ||
            stripos($branchName, 'BEKASI') !== false
        ) {
            return 'Area Jawa';
        }

        // Area Sulawesi
        if (
            stripos($branchName, 'MAKASSAR') !== false ||
            stripos($branchName, 'MANADO') !== false ||
            stripos($branchName, 'PALU') !== false ||
            stripos($branchName, 'KENDARI') !== false
        ) {
            return 'Area Sulawesi';
        }

        // Default
        return 'Nasional';
    }

    /**
     * Save NPPB Central data (bulk save)
     */
    public function saveNppbProducts(Request $request): JsonResponse
    {
        try {
            // Handle both JSON and form data
            $data = $request->json()->all() ?: $request->all();
            $branchCode = $data['branch_code'] ?? null;
            $branchName = $data['branch_name'] ?? null;
            $products = $data['products'] ?? [];
            $currentDate = date('Y-m-d');

            if (!$branchCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch code is required'
                ], 400);
            }

            // Filter cabang hanya untuk role cabang (authority_id 2); superadmin & ADP akses global
            $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
            if ($filteredBranchCodes !== null && !in_array($branchCode, $filteredBranchCodes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki wewenang untuk cabang ini.'
                ], 403);
            }

            // Get branch name if not provided
            if (!$branchName) {
                $branch = Branch::where('branch_code', $branchCode)->first();
                $branchName = $branch->branch_name ?? $branchCode;
            }

            // Tidak hapus data lama: setiap simpan = tambah data baru saja dengan stack baru (append only)
            // Generate satu stack untuk seluruh data yang disimpan bersamaan (format: WS + 5 digit urutan + 2 digit user id + DDMMYYYY)
            $datePart = date('dmY');
            $maxSeq = NppbCentral::where('stack', 'like', 'WS%')
                ->whereRaw('RIGHT(stack, 8) = ?', [$datePart])
                ->max(DB::raw('CAST(SUBSTRING(stack, 3, 5) AS UNSIGNED)'));
            $seq = str_pad((string)(($maxSeq ?? 0) + 1), 5, '0', STR_PAD_LEFT);
            $userIdPadded = substr(str_pad((string)(Auth::id() ?? 0), 2, '0', STR_PAD_LEFT), -2);
            $stack = 'WS' . $seq . $userIdPadded . $datePart;
            $createdBy = Auth::id();

            $saved = 0;
            $errors = [];
            $chunkSize = 100; // Process 100 products at a time to avoid max_input_vars limit

            // Process products in chunks to avoid max_input_vars limit
            $chunks = array_chunk($products, $chunkSize);

            foreach ($chunks as $chunk) {
                $dataToInsert = [];

                foreach ($chunk as $product) {
                    $bookCode = $product['book_code'] ?? null;
                    $bookName = $product['book_name'] ?? null;
                    $koli = isset($product['koli']) ? (float)$product['koli'] : 0;
                    $exp = isset($product['exp']) ? (float)$product['exp'] : 0;
                    $pls = isset($product['pls']) ? (float)$product['pls'] : 0;
                    $volume = isset($product['volume']) ? (float)$product['volume'] : 0;

                    if (!$bookCode) {
                        continue;
                    }

                    // Simpan data yang memiliki nilai != 0 (setidaknya salah satu dari koli, exp, atau pls)
                    if ($koli == 0 && $exp == 0 && $pls == 0) {
                        continue;
                    }

                    // Get book name if not provided
                    if (!$bookName) {
                        $book = Product::where('book_code', $bookCode)->first();
                        $bookName = $book->book_title ?? $bookCode;
                    }

                    $now = now();
                    $dataToInsert[] = [
                        'branch_code' => $branchCode,
                        'branch_name' => $branchName,
                        'book_code' => $bookCode,
                        'book_name' => $bookName,
                        'koli' => $koli,
                        'exp' => $exp,
                        'pls' => $pls,
                        'volume' => $volume,
                        'date' => $currentDate,
                        'stack' => $stack,
                        'created_by' => $createdBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Bulk insert using chunk (50 records at a time)
                if (!empty($dataToInsert)) {
                    try {
                        $insertChunks = array_chunk($dataToInsert, 50);
                        foreach ($insertChunks as $insertChunk) {
                            NppbCentral::insert($insertChunk);
                            $saved += count($insertChunk);
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error saving chunk: " . $e->getMessage();
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil menyimpan {$saved} data produk",
                'saved' => $saved,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
