<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Sp Terhadap Target</strong><br />
                    <small class="text-muted">Analisis persentase SP terhadap Target</small>
                </div>
            </div>

            <div class="mb-3">
                <form method="GET" action="{{ route('sp-v-target') }}" id="filterForm" class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="text" name="search" class="form-control"
                        placeholder="Cari kode buku atau nama produk..." value="{{ $search }}"
                        style="max-width: 300px;">
                    <label class="small text-muted mb-0">Per Halaman:</label>
                    <select name="per_page" class="form-select form-select-sm" style="max-width: 120px;"
                        onchange="document.getElementById('filterForm').submit()">
                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                        <option value="250" {{ $perPage == 250 ? 'selected' : '' }}>250</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    @if ($search || (request('per_page') && request('per_page') != 50))
                        <a href="{{ route('sp-v-target') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x me-1"></i>Reset
                        </a>
                    @endif
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;">NO</th>
                            <th class="text-left">Nama Produk</th>
                            <th class="text-center" style="width: 100px;">Target</th>
                            <th class="text-center" style="width: 100px;">SP</th>
                            <th class="text-center" style="width: 130px;">Persentase terpenuhi</th>
                            <th class="text-center" style="width: 130px;">Persentase belum terpenuhi</th>
                            <th class="text-center" style="width: 100px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data as $index => $item)
                            <tr>
                                <td class="text-center">{{ $data->firstItem() + $index }}</td>
                                <td class="text-left">
                                    <code>{{ $item['book_code'] }}</code><br>
                                    <small class="text-muted">{{ $item['book_name'] }}</small>
                                </td>
                                <td class="text-center">{{ number_format($item['target'], 0, ',', '.') }}</td>
                                <td class="text-center">{{ number_format($item['sp'], 0, ',', '.') }}</td>
                                <td class="text-center">
                                    <strong class="{{ $item['persentase_terpenuhi'] >= 100 ? 'text-success' : ($item['persentase_terpenuhi'] < 100 ? 'text-warning' : '') }}">
                                        {{ number_format($item['persentase_terpenuhi'], 2, ',', '.') }}%
                                    </strong>
                                </td>
                                <td class="text-center">
                                    <strong class="{{ $item['persentase_belum_terpenuhi'] > 0 ? 'text-danger' : 'text-muted' }}">
                                        {{ number_format($item['persentase_belum_terpenuhi'], 2, ',', '.') }}%
                                    </strong>
                                </td>
                                <td class="text-center">
                                    @if ($item['status'] == 'kurang')
                                        <span class="badge bg-danger">Kurang</span>
                                    @elseif ($item['status'] == 'cukup')
                                        <span class="badge bg-warning text-dark">Cukup</span>
                                    @else
                                        <span class="badge bg-success">Lebih</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Tidak ada data ditemukan.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($data->hasPages())
                <div class="mt-3">
                    {{ $data->links('pagination::bootstrap-5') }}
                </div>
            @endif

            <div class="mt-3">
                <small class="text-muted">
                    <strong>Rumus:</strong> Terpenuhi = (SP / Target) × 100%. Belum terpenuhi = 100% − Terpenuhi (min 0).<br>
                    <strong>Contoh:</strong> SP 80 vs Target 100 → Terpenuhi 80%, Belum terpenuhi 20%. SP 120 vs Target 100 → Terpenuhi 120%, Belum terpenuhi 0%.<br>
                    <strong>Status:</strong>
                    <span class="badge bg-danger">Kurang</span> = terpenuhi &lt; 100%,
                    <span class="badge bg-warning text-dark">Cukup</span> = terpenuhi = 100%,
                    <span class="badge bg-success">Lebih</span> = terpenuhi &gt; 100%.
                </small>
            </div>
        </div>
    </div>
</x-layouts>
