<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\NppbCentral;
use App\Models\CentralStock;
use App\Models\CentralStockKoli;
use App\Models\Periode;
use App\Models\SpBranch;
use App\Models\Target;
use App\Models\CutoffData;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
        $searchBookCode = $request->get('search_book_code', '');
        $searchBookName = $request->get('search_book_name', '');
        $percentageRaw = (int)$request->get('percentage', 100);
        $percentage = max(1, min(100, $percentageRaw));

        if (!$branchCode) {
            return response()->json([
                'results' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
                'per_page' => $perPage
            ]);
        }

        // Untuk user ADP (authority_id 3): hanya boleh akses cabang yang ada di user->branch
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

        // Get all products with separate filters for book_code and book_name
        $productsQuery = Product::select('book_code', 'book_title');

        if (!empty($searchBookCode)) {
            $productsQuery->where('book_code', 'like', '%' . $searchBookCode . '%');
        }
        if (!empty($searchBookName)) {
            $productsQuery->where('book_title', 'like', '%' . $searchBookName . '%');
        }

        $products = $productsQuery->orderBy('book_code')->get();

        // Get central stocks (total stock pusat per book_code, tidak berdasarkan branch)
        $centralStocks = CentralStock::select([
            'book_code',
            DB::raw('SUM(exemplar) as total_stock_pusat')
        ])
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Check if there's an active cutoff_data
        $activeCutoff = CutoffData::where('status', 'active')->first();

        // Get existing NPPB data for this branch and year
        // Group by book_code and sum the values
        $existingNppbQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(koli) as koli'),
            DB::raw('SUM(exp) as exp'),
            DB::raw('SUM(pls) as pls'),
        ])
            ->where('branch_code', $branchCode);

        // Filter by date range if there's an active cutoff_data (start_date null = data <= end_date)
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

        // Get SP, Faktur, and Stock Cabang from sp_branches - hanya data aktif
        // Use the same $activeCutoff variable from above
        $spBranchQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_sp) as sp'), // SP = ex_sp
            DB::raw('SUM(ex_ftr) as faktur'), // Faktur = ex_ftr
            DB::raw('SUM(ex_stock) as stock_cabang'), // Stock Cabang = ex_stock
        ])
            ->where('active_data', 'yes')
            ->where('branch_code', $branchCode);

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

        // Stock Nasional & SP Nasional: dari sp_branches seluruh cabang (group by book_code saja)
        $spBranchNasionalQuery = SpBranch::select([
            'book_code',
            DB::raw('SUM(ex_stock) as stock_nasional'),
            DB::raw('SUM(ex_sp) as sp_nasional'),
        ])
            ->where('active_data', 'yes');
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

        // Stock Teralokasikan: total eksemplar NPPB seluruh cabang (dari nppb_centrals)
        $stockTeralokasikanQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(COALESCE(exp, 0)) as stock_teralokasikan')
        ])->groupBy('book_code');
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
        // Group by branch_code dan book_code, ambil volume terbesar per book_code
        $allStockKolis = CentralStockKoli::select([
            'branch_code',
            'book_code',
            DB::raw('MAX(volume) as volume')
        ])
            ->whereIn('book_code', $products->pluck('book_code'))
            ->groupBy('branch_code', 'book_code')
            ->get();

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
                    ->whereNotNull('book_code')
                    ->groupBy('book_code');

                $intransitData = $intransitQuery->get()->keyBy('book_code');
            }
        }

        // Combine data
        $results = $products->map(function ($product) use ($centralStocks, $existingNppb, $spBranchData, $spBranchNasional, $stockTeralokasikanData, $targetNasional, $branchCode, $stockKolisByBranch, $stockKolisGeneral, $intransitData, $percentage) {
            $stock = $centralStocks->get($product->book_code);
            $nppb = $existingNppb->get($product->book_code);
            $spBranch = $spBranchData->get($product->book_code);
            $spNasionalRow = $spBranchNasional->get($product->book_code);
            $teralokasikanRow = $stockTeralokasikanData->get($product->book_code);
            $targetNasionalRow = $targetNasional->get($product->book_code);

            $sp = $spBranch->sp ?? 0;
            $faktur = $spBranch->faktur ?? 0;
            $stockCabang = $spBranch->stock_cabang ?? 0;
            $stockPusat = $stock->total_stock_pusat ?? 0;
            $stockNasional = $spNasionalRow->stock_nasional ?? 0;
            $spNasional = $spNasionalRow->sp_nasional ?? 0;
            $stockTeralokasikan = $teralokasikanRow->stock_teralokasikan ?? 0;
            $sisaStockPusat = max(0, $stockPusat - $stockTeralokasikan);
            $targetNasionalVal = $targetNasionalRow->target_nasional ?? 0;

            // Persentase
            $pctStockPusatVsTargetNasional = $targetNasionalVal > 0 ? round(($stockPusat / $targetNasionalVal) * 100, 2) : 0;
            $pctStockPusatVsSp = $sp > 0 ? round(($stockPusat / $sp) * 100, 2) : 0;

            // Calculate Sisa SP
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

            // Ambil volume koli dari pre-loaded data: prioritaskan sesuai branch, jika tidak ada pakai volume umum book_code
            $stockKoli = $stockKolisByBranch->get($product->book_code);
            if (!$stockKoli) {
                $stockKoli = $stockKolisGeneral->get($product->book_code);
            }

            // Jika belum ada data di database, lakukan perhitungan
            if (!$hasExistingData) {
                // Eksemplar diambil dari sisa SP
                $exp = $sisaSp;

                // Hitung koli dari eksemplar dibagi volume (modulo)
                // Koli = eksemplar / volume (pembulatan ke bawah)
                // Pls = eksemplar % volume (sisa pembagian)

                if ($stockKoli && $stockKoli->volume > 0 && $exp > 0) {
                    $volume = (float)$stockKoli->volume;
                    $volumeUsed = $volume;

                    $koli = floor($exp / $volume);
                    $pls = $exp % $volume;
                }
            } else {
                // Jika data sudah ada, ambil volume yang digunakan untuk info saja
                if ($stockKoli && $stockKoli->volume > 0) {
                    $volumeUsed = (float)$stockKoli->volume;
                }
            }



            // Get intransit for this book_code
            $intransit = $intransitData->get($product->book_code);
            $totalIntransit = $intransit ? ($intransit->total_intransit ?? 0) : 0;
            $stockCabang += $totalIntransit;

            // Persentase Penentuan Rencana Kirim: maksimal total eksemplar nasional = percentage% × Stock Pusat
            $maksimalTotalEksemplarNasional = (int) floor(($percentage / 100) * $stockPusat);
            $sisaKuotaEksemplar = $maksimalTotalEksemplarNasional - $stockTeralokasikan;

            return [
                'book_code' => $product->book_code,
                'book_name' => $product->book_title,
                'koli' => $koli,
                'exp' => $exp,
                'pls' => $pls,
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
                'intransit' => $totalIntransit,
                'volume_used' => $volumeUsed,
                'maksimal_total_eksemplar_nasional' => $maksimalTotalEksemplarNasional,
                'sisa_kuota_eksemplar' => $sisaKuotaEksemplar,
            ];
        });

        // Urutkan berdasarkan parameter sort
        $sort = $request->get('sort', '');
        if ($sort !== '') {
            switch ($sort) {
                case 'sp_desc':
                    $results = $results->sortByDesc('sp')->values();
                    break;
                case 'sp_asc':
                    $results = $results->sortBy('sp')->values();
                    break;
                case 'exp_desc':
                    $results = $results->sortByDesc('exp')->values();
                    break;
                case 'exp_asc':
                    $results = $results->sortBy('exp')->values();
                    break;
                case 'sisa_sp_desc':
                    $results = $results->sortByDesc('sisa_sp')->values();
                    break;
                case 'sisa_sp_asc':
                    $results = $results->sortBy('sisa_sp')->values();
                    break;
                default:
                    break;
            }
        }

        // Paginate results
        $total = $results->count();
        $lastPage = (int)ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedResults = $results->slice($offset, $perPage)->values();

        return response()->json([
            'results' => $paginatedResults,
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
            'per_page' => $perPage
        ]);
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

        $productsQuery = Product::select('book_code', 'book_title');
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

        $activeCutoff = CutoffData::where('status', 'active')->first();

        $existingNppbQuery = NppbCentral::select([
            'book_code',
            DB::raw('SUM(koli) as koli'),
            DB::raw('SUM(exp) as exp'),
            DB::raw('SUM(pls) as pls'),
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

        $results = $products->map(function ($product) use ($centralStocks, $existingNppb, $spBranchData, $intransitData, $allStockKolis) {
            $stock = $centralStocks->get($product->book_code);
            $nppb = $existingNppb->get($product->book_code);
            $spBranch = $spBranchData->get($product->book_code);

            $sp = $spBranch->sp ?? 0;
            $faktur = $spBranch->faktur ?? 0;
            $stockCabang = $spBranch->stock_cabang ?? 0;
            $stockPusat = $stock->total_stock_pusat ?? 0;

            $selisih = $sp - $faktur;
            if ($stockCabang >= $selisih) {
                $sisaSp = 0;
            } else {
                $sisaSp = max(0, $selisih - $stockCabang - $stockPusat);
            }

            $exp = $nppb->exp ?? 0;
            $koli = $nppb->koli ?? 0;
            $pls = $nppb->pls ?? 0;
            $stockKoli = $allStockKolis->get($product->book_code);
            $volumeUsed = $stockKoli ? (float)$stockKoli->volume : 0;

            $intransit = $intransitData->get($product->book_code);
            $totalIntransit = $intransit ? ($intransit->total_intransit ?? 0) : 0;

            return [
                'book_code' => $product->book_code,
                'book_name' => $product->book_title,
                'koli' => $koli,
                'exp' => $exp,
                'pls' => $pls,
                'stock_pusat' => $stockPusat,
                'sp' => $sp,
                'faktur' => $faktur,
                'stock_cabang' => $stockCabang,
                'sisa_sp' => $sisaSp,
                'intransit' => $totalIntransit,
                'volume_used' => $volumeUsed,
            ];
        });

        $sort = $request->get('sort', '');
        if ($sort !== '') {
            switch ($sort) {
                case 'sp_desc':
                    $results = $results->sortByDesc('sp')->values();
                    break;
                case 'sp_asc':
                    $results = $results->sortBy('sp')->values();
                    break;
                case 'exp_desc':
                    $results = $results->sortByDesc('exp')->values();
                    break;
                case 'exp_asc':
                    $results = $results->sortBy('exp')->values();
                    break;
                case 'sisa_sp_desc':
                    $results = $results->sortByDesc('sisa_sp')->values();
                    break;
                case 'sisa_sp_asc':
                    $results = $results->sortBy('sisa_sp')->values();
                    break;
                default:
                    break;
            }
        }

        $total = $results->count();
        $lastPage = (int)ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedResults = $results->slice($offset, $perPage)->values();

        return response()->json([
            'results' => $paginatedResults,
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
            $currentYear = date('Y');
            $currentDate = date('Y-m-d');

            if (!$branchCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch code is required'
                ], 400);
            }

            // Untuk user ADP: hanya boleh simpan untuk cabang yang ada di user->branch
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

            // Check if there's an active cutoff_data
            $activeCutoff = CutoffData::where('status', 'active')->first();

            // Delete existing data for this branch and year/cutoff range first
            $deleteQuery = NppbCentral::where('branch_code', $branchCode);

            // Filter by date range if there's an active cutoff_data
            if ($activeCutoff) {
                if ($activeCutoff->start_date !== null) {
                    $deleteQuery->whereBetween('date', [$activeCutoff->start_date, $activeCutoff->end_date]);
                } else {
                    $deleteQuery->where('date', '<=', $activeCutoff->end_date);
                }
            } else {
                $deleteQuery->whereYear('date', $currentYear);
            }

            $deleteQuery->delete();

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
