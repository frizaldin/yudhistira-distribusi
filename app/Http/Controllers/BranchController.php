<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DeliveryOrder;
use App\Models\Nkb;
use App\Jobs\SynchronizeBranchesJob;
use App\Imports\BranchesImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class BranchController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/branch');
        $this->title = 'Data Cabang';

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
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();

        $branches = Branch::query()
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->when($request->search, function ($query, $search) {
                return $query->where('branch_name', 'like', '%' . $search . '%')
                    ->orWhere('branch_code', 'like', '%' . $search . '%');
            })
            ->orderBy('branch_code')
            ->paginate(15);

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'branches' => $branches,
        ];

        return view($this->callbackfolder . '.master-data.branch.index', $data);
    }

    /**
     * History NKB untuk satu cabang (branch_code): NKB yang pengirim atau tujuannya cabang ini.
     * Filter opsional: start_date, end_date (filter by send_date).
     */
    public function nkbHistory(Request $request, string $branch_code)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        if ($filteredBranchCodes !== null && !in_array($branch_code, $filteredBranchCodes)) {
            abort(403);
        }

        $branch = Branch::where('branch_code', $branch_code)->firstOrFail();

        $query = Nkb::where(function ($q) use ($branch_code) {
            $q->where('sender_code', $branch_code)->orWhere('recipient_code', $branch_code);
        })
            ->with('senderBranch', 'recipientBranch', 'document');

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        if ($startDate && $endDate) {
            $query->whereBetween('send_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('send_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('send_date', '<=', $endDate);
        }

        $nkbs = $query->orderByDesc('send_date')->orderByDesc('id')->get();

        return view($this->callbackfolder . '.master-data.branch.nkb-history', [
            'branch' => $branch,
            'nkbs' => $nkbs,
            'title' => $this->title,
            'base_url' => $this->base_url,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * History Surat Jalan untuk satu cabang: Surat Jalan yang pengirim atau tujuannya cabang ini.
     */
    public function deliveryOrderHistory(string $branch_code)
    {
        $filteredBranchCodes = $this->getBranchFilterForCurrentUser();
        if ($filteredBranchCodes !== null && !in_array($branch_code, $filteredBranchCodes)) {
            abort(403);
        }

        $branch = Branch::where('branch_code', $branch_code)->firstOrFail();

        $deliveryOrders = DeliveryOrder::where('sender_code', $branch_code)
            ->orWhere('recipient_code', $branch_code)
            ->with('senderBranch', 'recipientBranch')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return view($this->callbackfolder . '.master-data.branch.delivery-order-history', [
            'branch' => $branch,
            'deliveryOrders' => $deliveryOrders,
            'title' => $this->title,
            'base_url' => $this->base_url,
        ]);
    }

    /**
     * Import branches from Excel
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

            // Import langsung tanpa queue untuk testing (lebih mudah debugging)
            // Jika butuh queue untuk file besar, ganti ke: Excel::queueImport(new BranchesImport, $fullPath);
            Excel::import(new BranchesImport, $fullPath);

            return redirect()->back()->with('success', 'File berhasil diimport! Data sudah masuk ke database.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Branch Import Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
        }
    }

    public function synchronize(Request $request)
    {
        try {
            Cache::forget('sync_branches_progress');

            SynchronizeBranchesJob::dispatch()
                ->onQueue('default');

            Log::info('Branch synchronization job dispatched to queue');

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
            Cache::forget('sync_branches_progress');

            $deletedCount = Branch::count();
            Branch::truncate();

            Log::info("Cleared {$deletedCount} branches before synchronization");

            SynchronizeBranchesJob::dispatch()
                ->onQueue('default');

            Log::info('Branch clear and synchronization job dispatched to queue');

            return redirect()->back()->with('success', "Semua data cabang ({$deletedCount} data) telah dihapus. Sinkronisasi data sedang diproses di background.");
        } catch (\Exception $e) {
            Log::error('Clear and Sync Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error clear and sync: ' . $e->getMessage());
        }
    }

    public function getProgress(Request $request)
    {
        $progress = Cache::get('sync_branches_progress', [
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
