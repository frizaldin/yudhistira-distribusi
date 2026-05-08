<x-layouts>
    <div class="mb-3">
        <a href="{{ route('kartu_gudang_besar.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke List Buku
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-1">Buku: <span class="text-primary">{{ $product->book_title }}</span></h5>
                    <p class="text-muted mb-0">Kode Buku: <code>{{ $product->book_code }}</code></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-3">Filter Waktu History</h6>
            <form class="row g-2" method="GET" action="{{ route('kartu_gudang_besar.show', $product->book_code) }}">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="date_from" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="date_to" value="{{ $filters['date_to'] }}">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-filter me-1"></i>Terapkan
                    </button>
                    <a href="{{ route('kartu_gudang_besar.show', $product->book_code) }}" class="btn btn-outline-secondary btn-sm">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <strong>History Pergerakan Stok (Gudang Besar)</strong>
            <p class="text-muted small mb-3">Menampilkan data Mutasi, NKB, dan NTB/Retur.</p>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Tipe</th>
                            <th>No Referensi</th>
                            <th>Cabang Pengirim</th>
                            <th>Cabang Penerima</th>
                            <th>Keterangan</th>
                            <th class="text-end">Exemplar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history ?? [] as $row)
                            @php
                                $badgeColor = match($row->type) {
                                    'Mutasi' => 'bg-info',
                                    'NKB' => 'bg-warning',
                                    'NTB' => 'bg-success',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row->action_date)->format('d M Y') }}</td>
                                <td><span class="badge {{ $badgeColor }}">{{ $row->type }}</span></td>
                                <td><code>{{ $row->ref_no }}</code></td>
                                <td>
                                    @if($row->branch_sender)
                                        {{ $row->branch_sender }} - {{ $branchNames[$row->branch_sender] ?? 'Unknown' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($row->branch_receiver)
                                        {{ $row->branch_receiver }} - {{ $branchNames[$row->branch_receiver] ?? 'Unknown' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">{{ Str::limit($row->info ?? '-', 50) }}</small>
                                </td>
                                <td class="text-end fw-bold">
                                    {{ number_format($row->qty ?? 0, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <p class="text-muted mb-0">Tidak ada history pergerakan stok pada rentang tanggal ini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts>
