<?php

namespace App\Http\Controllers;

use App\Models\CentralStock;
use App\Jobs\SynchronizeCentralStocksJob;
use App\Imports\CentralStocksImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class CentralStockController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/central-stock');
        $this->title = 'Data Stok Pusat';

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
        $stocks = CentralStock::query()
            ->select('central_stocks.*')
            ->distinct()
            ->leftJoin('branches', 'central_stocks.branch_code', '=', 'branches.branch_code')
            ->leftJoin('books', 'central_stocks.book_code', '=', 'books.book_code')
            ->when($request->search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('central_stocks.book_code', 'like', '%' . $search . '%')
                        ->orWhere('central_stocks.branch_code', 'like', '%' . $search . '%')
                        ->orWhere('branches.branch_name', 'like', '%' . $search . '%')
                        ->orWhere('books.book_title', 'like', '%' . $search . '%');
                });
            })
            ->when($request->branch, function ($query, $branch) {
                return $query->where('central_stocks.branch_code', $branch);
            })
            ->orderBy('central_stocks.branch_code')
            ->orderBy('central_stocks.book_code')
            ->with(['branch', 'product'])
            ->paginate(15);

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'stocks' => $stocks,
        ];

        return view($this->callbackfolder . '.master-data.central-stock.index', $data);
    }

    /**
     * Import central stocks from Excel
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

            // Import menggunakan queue (akan diproses per 100 data dengan chunking)
            Excel::queueImport(new CentralStocksImport, $fullPath);

            return redirect()->back()->with('success', 'File berhasil diupload dan sedang diproses di background. Data akan diimport secara bertahap per 100 data. Pastikan queue worker berjalan: php artisan queue:work');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('CentralStock Import Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
        }
    }

    public function synchronize(Request $request)
    {
        try {
            Cache::forget('sync_central_stocks_progress');

            SynchronizeCentralStocksJob::dispatch()
                ->onQueue('default');

            Log::info('CentralStock synchronization job dispatched to queue');

            return redirect()->back()->with('success', 'Sinkronisasi data sedang diproses di background. Data akan disinkronkan secara bertahap. Silakan refresh halaman beberapa saat kemudian untuk melihat hasil.');
        } catch (\Exception $e) {
            Log::error('Synchronize Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error sinkronisasi: ' . $e->getMessage());
        }
    }

    public function clearAndSync(Request $request)
    {
        try {
            Cache::forget('sync_central_stocks_progress');

            $deletedCount = CentralStock::count();
            CentralStock::truncate();

            Log::info("Cleared {$deletedCount} central stocks before synchronization");

            SynchronizeCentralStocksJob::dispatch()
                ->onQueue('default');

            Log::info('CentralStock clear and synchronization job dispatched to queue');

            return redirect()->back()->with('success', "Semua data stok pusat ({$deletedCount} data) telah dihapus. Sinkronisasi data sedang diproses di background.");
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
        $clearCache = $request->get('clear', false);
        
        if ($clearCache) {
            // Clear cache when requested (to prevent alert loop)
            Cache::forget('sync_central_stocks_progress');
        }
        
        $progress = Cache::get('sync_central_stocks_progress', [
            'status' => 'idle',
            'total' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'koli_created' => 0,
            'koli_updated' => 0,
            'errors' => 0,
            'percentage' => 0
        ]);

        return response()->json($progress);
    }
}
