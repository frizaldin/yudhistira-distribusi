<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Template Excel: kolom B = book_code, kolom D = nama gudang (field books.warehouse).
 * Kolom A/C boleh berisi label lain; baris tanpa kode di B dilewati.
 */
class ProductWarehouseImport implements ToCollection, WithStartRow
{
    public int $updatedRows = 0;

    public int $skippedEmptyCode = 0;

    /** @var list<string> */
    public array $notFoundCodes = [];

    public function startRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $code = $this->sanitize($row[1] ?? null);
            if ($code === '') {
                $this->skippedEmptyCode++;

                continue;
            }

            $warehouseRaw = $this->sanitize($row[3] ?? null);
            $warehouse = $warehouseRaw === '' ? null : $warehouseRaw;

            $affected = Product::query()->where('book_code', $code)->update(['warehouse' => $warehouse]);
            if ($affected > 0) {
                $this->updatedRows++;
            } else {
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
