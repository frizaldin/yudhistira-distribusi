<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Collection;

/**
 * Import update field category_manual, serial, warehouse, is_marketing_list dari Excel "IDENTIFIKASI BUKU".
 * Kolom A = KODE (book_code), F = GUDANG (warehouse), G = SERIAL, H = KATEGORI (category_manual).
 * Semua baris yang di-import akan di-set is_marketing_list = 'Y'.
 * Baris SUBTOTAL / SUB JUDUL di-skip.
 * Dipanggil dari ImportProductCategorySerialJob (satu job = satu file, chunk dibaca di dalam job).
 */
class ProductCategorySerialImport implements ToCollection, WithStartRow, WithChunkReading, SkipsEmptyRows
{
    /**
     * Baris mulai baca data (1-based). Header di row 3, data dari row 8.
     */
    public function startRow(): int
    {
        return 8;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $row = $row->toArray();
            $kode = $this->sanitize($row[0] ?? '');
            $kelas = $this->sanitize($row[4] ?? '');   // E = KELAS (untuk skip SUB JUDUL)
            $gudang = $this->sanitize($row[5] ?? '');  // F = GUDANG → warehouse
            $serial = $this->sanitize($row[6] ?? '');  // G = SERIAL
            $kategori = $this->sanitize($row[7] ?? ''); // H = KATEGORI → category_manual

            if (empty($kode)) {
                continue;
            }

            // Skip baris SUBTOTAL / SUB JUDUL
            $kelasLower = strtolower($kelas);
            if (str_contains($kelasLower, 'sub judul') || str_contains($kelasLower, 'subtotal')) {
                continue;
            }
            if (str_contains(strtolower($kode), 'subtotal')) {
                continue;
            }

            Product::where('book_code', $kode)->update([
                'serial' => $serial ?: null,
                'category_manual' => $kategori ?: null,
                'warehouse' => $gudang ?: null,
                'is_marketing_list' => 'Y',
            ]);
        }
    }

    private function sanitize($value): string
    {
        if ($value === null || ($value === '' && $value !== '0' && $value !== 0)) {
            return '';
        }
        $original = (string) $value;
        $value = @iconv('UTF-8', 'UTF-8//IGNORE//TRANSLIT', $original);
        if ($value === false || $value === '') {
            $value = mb_convert_encoding($original, 'UTF-8', 'UTF-8');
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value ?? '');
        }
        return trim((string) $value);
    }
}
