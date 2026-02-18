<?php

namespace App\Imports;

use App\Models\Target;
use App\Models\Branch;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

class TargetsImport implements ToModel, WithStartRow, SkipsEmptyRows, WithBatchInserts, WithChunkReading, WithCalculatedFormulas, WithEvents
{
    protected $branchCode;
    protected $branchName;
    protected $year;
    protected static $totalProcessed = 0;
    protected static $totalInserted = 0;
    protected static $totalUpdated = 0;

    public function __construct($branchCode = null, $branchName = null, $year = null)
    {
        $this->branchCode = $branchCode;
        $this->branchName = $branchName;
        $this->year = $year ?: date('Y');
        // Reset counters
        self::$totalProcessed = 0;
        self::$totalInserted = 0;
        self::$totalUpdated = 0;
    }

    /**
     * Register events
     */
    public function registerEvents(): array
    {
        return [
            AfterImport::class => function(AfterImport $event) {
                Log::info('Target Import - Import completed', [
                    'branch_code' => $this->branchCode,
                    'total_processed' => self::$totalProcessed,
                    'total_inserted' => self::$totalInserted,
                    'total_updated' => self::$totalUpdated
                ]);
            },
        ];
    }

    /**
     * Baris mulai membaca data (skip header)
     */
    public function startRow(): int
    {
        return 4; // Mulai dari baris 4 (setelah header dan summary row)
    }

    /**
     * Chunk size untuk membaca data (per 50 baris untuk mengurangi memory)
     */
    public function chunkSize(): int
    {
        return 50; // Kurangi dari 100 ke 50 untuk mengurangi memory usage
    }

    /**
     * Batch size untuk insert ke database (per 50 data)
     */
    public function batchSize(): int
    {
        return 50; // Kurangi dari 100 ke 50 untuk mengurangi memory usage
    }

    /**
     * Helper function untuk sanitasi UTF-8
     */
    private function sanitize($value)
    {
        if (empty($value) && $value !== '0' && $value !== 0) return '';
        try {
            $original = (string)$value;
            $value = @iconv('UTF-8', 'UTF-8//IGNORE//TRANSLIT', $original);
            if ($value === false || $value === '') {
                $value = mb_convert_encoding($original, 'UTF-8', 'UTF-8');
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
            }
            
            // Test apakah bisa di-encode ke JSON
            json_encode($value);
            if (json_last_error() === JSON_ERROR_UTF8) {
                $value = preg_replace('/[^\x00-\x7F]/u', '', $value);
                if (@json_encode($value) === false) {
                    return '';
                }
            }
            
            return trim($value);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        if (!$this->branchCode) {
            Log::warning('Target Import - model(): branchCode is null');
            return null;
        }

        $bookCode = isset($row[0]) ? $this->sanitize($row[0]) : '';
        $bookName = isset($row[1]) ? $this->sanitize($row[1]) : '';
        $target = isset($row[4]) && is_numeric($row[4]) ? (int)$row[4] : 0;

        // Skip jika tidak ada kode atau berisi header/subtotal/judul
        if (empty($bookCode)) {
            return null;
        }

        $kodeLower = strtolower($bookCode);
        $judulLower = strtolower($bookName);

        // Skip header rows, subtotal, judul
        if (stripos($kodeLower, 'kode') !== false || 
            stripos($judulLower, 'subtotal') !== false || 
            stripos($judulLower, 'judul') !== false ||
            stripos($judulLower, 'jenjang') !== false ||
            (stripos($judulLower, 'matematika') !== false && $target == 0)) {
            return null;
        }

        // Skip jika target 0 atau kosong
        if ($target <= 0) {
            return null;
        }

        // Increment counter
        self::$totalProcessed++;

        // Cek apakah data dengan branch_code, book_code, year yang sama sudah ada
        $existingTarget = Target::where('branch_code', $this->branchCode)
            ->where('book_code', $bookCode)
            ->where('year', $this->year)
            ->first();

        if ($existingTarget) {
            // Update data yang sudah ada
            $existingTarget->update([
                'branch_name' => $this->branchName,
                'book_name' => $bookName,
                'target' => $target,
            ]);
            self::$totalUpdated++;
            // Tidak perlu log setiap update untuk mengurangi memory
            return null; // Return null karena sudah di-update, tidak perlu insert baru
        }

        // Log hanya setiap 10 data untuk mengurangi memory usage
        if (self::$totalProcessed % 10 == 0) {
            Log::info('Target Import - Processing data', [
                'processed_count' => self::$totalProcessed,
                'inserted_count' => self::$totalInserted,
                'updated_count' => self::$totalUpdated,
                'branch_code' => $this->branchCode
            ]);
        }

        self::$totalInserted++;

        return new Target([
            'branch_code' => $this->branchCode,
            'branch_name' => $this->branchName,
            'book_code' => $bookCode,
            'book_name' => $bookName,
            'target' => $target,
            'year' => $this->year,
        ]);
    }
}
