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
                    <strong>Preparation Notes</strong><br />
                    <small class="text-muted">Daftar data NPPB Centrals (rencana kirim pusat)</small>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('preparation_notes.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari cabang, kode buku, nama buku..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <select name="branch_code" class="form-select">
                        <option value="">Semua Cabang</option>
                        @foreach ($branches ?? [] as $b)
                            <option value="{{ $b->branch_code }}"
                                {{ request('branch_code') == $b->branch_code ? 'selected' : '' }}>
                                {{ $b->branch_code }} - {{ $b->branch_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('preparation_notes.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Stack</th>
                            <th>Tanggal</th>
                            <th>Pembuat</th>
                            <th class="text-end">Jumlah Data</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stackList ?? [] as $item)
                            <tr>
                                <td><code>{{ $item->stack }}</code></td>
                                <td>{{ $item->date ? \Carbon\Carbon::parse($item->date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item->creator_name ?? '-' }}</td>
                                <td class="text-end">{{ number_format($item->count) }}</td>
                                <td class="text-center">
                                    <a href="{{ route('preparation_notes.detail', ['stack' => $item->stack]) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-list-ul me-1"></i>Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Belum ada data preparation notes (NPPB Centrals).
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($lastPage) && $lastPage > 1)
                <nav class="mt-3" aria-label="Paginasi stack">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        @if (isset($currentPage) && $currentPage > 1)
                            <li class="page-item">
                                <a class="page-link"
                                    href="{{ route('preparation_notes.index', array_merge($queryString ?? [], ['page' => $currentPage - 1])) }}">&laquo;</a>
                            </li>
                        @endif
                        @for ($p = 1; $p <= $lastPage; $p++)
                            <li class="page-item {{ ($currentPage ?? 1) == $p ? 'active' : '' }}">
                                <a class="page-link"
                                    href="{{ route('preparation_notes.index', array_merge($queryString ?? [], ['page' => $p])) }}">{{ $p }}</a>
                            </li>
                        @endfor
                        @if (isset($currentPage) && $currentPage < $lastPage)
                            <li class="page-item">
                                <a class="page-link"
                                    href="{{ route('preparation_notes.index', array_merge($queryString ?? [], ['page' => $currentPage + 1])) }}">&raquo;</a>
                            </li>
                        @endif
                    </ul>
                    <p class="text-center text-muted small mt-1">Stack {{ $currentPage ?? 1 }} / {{ $lastPage }}
                        ({{ $totalStacks ?? 0 }} stack)</p>
                </nav>
            @endif
        </div>
    </div>
</x-layouts>
