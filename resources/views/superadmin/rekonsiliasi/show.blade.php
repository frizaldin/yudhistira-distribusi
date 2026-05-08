<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Detail Rekonsiliasi NKB & NTB</h5>
                <div>
                    @if($item->source === 'new')
                        <a href="{{ route('nkb.show', $item->number) }}" class="btn btn-outline-primary btn-sm" target="_blank">
                            <i class="bi bi-box-seam me-1"></i>Detail NKB
                        </a>
                    @endif
                    @if(optional($item->receiveNote)->id)
                        <a href="{{ route('ntb.edit', $item->receiveNote->id) }}" class="btn btn-outline-success btn-sm" target="_blank">
                            <i class="bi bi-box-arrow-in-down me-1"></i>Detail NTB
                        </a>
                    @endif
                    <a href="{{ route('rekonsiliasi.index') }}" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </a>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td style="width: 150px" class="text-muted">Nomor NKB/Surat Jalan</td>
                            <td style="width: 10px">:</td>
                            <td class="fw-bold">{{ $item->number }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Cabang Pengirim</td>
                            <td>:</td>
                            <td>{{ $item->sender_code ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Cabang Penerima</td>
                            <td>:</td>
                            <td>{{ $item->recipient_code ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td style="width: 150px" class="text-muted">Tanggal Kirim</td>
                            <td style="width: 10px">:</td>
                            <td>{{ $item->send_date ? $item->send_date->format('d/m/Y') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tanggal Terima</td>
                            <td>:</td>
                            <td>
                                @if (optional($item->receiveNote)->retur_date)
                                    <span class="text-success fw-medium">{{ $item->receiveNote->retur_date->format('d/m/Y') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">Belum Diterima</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Kode Terima (NTB)</td>
                            <td>:</td>
                            <td>{{ optional($item->receiveNote)->receive_code ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <h6 class="fw-bold text-primary mb-2">Rincian Perbandingan Buku</h6>
            
            <div class="table-responsive mt-3">
                <table class="table table-bordered table-hover table-sm align-middle mb-0">
                    <thead class="table-light text-center">
                        <tr>
                            <th rowspan="2" class="align-middle" style="width:40px">No</th>
                            <th rowspan="2" class="align-middle text-start">Kode / Judul Buku</th>
                            <th colspan="3" class="table-primary text-primary">KIRIM (NKB)</th>
                            <th colspan="3" class="table-success text-success">TERIMA (NTB)</th>
                            <th colspan="3" class="table-danger text-danger">SELISIH</th>
                        </tr>
                        <tr>
                            <!-- NKB -->
                            <th class="table-primary text-primary" style="width: 70px;">Koli</th>
                            <th class="table-primary text-primary" style="width: 70px;">Eceran</th>
                            <th class="table-primary text-primary fw-bold" style="width: 80px;">Total</th>
                            
                            <!-- NTB -->
                            <th class="table-success text-success" style="width: 70px;">Koli</th>
                            <th class="table-success text-success" style="width: 70px;">Eceran</th>
                            <th class="table-success text-success fw-bold" style="width: 80px;">Total</th>
                            
                            <!-- SELISIH -->
                            <th class="table-danger text-danger" style="width: 70px;">Koli</th>
                            <th class="table-danger text-danger" style="width: 70px;">Eceran</th>
                            <th class="table-danger text-danger fw-bold" style="width: 80px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $sumNkbKoli = 0; $sumNkbEceran = 0; $sumNkbTotal = 0;
                            $sumNtbKoli = 0; $sumNtbEceran = 0; $sumNtbTotal = 0;
                            $sumDiffKoli = 0; $sumDiffEceran = 0; $sumDiffTotal = 0;
                        @endphp
                        @forelse($comparison as $i => $row)
                            @php
                                $sumNkbKoli += $row['nkb_koli'];
                                $sumNkbEceran += $row['nkb_eceran'];
                                $sumNkbTotal += $row['nkb_total'];

                                $sumNtbKoli += $row['ntb_koli'];
                                $sumNtbEceran += $row['ntb_eceran'];
                                $sumNtbTotal += $row['ntb_total'];

                                $sumDiffKoli += $row['diff_koli'];
                                $sumDiffEceran += $row['diff_eceran'];
                                $sumDiffTotal += $row['diff_total'];
                            @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $i + 1 }}</td>
                                <td>
                                    <strong>{{ $row['book_code'] }}</strong><br>
                                    <small class="text-muted">{{ $row['book_title'] }}</small>
                                </td>
                                
                                <!-- NKB -->
                                <td class="text-end">{{ number_format($row['nkb_koli'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row['nkb_eceran'], 0, ',', '.') }}</td>
                                <td class="text-end fw-bold text-primary">{{ number_format($row['nkb_total'], 0, ',', '.') }}</td>
                                
                                <!-- NTB -->
                                <td class="text-end">{{ number_format($row['ntb_koli'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row['ntb_eceran'], 0, ',', '.') }}</td>
                                <td class="text-end fw-bold text-success">{{ number_format($row['ntb_total'], 0, ',', '.') }}</td>
                                
                                <!-- SELISIH -->
                                <td class="text-end {{ $row['diff_koli'] != 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ number_format($row['diff_koli'], 0, ',', '.') }}</td>
                                <td class="text-end {{ $row['diff_eceran'] != 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ number_format($row['diff_eceran'], 0, ',', '.') }}</td>
                                <td class="text-end {{ $row['diff_total'] != 0 ? 'text-danger fw-bold fs-6' : 'text-muted' }}">
                                    {{ number_format($row['diff_total'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">Belum ada data buku untuk Nota Kirim Barag ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($comparison) > 0)
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="2" class="text-end text-uppercase">Subtotal</td>
                            
                            <!-- NKB -->
                            <td class="text-end text-primary">{{ number_format($sumNkbKoli, 0, ',', '.') }}</td>
                            <td class="text-end text-primary">{{ number_format($sumNkbEceran, 0, ',', '.') }}</td>
                            <td class="text-end text-primary fs-6">{{ number_format($sumNkbTotal, 0, ',', '.') }}</td>
                            
                            <!-- NTB -->
                            <td class="text-end text-success">{{ number_format($sumNtbKoli, 0, ',', '.') }}</td>
                            <td class="text-end text-success">{{ number_format($sumNtbEceran, 0, ',', '.') }}</td>
                            <td class="text-end text-success fs-6">{{ number_format($sumNtbTotal, 0, ',', '.') }}</td>
                            
                            <!-- SELISIH -->
                            <td class="text-end {{ $sumDiffKoli != 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($sumDiffKoli, 0, ',', '.') }}</td>
                            <td class="text-end {{ $sumDiffEceran != 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($sumDiffEceran, 0, ',', '.') }}</td>
                            <td class="text-end {{ $sumDiffTotal != 0 ? 'text-danger fw-bolder fs-6' : 'text-success fw-bolder fs-6' }}">
                                {{ number_format($sumDiffTotal, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            @if(optional($item->receiveNote)->receive_code == null)
            <div class="alert alert-warning mt-3 mb-0 pointer-events-none d-flex align-items-center">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                <div>
                    <strong>Perhatian:</strong> Data NKB ini belum mempunyai konfirmasi Penerimaan Barang (NTB). Seluruh selisih saat ini menampilkan minus sesuai dengan jumlah yang belum diterima.
                </div>
            </div>
            @endif

        </div>
    </div>
</x-layouts>
