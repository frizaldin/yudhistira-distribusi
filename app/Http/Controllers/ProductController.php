<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CentralStock;
use App\Models\CutoffData;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteDetail;
use App\Models\NppbCentral;
use App\Models\Product;
use App\Models\SpBranch;
use App\Models\Staging\Master\Book;
use App\Jobs\SynchronizeProductsJob;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Imports\ProductsImport;
use App\Imports\ProductCategorySerialImport;
use App\Jobs\ImportProductCategorySerialJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/product');
        $this->title = 'Data Produk';

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
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->search, function ($query, $search) {
                return $query->where('book_title', 'like', '%' . $search . '%');
            })
            ->when($request->jenis, function ($query, $jenis) {
                return $query->where('category', $jenis);
            })
            ->when($request->jenjang, function ($query, $jenjang) {
                return $query->where('jenjang', 'like', '%' . $jenjang . '%');
            })
            ->when($request->status, function ($query, $status) {
                // Tambahkan logika status jika diperlukan
            })
            ->orderBy('book_code')
            ->paginate(15);

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'products' => $products,
        ];

        return view($this->callbackfolder . '.master-data.product.index', $data);
    }

    /**
     * Detail produk: stok buku, SP, faktur, NPPB, intransit per cabang berdasarkan tanggal cutoff.
     */
    public function showDetail(Request $request, string $book_code)
    {
        $product = Product::where('book_code', $book_code)->firstOrFail();

        $cutoffId = $request->get('cutoff_id');
        $cutoff = $cutoffId
            ? CutoffData::find($cutoffId)
            : CutoffData::where('status', 'active')->first();

        if (!$cutoff) {
            return redirect()
                ->route('product.index')
                ->with('error', 'Tidak ada periode cutoff aktif. Silakan atur cutoff di halaman Staging.');
        }

        $endDate = $cutoff->end_date;
        $startDate = $cutoff->start_date; // null = data <= end_date

        $searchBranch = $request->get('search');
        $sortBy = $request->get('sort_by', 'branch_code');
        $sortDir = strtolower($request->get('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $branches = Branch::query()
            ->when($filteredBranchCodes !== null, function ($q) use ($filteredBranchCodes) {
                $q->whereIn('branch_code', $filteredBranchCodes);
            })
            ->when($searchBranch, function ($q) use ($searchBranch) {
                $q->where('branch_code', 'like', '%' . $searchBranch . '%')
                    ->orWhere('branch_name', 'like', '%' . $searchBranch . '%');
            })
            ->orderBy('branch_code')
            ->get();

        // SP, Faktur, Stock Cabang per cabang (sp_branches)
        $spBranchRowsQuery = SpBranch::select([
            'branch_code',
            DB::raw('SUM(ex_sp) as sp'),
            DB::raw('SUM(ex_ftr) as faktur'),
            DB::raw('SUM(ex_stock) as stock_cabang'),
        ])
            ->where('book_code', $book_code)
            ->where('active_data', 'yes');
        if ($startDate !== null) {
            $spBranchRowsQuery->whereBetween('trans_date', [$startDate, $endDate]);
        } else {
            $spBranchRowsQuery->where('trans_date', '<=', $endDate);
        }
        $spBranchRows = $spBranchRowsQuery->groupBy('branch_code')->get()->keyBy('branch_code');

        // NPPB per cabang (eksemplar, koli, pls)
        $nppbRowsQuery = NppbCentral::select([
            'branch_code',
            DB::raw('SUM(COALESCE(exp, 0)) as nppb_exp'),
            DB::raw('SUM(COALESCE(koli, 0)) as nppb_koli'),
            DB::raw('SUM(COALESCE(pls, 0)) as nppb_pls'),
        ])
            ->where('book_code', $book_code);
        if ($startDate !== null) {
            $nppbRowsQuery->whereBetween('date', [$startDate, $endDate]);
        } else {
            $nppbRowsQuery->where('date', '<=', $endDate);
        }
        $nppbRows = $nppbRowsQuery->groupBy('branch_code')->get()->keyBy('branch_code');

        // Intransit per cabang (cabang = tujuan delivery_notes.branch_code)
        $deliveryNoteQuery = DeliveryNote::query();
        if ($startDate !== null) {
            $deliveryNoteQuery->whereBetween('send_date', [$startDate, $endDate]);
        } else {
            $deliveryNoteQuery->where('send_date', '<=', $endDate);
        }
        $deliveryNoteIds = $deliveryNoteQuery->pluck('nota_kirim_cab');

        $intransitRows = collect();
        if ($deliveryNoteIds->isNotEmpty()) {
            $intransitRows = DeliveryNoteDetail::select('delivery_notes.branch_code', DB::raw('SUM(delivery_note_details.exemplar) as intransit'))
                ->join('delivery_notes', 'delivery_note_details.nota_kirim_cab', '=', 'delivery_notes.nota_kirim_cab')
                ->whereIn('delivery_note_details.nota_kirim_cab', $deliveryNoteIds)
                ->where('delivery_note_details.book_code', $book_code)
                ->groupBy('delivery_notes.branch_code')
                ->get()
                ->keyBy('branch_code');
        }

        $branchData = [];
        foreach ($branches as $branch) {
            $spRow = $spBranchRows->get($branch->branch_code);
            $nppbRow = $nppbRows->get($branch->branch_code);
            $intransitRow = $intransitRows->get($branch->branch_code);
            $branchData[$branch->branch_code] = [
                'sp' => $spRow ? (int) $spRow->sp : 0,
                'faktur' => $spRow ? (int) $spRow->faktur : 0,
                'stock_cabang' => $spRow ? (int) $spRow->stock_cabang : 0,
                'nppb_exp' => $nppbRow ? (int) $nppbRow->nppb_exp : 0,
                'nppb_koli' => $nppbRow ? (int) $nppbRow->nppb_koli : 0,
                'nppb_pls' => $nppbRow ? (int) $nppbRow->nppb_pls : 0,
                'intransit' => $intransitRow ? (int) $intransitRow->intransit : 0,
            ];
        }

        $centralStockTotal = (int) CentralStock::where('book_code', $book_code)->sum('exemplar');

        // Sortir: by branch_code, branch_name, atau kolom numerik dari branchData
        $validSortColumns = ['branch_code', 'branch_name', 'stock_cabang', 'sp', 'faktur', 'nppb_exp', 'nppb_koli', 'intransit'];
        if (!in_array($sortBy, $validSortColumns)) {
            $sortBy = 'branch_code';
        }
        $branches = $branches->sort(function ($a, $b) use ($sortBy, $sortDir, $branchData) {
            $valA = in_array($sortBy, ['branch_code', 'branch_name'], true)
                ? ($sortBy === 'branch_code' ? $a->branch_code : ($a->branch_name ?? ''))
                : ($branchData[$a->branch_code][$sortBy] ?? 0);
            $valB = in_array($sortBy, ['branch_code', 'branch_name'], true)
                ? ($sortBy === 'branch_code' ? $b->branch_code : ($b->branch_name ?? ''))
                : ($branchData[$b->branch_code][$sortBy] ?? 0);
            if (is_numeric($valA) && is_numeric($valB)) {
                return $sortDir === 'asc' ? $valA <=> $valB : $valB <=> $valA;
            }
            $cmp = strcasecmp((string) $valA, (string) $valB);
            return $sortDir === 'asc' ? $cmp : -$cmp;
        })->values();

        $cutoffs = CutoffData::orderByDesc('end_date')->get();

        $data = [
            'title' => 'Detail Produk - ' . $product->book_title,
            'base_url' => $this->base_url,
            'product' => $product,
            'cutoff' => $cutoff,
            'cutoffs' => $cutoffs,
            'branches' => $branches,
            'branchData' => $branchData,
            'centralStockTotal' => $centralStockTotal,
            'searchBranch' => $searchBranch,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ];

        return view($this->callbackfolder . '.master-data.product.detail', $data);
    }

    /**
     * Import products from Excel
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls|max:10240' // Max 10MB untuk file besar
            ]);

            $file = $request->file('file');

            if (!$file) {
                return redirect()->back()->with('error', 'File tidak ditemukan. Pastikan Anda memilih file untuk diupload.');
            }

            if (!$file->isValid()) {
                return redirect()->back()->with('error', 'File tidak valid. Error: ' . $file->getErrorMessage());
            }

            // Pastikan direktori imports ada di storage
            $importDir = storage_path('app/private/imports');
            if (!is_dir($importDir)) {
                if (!mkdir($importDir, 0755, true)) {
                    return redirect()->back()->with('error', 'Gagal membuat direktori imports. Pastikan storage/app/private bisa ditulis.');
                }
            }

            // Buat filename yang aman
            $originalName = $file->getClientOriginalName();
            if (empty($originalName)) {
                $originalName = 'import_' . time() . '.xlsx';
            }

            $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $filename = time() . '_' . uniqid() . '_' . $safeFilename;

            // Pastikan filename tidak kosong
            if (empty($filename)) {
                $filename = time() . '_' . uniqid() . '.xlsx';
            }

            // Simpan file langsung ke path lengkap (lebih reliable)
            $fullPath = $importDir . DIRECTORY_SEPARATOR . $filename;

            // Pindahkan file yang di-upload
            if (!$file->move($importDir, $filename)) {
                return redirect()->back()->with('error', 'Gagal menyimpan file. Pastikan direktori bisa ditulis.');
            }

            if (!file_exists($fullPath)) {
                return redirect()->back()->with('error', 'File tidak ditemukan setelah disimpan di: ' . $fullPath);
            }

            // Import menggunakan queue dengan path file lengkap
            Excel::queueImport(new ProductsImport, $fullPath);

            return redirect()->back()->with('success', 'File berhasil diupload dan sedang diproses di background. Data akan diimport secara bertahap per 100 data. Silakan refresh halaman beberapa saat kemudian untuk melihat hasil import.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Import Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
        }
    }

    /**
     * Import Excel identifikasi buku: update field category_manual dan serial saja (KODE = book_code).
     * Kolom A=KODE, G=SERIAL, H=KATEGORI. Diproses via queue.
     */
    public function importCategorySerial(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls|max:10240',
            ]);

            $file = $request->file('file');
            if (!$file || !$file->isValid()) {
                return redirect()->back()->with('error', 'File tidak valid. Silakan pilih file Excel (xlsx/xls).');
            }

            $path = $file->store('imports/product-category-serial');

            ImportProductCategorySerialJob::dispatch($path);

            return redirect()->back()->with('success', 'File berhasil diupload. Import kategori & serial diproses di background. Pastikan queue worker berjalan. Refresh halaman beberapa saat kemudian.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Import Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        //
    }

    /**
     * Synchronize products from PostgreSQL (Book model) using queue
     */
    public function synchronize(Request $request)
    {
        try {
            // Clear previous progress
            Cache::forget('sync_products_progress');

            // Dispatch job ke queue untuk diproses di background
            SynchronizeProductsJob::dispatch()
                ->onQueue('default');

            Log::info('Product synchronization job dispatched to queue');

            return redirect()->back()->with('success', 'Sinkronisasi data sedang diproses di background. Data akan disinkronkan secara bertahap. Silakan refresh halaman beberapa saat kemudian untuk melihat hasil.');
        } catch (\Exception $e) {
            Log::error('Synchronize Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error sinkronisasi: ' . $e->getMessage());
        }
    }

    /**
     * Clear all products and synchronize from PostgreSQL
     */
    public function clearAndSync(Request $request)
    {
        try {
            // Clear previous progress
            Cache::forget('sync_products_progress');

            // Delete all products
            $deletedCount = Product::count();
            Product::truncate();

            Log::info("Cleared {$deletedCount} products before synchronization");

            // Dispatch job ke queue untuk diproses di background
            SynchronizeProductsJob::dispatch()
                ->onQueue('default');

            Log::info('Product clear and synchronization job dispatched to queue');

            return redirect()->back()->with('success', "Semua data produk ({$deletedCount} data) telah dihapus. Sinkronisasi data sedang diproses di background.");
        } catch (\Exception $e) {
            Log::error('Clear and Sync Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error clear and sync: ' . $e->getMessage());
        }
    }

    /**
     * Get synchronization progress
     */
    public function getProgress(Request $request)
    {
        $progress = Cache::get('sync_products_progress', [
            'status' => 'idle',
            'total' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'percentage' => 0
        ]);

        return response()->json($progress);
    }
}
