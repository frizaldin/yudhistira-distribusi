<x-layouts>
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Daftar NTB</h5>
                <a href="{{ route('ntb.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Tambah NTB
                </a>
            </div>

            <form action="{{ route('ntb.index') }}" method="GET" class="mb-3">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select name="type" class="form-select form-select-sm" style="max-width: 200px;"
                        aria-label="Filter jenis NTB">
                        <option value="" {{ request('type') === null || request('type') === '' ? 'selected' : '' }}>Semua
                            jenis</option>
                        <option value="non_retur" {{ request('type') === 'non_retur' ? 'selected' : '' }}>Non retur
                        </option>
                        <option value="retur" {{ request('type') === 'retur' ? 'selected' : '' }}>Retur</option>
                    </select>
                    <div class="input-group input-group-sm w-auto">
                        <input type="text" name="search" class="form-control"
                            placeholder="Cari Surat Jalan / Receive Code..." value="{{ request('search') }}"
                            style="max-width: 300px;">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                        @if ((request()->filled('search') && request('search') != '') || request()->filled('type'))
                            <a href="{{ route('ntb.index') }}" class="btn btn-outline-danger" title="Reset filter">
                                <i class="bi bi-x"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Jenis</th>
                            <th>Kode Terima</th>
                            <th>Nomor Surat Jalan</th>
                            <th>Cabang Penerima</th>
                            <th>Cabang Pengirim</th>
                            <th>Tgl Terima</th>
                            <th>Info</th>
                            <th class="text-center" style="width: 150px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $i => $item)
                            <tr>
                                <td class="text-center">{{ $items->firstItem() + $i }}</td>
                                <td class="text-center">
                                    @if ((int) ($item->receive_type ?? 0) === 1)
                                        <span class="badge text-bg-secondary">Retur</span>
                                    @else
                                        <span class="badge text-bg-primary">Non retur</span>
                                    @endif
                                </td>
                                <td>{{ $item->receive_code }}</td>
                                <td>{{ $item->nota_kirim_cab }}</td>
                                <td>{{ $item->branch_code }}</td>
                                <td>{{ $item->branch_sender }}</td>
                                <td>{{ $item->retur_date ? $item->retur_date->format('d/m/Y') : '' }}</td>
                                <td>{{ $item->info }}</td>
                                <td class="text-center">
                                    <a href="{{ route('ntb.edit', $item->id) }}"
                                        class="btn btn-sm btn-outline-warning py-0 px-2" title="Edit">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <form action="{{ route('ntb.destroy', $item->id) }}" method="POST"
                                        class="d-inline-block"
                                        onsubmit="return confirm('Batalkan NTB {{ $item->nota_kirim_cab }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"
                                            title="Batalkan NTB">
                                            <i class="bi bi-x-circle"></i> Batalkan NTB
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-3 text-muted">Belum ada data NTB.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $items->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</x-layouts>
