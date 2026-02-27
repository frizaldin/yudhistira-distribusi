<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Nota {{ $document->number ?? '' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 15px;
            color: #000;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px 6px;
            text-align: left;
        }

        th {
            background: #f5f5f5;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .header-table td {
            border: none;
            padding: 2px 8px 2px 0;
            vertical-align: top;
        }

        .nota-sah {
            font-weight: bold;
            font-size: 14px;
            text-align: center;
            margin: 10px 0 12px 0;
        }

        .footer-table td {
            border: none;
            padding: 12px 16px 0 0;
            vertical-align: top;
            width: 25%;
        }

        .no-print {
            margin-bottom: 14px;
        }

        @media print {
            @page {
                margin: 0.5cm;
                size: A4;
            }

            .no-print {
                display: none !important;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>

<body>
    {{-- <div class="no-print">
        <button type="button" onclick="window.print();"
            style="padding: 8px 16px; cursor: pointer; margin-right: 10px;">Cetak</button>
        <a href="{{ route('preparation_notes.detail', ['stack' => $stack ?? '']) }}">Kembali ke Detail</a>
        <span style="margin-left: 12px; color: #666; font-size: 10px;">Agar tidak ada tanggal/URL di hasil cetak: di
            jendela Print → matikan &quot;Header dan footer&quot;.</span>
    </div> --}}

    {{-- Header: kiri = box, tengah = judul, kanan = No. + info --}}
    <table class="header-table" cellpadding="0" cellspacing="0" style="margin-bottom: 6px;width: 100%;">
        <tr>
            <td style="width: 24%;vertical-align: bottom;text-align: center;">
                <div style="display: flex; justify-content:flex-start;width: 100%;">
                    <div>
                        <span
                            style="border-left: 1px solid #000; border-right: 1px solid #000; border-bottom: 1px solid #000; border-top: none; padding: 6px 8px; font-weight: bold; display: inline-block;">BUKAN
                            BUKTI PENGELUARAN BARANG</span>
                    </div>
                </div>
            </td>
            <td style="width: 52%; text-align: center;vertical-align: bottom;">
                <div style="display: flex; justify-content: center;width: 100%;">
                    <p style="width: 120px;text-align: center;border-bottom: 1px solid #000;">
                        <strong>NOTA PERMINTAAN PENYIAPAN BARANG</strong>
                    </p>
                </div>
            </td>
            <td style="width: 24%; text-align: right;vertical-align: bottom;">
                <strong
                    style="border-bottom: 1px solid #000;border-top: 1px solid #000;padding: 3px 8px;margin-right: 55px;">No.
                    {{ $document->number ?? '-' }}</strong>
            </td>
        </tr>
    </table>

    {{-- Baris 2: KEPADA (kiri), kosong tengah, info pengiriman (kanan) --}}
    <table class="header-table" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
        <tr>
            <td style="width: 24%;">
                <strong>KEPADA,</strong><br>
                {{ nl2br(e($document->note ?? '')) }}
                @if (!empty($document->note_more))
                    <br>{{ nl2br(e($document->note_more)) }}
                @endif
            </td>
            <td style="width: 52%;vertical-align: bottom;text-align: center;">
                <strong style="border-bottom: 1px solid #000;">
                    NOTA SAH
                </strong>
            </td>
            <td style="width: 24%;text-align: left;">
                <div style="display: flex; justify-content: flex-end;">
                    <div>
                        BUKTINPPB KE CABANG <br>
                        {{ $document->recipientBranch->branch_name ?? $document->recipient_code }}<br>
                        KODE CABANG {{ $document->senderBranch->branch_code ?? $document->sender_code }}<br>
                        TGL. PENGIRIMAN: {{ $document->send_date ? $document->send_date->format('d-m-Y') : '-' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th rowspan="2" style="width: 30px; text-align: center;">NO</th>
                <th rowspan="2" style="width:80px;">KODE BUKU</th>
                <th rowspan="2" style="">JUDUL BUKU</th>
                <th colspan="2" style=" text-align: center;">JUMLAH</th>
                <th rowspan="2" style="width: 40px; text-align: center;">ISI KOLI (Ex)</th>
                <th rowspan="2" style="width: 50px; text-align: right;">JUMLAH (Ex)</th>
                <th rowspan="2" style=" text-align: right;">Rp</th>
                <th rowspan="2" style=" text-align: right;">JUMLAH (Rp)</th>
            </tr>
            <tr>
                <th style="width: 40px; text-align: center;">KOLI</th>
                <th style="width: 40px; text-align: center;">ECERAN</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows ?? [] as $idx => $row)
                @php
                    $priceRow = $prices_by_book[$row->book_code] ?? null;
                    $unitPrice = $priceRow ? (float) $priceRow->sale_price : 0;
                    $jumlahEx = (float) ($row->exp ?? 0);
                    $jumlahRp = $jumlahEx * $unitPrice;
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td>{{ $row->book_code ?? '-' }}</td>
                    <td>{{ $row->book_name ?? '-' }}</td>
                    <td style="text-align: center;">{{ number_format($row->koli) }}</td>
                    <td style="text-align: center;">{{ number_format($row->pls) }}</td>
                    <td style="text-align: center;">{{ number_format($row->volume) }}</td>
                    <td style="text-align: right;">{{ number_format($jumlahEx) }}</td>
                    <td style="text-align: right;">{{ $unitPrice > 0 ? number_format($unitPrice) : '-' }}</td>
                    <td style="text-align: right;">{{ $jumlahRp > 0 ? number_format($jumlahRp) : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="font-weight: bold;text-align: right;">Jumlah Unit</td>
                <td style="text-align: center; font-weight: bold;">{{ number_format($total_koli ?? 0) }}</td>
                <td style="text-align: center; font-weight: bold;">{{ number_format($total_pls ?? 0) }}</td>
                <td></td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($total_ex ?? 0) }}</td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td colspan="7" style="font-weight: bold; text-align: right;">Jumlah (Rp)</td>
                <td colspan="2" style="text-align: right; font-weight: bold;">{{ number_format($total_rp ?? 0) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <table class="footer-table" style="margin-top: 28px; width: 100%;">
        <tr>
            <td>
                <strong>DITERIMA</strong><br>
                <span style="display: inline-block; margin-top: 20px;">(__________________)</span>
            </td>
            <td>
                <strong>MENGETAHUI</strong><br>
                <span style="display: inline-block; margin-top: 20px;">(__________________)</span>
            </td>
            <td>
                <strong>KETERANGAN :</strong><br>
                @if (!empty($document->note_more))
                    {{ nl2br(e($document->note_more)) }}
                @endif
            </td>
            <td>
                <strong>PUSAT</strong>,
                {{ $document->send_date ? $document->send_date->locale('id')->translatedFormat('d F Y') : '' }}<br>
                <span style="display: inline-block; margin-top: 20px;">(__________________)</span>
            </td>
        </tr>
    </table>

    @if (request()->get('print'))
        <script>
            (function() {
                if (window.location.search.indexOf('print=1') !== -1) {
                    window.onload = function() {
                        window.print();
                    };
                }
            })();
        </script>
    @endif
</body>

</html>
