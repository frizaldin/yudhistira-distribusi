<x-layouts>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-bold">Detail Nota Promosi</h5>
            <small class="text-muted">{{ $data->nota_kirim_promo }}</small>
        </div>
        <a href="{{ route('delivery_promo.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    {{-- ====== INFO HEADER ====== --}}
    <div class="card mb-3">
        <div class="card-header py-2 px-3 bg-light">
            <span class="fw-semibold text-secondary small"><i class="bi bi-info-circle me-1"></i>Informasi Nota</span>
        </div>
        <div class="card-body px-3 py-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="detail-label">No. Referensi</div>
                    <div class="detail-value fw-bold fs-6">{{ $data->nota_kirim_promo }}</div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">Cabang Pengirim</div>
                    <div class="detail-value">{{ $data->branch_sender ?? '-' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">Tanggal Kirim</div>
                    <div class="detail-value">
                        {{ $data->send_date ? \Carbon\Carbon::parse($data->send_date)->format('d/m/Y') : '-' }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">Kode Sales</div>
                    <div class="detail-value">{{ $data->sales_code ?? '-' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">Pengirim</div>
                    <div class="detail-value">{{ $data->deliver_by ?? '-' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">Disetujui Oleh</div>
                    <div class="detail-value">{{ $data->approve_by ?? '-' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">Kepala Gudang</div>
                    <div class="detail-value">{{ $data->whouse_head ?? '-' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        @if($data->status == 1)
                            <span class="badge bg-success">Selesai</span>
                        @elseif($data->status == 0)
                            <span class="badge bg-warning text-dark">Pending</span>
                        @else
                            <span class="badge bg-secondary">{{ $data->status }}</span>
                        @endif
                    </div>
                </div>
                @if($data->info)
                <div class="col-md-8">
                    <div class="detail-label">Keterangan</div>
                    <div class="detail-value">{{ $data->info }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ====== TABEL ITEM BUKU ====== --}}
    <div class="card">
        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
            <span class="fw-semibold text-secondary small"><i class="bi bi-list-ul me-1"></i>Detail Item Buku</span>
            <span class="badge bg-primary rounded-pill">{{ $data->items->count() }} item</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" style="white-space: nowrap;">
                    <thead class="table-light text-center">
                        <tr>
                            <th class="text-center" style="width:45px">#</th>
                            <th class="text-start" style="min-width:160px">Kode Buku</th>
                            <th class="text-end" style="width:120px">Harga Buku</th>
                            <th style="width:130px">Cabang</th>
                            <th class="text-end" style="width:90px">Koli</th>
                            <th class="text-end" style="width:90px">Isi Koli</th>
                            <th class="text-end" style="width:90px">Eceran</th>
                            <th class="text-end" style="width:110px">Total Eks.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $grandTotal = 0; @endphp
                        @forelse($data->items as $i => $item)
                            @php $grandTotal += $item->total_exemplar ?? 0; @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $i + 1 }}</td>
                                <td><strong>{{ $item->book_code ?? '-' }}</strong></td>
                                <td class="text-end">Rp {{ number_format($item->book_price ?? 0, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $item->branch_sender ?? '-' }}</td>
                                <td class="text-end">{{ number_format($item->koli ?? 0) }}</td>
                                <td class="text-end">{{ number_format($item->volume ?? 0) }}</td>
                                <td class="text-end">{{ number_format($item->exemplar ?? 0) }}</td>
                                <td class="text-end fw-semibold text-primary">{{ number_format($item->total_exemplar ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox me-1"></i>Tidak ada item buku.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($data->items->count() > 0)
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="7" class="text-end fw-semibold">Total Keseluruhan</td>
                            <td class="text-end fw-bold text-success">{{ number_format($grandTotal) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <style>
        .detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 0.925rem;
            color: #212529;
        }
    </style>
</x-layouts>