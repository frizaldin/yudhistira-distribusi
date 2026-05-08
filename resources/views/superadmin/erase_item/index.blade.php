<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Nota Penghapusan</strong><br />
                    <small class="text-muted">Daftar Nota Penghapusan</small>
                </div>
                <a href="{{ route('erase_item.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Tambah Data
                </a>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('erase_item.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari erase_code..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('erase_item.index') }}" class="btn btn-outline-secondary">
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
                            <th>Tgl Transaksi</th>
                            <th>Tgl Edit</th>
                            <th>Kode Karyawan</th>
                            <th>Kepala Gudang</th>
                            <th>Info</th>
                            
                            <th class="text-center sticky-end bg-light">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td><strong>{{ $item->erase_code }}</strong></td>
                                <td>{{ $item->branch_code ?? '-' }}</td>
                                <td>{{ $item->trans_date ? \Carbon\Carbon::parse($item->trans_date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item->edit_date ? \Carbon\Carbon::parse($item->edit_date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item->empl_code ?? '-' }}</td>
                                <td>{{ $item->whouse_head ?? '-' }}</td>
                                <td>{{ $item->info ?? '-' }}</td>
                                
                                <td class="text-center sticky-end bg-white">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="{{ route('erase_item.show', ['erase_code' => $item->erase_code]) }}"
                                            class="btn btn-sm btn-outline-primary" title="Detail">
                                            <i class="bi bi-eye"></i> Detail
                                        </a>
                                        <form action="{{ route('erase_item.destroy', ['erase_code' => $item->erase_code]) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center py-4 text-muted">
                                    Belum ada data Nota Penghapusan.
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