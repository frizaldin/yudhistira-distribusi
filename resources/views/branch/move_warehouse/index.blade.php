<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Gudang Isolasi</strong><br />
                    <small class="text-muted">Daftar Gudang Isolasi</small>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('move_warehouse.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari move_code..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('move_warehouse.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" style="white-space: nowrap;">
                    <thead class="table-light">
                        <tr>
                            <th>No. Referensi</th>
                            <th>Cabang</th>
                            <th>Tanggal</th>
                            <th>Officer</th>
                            <th>Kepala Gudang</th>
                            <th>User ID</th>
                            <th>Info</th>
                            <th>Status</th>
                            
                            <th class="text-center sticky-end bg-light">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td><strong>{{ $item->move_code }}</strong></td>
                                <td>{{ $item->branch_code ?? '-' }}</td>
                                <td>{{ $item->mova_date ? \Carbon\Carbon::parse($item->mova_date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item->officer ?? '-' }}</td>
                                <td>{{ $item->whouse_head ?? '-' }}</td>
                                <td>{{ $item->user_id ?? '-' }}</td>
                                <td>{{ $item->info ?? '-' }}</td>
                                <td>{{ $item->status ?? '-' }}</td>
                                
                                <td class="text-center sticky-end bg-white">
                                    <a href="{{ route('move_warehouse.show', ['move_code' => $item->move_code]) }}"
                                        class="btn btn-sm btn-outline-primary" title="Detail">
                                        <i class="bi bi-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center py-4 text-muted">
                                    Belum ada data Gudang Isolasi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->hasPages())
                <nav class="mt-3" aria-label="Paginasi">
                    {{ $items->links('pagination::bootstrap-5') }}
                </nav>
            @endif
        </div>
    </div>
    
    <style>
        .sticky-end {
            position: sticky;
            right: 0;
            z-index: 1;
            box-shadow: -2px 0 5px rgba(0,0,0,0.05);
        }
    </style>
</x-layouts>