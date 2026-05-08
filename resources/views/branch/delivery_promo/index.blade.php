<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Nota Promosi</strong><br />
                    <small class="text-muted">Daftar Nota Promosi</small>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('delivery_promo.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari nota_kirim_promo..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('delivery_promo.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" style="white-space: nowrap;">
                    <thead class="table-light">
                        <tr>
                            <th>No. Referensi</th>
                            <th>Cabang Pengirim</th>
                            <th>Tgl Kirim</th>
                            <th>Sales Code</th>
                            <th>Pengirim</th>
                            <th>Disetujui Oleh</th>
                            <th>Kepala Gudang</th>
                            <th>User ID</th>
                            <th>Printed</th>
                            <th>Info</th>
                            <th>Status</th>
                            
                            <th class="text-center sticky-end bg-light">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td><strong>{{ $item->nota_kirim_promo }}</strong></td>
                                <td>{{ $item->branch_sender ?? '-' }}</td>
                                <td>{{ $item->send_date ? \Carbon\Carbon::parse($item->send_date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item->sales_code ?? '-' }}</td>
                                <td>{{ $item->deliver_by ?? '-' }}</td>
                                <td>{{ $item->approve_by ?? '-' }}</td>
                                <td>{{ $item->whouse_head ?? '-' }}</td>
                                <td>{{ $item->user_id ?? '-' }}</td>
                                <td>{{ $item->printed ?? '-' }}</td>
                                <td>{{ $item->info ?? '-' }}</td>
                                <td>{{ $item->status ?? '-' }}</td>
                                
                                <td class="text-center sticky-end bg-white">
                                    <a href="{{ route('delivery_promo.show', ['nota_kirim_promo' => $item->nota_kirim_promo]) }}"
                                        class="btn btn-sm btn-outline-primary" title="Detail">
                                        <i class="bi bi-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center py-4 text-muted">
                                    Belum ada data Nota Promosi.
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