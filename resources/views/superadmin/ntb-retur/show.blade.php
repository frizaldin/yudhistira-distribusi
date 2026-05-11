<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Detail NTB Retur</strong><br />
                    <small class="text-muted">Rincian Buku pada NTB Retur</small>
                </div>
                <a href="{{ route('ntb.index', ['type' => 'retur']) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar NTB
                </a>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td style="width: 150px">No. NTB</td>
                            <td style="width: 10px">:</td>
                            <th>{{ $ntb->receive_code }}</th>
                        </tr>
                        <tr>
                            <td>No. NKB</td>
                            <td>:</td>
                            <th>{{ $ntb->nota_kirim_cab ?? '-' }}</th>
                        </tr>
                        <tr>
                            <td>Tgl Terima</td>
                            <td>:</td>
                            <td>{{ $ntb->retur_date ? \Carbon\Carbon::parse($ntb->retur_date)->format('d F Y') : '-' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td style="width: 150px">Cabang Tujuan</td>
                            <td style="width: 10px">:</td>
                            <td>{{ $ntb->branch_code ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Cabang Pengirim</td>
                            <td>:</td>
                            <td>{{ $ntb->branch_sender ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Keterangan</td>
                            <td>:</td>
                            <td>{{ $ntb->info ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light text-center">
                        <tr>
                            <th style="width: 50px">No</th>
                            <th class="text-start">Kode Buku</th>
                            <th style="width: 150px" class="text-end">Harga (Rp)</th>
                            <th style="width: 100px" class="text-end">Koli</th>
                            <th style="width: 100px" class="text-end">Isi Koli</th>
                            <th style="width: 100px" class="text-end">Pls/Exp</th>
                            <th style="width: 150px" class="text-end">Total Eksemplar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalKoli = 0;
                            $totalVolume = 0;
                            $totalExemplar = 0;
                            $totalSemuaEks = 0;
                        @endphp
                        @forelse ($ntb->items as $idx => $item)
                            @php
                                $totalKoli += $item->koli;
                                $totalVolume += $item->volume;
                                $totalExemplar += $item->exemplar;
                                $totalSemuaEks += $item->total_exemplar;
                            @endphp
                            <tr>
                                <td class="text-center">{{ $idx + 1 }}</td>
                                <td>{{ $item->book_code }}</td>
                                <td class="text-end">{{ number_format($item->book_price ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($item->koli, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($item->volume, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($item->exemplar, 0, ',', '.') }}</td>
                                <td class="text-end fw-semibold text-primary">{{ number_format($item->total_exemplar, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">Tidak ada detail buku</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="3" class="text-end fw-bold">Total Keseluruhan</td>
                            <td class="text-end fw-bold">{{ number_format($totalKoli, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold">{{ number_format($totalVolume, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold">{{ number_format($totalExemplar, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold text-success">{{ number_format($totalSemuaEks, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>
</x-layouts>
