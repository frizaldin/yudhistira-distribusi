<?php

namespace App\Imports;

use App\Models\CentralStock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class CentralStocksImport implements ToModel, WithStartRow, SkipsEmptyRows, WithCalculatedFormulas, WithBatchInserts, WithChunkReading, ShouldQueue
{
    /**
     * Baris mulai membaca data (skip header)
     */
    public function startRow(): int
    {
        return 2; // Skip baris pertama (header)
    }

    /**
     * Chunk size untuk membaca data (per 100 baris)
     */
    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Batch size untuk insert ke database (per 100 data)
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // Mapping kolom Excel berdasarkan index:
        // A = branch_code (index 0)
        // B = book_code (index 1)
        // C = koli_besar (index 2)
        // D = eks_besar (index 3)
        // E = total_besar (index 4)
        // F = koli_kecil (index 5)
        // G = eks_kecil (index 6)
        // H = total_kecil (index 7)
        // I = judulbuku (index 8)
        // J = brach_name (index 9) - typo di Excel, tapi kita map ke branch_name

        // Helper function untuk sanitasi UTF-8
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

        $branchCode = isset($row[0]) ? $sanitize($row[0]) : '';
        $bookCode = isset($row[1]) ? $sanitize($row[1]) : '';
        $koliBesar = isset($row[2]) && is_numeric($row[2]) ? (int)$row[2] : 0;
        $eksBesar = isset($row[3]) && is_numeric($row[3]) ? (int)$row[3] : 0;
        $totalBesar = isset($row[4]) && is_numeric($row[4]) ? (int)$row[4] : 0;
        $koliKecil = isset($row[5]) && is_numeric($row[5]) ? (int)$row[5] : 0;
        $eksKecil = isset($row[6]) && is_numeric($row[6]) ? (int)$row[6] : 0;
        $totalKecil = isset($row[7]) && is_numeric($row[7]) ? (int)$row[7] : 0;
        $judulBuku = isset($row[8]) ? $sanitize($row[8]) : '';
        $branchName = isset($row[9]) ? $sanitize($row[9]) : '';

        // Wajib ada branch_code dan book_code
        if (empty($branchCode) || empty($bookCode)) {
            Log::info('CentralStock Import - Skipped: Empty branch_code or book_code', [
                'branch_code' => $branchCode,
                'book_code' => $bookCode,
            ]);
            return null;
        }

        // Skip header rows
        $branchCodeLower = strtolower($branchCode);
        $bookCodeLower = strtolower($bookCode);
        
        if ($branchCodeLower === 'branch_code' || $bookCodeLower === 'book_code') {
            Log::info('CentralStock Import - Skipped: Header row', [
                'branch_code' => $branchCode,
                'book_code' => $bookCode,
            ]);
            return null;
        }

        // Skip jika berisi formula Excel (dimulai dengan =)
        if (str_starts_with($branchCode, '=') || str_starts_with($bookCode, '=')) {
            Log::info('CentralStock Import - Skipped: Excel formula', [
                'branch_code' => $branchCode,
                'book_code' => $bookCode,
            ]);
            return null;
        }

        // Cek apakah data dengan kombinasi branch_code dan book_code sudah ada di database
        $existingStock = CentralStock::where('branch_code', $branchCode)
            ->where('book_code', $bookCode)
            ->first();
        if ($existingStock) {
            Log::info('CentralStock Import - Skipped: Duplicate branch_code + book_code', [
                'branch_code' => $branchCode,
                'book_code' => $bookCode,
            ]);
            return null; // Skip jika sudah ada (tidak masukkan lagi ke DB)
        }

        try {
            $stock = new CentralStock([
                'branch_code' => $branchCode,
                'book_code' => $bookCode,
                'koli_besar' => $koliBesar,
                'eks_besar' => $eksBesar,
                'total_besar' => $totalBesar,
                'koli_kecil' => $koliKecil,
                'eks_kecil' => $eksKecil,
                'total_kecil' => $totalKecil,
                'judulbuku' => $judulBuku ?: null,
                'branch_name' => $branchName ?: null,
            ]);

            Log::info('CentralStock Import - Success: Creating stock', [
                'branch_code' => $branchCode,
                'book_code' => $bookCode,
            ]);
            return $stock;
        } catch (\Exception $e) {
            Log::error('CentralStock Import Error: ' . $e->getMessage(), [
                'branch_code' => $branchCode,
                'book_code' => $bookCode,
                'row' => $row,
            ]);
            return null;
        }
    }
}
