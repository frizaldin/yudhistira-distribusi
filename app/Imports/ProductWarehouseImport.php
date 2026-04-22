<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Template Excel: kolom A = urutan, kolom B = book_code, kolom D = nama gudang (field books.warehouse).
 * Kolom A/C boleh berisi label lain; baris tanpa kode di B dilewati.
 */
class ProductWarehouseImport implements ToCollection, WithStartRow
{
    public int $updatedRows = 0;

    public int $skippedEmptyCode = 0;

    public int $notFoundCount = 0;

    /** @var list<string> */
    public array $notFoundCodes = [];

    public function startRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows): void
    {
        $hasUrutanColumn = Schema::hasColumn('books', 'urutan');

        foreach ($rows as $row) {
            $code = $this->sanitize($row[1] ?? null);
            if ($code === '') {
                $this->skippedEmptyCode++;

                continue;
            }

            $warehouseRaw = $this->sanitize($row[3] ?? null);
            $warehouse = $warehouseRaw === '' ? null : $warehouseRaw;
            $urutanRaw = $this->sanitize($row[0] ?? null);
            $urutan = $urutanRaw === '' ? null : $urutanRaw;

            $updatePayload = ['warehouse' => $warehouse];
            if ($hasUrutanColumn) {
                $updatePayload['urutan'] = $urutan;
            }

            $affected = Product::query()->where('book_code', $code)->update($updatePayload);
            if ($affected > 0) {
                $this->updatedRows++;
            } else {
                $this->notFoundCount++;
                if (count($this->notFoundCodes) < 200) {
                    $this->notFoundCodes[] = $code;
                }
            }
        }
    }

    private function sanitize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value)) {
            return trim((string) $value);
        }

        return trim((string) $value);
    }
}
