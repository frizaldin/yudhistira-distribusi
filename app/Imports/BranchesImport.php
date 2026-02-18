<?php

namespace App\Imports;

use App\Models\Branch;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class BranchesImport implements ToModel, WithStartRow, SkipsEmptyRows, WithCalculatedFormulas
{
    /**
     * Baris mulai membaca data (skip header)
     */
    public function startRow(): int
    {
        return 2; // Skip baris pertama (header CABANG)
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Mapping kolom Excel berdasarkan index:
        // A = NO (index 0) - bisa diabaikan
        // B = KODE (index 1) -> branch_code
        // C = NAMA (index 2) -> branch_name

        // Helper function untuk sanitasi UTF-8
        $sanitize = function ($value) {
            if (empty($value) && $value !== '0' && $value !== 0) return '';
            $original = (string)$value;
            $value = @iconv('UTF-8', 'UTF-8//IGNORE//TRANSLIT', $original);
            if ($value === false || $value === '') {
                $value = mb_convert_encoding($original, 'UTF-8', 'UTF-8');
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
            }
            return trim($value);
        };

        // Ambil nilai calculated/formula value, bukan formula string
        // Dengan WithCalculatedFormulas, Excel akan mengembalikan nilai hasil formula
        $no = isset($row[0]) ? (is_null($row[0]) ? '' : $sanitize($row[0])) : '';
        $kode = isset($row[1]) ? (is_null($row[1]) ? '' : $sanitize($row[1])) : '';
        $nama = isset($row[2]) ? (is_null($row[2]) ? '' : $sanitize($row[2])) : '';

        // Debug: Log semua row yang dibaca
        Log::info('Branch Import - Reading row', [
            'no' => $no,
            'kode' => $kode,
            'nama' => $nama,
            'row_count' => count($row),
        ]);

        // Skip jika KODE atau NAMA kosong
        if (empty($kode) || empty($nama)) {
            Log::info('Branch Import - Skipped: Empty KODE or NAMA', ['kode' => $kode, 'nama' => $nama]);
            return null;
        }

        // Skip row yang berisi header seperti "KODE", "NAMA", "NO", "CABANG"
        $kodeLower = strtolower($kode);
        $namaLower = strtolower($nama);
        $noLower = strtolower($no);

        // Skip header rows
        if ($kodeLower === 'kode' || $namaLower === 'nama' || $noLower === 'no' || $kodeLower === 'cabang') {
            Log::info('Branch Import - Skipped: Header row', ['kode' => $kode, 'nama' => $nama]);
            return null;
        }

        // Skip area headers (tapi tidak skip jika ada NO yang valid)
        if ((str_contains($kodeLower, 'area') || str_contains($namaLower, 'area')) && (empty($no) || !is_numeric($no))) {
            Log::info('Branch Import - Skipped: Area header', ['kode' => $kode, 'nama' => $nama]);
            return null;
        }

        // Skip jika NAMA masih berisi formula Excel yang tidak ter-evaluate (dimulai dengan =)
        // Dengan WithCalculatedFormulas, seharusnya formula sudah ter-evaluate
        // Tapi tetap cek untuk safety
        if (str_starts_with($nama, '=') || str_starts_with($kode, '=')) {
            Log::info('Branch Import - Skipped: Excel formula not evaluated', ['kode' => $kode, 'nama' => $nama]);
            return null;
        }

        // Cek apakah data dengan branch_code yang sama sudah ada di database
        $existingBranch = Branch::where('branch_code', $kode)->first();
        if ($existingBranch) {
            Log::info('Branch Import - Skipped: Duplicate branch_code', ['kode' => $kode, 'nama' => $nama]);
            return null; // Skip jika sudah ada (tidak masukkan lagi ke DB)
        }

        try {
            $branch = new Branch([
                'branch_code' => $kode,
                'branch_name' => $nama,
            ]);

            Log::info('Branch Import - Success: Creating branch', ['kode' => $kode, 'nama' => $nama]);
            return $branch;
        } catch (\Exception $e) {
            Log::error('Branch Import Error: ' . $e->getMessage(), [
                'kode' => $kode,
                'nama' => $nama,
                'row' => $row,
            ]);
            return null;
        }
    }
}
