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
                    <strong>Surat Jalan</strong><br />
                    <small class="text-muted">Daftar Surat Jalan</small>
                </div>
                <a href="{{ route('delivery-orders.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Tambah Surat Jalan
                </a>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('delivery-orders.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 20) }}" />
                <div class="col-md-5">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari nomor, pengirim, tujuan, ekspedisi, supir..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('delivery-orders.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
                <div class="col-md-3 text-end">
                    <select name="per_page" class="form-select form-select-sm" style="width:auto;display:inline-block"
                        onchange="this.form.submit()">
                        <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20 per halaman</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 per halaman</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 per halaman</option>
                    </select>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. Surat Jalan</th>
                            <th>Pengirim</th>
                            <th>Tujuan</th>
                            <th>Tanggal</th>
                            <th>Expedisi</th>
                            <th>Supir</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items ?? [] as $item)
                            <tr>
                                <td><code>{{ $item->number }}</code></td>
                                <td>{{ $item->senderBranch->branch_name ?? $item->sender_code }}</td>
                                <td>{{ $item->recipientBranch->branch_name ?? $item->recipient_code }}</td>
                                <td>{{ $item->date ? $item->date->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item->expedition ?? '-' }}</td>
                                <td>{{ $item->driver ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('delivery-orders.show', $item->id) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>Detail
                                    </a>
                                    <a href="{{ route('delivery-orders.edit', $item->id) }}"
                                        class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                    <form action="{{ route('delivery-orders.destroy', $item->id) }}" method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Yakin hapus Surat Jalan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash me-1"></i>Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Belum ada data Surat Jalan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(isset($items) && $items->hasPages())
                <div class="mt-3">
                    {{ $items->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</x-layouts>
