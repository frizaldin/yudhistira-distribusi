<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class PreparationNotesDetailExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    public function __construct(
        protected Collection $rows,
        protected string $stack,
        protected ?string $nppbDocumentNumber = null
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Nomor NPPB',
            'Tanggal',
            'Kode Cabang',
            'Nama Cabang',
            'Kode Buku',
            'Nama Buku',
            'Isi',
            'Koli',
            'Eceran',
            'Total',
        ];
    }

    /**
     * @param  \App\Models\NppbCentral  $row
     */
    public function map($row): array
    {
        return [
            $this->nppbDocumentNumber ?? '',
            $row->date ? Carbon::parse($row->date)->format('Y-m-d') : '',
            $row->branch_code ?? '',
            $row->branch_name ?? '',
            $row->book_code ?? '',
            $row->book_name ?? '',
            (float) ($row->volume ?? 0),
            (int) ($row->koli ?? 0),
            (int) ($row->pls ?? 0),
            (int) ($row->exp ?? 0),
        ];
    }

    public function title(): string
    {
        $base = $this->nppbDocumentNumber !== null && $this->nppbDocumentNumber !== ''
            ? $this->nppbDocumentNumber
            : $this->stack;
        $t = preg_replace('/[^A-Za-z0-9_-]/', '_', $base);

        return $t !== '' ? substr($t, 0, 31) : 'Detail';
    }
}
