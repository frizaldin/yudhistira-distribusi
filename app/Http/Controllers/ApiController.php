<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Book;
use App\Models\NppbCentral;
use App\Models\CentralStock;
use App\Models\CentralStockDeduction;
use App\Models\StockMutation;
use App\Models\StockMutationItem;
use App\Models\CentralStockKoli;
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
     * Get all branches (semua cabang, termasuk warehouse) dengan flag is_warehouse.
     * Dipakai untuk Select2 di NPPB Central (satu select, tanpa select warehouse terpisah).
     */
    public function getAllBranchesWithWarehouseInfo(Request $request): JsonResponse
    {
        $search = $request->get('q', '');
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        // Kumpulkan semua warehouse_code yang ada (untuk menentukan branch mana yang merupakan warehouse)
        $warehouseCodes = Branch::whereNotNull('warehouse_code')
            ->where('warehouse_code', '!=', '')
            ->pluck('warehouse_code')
            ->unique()
            ->values()
            ->all();

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
            ->orderBy('branch_code')
            ->limit(200)
            ->get();

        $warehouseLabelAllowedCodes = ['DY00', 'JT00', 'WS00', 'KT00', 'SM00', 'PS00', 'SU00', 'TB00', 'UM00'];

        $results = $branches->map(function ($branch) use ($warehouseCodes, $warehouseLabelAllowedCodes) {
            $isWarehouse = in_array($branch->branch_code, $warehouseCodes, true)
                && in_array(strtoupper((string) $branch->branch_code), $warehouseLabelAllowedCodes, true);
            $label = $branch->branch_code . ' - ' . $branch->branch_name;
            if ($isWarehouse) {
                $label .= ' [Warehouse]';
            }
            return [
                'id' => $branch->branch_code,
                'text' => $label,
                'branch_name' => $branch->branch_name,
                'is_warehouse' => $isWarehouse,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Get sub-branches by warehouse branch_code.
     * Jika branch_code tersebut adalah warehouse, kembalikan semua branch yang warehouse_code-nya = branch_code tsb.
     */
    public function getSubBranchesByWarehouseCode(Request $request): JsonResponse
    {
        $branchCode = $request->get('branch_code', '');
        if (!$branchCode) {
            return response()->json(['is_warehouse' => false, 'sub_branches' => [], 'branch_codes' => []]);
        }

        // Cari semua sub-cabang yang warehouse_code-nya = branch_code ini
        $subBranches = Branch::where('warehouse_code', $branchCode)
            ->orderBy('branch_code')
            ->get(['branch_code', 'branch_name']);

        $isWarehouse = $subBranches->isNotEmpty();

        return response()->json([
            'is_warehouse' => $isWarehouse,
            'sub_branches' => $subBranches->map(fn($b) => [
                'branch_code' => $b->branch_code,
                'branch_name' => $b->branch_name,
            ])->values(),
            'branch_codes' => $subBranches->pluck('branch_code')->values(),
        ]);
    }

    /**
     * Get distinct warehouse_code from branches (untuk NPPB Warehouse / Rencana Kirim Cabang Area)
     */
    public function getWarehouseCodes(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('q', ''));

        $warehouseCodes = Branch::select('warehouse_code')
            ->whereNotNull('warehouse_code')
            ->where('warehouse_code', '!=', '')
            ->when($search !== '', function ($query) use ($search) {
                return $query->where('warehouse_code', 'like', '%' . $search . '%');
            })
            ->distinct()
            ->orderBy('warehouse_code')
            ->limit(100)
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
     * Get branches by warehouse_code untuk filter Cabang di Report (list penuh, bukan Select2).
     * Query: Branches where warehouse_code == warehouse_code (area yang dipilih).
     */
    public function getReportBranchesByArea(Request $request): JsonResponse
    {
        $warehouseCode = $request->get('warehouse_code', '');
        if ($warehouseCode === '' || $warehouseCode === null) {
            return response()->json(['branches' => []]);
        }
        $branches = Branch::query()
            ->where('warehouse_code', $warehouseCode)
            ->orderBy('branch_name')
            ->get(['branch_code', 'branch_name']);
        $list = $branches->map(function ($b) {
            return ['branch_code' => $b->branch_code, 'branch_name' => $b->branch_name];
        })->values();
        return response()->json(['branches' => $list]);
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
        $all = $request->boolean('all');
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $branchesQuery = Branch::query()
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
            ->orderBy('branch_name');

        if (!$all) {
            $branchesQuery->limit(100);
        }

        $branches = $branchesQuery->get();

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

        $products = Book::query()
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
        // Mode warehouse: branch_codes[] berisi daftar sub-cabang
        $branchCodesRaw = $request->get('branch_codes', []);
        $branchCodes = is_array($branchCodesRaw)
            ? array_filter(array_map('strval', $branchCodesRaw))
            : (is_string($branchCodesRaw) ? array_filter(explode(',', $branchCodesRaw)) : []);
        $branchCodes = array_values($branchCodes);
        $isWarehouseMode = !empty($branchCodes);

        // Jika mode warehouse, gunakan branch_codes ditambah branch_code utama (warehouse-nya)
        // untuk membaca draft/rencana yang tersimpan atas nama warehouse tersebut
        $effectiveBranchCodes = $isWarehouseMode ? array_unique(array_merge($branchCodes, [$branchCode])) : ($branchCode ? [$branchCode] : []);

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
        $applyTargetCap = $request->boolean('apply_target_cap');
        $percentageTargetRaw = (int) $request->get('percentage_target', 100);
        $percentageTarget = max(1, min(100, $percentageTargetRaw));
        $skipTotals = $request->boolean('skip_totals');
        $totalsOnly = $request->boolean('totals_only');
        $sort = $request->get('sort', '');

        if (empty($effectiveBranchCodes)) {
            return response()->json([
                'results' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
                'per_page' => $perPage
            ]);
        }

        // Untuk backward compatibility: branchCode tetap dipakai sebagai representasi utama
        if (!$branchCode && !empty($effectiveBranchCodes)) {
            $branchCode = $effectiveBranchCodes[0];
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
            $totalFull = Book::count();
            // Fast path: totals_only tidak perlu load daftar semua book_code ke memory
            $totals = $this->getNppbCentralTotalsFast($branchCode, $activeCutoff, $currentYear, $percentage, $effectiveBranchCodes);
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
        $productsQuery = Book::select('book_code', 'book_title', 'urutan');
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
        // Untuk sort computed field (sisa_sp, sp): gunakan DB-level JOIN agar sorting berlaku lintas halaman
        if (in_array($sort, ['sisa_sp_desc', 'sisa_sp_asc', 'sp_desc', 'sp_asc'])) {
            $activeCutoffForSort = CutoffData::where('status', 'active')->first();
            $spSortSubQ = SpBranch::select([
                DB::raw('book_code as _sort_bc'),
                DB::raw('GREATEST(0, COALESCE(SUM(ex_sp) - SUM(ex_ftr) - SUM(ex_stock), 0)) as _sisa_sp'),
                DB::raw('COALESCE(SUM(ex_sp), 0) as _sp'),
            ])->where('active_data', 'yes')
                ->whereIn('branch_code', $effectiveBranchCodes);
            if ($activeCutoffForSort) {
                $activeCutoffForSort->start_date !== null
                    ? $spSortSubQ->whereBetween('trans_date', [$activeCutoffForSort->start_date, $activeCutoffForSort->end_date])
                    : $spSortSubQ->where('trans_date', '<=', $activeCutoffForSort->end_date);
            }
            $spSortSubQ->groupBy('book_code');
            $productsQuery->leftJoinSub($spSortSubQ, '_sp_sort', '_sp_sort._sort_bc', '=', 'books.book_code');
            $orderDir = in_array($sort, ['sisa_sp_desc', 'sp_desc']) ? 'DESC' : 'ASC';
            $orderCol = in_array($sort, ['sisa_sp_desc', 'sisa_sp_asc']) ? '_sisa_sp' : '_sp';
            $products = $productsQuery
                ->orderByRaw('COALESCE(_sp_sort.' . $orderCol . ', 0) ' . $orderDir)
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();
        } else {
            $products = $productsQuery
                // Default urutan list mengikuti books.urutan; urutan kosong diletakkan di bawah.
                ->orderByRaw("CASE WHEN urutan IS NULL OR urutan = '' THEN 1 ELSE 0 END ASC")
                ->orderByRaw('CAST(urutan AS UNSIGNED) ASC')
                ->orderBy('book_code')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();
        }

        // Check if there's an active cutoff_data (perlu sebelum empty return)
        $activeCutoff = CutoffData::where('status', 'active')->first();

        if ($products->isEmpty()) {
            $totals = $skipTotals ? null : $this->getNppbCentralTotals($branchCode, $activeCutoff, $currentYear, $percentage, $productsQueryForTotals, false);
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

        $stockMutationsByBook = StockMutationItem::select([
            'book_code',
            DB::raw('SUM(total_eksemplar) as total_mutasi'),
        ])
            ->whereIn('book_code', $bookCodesPage)
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        $lastSpBranchSync = Cache::get('sync_sp_branches_progress_last_sync');

        // Get existing NPPB data for this branch/branches and year - hanya untuk book di halaman ini
        $existingNppbQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(koli) as koli'),
            DB::raw('SUM(exp) as exp'),
            DB::raw('SUM(pls) as pls'),
            DB::raw('MAX(updated_at) as nppb_updated_at'),
        ])
            ->whereIn('branch_code', $effectiveBranchCodes)
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
        // Mode warehouse: aggregate dari semua sub-cabang (effectiveBranchCodes)
        $spBranchQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_sp) as sp'),
            DB::raw('SUM(ex_ftr) as faktur'),
            DB::raw('SUM(ex_stock) as stock_cabang'),
        ])
            ->where('active_data', 'yes')
            ->whereIn('branch_code', $effectiveBranchCodes)
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
        // Mode warehouse: aggregate dari semua sub-cabang
        $nppbApprovedExpByBook = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as nppb_approved_exp'),
        ])
            ->whereIn('branch_code', $effectiveBranchCodes)
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

        // Stock Teralokasikan: total exemplar NKB (delivery_notes + delivery_note_details)
        // yang BELUM punya NTB (m_terima_buku) berdasarkan nota_kirim_cab.
        $stockTeralokasikanQuery = DeliveryNoteDetail::select([
            'delivery_note_details.book_code as book_code',
            DB::raw('SUM(COALESCE(delivery_note_details.exemplar, 0)) as stock_teralokasikan')
        ])
            ->join('delivery_notes', 'delivery_notes.nota_kirim_cab', '=', 'delivery_note_details.nota_kirim_cab')
            ->leftJoin('m_terima_buku as ntb', 'ntb.nota_kirim_cab', '=', 'delivery_notes.nota_kirim_cab')
            ->whereNull('ntb.nota_kirim_cab')
            ->whereIn('delivery_note_details.book_code', $bookCodesPage)
            ->whereNotNull('delivery_note_details.book_code')
            ->groupBy('delivery_note_details.book_code');
        if ($activeCutoff) {
            if ($activeCutoff->start_date !== null) {
                $stockTeralokasikanQuery->whereBetween('delivery_notes.send_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $stockTeralokasikanQuery->where('delivery_notes.send_date', '<=', $activeCutoff->end_date);
            }
        } else {
            $stockTeralokasikanQuery->whereYear('delivery_notes.send_date', $currentYear);
        }
        $stockTeralokasikanData = $stockTeralokasikanQuery->get()->keyBy('book_code');

        // Target Nasional: SUM(exemplar) semua cabang per book, periode yang overlap cutoff (sama logika dashboard)
        $targetNasional = $this->getTargetNasionalByBookCodes($products->pluck('book_code'), $activeCutoff, $currentYear);

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
        // Mode warehouse: aggregate dari semua sub-cabang (effectiveBranchCodes)
        $intransitData = collect();
        if ($activeCutoff) {
            $deliveryNotesQuery = DeliveryNote::whereIn('branch_code', $effectiveBranchCodes);
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
        $results = $products->map(function ($product) use ($centralStocks, $centralStockDeductions, $stockMutationsByBook, $existingNppb, $spBranchData, $spBranchNasional, $stockTeralokasikanData, $targetNasional, $branchCode, $stockKolisByBranch, $stockKolisGeneral, $volumeOptionsByBook, $intransitData, $nppbApprovedExpByBook, $intransitDataNasional, $nppbApprovedExpNasional, $percentage, $lastSpBranchSync, $applyTargetCap, $percentageTarget) {
            $stock = $centralStocks->get($product->book_code);
            $nppb = $existingNppb->get($product->book_code);
            $spBranch = $spBranchData->get($product->book_code);
            $spNasionalRow = $spBranchNasional->get($product->book_code);
            $teralokasikanRow = $stockTeralokasikanData->get($product->book_code);
            $targetNasionalRow = $targetNasional->get($product->book_code);

            $sp = $spBranch?->sp ?? 0;
            $faktur = $spBranch?->faktur ?? 0;
            $stockCabang = $spBranch?->stock_cabang ?? 0;
            $mutasiRow = $stockMutationsByBook->get($product->book_code);
            $rawStockPusat = (float) ($stock->total_stock_pusat ?? 0) + (float) ($mutasiRow?->total_mutasi ?? 0);
            $deducted = $centralStockDeductions->get($product->book_code)?->total_deducted ?? 0;
            $stockPusat = max(0, $rawStockPusat - $deducted);
            $stockNasional = $spNasionalRow?->stock_nasional ?? 0;
            $spNasional = $spNasionalRow?->sp_nasional ?? 0;
            $stockTeralokasikan = $teralokasikanRow?->stock_teralokasikan ?? 0;
            $sisaStockPusat = max(0, $stockPusat - $stockTeralokasikan);
            $targetNasionalVal = $targetNasionalRow?->target_nasional ?? 0;

            // Akumulasi stock cabang: + intransit + exp NPPB yang sudah approve (document_id), dipakai juga untuk hitung Kurang SP
            $intransit = $intransitData->get($product->book_code);
            $totalIntransit = $intransit ? ($intransit->total_intransit ?? 0) : 0;
            $stockCabang += $totalIntransit;
            $nppbApproved = $nppbApprovedExpByBook->get($product->book_code);
            $stockCabang += $nppbApproved ? (int) ($nppbApproved->nppb_approved_exp ?? 0) : 0;
            // Permintaan bisnis: stock cabang ikut menampung stock teralokasikan (NKB belum NTB).
            $stockCabang += (int) $stockTeralokasikan;

            // Nasional: Faktur + (Stock cabang + Intransit + NPPB disetujui) seluruh cabang → untuk % (Ftr+Stk+Kirim vs Target)
            $fakturNasional = $spNasionalRow?->faktur_nasional ?? 0;
            $intransitNasional = $intransitDataNasional->get($product->book_code);
            $totalIntransitNasional = $intransitNasional ? ($intransitNasional->total_intransit ?? 0) : 0;
            $nppbApprovedNasional = $nppbApprovedExpNasional->get($product->book_code);
            $stockCabangNasional = ($spNasionalRow?->stock_nasional ?? 0)
                + $totalIntransitNasional
                + ($nppbApprovedNasional ? (int) ($nppbApprovedNasional->nppb_approved_exp ?? 0) : 0)
                + (int) $stockTeralokasikan;

            // Persentase
            $pctStockPusatVsTargetNasional = $targetNasionalVal > 0 ? round(($stockPusat / $targetNasionalVal) * 100, 2) : 0;
            $pctStockPusatVsSp = $sp > 0 ? round(($stockPusat / $sp) * 100, 2) : 0;

            // Calculate Sisa SP (Kurang SP = (SP - Faktur) - stock_cabang; stock_cabang sudah termasuk intransit + NPPB approve). Stok pusat tidak mengurangi Kur. SP.
            // SP - Faktur
            $selisih = $sp - $faktur;

            // Jika stok cabang memenuhi (>= selisih), maka sisa SP = 0
            if ($stockCabang >= $selisih) {
                $sisaSp = 0;
            } else {
                // Jika stok cabang tidak memenuhi, maka sisa SP = SP - Faktur - Stok Cabang
                $sisaSp = max(0, $selisih - $stockCabang);
            }

            // Ada baris NPPB di DB vs ada rencana kirim yang benar-benar diisi (koli/exp/pls > 0)
            $hasExistingData = ($nppb !== null);
            $useSavedNppbQuantities = $hasExistingData
                && (((int) ($nppb->exp ?? 0)) + ((int) ($nppb->koli ?? 0)) + ((int) ($nppb->pls ?? 0)) > 0);

            // Jika hanya baris NPPB kosong (semua 0), tetap isi dari Kurang SP — hindari koli/eceran/total nempel 0 padahal Kur. SP > 0
            $exp = $useSavedNppbQuantities ? (int) ($nppb->exp ?? 0) : 0;
            $koli = $useSavedNppbQuantities ? (int) ($nppb->koli ?? 0) : 0;
            $pls = $useSavedNppbQuantities ? (int) ($nppb->pls ?? 0) : 0;

            $nppbUpdatedAt = $nppb && isset($nppb->nppb_updated_at) ? $nppb->nppb_updated_at : null;
            $rowHighlightYellow = $hasExistingData && ($lastSpBranchSync === null || ($nppbUpdatedAt && $nppbUpdatedAt > $lastSpBranchSync));

            // Ambil volume koli dari pre-loaded data: prioritaskan sesuai branch, jika tidak ada pakai volume umum book_code
            $stockKoli = $stockKolisByBranch->get($product->book_code);
            if (!$stockKoli) {
                $stockKoli = $stockKolisGeneral->get($product->book_code);
            }

            // Pilihan Isi: row central_stock_kolis dengan koli > 0 saja (yang koli 0/kosong disembunyikan di dropdown)
            $opts = $volumeOptionsByBook->get($product->book_code) ?? collect();
            $optsWithKoli = $opts->filter(fn($r) => (int) ($r->koli ?? 0) > 0);
            $volumeOptions = $optsWithKoli->sortByDesc('volume')->values()->map(function ($r) {
                $v = (float) $r->volume;
                $koliVal = (int) ($r->koli ?? 0);
                $label = (string) (int) $v . ' (' . $koliVal . ')';
                return ['value' => $v, 'label' => $label];
            })->values()->toArray();
            $volumeUsedRaw = $optsWithKoli->isEmpty() ? 0.0 : (float) $optsWithKoli->max('volume');
            if ($stockKoli && $stockKoli->volume > 0 && $volumeUsedRaw <= 0) {
                $volumeUsedRaw = (float) $stockKoli->volume;
            }
            // Tanpa isi koli di DB (0 / kosong): tetap bagi koli/eceran pakai isi minimal 1 agar angka generate
            $volumeForSplit = $volumeUsedRaw > 0 ? $volumeUsedRaw : 1.0;
            if ($volumeOptions === []) {
                $volumeOptions = [['value' => 0, 'label' => '0']];
            }

            // Isi dari rumus Kurang SP bila belum ada rencana tersimpan yang berisi angka
            if (! $useSavedNppbQuantities) {
                // Eksemplar diambil dari sisa SP
                $exp = $sisaSp;
                $koli = 0;
                $pls = 0;

                if ($exp > 0) {
                    $koli = (int) floor($exp / $volumeForSplit);
                    $pls = (int) ($exp % $volumeForSplit);
                }
            }

            // Kurang SP Nasional = max(0, SP Nasional - Faktur Nasional - Stock Cabang Nasional); stok pusat tidak mengurangi.
            $kurangSpNasional = max(0, $spNasional - $fakturNasional - $stockCabangNasional);
            // Persentase SP (Stock Pusat vs Kurang SP) = sisa_sp / stock_pusat × 100 (per cabang)
            $pctSpVsStock = $stockPusat > 0 ? round(($sisaSp / $stockPusat) * 100, 2) : 0;
            // Flag UI: rencana tetap boleh diisi; pembatasan kuota & % target dihitung di bawah.
            $allowRencanaKirim = true;

            // Persentase Penentuan Rencana Kirim: maksimal total eksemplar nasional = percentage% × Stock Pusat
            $maksimalTotalEksemplarNasional = (int) floor(($percentage / 100) * $stockPusat);
            $sisaKuotaEksemplar = $maksimalTotalEksemplarNasional - $stockTeralokasikan;

            // Batasi ke sisa kuota nasional hanya jika kuota masih > 0.
            // Hindari (int) langsung pada float kuota (mis. 0,9 → 0) yang memaksa min(exp,0)=0 padahal Kur. SP > 0.
            // Hanya clamp jika kuota dibulatkan minimal 1 eksemplar.
            if (! $useSavedNppbQuantities && $sisaKuotaEksemplar > 0) {
                $quotaCap = (int) max(0, round((float) $sisaKuotaEksemplar));
                if ($quotaCap >= 1) {
                    $exp = min((int) $exp, $quotaCap);
                    if ($exp > 0) {
                        $koli = (int) floor($exp / $volumeForSplit);
                        $pls = (int) ($exp % $volumeForSplit);
                    } else {
                        $koli = 0;
                        $pls = 0;
                    }
                }
            }

            // Plafon rencana dari % × Target nasional (setelah kuota % rencana kirim). Rencana = min(Kurang SP yang sudah dibatasi kuota, ⌊Target × % target / 100⌋).
            $capTargetEksemplar = null;
            if ($applyTargetCap && $targetNasionalVal > 0) {
                $capTargetEksemplar = (int) floor(($percentageTarget / 100) * $targetNasionalVal);
            }
            if (! $useSavedNppbQuantities && $capTargetEksemplar !== null && $capTargetEksemplar >= 1) {
                $exp = min((int) $exp, $capTargetEksemplar);
                if ($exp > 0) {
                    $koli = (int) floor($exp / $volumeForSplit);
                    $pls = (int) ($exp % $volumeForSplit);
                } else {
                    $koli = 0;
                    $pls = 0;
                }
            }

            // (Faktur + Stock Cabang + NPPB yang telah disetujui) vs SP (per cabang) dan vs Target (nasional: seluruh cabang).
            $pctFakturStockTotalVsSp = $sp > 0 ? round((($faktur + $stockCabang) / $sp) * 100, 2) : 0;
            $pctFakturStockTotalVsTarget = $targetNasionalVal > 0 ? round((($fakturNasional + $stockCabangNasional) / $targetNasionalVal) * 100, 2) : 0;

            return [
                'book_code' => $product->book_code,
                'book_name' => $product->book_title,
                'koli' => $koli,
                'exp' => $exp,
                'pls' => $pls,
                'cap_target_eksemplar' => $capTargetEksemplar,
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
                'volume_used' => $volumeUsedRaw > 0 ? $volumeUsedRaw : 0,
                'volume_options' => $volumeOptions,
                'maksimal_total_eksemplar_nasional' => $maksimalTotalEksemplarNasional,
                'sisa_kuota_eksemplar' => $sisaKuotaEksemplar,
                'row_highlight_yellow' => $rowHighlightYellow,
            ];
        });

        // Urutkan berdasarkan parameter sort (pakai numerik agar string dari DB tidak salah urut)
        if ($sort !== '') {
            switch ($sort) {
                case 'sp_desc':
                    $results = $results->sortByDesc(fn($item) => (float)($item['sp'] ?? 0))->values();
                    break;
                case 'sp_asc':
                    $results = $results->sortBy(fn($item) => (float)($item['sp'] ?? 0))->values();
                    break;
                case 'pct_sp_desc':
                    $results = $results->sortByDesc(fn($item) => (float)($item['pct_stock_pusat_sp'] ?? 0))->values();
                    break;
                case 'pct_sp_asc':
                    $results = $results->sortBy(fn($item) => (float)($item['pct_stock_pusat_sp'] ?? 0))->values();
                    break;
                case 'exp_desc':
                    $results = $results->sortByDesc(fn($item) => (float)($item['exp'] ?? 0))->values();
                    break;
                case 'exp_asc':
                    $results = $results->sortBy(fn($item) => (float)($item['exp'] ?? 0))->values();
                    break;
                case 'sisa_sp_desc':
                    $results = $results->sortByDesc(fn($item) => (float)($item['sisa_sp'] ?? 0))->values();
                    break;
                case 'sisa_sp_asc':
                    $results = $results->sortBy(fn($item) => (float)($item['sisa_sp'] ?? 0))->values();
                    break;
                case 'target_desc':
                    $results = $results->sortByDesc(fn($item) => (float)($item['target_nasional'] ?? 0))->values();
                    break;
                case 'target_asc':
                    $results = $results->sortBy(fn($item) => (float)($item['target_nasional'] ?? 0))->values();
                    break;
                default:
                    break;
            }
        }

        // Totals hanya dihitung jika tidak skip_totals (sesi 1 load rows saja; totals di sesi 2)
        $totals = $skipTotals ? null : $this->getNppbCentralTotals($branchCode, $activeCutoff, $currentYear, $percentage, $productsQueryForTotals, false);

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
     * Target join periods dengan filter tanggal selaras dashboard/rekap:
     * overlap cutoff aktif, atau overlap tahun kalender jika tidak ada cutoff.
     * (Tidak memakai periods.status saja — di data nyata status sering tidak aktif sehingga target jadi 0.)
     */
    protected function targetsJoinedPeriodsForNppbCutoff(?object $activeCutoff, string $currentYear)
    {
        $q = Target::query()->join('periods', 'targets.period_code', '=', 'periods.period_code');
        if ($activeCutoff) {
            $endDate = $activeCutoff->end_date;
            $startDate = $activeCutoff->start_date;
            if ($startDate !== null) {
                $q->where('periods.from_date', '<=', $endDate)
                    ->where('periods.to_date', '>=', $startDate);
            } else {
                $q->where('periods.to_date', '<=', $endDate);
            }
        } else {
            $yearStart = $currentYear . '-01-01';
            $yearEnd = $currentYear . '-12-31';
            $q->where('periods.from_date', '<=', $yearEnd)
                ->where('periods.to_date', '>=', $yearStart);
        }

        return $q;
    }

    /**
     * @return \Illuminate\Support\Collection<string, object> key book_code
     */
    protected function getTargetNasionalByBookCodes($bookCodes, ?object $activeCutoff, string $currentYear)
    {
        $bookCodes = collect($bookCodes)->filter()->unique()->values()->all();
        if ($bookCodes === []) {
            return collect();
        }

        return $this->targetsJoinedPeriodsForNppbCutoff($activeCutoff, $currentYear)
            ->select(['targets.book_code', DB::raw('SUM(targets.exemplar) as target_nasional')])
            ->whereIn('targets.book_code', $bookCodes)
            ->whereNotNull('targets.book_code')
            ->groupBy('targets.book_code')
            ->get()
            ->keyBy('book_code');
    }

    protected function sumTargetNasionalForNppbCutoff(?object $activeCutoff, string $currentYear): float
    {
        return (float) $this->targetsJoinedPeriodsForNppbCutoff($activeCutoff, $currentYear)
            ->sum('targets.exemplar');
    }

    protected function sumTargetNasionalForBooks($bookCodes, ?object $activeCutoff, string $currentYear): float
    {
        $bookCodes = collect($bookCodes)->filter()->unique()->values()->all();
        if ($bookCodes === []) {
            return 0.0;
        }

        return (float) $this->targetsJoinedPeriodsForNppbCutoff($activeCutoff, $currentYear)
            ->whereIn('targets.book_code', $bookCodes)
            ->sum('targets.exemplar');
    }

    /**
     * Subquery: semua kode buku di katalog (hindari WHERE IN (...) dengan ribuan literal — jauh lebih cepat di MySQL).
     */
    protected function booksBookCodeSubquery()
    {
        return Book::query()->select('book_code');
    }

    /**
     * Totals untuk endpoint totals_only: hitung penuh dengan subquery + cursor (tanpa pluck besar / IN literal).
     */
    protected function getNppbCentralTotalsFast(string $branchCode, ?object $activeCutoff, string $currentYear, int $percentage, array $effectiveBranchCodes = []): array
    {
        return $this->getNppbCentralTotals($branchCode, $activeCutoff, $currentYear, $percentage, null, true, $effectiveBranchCodes);
    }

    /**
     * Hitung totals NPPB Central (untuk baris Total) via query agregat - tanpa load semua baris ke memory
     *
     * @param  mixed  $productsQuery  Clone query produk (dengan filter); null jika $allBooksViaSubquery true
     * @param  bool  $allBooksViaSubquery  true = seluruh baris di tabel books (path totals_only cepat)
     */
    protected function getNppbCentralTotals(string $branchCode, ?object $activeCutoff, string $currentYear, int $percentage, $productsQuery, bool $allBooksViaSubquery = false, array $effectiveBranchCodes = []): array
    {
        $emptyTotals = [
            'stock_pusat' => 0,
            'target_nasional' => 0,
            'stock_nasional' => 0,
            'sp_nasional' => 0,
            'stock_teralokasikan' => 0,
            'maksimal_total_eksemplar_nasional' => 0,
            'sisa_kuota_eksemplar' => 0,
            'sisa_stock_pusat' => 0,
            'sp' => 0,
            'faktur' => 0,
            'stock_cabang' => 0,
            'sisa_sp' => 0,
            'sisa_sp_nasional' => 0,
            'koli' => 0,
            'pls' => 0,
            'exp' => 0,
            'pct_stock_pusat_target_nasional_avg' => 0,
            'pct_stock_pusat_sp_avg' => 0,
            'pct_faktur_stock_total_vs_sp_avg' => 0,
            'pct_faktur_stock_total_vs_target_avg' => 0,
        ];

        if ($allBooksViaSubquery) {
            if (! Book::query()->exists()) {
                return $emptyTotals;
            }
            $allBookCodes = null;
            // Subquery baru per pemakaian agar builder tidak terkontaminasi antar-query
            $booksIn = fn() => Book::query()->select('book_code');
        } else {
            $allBookCodes = (clone $productsQuery)->orderBy('book_code')->pluck('book_code');
            if ($allBookCodes->isEmpty()) {
                return $emptyTotals;
            }
            $booksIn = fn() => $allBookCodes;
        }

        // Jika array effectiveBranchCodes kosong, gunakan branchCode
        $branchesToQuery = empty($effectiveBranchCodes) ? [$branchCode] : $effectiveBranchCodes;

        $totalStockPusat = CentralStock::whereIn('book_code', $booksIn())->sum('exemplar')
            + StockMutationItem::whereIn('book_code', $booksIn())->sum('total_eksemplar');
        $totalDeducted = CentralStockDeduction::whereIn('book_code', $booksIn())->sum('quantity');
        $stockPusatTotal = max(0, $totalStockPusat - $totalDeducted);

        $spBranchBase = SpBranch::where('active_data', 'yes')->whereIn('branch_code', $branchesToQuery)->whereIn('book_code', $booksIn());
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $spBranchBase->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $spBranchBase->where('trans_date', '<=', $activeCutoff->end_date);
        }
        $spTotal = (clone $spBranchBase)->sum('ex_sp');
        $fakturTotal = (clone $spBranchBase)->sum('ex_ftr');
        $stockCabangTotal = (clone $spBranchBase)->sum('ex_stock');

        $spNasionalBase = SpBranch::where('active_data', 'yes')->whereIn('book_code', $booksIn());
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $spNasionalBase->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $spNasionalBase->where('trans_date', '<=', $activeCutoff->end_date);
        }
        $stockNasionalTotal = (clone $spNasionalBase)->sum('ex_stock');
        $spNasionalTotal = (clone $spNasionalBase)->sum('ex_sp');

        $stockTeralokasikanQuery = DeliveryNoteDetail::query()
            ->join('delivery_notes', 'delivery_notes.nota_kirim_cab', '=', 'delivery_note_details.nota_kirim_cab')
            ->leftJoin('m_terima_buku as ntb', 'ntb.nota_kirim_cab', '=', 'delivery_notes.nota_kirim_cab')
            ->whereNull('ntb.nota_kirim_cab')
            ->whereIn('delivery_note_details.book_code', $booksIn())
            ->whereNotNull('delivery_note_details.book_code');
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $stockTeralokasikanQuery->whereBetween('delivery_notes.send_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $stockTeralokasikanQuery->where('delivery_notes.send_date', '<=', $activeCutoff->end_date);
        } else {
            $stockTeralokasikanQuery->whereYear('delivery_notes.send_date', $currentYear);
        }
        $stockTeralokasikanTotal = (clone $stockTeralokasikanQuery)->sum('delivery_note_details.exemplar');
        $stockCabangTotal += (float) $stockTeralokasikanTotal;
        $stockTeralokasikanPerBook = (clone $stockTeralokasikanQuery)
            ->select([
                'delivery_note_details.book_code',
                DB::raw('SUM(delivery_note_details.exemplar) as stock_teralokasikan'),
            ])
            ->groupBy('delivery_note_details.book_code')
            ->get()
            ->keyBy('book_code');

        $nppbCentralBranchQ = NppbCentral::whereIn('branch_code', $branchesToQuery)->whereIn('book_code', $booksIn());
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $nppbCentralBranchQ->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $nppbCentralBranchQ->where('date', '<=', $activeCutoff->end_date);
        } else {
            $nppbCentralBranchQ->whereYear('date', $currentYear);
        }
        $existingNppbTotalsRow = (clone $nppbCentralBranchQ)
            ->selectRaw('COALESCE(SUM(koli), 0) as sum_koli, COALESCE(SUM(exp), 0) as sum_exp, COALESCE(SUM(pls), 0) as sum_pls')
            ->first();

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
            ->whereIn('branch_code', $branchesToQuery)
            ->whereIn('book_code', $booksIn());
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $spBranchPerBookQ->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $spBranchPerBookQ->where('trans_date', '<=', $activeCutoff->end_date);
        }
        $spBranchPerBook = $spBranchPerBookQ->groupBy('book_code')->get()->keyBy('book_code');

        // Intransit per book untuk cabang (tujuan delivery_notes.branch_code)
        $intransitQuery = DeliveryNoteDetail::select([
            'delivery_note_details.book_code',
            DB::raw('SUM(exemplar) as total_intransit'),
        ])->join('delivery_notes', 'delivery_notes.nota_kirim_cab', '=', 'delivery_note_details.nota_kirim_cab')
            ->whereIn('delivery_notes.branch_code', $branchesToQuery)
            ->whereIn('delivery_note_details.book_code', $booksIn());
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $intransitQuery->whereBetween('delivery_notes.send_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $intransitQuery->where('delivery_notes.send_date', '<=', $activeCutoff->end_date);
        } else {
            $intransitQuery->whereYear('delivery_notes.send_date', $currentYear);
        }
        $intransitPerBook = $intransitQuery->groupBy('delivery_note_details.book_code')->get()->keyBy('book_code');

        // NPPB Central yang sudah disetujui (punya document_id) per book cabang
        $nppbApprovedPerBook = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as nppb_approved_exp'),
        ])->whereIn('branch_code', $branchesToQuery)
            ->whereIn('book_code', $booksIn())
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

        $centralStocksByBook = CentralStock::select([
            'book_code',
            DB::raw('SUM(exemplar) as total_stock_pusat'),
        ])
            ->whereIn('book_code', $booksIn())
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        $centralStockDeductionsByBook = CentralStockDeduction::select([
            'book_code',
            DB::raw('SUM(quantity) as total_deducted'),
        ])
            ->whereIn('book_code', $booksIn())
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        $stockMutationsByBook = StockMutationItem::select([
            'book_code',
            DB::raw('SUM(total_eksemplar) as total_mutasi'),
        ])
            ->whereIn('book_code', $booksIn())
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        $spBranchNasionalPerBookQ = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_stock) as stock_nasional'),
            DB::raw('SUM(ex_sp) as sp_nasional'),
            DB::raw('SUM(ex_ftr) as faktur_nasional'),
        ])
            ->where('active_data', 'yes')
            ->whereIn('book_code', $booksIn());
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $spBranchNasionalPerBookQ->whereBetween('trans_date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $spBranchNasionalPerBookQ->where('trans_date', '<=', $activeCutoff->end_date);
        }
        $spBranchNasionalPerBook = $spBranchNasionalPerBookQ->groupBy('book_code')->get()->keyBy('book_code');

        // Intransit nasional: satu query JOIN (hindari pluck ribuan nota_kirim_cab + WHERE IN nota)
        $intransitDataNasionalTotals = collect();
        if ($activeCutoff) {
            $inNasQ = DeliveryNoteDetail::query()
                ->select(['delivery_note_details.book_code', DB::raw('SUM(delivery_note_details.exemplar) as total_intransit')])
                ->join('delivery_notes', 'delivery_notes.nota_kirim_cab', '=', 'delivery_note_details.nota_kirim_cab')
                ->whereIn('delivery_note_details.book_code', $booksIn())
                ->whereNotNull('delivery_note_details.book_code');
            if ($activeCutoff->start_date !== null) {
                $inNasQ->whereBetween('delivery_notes.send_date', [$activeCutoff->start_date, $activeCutoff->end_date]);
            } else {
                $inNasQ->where('delivery_notes.send_date', '<=', $activeCutoff->end_date);
            }
            $intransitDataNasionalTotals = $inNasQ->groupBy('delivery_note_details.book_code')->get()->keyBy('book_code');
        }

        $nppbApprovedExpNasionalQ = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as nppb_approved_exp'),
        ])
            ->whereIn('book_code', $booksIn())
            ->whereNotNull('document_id')
            ->where('document_id', '!=', 0);
        if ($activeCutoff) {
            $activeCutoff->start_date !== null
                ? $nppbApprovedExpNasionalQ->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date])
                : $nppbApprovedExpNasionalQ->where('date', '<=', $activeCutoff->end_date);
        } else {
            $nppbApprovedExpNasionalQ->whereYear('date', $currentYear);
        }
        $nppbApprovedExpNasionalTotals = $nppbApprovedExpNasionalQ->groupBy('book_code')->get()->keyBy('book_code');

        if ($allBooksViaSubquery) {
            $targetNasionalByBook = $this->targetsJoinedPeriodsForNppbCutoff($activeCutoff, $currentYear)
                ->select(['targets.book_code', DB::raw('SUM(targets.exemplar) as target_nasional')])
                ->whereIn('targets.book_code', $this->booksBookCodeSubquery())
                ->whereNotNull('targets.book_code')
                ->groupBy('targets.book_code')
                ->get()
                ->keyBy('book_code');
        } else {
            $targetNasionalByBook = $this->getTargetNasionalByBookCodes($allBookCodes, $activeCutoff, $currentYear);
        }

        $pctVals = [];
        $pctStockTargetVals = [];
        $pctStockSpVals = [];
        $pctFtrTargetVals = [];

        $bookIterator = $allBooksViaSubquery
            ? Book::query()->select('book_code')->orderBy('book_code')->cursor()
            : $allBookCodes;

        foreach ($bookIterator as $bookRowOrCode) {
            $bc = $allBooksViaSubquery ? $bookRowOrCode->book_code : $bookRowOrCode;
            $row = $spBranchPerBook->get($bc);
            $sp = $row ? (float) ($row->sp ?? 0) : 0;
            $faktur = $row ? (float) ($row->faktur ?? 0) : 0;
            $stockCabangBase = $row ? (float) ($row->stock_cabang ?? 0) : 0;
            $intr = (float) (($intransitPerBook->get($bc)?->total_intransit ?? 0) ?: 0);
            $apr = (float) (($nppbApprovedPerBook->get($bc)?->nppb_approved_exp ?? 0) ?: 0);
            $teralokasi = (float) (($stockTeralokasikanPerBook->get($bc)?->stock_teralokasikan ?? 0) ?: 0);
            $stockCabang = $stockCabangBase + $intr + $apr + $teralokasi;

            $stkRow = $centralStocksByBook->get($bc);
            $mutRow = $stockMutationsByBook->get($bc);
            $dedRow = $centralStockDeductionsByBook->get($bc);
            $rawStockPusat = (float) ($stkRow?->total_stock_pusat ?? 0) + (float) ($mutRow?->total_mutasi ?? 0);
            $deducted = (float) ($dedRow?->total_deducted ?? 0);
            $stockPusat = max(0, $rawStockPusat - $deducted);

            $selisih = $sp - $faktur;
            if ($stockCabang >= $selisih) {
                $sisaSp = 0;
            } else {
                $sisaSp = (int) max(0, $selisih - $stockCabang);
            }
            $sisaSpTotal += $sisaSp;

            $spNas = $spBranchNasionalPerBook->get($bc);
            $spNasional = (float) ($spNas?->sp_nasional ?? 0);
            $fakturNasional = (float) ($spNas?->faktur_nasional ?? 0);
            $inNas = $intransitDataNasionalTotals->get($bc);
            $totalIntransitNasional = $inNas ? (float) ($inNas->total_intransit ?? 0) : 0;
            $nppbAppNas = $nppbApprovedExpNasionalTotals->get($bc);
            $nppbAppNasVal = $nppbAppNas ? (float) ($nppbAppNas->nppb_approved_exp ?? 0) : 0;
            $stockCabangNasional = (float) ($spNas?->stock_nasional ?? 0) + $totalIntransitNasional + $nppbAppNasVal + $teralokasi;

            $kurangSpNasional = (int) max(0, $spNasional - $fakturNasional - $stockCabangNasional);
            $kurangSpNasionalTotal += $kurangSpNasional;

            if ($sp > 0) {
                $pctVals[] = (($faktur + $stockCabangBase + $intr + $apr + $teralokasi) / $sp) * 100;
            }

            $targetNasionalVal = (float) ($targetNasionalByBook->get($bc)?->target_nasional ?? 0);
            if ($targetNasionalVal > 0) {
                $pctStockTargetVals[] = ($stockPusat / $targetNasionalVal) * 100;
                $pctFtrTargetVals[] = (($fakturNasional + $stockCabangNasional) / $targetNasionalVal) * 100;
            }
            if ($sp > 0) {
                $pctStockSpVals[] = ($stockPusat / $sp) * 100;
            }
        }

        $pctFtrSpAvg = count($pctVals) ? round(array_sum($pctVals) / count($pctVals), 2) : 0;
        $pctStockTargetAvg = count($pctStockTargetVals) ? round(array_sum($pctStockTargetVals) / count($pctStockTargetVals), 2) : 0;
        $pctStockSpAvg = count($pctStockSpVals) ? round(array_sum($pctStockSpVals) / count($pctStockSpVals), 2) : 0;
        $pctFtrTargetAvg = count($pctFtrTargetVals) ? round(array_sum($pctFtrTargetVals) / count($pctFtrTargetVals), 2) : 0;

        $targetNasionalSum = $allBooksViaSubquery
            ? (float) $this->targetsJoinedPeriodsForNppbCutoff($activeCutoff, $currentYear)
                ->whereIn('targets.book_code', $this->booksBookCodeSubquery())
                ->sum('targets.exemplar')
            : $this->sumTargetNasionalForBooks($allBookCodes, $activeCutoff, $currentYear);

        return [
            'stock_pusat' => $stockPusatTotal,
            'target_nasional' => $targetNasionalSum,
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
            'koli' => (float) ($existingNppbTotalsRow->sum_koli ?? 0),
            'pls' => (float) ($existingNppbTotalsRow->sum_pls ?? 0),
            'exp' => (float) ($existingNppbTotalsRow->sum_exp ?? 0),
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
        $productsQuery = Book::select('book_code', 'book_title');
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

        $stockMutationsByBookWarehouse = StockMutationItem::select([
            'book_code',
            DB::raw('SUM(total_eksemplar) as total_mutasi'),
        ])
            ->whereIn('book_code', $products->pluck('book_code'))
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

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

        $targetNasional = $this->getTargetNasionalByBookCodes($products->pluck('book_code'), $activeCutoff, $currentYear);

        $results = $products->map(function ($product) use ($centralStocks, $centralStockDeductionsWarehouse, $stockMutationsByBookWarehouse, $existingNppb, $spBranchData, $intransitData, $allStockKolis, $volumeOptionsByBookWarehouse, $nppbApprovedExpByBook, $targetNasional, $spBranchNasional, $intransitDataNasional, $nppbApprovedExpNasional, $percentage, $lastSpBranchSync) {
            $stock = $centralStocks->get($product->book_code);
            $nppb = $existingNppb->get($product->book_code);
            $hasExistingData = ($nppb !== null);
            $nppbUpdatedAt = $nppb && isset($nppb->nppb_updated_at) ? $nppb->nppb_updated_at : null;
            $rowHighlightYellow = $hasExistingData && ($lastSpBranchSync === null || ($nppbUpdatedAt && $nppbUpdatedAt > $lastSpBranchSync));
            $spBranch = $spBranchData->get($product->book_code);
            $targetNasionalRow = $targetNasional->get($product->book_code);

            $sp = $spBranch?->sp ?? 0;
            $faktur = $spBranch?->faktur ?? 0;
            $stockCabang = $spBranch?->stock_cabang ?? 0;
            $mutasiWh = $stockMutationsByBookWarehouse->get($product->book_code);
            $rawStockPusatWarehouse = (float) ($stock->total_stock_pusat ?? 0) + (float) ($mutasiWh?->total_mutasi ?? 0);
            $deductedWarehouse = $centralStockDeductionsWarehouse->get($product->book_code)?->total_deducted ?? 0;
            $stockPusat = max(0, $rawStockPusatWarehouse - $deductedWarehouse);
            $targetNasionalVal = $targetNasionalRow?->target_nasional ?? 0;

            // Tambahkan eksemplar dari NPPB yang sudah diapprove ke stock cabang
            $nppbApproved = $nppbApprovedExpByBook->get($product->book_code);
            $stockCabang += $nppbApproved ? (int) ($nppbApproved->nppb_approved_exp ?? 0) : 0;

            $selisih = $sp - $faktur;
            if ($stockCabang >= $selisih) {
                $sisaSp = 0;
            } else {
                $sisaSp = max(0, $selisih - $stockCabang);
            }

            $exp = (int) ($nppb?->exp ?? 0);
            $koli = (int) ($nppb?->koli ?? 0);
            $pls = (int) ($nppb?->pls ?? 0);
            // Pilihan Isi: hanya row dengan koli > 0 (selaras NPPB Central)
            $optsWh = $volumeOptionsByBookWarehouse->get($product->book_code) ?? collect();
            $optsWhWithKoli = $optsWh->filter(fn($r) => (int) ($r->koli ?? 0) > 0);
            $volumeOptionsWh = $optsWhWithKoli->sortByDesc('volume')->values()->map(function ($r) {
                $v = (float) $r->volume;
                $koliVal = (int) ($r->koli ?? 0);
                $label = (string) (int) $v . ' (' . $koliVal . ')';
                return ['value' => $v, 'label' => $label];
            })->values()->toArray();
            $volumeUsed = $optsWhWithKoli->isEmpty() ? 0 : (float) $optsWhWithKoli->max('volume');
            $stockKoli = $allStockKolis->get($product->book_code);
            if ($volumeUsed <= 0 && $stockKoli) {
                $volumeUsed = (float)$stockKoli->volume;
            }

            if (!$hasExistingData) {
                $exp = (int) $sisaSp;
                $koli = 0;
                $pls = 0;
                if ($volumeUsed > 0 && $exp > 0) {
                    $koli = (int) floor($exp / $volumeUsed);
                    $pls = (int) ($exp % $volumeUsed);
                } elseif ($volumeUsed <= 0 && $exp > 0) {
                    $pls = $exp;
                    $koli = 0;
                }
            }

            $intransit = $intransitData->get($product->book_code);
            $totalIntransit = $intransit ? ($intransit->total_intransit ?? 0) : 0;

            // % vs SP: per warehouse. % vs Target: nasional (faktur + stock cabang + intransit + nppb disetujui seluruh cabang) vs target dari table target.
            $spNasionalRow = $spBranchNasional->get($product->book_code);
            $spNasional = $spNasionalRow?->sp_nasional ?? 0;
            $fakturNasional = $spNasionalRow?->faktur_nasional ?? 0;
            $intransitNasional = $intransitDataNasional->get($product->book_code);
            $totalIntransitNasional = $intransitNasional ? ($intransitNasional->total_intransit ?? 0) : 0;
            $nppbApprovedNasional = $nppbApprovedExpNasional->get($product->book_code);
            $stockCabangNasional = ($spNasionalRow?->stock_nasional ?? 0) + $totalIntransitNasional + ($nppbApprovedNasional ? (int)($nppbApprovedNasional->nppb_approved_exp ?? 0) : 0);

            $kurangSpNasional = max(0, $spNasional - $fakturNasional - $stockCabangNasional);
            $pctSpVsStock = $stockPusat > 0 ? round(($kurangSpNasional / $stockPusat) * 100, 2) : 0;
            // Target & % vs target informatif; rencana tidak diblokir oleh target / ambang % Stk/Kur SP.
            $allowRencanaKirim = true;

            $pctFakturStockTotalVsSp = $sp > 0 ? round((($faktur + $stockCabang) / $sp) * 100, 2) : 0;
            $pctFakturStockTotalVsTarget = $targetNasionalVal > 0 ? round((($fakturNasional + $stockCabangNasional) / $targetNasionalVal) * 100, 2) : 0;

            $stockNasional = $spNasionalRow?->stock_nasional ?? 0;

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
                    $results = $results->sortByDesc(fn($item) => (float)($item['sp'] ?? 0))->values();
                    break;
                case 'sp_asc':
                    $results = $results->sortBy(fn($item) => (float)($item['sp'] ?? 0))->values();
                    break;
                case 'exp_desc':
                    $results = $results->sortByDesc(fn($item) => (float)($item['exp'] ?? 0))->values();
                    break;
                case 'exp_asc':
                    $results = $results->sortBy(fn($item) => (float)($item['exp'] ?? 0))->values();
                    break;
                case 'sisa_sp_desc':
                    $results = $results->sortByDesc(fn($item) => (float)($item['sisa_sp'] ?? 0))->values();
                    break;
                case 'sisa_sp_asc':
                    $results = $results->sortBy(fn($item) => (float)($item['sisa_sp'] ?? 0))->values();
                    break;
                case 'target_desc':
                    $results = $results->sortByDesc(fn($item) => (float)($item['target_nasional'] ?? 0))->values();
                    break;
                case 'target_asc':
                    $results = $results->sortBy(fn($item) => (float)($item['target_nasional'] ?? 0))->values();
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
                        $book = Book::where('book_code', $bookCode)->first();
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
