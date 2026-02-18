<?php

namespace App\Http\Controllers;

use App\Models\Target;
use App\Models\Branch;
use App\Jobs\SynchronizeTargetsJob;
use App\Imports\TargetsImport;
use App\Jobs\ImportTargetsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TargetController extends Controller
{
    protected $base_url;
    protected $title;
    protected $callbackfolder;
    protected $role;

    public function __construct()
    {
        $this->base_url = url('/target');
        $this->title = 'Master Data Target';

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

        $targets = Target::query()
            ->with(['branch', 'product'])
            ->when($filteredBranchCodes !== null, function ($query) use ($filteredBranchCodes) {
                return $query->whereIn('branch_code', $filteredBranchCodes);
            })
            ->when($request->search, function ($query, $search) {
                return $query->where('branch_code', 'like', '%' . $search . '%')
                    ->orWhere('book_code', 'like', '%' . $search . '%')
                    ->orWhere('period_code', 'like', '%' . $search . '%')
                    ->orWhereHas('branch', function ($q) use ($search) {
                        $q->where('branch_name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('product', function ($q) use ($search) {
                        $q->where('book_title', 'like', '%' . $search . '%');
                    });
            })
            ->when($request->branch_code, function ($query, $branchCode) {
                return $query->where('branch_code', $branchCode);
            })
            ->orderBy('branch_code')
            ->orderBy('book_code')
            ->orderBy('period_code')
            ->paginate(15);

        $data = [
            'title' => $this->title,
            'base_url' => $this->base_url,
            'targets' => $targets,
        ];

        return view($this->callbackfolder . '.master-data.target.index', $data);
    }

    /**
     * Import targets from Excel
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls|max:102400' // Max 100MB
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

            // Simpan file langsung ke path lengkap
            $fullPath = $importDir . DIRECTORY_SEPARATOR . $filename;

            // Pindahkan file yang di-upload
            if (!$file->move($importDir, $filename)) {
                return redirect()->back()->with('error', 'Gagal menyimpan file. Pastikan direktori bisa ditulis.');
            }

            if (!file_exists($fullPath)) {
                return redirect()->back()->with('error', 'File tidak ditemukan setelah disimpan di: ' . $fullPath);
            }

            // Normalize path untuk Windows
            $normalizedPath = str_replace('\\', '/', $fullPath);
            $normalizedPath = str_replace('//', '/', $normalizedPath);

            // Import menggunakan queue untuk menghindari timeout pada file besar
            $fileSize = filesize($normalizedPath);
            Log::info('Target Import: Dispatching to queue', [
                'file_size' => $fileSize,
                'file_path' => $normalizedPath
            ]);

            // Import menggunakan queue dengan custom Job
            // Job akan membaca header dan melakukan import dengan chunk reading
            ImportTargetsJob::dispatch($normalizedPath, 'local')
                ->onQueue('default');

            return redirect()->back()->with('success', 'File berhasil diupload dan sedang diproses di background. Data akan diimport secara bertahap per 100 data. Pastikan queue worker berjalan: php artisan queue:work');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Target Import Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $target = Target::findOrFail($id);
            $target->delete();

            return redirect()->back()->with('success', 'Data target berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Target Delete Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus data target.');
        }
    }

    /**
     * Baca header dari file untuk mendapatkan branch code
     */
    private function readHeaderFromFile($filePath)
    {
        try {
            Log::info('Target Import: Reading header from file', ['file_path' => $filePath]);
            
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            // Baca baris 1 (index 1, karena Excel 1-based)
            $headerRow = $sheet->getRowIterator(1, 1)->current();
            if (!$headerRow) {
                Log::warning('Target Import: Header row not found');
                return null;
            }

            $cellIterator = $headerRow->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            // Helper untuk sanitize
            $sanitize = function($value) {
                if (empty($value) && $value !== '0' && $value !== 0) return '';
                $original = (string)$value;
                $value = @iconv('UTF-8', 'UTF-8//IGNORE//TRANSLIT', $original);
                if ($value === false || $value === '') {
                    $value = mb_convert_encoding($original, 'UTF-8', 'UTF-8');
                    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
                }
                return trim($value);
            };

            // Extract branch dari header
            $extractBranch = function($headerText) use ($sanitize) {
                $headerText = $sanitize($headerText);
                if (preg_match('/CAB\.?\s*(.+)/i', $headerText, $matches)) {
                    return trim($matches[1]);
                }
                return $headerText;
            };
            
            // Cari branch name di header (biasanya di kolom C, D, atau E, atau bisa di kolom manapun)
            $branchNameFromHeader = null;
            $colIndex = 0;
            $headerValues = [];
            
            foreach ($cellIterator as $cell) {
                $headerValue = $sanitize($cell->getValue());
                $headerValues[] = $headerValue;
                
                // Cek semua kolom, tidak hanya C, D, E
                if (!empty($headerValue) && (stripos($headerValue, 'CAB') !== false || stripos($headerValue, 'CABANG') !== false)) {
                    $branchNameFromHeader = $extractBranch($headerValue);
                    Log::info('Target Import: Found branch in header', [
                        'col_index' => $colIndex,
                        'header_value' => $headerValue,
                        'extracted_branch' => $branchNameFromHeader
                    ]);
                    break;
                }
                $colIndex++;
            }

            if (!$branchNameFromHeader) {
                Log::warning('Target Import: Branch name not found in header row', [
                    'header_values' => $headerValues
                ]);
                return null;
            }

            // Cari branch di database
            $branch = Branch::where('branch_name', 'like', '%' . $branchNameFromHeader . '%')
                ->orWhere('branch_code', 'like', '%' . $branchNameFromHeader . '%')
                ->first();

            if (!$branch) {
                Log::warning('Target Import: Branch not found in database', [
                    'branch_name_from_header' => $branchNameFromHeader,
                    'available_branches' => Branch::pluck('branch_name', 'branch_code')->toArray()
                ]);
                return null;
            }

            // Ambil year dari baris 2
            $year = date('Y');
            $headerRow2 = $sheet->getRowIterator(2, 2)->current();
            if ($headerRow2) {
                $cellIterator2 = $headerRow2->getCellIterator();
                $cellIterator2->setIterateOnlyExistingCells(false);
                foreach ($cellIterator2 as $cell) {
                    $valueStr = $sanitize($cell->getValue());
                    if (is_numeric($valueStr) && strlen($valueStr) == 4 && $valueStr >= 1900 && $valueStr <= 9999) {
                        $year = (int)$valueStr;
                        break;
                    }
                }
            }

            Log::info('Target Import: Header parsed successfully', [
                'branch_code' => $branch->branch_code,
                'branch_name' => $branch->branch_name,
                'year' => $year,
                'branch_name_from_header' => $branchNameFromHeader
            ]);

            return [
                'branch_code' => $branch->branch_code,
                'branch_name' => $branch->branch_name,
                'year' => $year,
            ];
        } catch (\Exception $e) {
            Log::error('Target Import: Error reading header: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function synchronize(Request $request)
    {
        try {
            Cache::forget('sync_targets_progress');

            SynchronizeTargetsJob::dispatch()
                ->onQueue('default');

            Log::info('Target synchronization job dispatched to queue');

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
            Cache::forget('sync_targets_progress');

            $deletedCount = Target::count();
            Target::truncate();

            Log::info("Cleared {$deletedCount} targets before synchronization");

            SynchronizeTargetsJob::dispatch(true)
                ->onQueue('default');

            Log::info('Target clear and synchronization job dispatched to queue');

            return redirect()->back()->with('success', "Semua data target ({$deletedCount} data) telah dihapus. Sinkronisasi data sedang diproses di background.");
        } catch (\Exception $e) {
            Log::error('Clear and Sync Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error clear and sync: ' . $e->getMessage());
        }
    }
}
