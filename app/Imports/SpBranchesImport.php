<?php

namespace App\Imports;

use App\Models\SpBranch;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class SpBranchesImport implements ToModel, WithStartRow, SkipsEmptyRows, WithCalculatedFormulas, WithBatchInserts, WithChunkReading
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
     * Helper function untuk sanitasi UTF-8 - PENDEKATAN LEBIH AGRESIF
     * Dijadikan method private agar bisa di-serialize untuk queue
     */
    private function sanitize($value)
    {
        if (empty($value) && $value !== '0' && $value !== 0) return '';
        
        try {
            $original = (string)$value;
            
            // Langkah 1: Coba encode dulu untuk deteksi awal
            if (@json_encode($original) === false && json_last_error() === JSON_ERROR_UTF8) {
                // Langkah 2: Deteksi encoding dan convert ke UTF-8
                $encoding = mb_detect_encoding($original, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
                if ($encoding && $encoding !== 'UTF-8') {
                    $original = mb_convert_encoding($original, 'UTF-8', $encoding);
                }
            }
            
            // Langkah 3: Hapus karakter non-UTF-8 dengan iconv (IGNORE mode) - lebih agresif
            $value = @iconv('UTF-8', 'UTF-8//IGNORE//TRANSLIT', $original);
            if ($value === false || $value === '') {
                // Fallback: gunakan mb_convert_encoding dengan error handling
                $value = @mb_convert_encoding($original, 'UTF-8', 'UTF-8');
                if ($value === false) {
                    $value = $original;
                }
            }
            
            // Langkah 4: Hapus karakter kontrol dan karakter bermasalah
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $value);
            
            // Langkah 5: Pastikan encoding valid
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $value);
            }
            
            // Langkah 6: Validasi dengan json_encode - jika masih error, hapus karakter non-ASCII
            json_encode($value);
            if (json_last_error() === JSON_ERROR_UTF8) {
                // Hapus semua karakter non-ASCII yang bermasalah
                $value = preg_replace('/[^\x00-\x7F]/u', '', $value);
                // Test lagi
                if (@json_encode($value) === false) {
                    return ''; // Return empty jika masih tidak bisa di-encode
                }
            }
            
            return trim($value);
        } catch (\Exception $e) {
            // Jika ada error apapun, return empty string
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
        // Wrap seluruh method dengan try-catch untuk handle error encoding dan serialization
        try {

        // Mapping kolom Excel berdasarkan index:
        // A = order_code (index 0)
        // B = book_code (index 1)
        // C = book_title (index 2)
        // D = stok (index 3)
        // E = jual (index 4)
        // F = ret (index 5)
        // G = pesanan (index 6)
        // H = nt (index 7)
        // I = nk (index 8)
        // J = ntb (index 9)
        // K = nkb (index 10)
        // L = stok_1 (index 11)
        // M = jual_1 (index 12)
        // N = ret_1 (index 13)
        // O = pesanan_1 (index 14)
        // P = nt_1 (index 15)
        // Q = nk_1 (index 16)
        // R = ntb_1 (index 17)
        // S = nkb_1 (index 18)
        // T = branch_code (index 19)
        // U = branch_name (index 20)

        $orderCode = isset($row[0]) ? $this->sanitize($row[0]) : '';
        $bookCode = isset($row[1]) ? $this->sanitize($row[1]) : '';
        $bookTitle = isset($row[2]) ? $this->sanitize($row[2]) : '';
        $stok = isset($row[3]) && is_numeric($row[3]) ? (int)$row[3] : 0;
        $jual = isset($row[4]) && is_numeric($row[4]) ? (int)$row[4] : 0;
        $ret = isset($row[5]) && is_numeric($row[5]) ? (int)$row[5] : 0;
        $pesanan = isset($row[6]) && is_numeric($row[6]) ? (int)$row[6] : 0;
        $nt = isset($row[7]) && is_numeric($row[7]) ? (int)$row[7] : 0;
        $nk = isset($row[8]) && is_numeric($row[8]) ? (int)$row[8] : 0;
        $ntb = isset($row[9]) && is_numeric($row[9]) ? (int)$row[9] : 0;
        $nkb = isset($row[10]) && is_numeric($row[10]) ? (int)$row[10] : 0;
        $stok1 = isset($row[11]) && is_numeric($row[11]) ? (int)$row[11] : 0;
        $jual1 = isset($row[12]) && is_numeric($row[12]) ? (int)$row[12] : 0;
        $ret1 = isset($row[13]) && is_numeric($row[13]) ? (int)$row[13] : 0;
        $pesanan1 = isset($row[14]) && is_numeric($row[14]) ? (int)$row[14] : 0;
        $nt1 = isset($row[15]) && is_numeric($row[15]) ? (int)$row[15] : 0;
        $nk1 = isset($row[16]) && is_numeric($row[16]) ? (int)$row[16] : 0;
        $ntb1 = isset($row[17]) && is_numeric($row[17]) ? (int)$row[17] : 0;
        $nkb1 = isset($row[18]) && is_numeric($row[18]) ? (int)$row[18] : 0;
        $branchCode = isset($row[19]) ? $this->sanitize($row[19]) : '';
        $branchName = isset($row[20]) ? $this->sanitize($row[20]) : '';

        // WAJIB ADA book_code - hanya ambil data yang memiliki book_code
        // Trim dan validasi setelah sanitize
        $bookCode = trim($bookCode);
        if (empty($bookCode) || $bookCode === '') {
            return null; // Skip jika tidak ada book_code - HANYA AMBIL DATA YANG MEMILIKI book_code
        }

        // Skip header rows
        $bookCodeLower = strtolower($bookCode);
        if ($bookCodeLower === 'book_code' || $bookCodeLower === 'kode buku') {
            return null;
        }

        // Skip baris yang hanya berisi branch_code dan branch_name (separator rows)
        // Jika book_code kosong tapi branch_code ada, berarti separator row
        if (empty($bookCode) && !empty($branchCode)) {
            return null;
        }

        // Skip jika berisi formula Excel (dimulai dengan =)
        if (str_starts_with($bookCode, '=') || (isset($bookTitle) && str_starts_with($bookTitle, '='))) {
            return null;
        }

        // CEK DUPLIKASI - Tidak boleh ada double data
        // Cek berdasarkan book_code (wajib) + branch_code (jika ada)
        // Jika branch_code kosong, cek hanya berdasarkan book_code
        $existingQuery = SpBranch::where('book_code', $bookCode);
        
        // Jika branch_code ada, tambahkan kondisi branch_code untuk mencegah duplikasi per branch
        if (!empty($branchCode) && trim($branchCode) !== '') {
            $existingQuery->where('branch_code', trim($branchCode));
        }
        
        // Cek apakah data sudah ada
        if ($existingQuery->exists()) {
            Log::info('SpBranchesImport - Skipped: Duplicate entry', [
                'book_code' => $bookCode,
                'branch_code' => $branchCode ?? null
            ]);
            return null; // Skip jika sudah ada - TIDAK MASUKKAN DOUBLE DATA
        }

        // Pastikan semua string adalah UTF-8 valid sebelum disimpan (untuk mencegah JSON encode error)
            // Sanitasi ulang semua string untuk memastikan tidak ada karakter non-UTF-8
            $orderCode = $orderCode ? $this->sanitize($orderCode) : null;
            $bookCode = $this->sanitize($bookCode);
            $bookTitle = $bookTitle ? $this->sanitize($bookTitle) : null;
            $branchCode = $branchCode ? $this->sanitize($branchCode) : null;
            $branchName = $branchName ? $this->sanitize($branchName) : null;

            // Validasi final - test dengan json_encode untuk memastikan bisa di-serialize oleh queue
            // SKIP ROW JIKA TIDAK BISA DI-ENCODE - ini penting untuk queue!
            
            // Test bookCode dulu (required field) - jika tidak bisa di-encode, skip row
            json_last_error(); // Reset error state
            if (@json_encode($bookCode) === false && json_last_error() === JSON_ERROR_UTF8) {
                Log::warning('SpBranchesImport - Invalid UTF-8 in bookCode, skipping row', [
                    'bookCode_preview' => substr($bookCode, 0, 50)
                ]);
                return null;
            }
            
            // Test field lainnya - set null jika tidak valid
            if ($orderCode) {
                json_last_error(); // Reset
                if (@json_encode($orderCode) === false && json_last_error() === JSON_ERROR_UTF8) {
                    $orderCode = null;
                }
            }
            if ($bookTitle) {
                json_last_error(); // Reset
                if (@json_encode($bookTitle) === false && json_last_error() === JSON_ERROR_UTF8) {
                    $bookTitle = null;
                }
            }
            if ($branchCode) {
                json_last_error(); // Reset
                if (@json_encode($branchCode) === false && json_last_error() === JSON_ERROR_UTF8) {
                    $branchCode = null;
                }
            }
            if ($branchName) {
                json_last_error(); // Reset
                if (@json_encode($branchName) === false && json_last_error() === JSON_ERROR_UTF8) {
                    $branchName = null;
                }
            }

            // Final test - coba encode semua data sebagai array sebelum membuat model
            // Ini penting untuk queue karena Laravel akan serialize data ini
            // Jika tidak bisa di-encode, SKIP ROW INI
            json_last_error(); // Reset
            $testData = [
                'order_code' => $orderCode,
                'book_code' => $bookCode,
                'book_title' => $bookTitle,
                'branch_code' => $branchCode,
                'branch_name' => $branchName,
            ];
            
            // Test encode dengan error suppression dan check error
            $encoded = @json_encode($testData);
            if ($encoded === false && json_last_error() === JSON_ERROR_UTF8) {
                Log::warning('SpBranchesImport - Invalid UTF-8 in payload, skipping entire row', [
                    'json_error' => json_last_error_msg(),
                    'book_code' => substr($bookCode, 0, 50)
                ]);
                return null; // SKIP ROW JIKA TIDAK BISA DI-ENCODE
            }

            return new SpBranch([
                'order_code' => $orderCode,
                'book_code' => $bookCode,
                'book_title' => $bookTitle,
                'branch_code' => $branchCode,
                'branch_name' => $branchName,
                'stok' => $stok,
                'jual' => $jual,
                'ret' => $ret,
                'pesanan' => $pesanan,
                'nt' => $nt,
                'nk' => $nk,
                'ntb' => $ntb,
                'nkb' => $nkb,
                'stok_1' => $stok1,
                'jual_1' => $jual1,
                'ret_1' => $ret1,
                'pesanan_1' => $pesanan1,
                'nt_1' => $nt1,
                'nk_1' => $nk1,
                'ntb_1' => $ntb1,
                'nkb_1' => $nkb1,
            ]);
        } catch (\Exception $e) {
            Log::error('SpBranch Import Error: ' . $e->getMessage(), [
                'book_code' => isset($bookCode) ? substr($bookCode, 0, 50) : 'N/A',
                'row' => isset($row) ? array_slice($row, 0, 5) : [],
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        } catch (\Error $e) {
            // Handle fatal errors (seperti JSON encoding errors)
            Log::error('SpBranch Import Fatal Error: ' . $e->getMessage(), [
                'row' => isset($row) ? array_slice($row, 0, 5) : [],
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
