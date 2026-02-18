<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductsImport implements ToModel, WithStartRow, SkipsEmptyRows, WithBatchInserts, WithChunkReading, ShouldQueue
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

        // Mapping kolom Excel berdasarkan index:
        // A = KODE (index 0)
        // B = JUDUL BUKU (index 1)
        // C = NO (index 2)
        // D = SEGMENT (index 3)
        // E = KURIKULUM (index 4)
        // F = BID. STUDY (index 5)
        // G = KELAS (index 6)

        $kode = $sanitize($row[0] ?? '');
        $judulBuku = $sanitize($row[1] ?? '');
        $no = $sanitize($row[2] ?? '');
        $segment = $sanitize($row[3] ?? '');
        $kurikulum = $sanitize($row[4] ?? '');
        $bidStudy = $sanitize($row[5] ?? '');
        $kelas = $sanitize($row[6] ?? '');

        // Wajib ada KODE, jika tidak ada KODE maka skip (tidak dimasukkan ke DB)
        if (empty($kode)) {
            return null;
        }

        // Skip row yang berisi SUBTOTAL, JUDUL, atau SUB JUDUL
        $judulBukuLower = strtolower($judulBuku);
        $kelasLower = strtolower($kelas);
        
        if (str_contains($judulBukuLower, 'subtotal') || 
            str_contains($kelasLower, 'judul') || 
            str_contains($kelasLower, 'sub judul')) {
            return null;
        }

        // Validasi KELAS harus angka 1-6
        if (!in_array($kelas, ['1', '2', '3', '4', '5', '6'])) {
            return null;
        }

        // Cek apakah data dengan KODE yang sama sudah ada di database
        $existingProduct = Product::where('code', $kode)->first();
        if ($existingProduct) {
            // Skip jika sudah ada (tidak masukkan lagi ke DB)
            return null;
        }

        return new Product([
            'code' => $kode,
            'title' => $judulBuku,
            'number_book' => !empty($no) && is_numeric($no) ? (int)$no : null,
            'book_segment' => $segment ?: null,
            'curriculum' => $kurikulum ?: null,
            'bid_study' => $bidStudy ?: null,
            'class' => $kelas,
        ]);
    }
}
