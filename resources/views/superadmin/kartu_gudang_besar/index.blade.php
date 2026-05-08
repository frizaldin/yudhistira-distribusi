<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Kartu Gudang Besar</strong><br />
                    <small class="text-muted">Pilih buku untuk melihat history pergerakan stok (Mutasi, NKB, NTB)</small>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('kartu_gudang_besar.index') }}">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search_book_code"
                        placeholder="Kode Buku" value="{{ request('search_book_code') }}" />
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Judul Buku" value="{{ request('search') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('kartu_gudang_besar.index') }}" class="btn btn-outline-secondary"
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
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products ?? [] as $index => $product)
                            <tr>
                                <td>{{ $products->firstItem() + $index }}</td>
                                <td><code>{{ $product->book_code }}</code></td>
                                <td>{{ $product->book_title ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('kartu_gudang_besar.show', $product->book_code) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-clock-history me-1"></i> Lihat History
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <p class="text-muted mb-0">Data buku tidak ditemukan.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($products) && $products->hasPages())
                <div class="mt-3">
                    {{ $products->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</x-layouts>
