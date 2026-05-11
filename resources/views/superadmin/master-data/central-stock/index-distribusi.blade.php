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

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Data Posisi Stok Pusat (Stok Real)</strong><br />
                    <small class="text-muted">Total stok pusat per judul buku</small>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('central-stock.index') }}">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari kode buku atau judul buku" value="{{ request('search') }}" />
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('central-stock.index') }}" class="btn btn-outline-secondary"
                        style="height: 38px;">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kode Buku</th>
                            <th>Judul Buku</th>
                            <th class="text-end">Total Exemplar (Stok Real)</th>
                            <th class="text-end">Total Exemplar Isolasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stocks ?? [] as $index => $stock)
                            <tr>
                                <td>{{ $stocks->firstItem() + $index }}</td>
                                <td><code>{{ $stock->book_code }}</code></td>
                                <td>{{ $stock->product->book_title ?? '-' }}</td>
                                <td class="text-end fw-bold">{{ number_format($stock->exemplar ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end fw-bold">{{ number_format($stock->isolation_exemplar ?? 0, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <p class="text-muted mb-0">Belum ada data stok pusat.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($stocks) && $stocks->hasPages())
                <div class="mt-3">
                    {{ $stocks->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</x-layouts>
