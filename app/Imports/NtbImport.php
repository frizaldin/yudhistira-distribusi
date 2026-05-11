<?php

namespace App\Imports;

use App\Models\ReceiveBookNote;
use App\Models\ReceiveBookNoteDetail;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Carbon\Carbon;

class NtbImport implements ToCollection, WithHeadingRow
{
    public int $inserted = 0;
    public int $skipped = 0;
    public array $errors = [];

    public function headingRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // 1-indexed + header row

            // Skip empty rows
            $notaKirim = trim($row['nota_kirim'] ?? $row['nota_kirim_cab'] ?? '');
            if (empty($notaKirim)) {
                continue;
            }

            $bookCode = trim($row['book_cod'] ?? $row['book_code'] ?? '');
            if (empty($bookCode)) {
                continue;
            }

            try {
                // Parse send_date safely
                $sendDate = null;
                $rawDate = trim($row['send_date'] ?? '');
                if (!empty($rawDate)) {
                    try {
                        // Handle Excel numeric date or string
                        if (is_numeric($rawDate)) {
                            $sendDate = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate))->format('Y-m-d');
                        } else {
                            $sendDate = Carbon::parse($rawDate)->format('Y-m-d');
                        }
                    } catch (\Exception $e) {
                        $sendDate = null;
                    }
                }

                $branchCode   = trim($row['branch_cod'] ?? $row['branch_code'] ?? '');
                $branchSender = trim($row['branch_sen'] ?? $row['branch_sender'] ?? '');
                $info         = trim($row['approve_info'] ?? $row['info'] ?? '');

                // Upsert header (m_terima_buku) — hanya buat jika belum ada
                $existing = ReceiveBookNote::where('nota_kirim_cab', $notaKirim)->first();
                if (!$existing) {
                    ReceiveBookNote::create([
                        'nota_kirim_cab' => $notaKirim,
                        'branch_code'    => $branchCode,
                        'branch_sender'  => $branchSender,
                        'send_date'      => $sendDate,
                        'retur_date'     => $sendDate,
                        'info'           => $info,
                    ]);
                }

                // Check if detail for this book_code already exists for this NTB
                $detailExists = ReceiveBookNoteDetail::where('nota_kirim_cab', $notaKirim)
                    ->where('book_code', $bookCode)
                    ->exists();

                if ($detailExists) {
                    $this->skipped++;
                    continue;
                }

                // Insert detail (d_terima_buku)
                ReceiveBookNoteDetail::create([
                    'nota_kirim_cab' => $notaKirim,
                    'book_code'      => $bookCode,
                    'book_price'     => (float) ($row['book_price'] ?? 0),
                    'koli'           => (int) ($row['kol'] ?? $row['koli'] ?? 0),
                    'exemplar'       => (int) ($row['exemplar'] ?? 0),
                    'total_exemplar' => (int) ($row['total_exem'] ?? $row['total_exemplar'] ?? 0),
                    'volume'         => (int) ($row['volume'] ?? 0),
                    'branch_sender'  => $branchSender,
                    'skip_central_stock_deduction' => false,
                ]);

                $this->inserted++;
            } catch (\Exception $e) {
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }
    }
}
