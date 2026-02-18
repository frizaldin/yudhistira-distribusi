<?php

namespace App\Http\Controllers;

use App\Models\SpBranch;
use App\Models\CentralStock;
use App\Imports\SpBranchesImport;
use App\Jobs\ImportSpBranchesJob;
use App\Jobs\SynchronizeSpBranchesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SpBranchController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/pesanan');
        $this->title = 'Data Pesanan';

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
        $orders = SpBranch::query()
            ->where('active_data', 'yes')
            ->when($request->search, function ($query, $search) {
                return $query->where('book_code', 'like', '%' . $search . '%')
                    ->orWhere('branch_code', 'like', '%' . $search . '%');
            })
            ->when($request->branch, function ($query, $branch) {
                return $query->where('branch_code', $branch);
            })
            ->orderBy('branch_code')
            ->orderBy('book_code')
            ->paginate(15);

        // Get central stocks (total stock pusat per book_code, tidak berdasarkan branch)
        $centralStocks = CentralStock::select([
            'book_code',
            DB::raw('SUM(exemplar) as total_stock_pusat')
        ])
            ->groupBy('book_code')
            ->get()
            ->keyBy('book_code');

        // Add stock pusat and calculate sisa SP to each order
        foreach ($orders as $order) {
            $stock = $centralStocks->get($order->book_code);
            $order->stock_pusat = $stock->total_stock_pusat ?? 0;

            // Calculate Sisa SP
            // SP - Faktur
            $selisih = ($order->ex_sp ?? 0) - ($order->ex_ftr ?? 0);
            $stokCabang = $order->ex_stock ?? 0;
            $stokPusat = $order->stock_pusat ?? 0;

            // Jika stok cabang memenuhi (>= selisih), maka sisa SP = 0
            if ($stokCabang >= $selisih) {
                $order->sisa_sp = 0;
            } else {
                // Jika stok cabang tidak memenuhi, maka sisa SP = SP - Faktur - Stok Cabang - Stok Pusat
                $order->sisa_sp = max(0, $selisih - $stokCabang - $stokPusat);
            }
        }

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'orders' => $orders,
        ];

        return view($this->callbackfolder . '.pesanan.index', $data);
    }

    /**
     * Import orders from Excel
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

            // Buat filename yang aman dan valid UTF-8
            $originalName = $file->getClientOriginalName();
            if (empty($originalName)) {
                $originalName = 'import_' . time() . '.xlsx';
            }

            // Sanitize filename untuk menghindari karakter non-UTF-8
            $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            // Pastikan filename adalah UTF-8 valid
            if (!mb_check_encoding($safeFilename, 'UTF-8')) {
                $safeFilename = mb_convert_encoding($safeFilename, 'UTF-8', 'UTF-8');
                $safeFilename = preg_replace('/[^\x00-\x7F]/', '_', $safeFilename); // Hapus non-ASCII jika masih bermasalah
            }

            $filename = time() . '_' . uniqid() . '_' . $safeFilename;

            // Pastikan filename tidak kosong dan valid UTF-8
            if (empty($filename)) {
                $filename = time() . '_' . uniqid() . '.xlsx';
            }

            // Pastikan filename bisa di-encode ke JSON (untuk queue)
            if (@json_encode($filename) === false && json_last_error() === JSON_ERROR_UTF8) {
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

            // Normalize path untuk menghindari masalah encoding
            $normalizedPath = realpath($fullPath);
            if ($normalizedPath === false) {
                $normalizedPath = $fullPath;
            }

            // Pastikan path valid UTF-8 untuk queue
            // Sanitize path jika mengandung karakter non-UTF-8
            if (!mb_check_encoding($normalizedPath, 'UTF-8')) {
                $normalizedPath = mb_convert_encoding($normalizedPath, 'UTF-8', 'UTF-8');
            }

            // Test apakah path bisa di-encode ke JSON (untuk queue)
            if (@json_encode($normalizedPath) === false && json_last_error() === JSON_ERROR_UTF8) {
                // Jika path tidak valid, gunakan import langsung tanpa queue
                Log::warning('SpBranch Import: Path contains invalid UTF-8, using direct import instead of queue', [
                    'path' => substr($normalizedPath, 0, 100)
                ]);
                Excel::import(new SpBranchesImport, $normalizedPath);
                return redirect()->back()->with('success', 'File berhasil diimport! Data sudah masuk ke database.');
            }

            // Import menggunakan queue untuk semua file (menghindari timeout)
            $fileSize = filesize($normalizedPath);
            Log::info('SpBranch Import: Dispatching to queue', [
                'file_size' => $fileSize,
                'file_path' => $normalizedPath
            ]);

            // Import menggunakan queue dengan custom Job untuk menghindari masalah serialization
            // Job akan memanggil Excel::import() di dalam handle(), bukan Excel::queueImport()
            ImportSpBranchesJob::dispatch($normalizedPath, 'local')
                ->onQueue('default');

            return redirect()->back()->with('success', 'File berhasil diupload dan sedang diproses di background. Data akan diimport secara bertahap per 100 data. Pastikan queue worker berjalan: php artisan queue:work');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('SpBranch Import Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
        }
    }

    /**
     * Synchronize data from staging
     */
    public function synchronize(Request $request)
    {
        try {
            SynchronizeSpBranchesJob::dispatch(false)
                ->onQueue('default');

            return redirect()->back()->with('success', 'Sinkronisasi data sedang diproses di background. Data akan disinkronkan secara bertahap. Silakan refresh halaman beberapa saat kemudian untuk melihat hasil.');
        } catch (\Exception $e) {
            Log::error('SpBranch Synchronize Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Clear all data and synchronize from staging
     */
    public function clearAndSync(Request $request)
    {
        try {
            // Count data before deletion
            $deletedCount = SpBranch::count();

            // Delete all data (will be done in job, but we count here for message)
            SynchronizeSpBranchesJob::dispatch(true)
                ->onQueue('default');

            Log::info("Cleared {$deletedCount} sp_branches before synchronization");

            return redirect()->back()->with('success', "Semua data pesanan ({$deletedCount} data) telah dihapus. Sinkronisasi data sedang diproses di background.");
        } catch (\Exception $e) {
            Log::error('SpBranch ClearAndSync Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
