<x-layouts>
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="mb-3">
        <a href="{{ route('nkb_penyesuaian.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white pb-0">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0 text-primary">
                    <i class="bi bi-file-earmark-text me-2"></i>Detail NKB Penyesuaian
                </h5>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-3">
                    <label class="text-muted small mb-1">No. Nota Kirim</label>
                    <div class="fw-semibold">{{ $nkb->nota_kirim_cab }}</div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="text-muted small mb-1">Tanggal Kirim</label>
                    <div class="fw-semibold">
                        {{ $nkb->send_date ? \Carbon\Carbon::parse($nkb->send_date)->format('d F Y') : '-' }}
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="text-muted small mb-1">NPPB</label>
                    <div class="fw-semibold">{{ $nkb->nppb ?? '-' }}</div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="text-muted small mb-1">Surat Jalan (SJ)</label>
                    <div class="fw-semibold">{{ $nkb->sj ?? '-' }}</div>
                </div>
                <div class="col-12">
                    <label class="text-muted small mb-1">Info</label>
                    <div class="fw-semibold">{{ $nkb->info ?? '-' }}</div>
                </div>
            </div>

            <h6 class="mb-3 border-bottom pb-2">Rincian Buku</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <th rowspan="2" class="align-middle" style="width: 50px;">No</th>
                            <th rowspan="2" class="align-middle text-start">Kode Buku</th>
                            <th rowspan="2" class="align-middle text-end">Harga</th>
                            <th colspan="4">Kuantitas</th>
                        </tr>
                        <tr>
                            <th>Koli</th>
                            <th>Isi Koli</th>
                            <th>Pls / Exp</th>
                            <th>Total Exp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalKoli = 0;
                            $totalVolume = 0;
                            $totalExemplar = 0;
                            $totalTotalExemplar = 0;
                        @endphp
                        @forelse($nkb->details as $idx => $detail)
                            @php
                                $totalKoli += (int) $detail->koli;
                                $totalVolume += (int) $detail->volume;
                                $totalExemplar += (int) $detail->exemplar;
                                $totalTotalExemplar += (int) $detail->total_exemplar;
                            @endphp
                            <tr class="text-center">
                                <td>{{ $idx + 1 }}</td>
                                <td class="text-start"><code>{{ $detail->book_code }}</code></td>
                                <td class="text-end">Rp {{ number_format((float) $detail->book_price, 0, ',', '.') }}</td>
                                <td>{{ number_format((int) $detail->koli) }}</td>
                                <td>{{ number_format((int) $detail->volume) }}</td>
                                <td>{{ number_format((int) $detail->exemplar) }}</td>
                                <td><strong>{{ number_format((int) $detail->total_exemplar) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">Belum ada rincian buku.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light text-center fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">Total:</td>
                            <td>{{ number_format($totalKoli) }}</td>
                            <td>{{ number_format($totalVolume) }}</td>
                            <td>{{ number_format($totalExemplar) }}</td>
                            <td>{{ number_format($totalTotalExemplar) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-layouts>
