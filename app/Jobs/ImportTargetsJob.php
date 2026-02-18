<?php

namespace App\Jobs;

use App\Imports\TargetsImport;
use App\Models\Branch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\File;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ImportTargetsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected $filePath;
    protected $disk;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $disk = 'local')
    {
        $this->filePath = $filePath;
        $this->disk = $disk;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (!file_exists($this->filePath)) {
                Log::error('ImportTargetsJob Error: File not found', [
                    'file_path' => $this->filePath
                ]);
                return;
            }

            Log::info('ImportTargetsJob: Starting import', [
                'file_path' => $this->filePath,
                'file_size' => filesize($this->filePath),
                'file_exists' => file_exists($this->filePath)
            ]);

            // Convert absolute path ke relative path dari storage/app
            $storagePath = storage_path('app');
            $relativePath = null;

            Log::info('ImportTargetsJob: Checking path conversion', [
                'file_path' => $this->filePath,
                'storage_path' => $storagePath,
                'strpos_result' => strpos($this->filePath, $storagePath)
            ]);

            // Normalize path untuk perbandingan (case-insensitive di Windows)
            $normalizedFilePath = str_replace('\\\\', '/', $this->filePath);
            $normalizedStoragePath = str_replace('\\\\', '/', $storagePath);

            if (stripos($normalizedFilePath, $normalizedStoragePath) === 0) {
                // Path adalah absolute path di dalam storage/app
                $relativePath = str_replace($normalizedStoragePath . '/', '', $normalizedFilePath);

                Log::info('ImportTargetsJob: Converted to relative path', [
                    'absolute_path' => $this->filePath,
                    'relative_path' => $relativePath
                ]);
            } else {
                Log::warning('ImportTargetsJob: Path not in storage/app, using File object', [
                    'file_path' => $this->filePath,
                    'storage_path' => $storagePath
                ]);
            }

            // Baca header terlebih dahulu untuk mendapatkan branch code
            Log::info('ImportTargetsJob: Reading header from file');
            $branchInfo = $this->readHeaderFromFile($this->filePath);
            
            if (!$branchInfo) {
                Log::error('ImportTargetsJob: Failed to read header or branch not found', [
                    'file_path' => $this->filePath
                ]);
                return;
            }
            
            Log::info('ImportTargetsJob: Header parsed successfully, starting Excel import', [
                'branch_code' => $branchInfo['branch_code'],
                'year' => $branchInfo['year']
            ]);

            // Import dengan chunking per 100 data, pass branch info ke import class
            $import = new TargetsImport($branchInfo['branch_code'], $branchInfo['branch_name'], $branchInfo['year']);
            
            if ($relativePath !== null) {
                Log::info('ImportTargetsJob: Using relative path with disk local', [
                    'relative_path' => $relativePath,
                    'branch_code' => $branchInfo['branch_code']
                ]);
                Excel::import($import, $relativePath, 'local');
            } else {
                Log::info('ImportTargetsJob: Using File object for absolute path', [
                    'branch_code' => $branchInfo['branch_code']
                ]);
                // Fallback: gunakan File object untuk absolute path
                $file = new File($this->filePath);
                Excel::import($import, $file);
            }

            Log::info('ImportTargetsJob: Import completed successfully', [
                'file_path' => $this->filePath,
                'branch_code' => $branchInfo['branch_code'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            Log::error('ImportTargetsJob Error: ' . $e->getMessage(), [
                'file_path' => $this->filePath,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Baca header dari file untuk mendapatkan branch code
     */
    private function readHeaderFromFile($filePath)
    {
        try {
            // Gunakan reader dengan mode read-only untuk mengurangi memory
            $reader = IOFactory::createReader(IOFactory::identify($filePath));
            $reader->setReadDataOnly(true); // Hanya baca data, tidak baca style (mengurangi memory)
            $reader->setReadEmptyCells(false); // Skip empty cells
            
            // Load spreadsheet dengan memory limit
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            
            // Unset reader untuk free memory
            unset($reader);

            // Baca baris 1 (index 1, karena Excel 1-based)
            $headerRow = $sheet->getRowIterator(1, 1)->current();
            if (!$headerRow) {
                Log::warning('ImportTargetsJob: Header row not found');
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
            
            // Cari semua branch name di header (bisa ada multiple cabang dalam satu file)
            $foundBranches = [];
            $colIndex = 0;
            $headerValues = [];
            
            foreach ($cellIterator as $cell) {
                $headerValue = $sanitize($cell->getValue());
                $headerValues[] = $headerValue;
                
                // Cek semua kolom untuk mencari "CAB" atau "CABANG"
                if (!empty($headerValue) && (stripos($headerValue, 'CAB') !== false || stripos($headerValue, 'CABANG') !== false)) {
                    $extractedBranch = $extractBranch($headerValue);
                    $foundBranches[] = [
                        'col_index' => $colIndex,
                        'header_value' => $headerValue,
                        'extracted_branch' => $extractedBranch,
                        'col_letter' => $cell->getColumn()
                    ];
                    Log::info('ImportTargetsJob: Found branch in header', [
                        'col_index' => $colIndex,
                        'col_letter' => $cell->getColumn(),
                        'header_value' => $headerValue,
                        'extracted_branch' => $extractedBranch
                    ]);
                }
                $colIndex++;
            }

            if (empty($foundBranches)) {
                Log::warning('ImportTargetsJob: Branch name not found in header row', [
                    'header_values' => array_slice($headerValues, 0, 20) // Log 20 pertama
                ]);
                return null;
            }

            // Untuk setiap cabang yang ditemukan, cari match terbaik di database
            // Pilih cabang yang memiliki match paling cocok (bukan yang pertama ditemukan)
            $bestMatch = null;
            $bestMatchScore = 0;
            $selectedBranchInfo = null;
            
            // Ambil semua cabang dari database untuk matching
            $allBranches = Branch::all();
            
            Log::info('ImportTargetsJob: Searching for best branch match', [
                'found_branches_in_excel' => array_map(function($b) {
                    return $b['extracted_branch'] . ' (col ' . $b['col_letter'] . ')';
                }, $foundBranches),
                'total_branches_in_db' => $allBranches->count()
            ]);
            
            foreach ($foundBranches as $branchInfo) {
                $branchNameFromHeader = strtolower(trim($branchInfo['extracted_branch']));
                
                // Cari semua kemungkinan match di database
                foreach ($allBranches as $dbBranch) {
                    $dbBranchName = strtolower(trim($dbBranch->branch_name));
                    $dbBranchCode = strtolower(trim($dbBranch->branch_code));
                    
                    $score = 0;
                    
                    // Exact match = score tertinggi
                    if ($dbBranchName === $branchNameFromHeader || $dbBranchCode === $branchNameFromHeader) {
                        $score = 100;
                    }
                    // Contains match (header ada di nama database atau sebaliknya)
                    elseif (stripos($dbBranchName, $branchNameFromHeader) !== false || 
                            stripos($branchNameFromHeader, $dbBranchName) !== false) {
                        // Hitung similarity berdasarkan panjang substring yang match
                        $matchLength = min(strlen($branchNameFromHeader), strlen($dbBranchName));
                        $score = 80 + ($matchLength * 2);
                    }
                    // Partial match (salah satu kata cocok)
                    else {
                        $headerWords = explode(' ', $branchNameFromHeader);
                        $dbWords = explode(' ', $dbBranchName);
                        
                        foreach ($headerWords as $headerWord) {
                            if (strlen($headerWord) < 3) continue; // Skip kata pendek
                            foreach ($dbWords as $dbWord) {
                                if (stripos($dbWord, $headerWord) !== false || 
                                    stripos($headerWord, $dbWord) !== false) {
                                    $score = max($score, 50 + (strlen($headerWord) * 5));
                                }
                            }
                        }
                    }
                    
                    // Bonus jika ada di branch_code
                    if (stripos($dbBranchCode, $branchNameFromHeader) !== false) {
                        $score += 10;
                    }
                    
                    // Update best match jika score lebih tinggi
                    if ($score > $bestMatchScore) {
                        $bestMatchScore = $score;
                        $bestMatch = $dbBranch;
                        $selectedBranchInfo = $branchInfo;
                        
                        Log::info('ImportTargetsJob: Found better match', [
                            'excel_branch' => $branchInfo['extracted_branch'],
                            'db_branch' => $dbBranch->branch_name,
                            'db_code' => $dbBranch->branch_code,
                            'score' => $score,
                            'col_letter' => $branchInfo['col_letter']
                        ]);
                    }
                }
            }
            
            if (!$bestMatch) {
                Log::warning('ImportTargetsJob: No matching branch found in database', [
                    'found_branches' => array_map(function($b) {
                        return $b['extracted_branch'];
                    }, $foundBranches),
                    'available_branches' => $allBranches->pluck('branch_name', 'branch_code')->take(10)->toArray()
                ]);
                return null;
            }
            
            $branch = $bestMatch;
            $branchNameFromHeader = $selectedBranchInfo['extracted_branch'];
            
            Log::info('ImportTargetsJob: Selected best matching branch', [
                'excel_branch' => $branchNameFromHeader,
                'matched_db_branch' => $branch->branch_name,
                'matched_db_code' => $branch->branch_code,
                'match_score' => $bestMatchScore,
                'col_letter' => $selectedBranchInfo['col_letter']
            ]);

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

            Log::info('ImportTargetsJob: Header parsed', [
                'branch_code' => $branch->branch_code,
                'branch_name' => $branch->branch_name,
                'year' => $year,
                'branch_name_from_header' => $branchNameFromHeader
            ]);

            // Free memory setelah selesai membaca header
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [
                'branch_code' => $branch->branch_code,
                'branch_name' => $branch->branch_name,
                'year' => $year,
            ];
        } catch (\Exception $e) {
            Log::error('ImportTargetsJob: Error reading header: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file_path' => $filePath
            ]);
            return null;
        }
    }
}
