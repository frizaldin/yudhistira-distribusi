<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Tanda Terima Surat Jalan {{ $deliveryOrder->number ?? '' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 15px;
            color: #000;
        }

        .header-wrap {
            display: table;
            width: 100%;
            margin-bottom: 4px;
        }

        .header-left {
            display: table-cell;
            width: 70%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 30%;
            text-align: right;
            vertical-align: top;
        }

        .title-block {
            text-align: center;
            margin: 5px 0 5px 0;
        }

        .title-line {
            border: none;
            border-top: 2px solid #000;
            margin: 0;
        }

        .title-text {
            font-size: 14px;
            font-weight: bold;
            padding: 6px 0;
            font-style: italic
        }

        .doc-table {
            width: 100%;
            border-collapse: collapse;
        }

        .doc-table td {
            padding: 4px 8px 4px 0;
            vertical-align: top;
            border: none;
            border-bottom: 1px dashed #000;
        }

        .doc-table .label {
            width: 140px;
            font-weight: bold;
        }

        .doc-table .colon {
            width: 12px;
            padding-right: 4px;
        }

        .doc-table .value {
            font-weight: normal;
        }

        .doc-table tr:last-child td {
            border-bottom: 1px solid #000;
        }

        .row-split {
            display: table;
            width: 100%;
        }

        .row-split-left {
            display: table-cell;
            width: 50%;
        }

        .row-split-right {
            display: table-cell;
            width: 50%;
            text-align: right;
        }

        .keterangan-wrap {
            border-bottom: 1px solid #000;
            padding: 6px 0;
            margin-top: 2px;
        }

        .keterangan-label {
            font-weight: bold;
        }

        .footer-line {
            border: none;
            border-top: 2px solid #000;
            margin: 16px 0 12px 0;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            border: none;
            padding: 0 16px 0 0;
            vertical-align: top;
            width: 33.33%;
        }

        .footer-table td:first-child {
            text-align: left;
        }

        .footer-table td:nth-child(2) {
            text-align: center;
        }

        .footer-table td:last-child {
            text-align: right;
        }

        .footer-table .sig-line {
            display: inline-block;
            margin-top: 28px;
            border-bottom: 1px solid #000;
            min-width: 120px;
        }

        .footer-table .sig-name {
            margin-top: 4px;
            font-size: 10px;
        }

        .no-print {
            margin-bottom: 14px;
        }

        .sj-number {
            font-weight: bold;
            font-size: 12px;
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
    <div class="no-print">
        <button type="button" onclick="window.print();"
            style="padding: 8px 16px; cursor: pointer; margin-right: 10px;">Cetak</button>
        <a href="{{ route('delivery-orders.show', $deliveryOrder->id) }}">Kembali ke Detail</a>
        <span style="margin-left: 12px; color: #666; font-size: 10px;">Di jendela Print, matikan &quot;Header dan
            footer&quot; agar hasil rapi.</span>
    </div>

    @php
        $do = $deliveryOrder;
        $sender = $do->senderBranch;
        $recipient = $do->recipientBranch;
        $nkbList = $do->items
            ->map(fn($i) => $i->nkb->number ?? $i->nkb_id)
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');
        $senderLabel = $sender ? trim($sender->branch_code . ' ' . ($sender->branch_name ?? '')) : $do->sender_code;
        $recipientName =
            $recipient->contact_person ??
            ($recipient->branch_head ?? 'BPK./IBU. ' . ($recipient->branch_name ?? $do->recipient_code));
        if ($recipientName && stripos($recipientName, 'BPK') !== 0 && stripos($recipientName, 'IBU') !== 0) {
            $recipientName = 'BPK. ' . $recipientName;
        }
    @endphp

    {{-- Header: perusahaan kiri, cabang kanan --}}
    <div class="header-wrap">
        <div class="header-left">
            <strong>PT. YUDHISTIRA GHALIA INDONESIA</strong><br>
            <span style="font-size: 10px;">(Penerbit &amp; Percetakan)</span><br>
            JL. RANCAMAYA 47 CIAWI - BOGOR<br>
            02518-240628
        </div>
        <div class="header-right">
            {{ strtoupper(str_replace(' ', '-', $senderLabel)) }}
        </div>
    </div>

    {{-- Judul dengan dua garis --}}
    <hr class="title-line">
    <div class="title-block">
        <div class="title-text">TANDA TERIMA SURAT JALAN BUKU</div>
    </div>
    <hr class="title-line">

    {{-- Isi dokumen: tiap baris label : value + garis bawah --}}
    <table class="doc-table">
        <tr>
            <td class="label">SURAT JALAN NO</td>
            <td class="colon">:</td>
            <td class="value"><span class="sj-number">{{ $do->number ?? '-' }}</span></td>
        </tr>
        <tr>
            <td class="label">Tanggal</td>
            <td class="colon">:</td>
            <td class="value">{{ $do->date ? $do->date->format('d/m/Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tujuan Bpk/Ibu</td>
            <td class="colon">:</td>
            <td class="value">{{ $recipientName }}</td>
        </tr>
        <tr>
            <td class="label">Cabang</td>
            <td class="colon">:</td>
            <td class="value">{{ $recipient->branch_name ?? $do->recipient_code }}</td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td class="colon">:</td>
            <td class="value">{{ $recipient->address ?? '-' }}{{ $recipient->city ? ' / ' . $recipient->city : '' }}
            </td>
        </tr>
        <tr>
            <td class="label">Telp/Fax</td>
            <td class="colon">:</td>
            <td class="value">{{ $recipient->phone_no ?? '-' }} / {{ $recipient->fax_no ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Expedisi</td>
            <td class="colon">:</td>
            <td class="value">
                <div class="row-split">
                    <div class="row-split-left">{{ $do->expedition ?? '-' }}</div>
                    <div class="row-split-right"><strong>No. Polisi:</strong> {{ $do->plate_number ?? '-' }}</div>
                </div>
            </td>
        </tr>
        <tr>
            <td class="label">Pengirim</td>
            <td class="colon">:</td>
            <td class="value">
                <div class="row-split">
                    <div class="row-split-left">{{ $do->driver ?? '-' }}</div>
                    <div class="row-split-right"><strong>Telp/HP:</strong> {{ $do->driver_phone ?? '' }}</div>
                </div>
            </td>
        </tr>
        <tr>
            <td class="label">NKB</td>
            <td class="colon">:</td>
            <td class="value">{{ $nkbList ?: '-' }}</td>
        </tr>
    </table>

    {{-- Keterangan (garis pemisah, lalu label + isi) --}}
    <div class="keterangan-wrap">
        <span class="keterangan-label">Keterangan:</span> {{ $do->note ? nl2br(e($do->note)) : '-' }}
    </div>

    {{-- Footer: garis ganda lalu tiga kolom tanda tangan --}}
    <hr class="footer-line">
    <hr class="footer-line">
    <table class="footer-table">
        <tr>
            <td>
                <strong>Yang Menerima,</strong><br>
                <span class="sig-line">&nbsp;</span>
            </td>
            <td>
                <strong>Security,</strong><br>
                <span class="sig-line">&nbsp;</span>
            </td>
            <td>
                <strong>Petugas Gudang.</strong><br>
                <span class="sig-line">&nbsp;</span>
                {{-- @if ($do->creator)
                    <div class="sig-name">{{ strtoupper($do->creator->name) }}</div>
                @endif --}}
            </td>
        </tr>
    </table>

    <script>
        (function() {
            function doPrint() {
                window.print();
            }
            if (document.readyState === 'complete') {
                setTimeout(doPrint, 250);
            } else {
                window.addEventListener('load', function() {
                    setTimeout(doPrint, 250);
                });
            }
        })();
    </script>
</body>

</html>
