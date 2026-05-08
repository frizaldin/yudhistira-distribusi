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
                    <strong>NTB Retur</strong><br />
                    <small class="text-muted">Daftar Nota Terima Buku (Retur)</small>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('ntb_retur.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari No. NTB, No. NKB..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('ntb_retur.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. Nota Terima</th>
                            <th>No. Nota Kirim</th>
                            <th>Tanggal Terima</th>
                            <th>Cabang Tujuan</th>
                            <th>Cabang Pengirim</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td><strong>{{ $item->receive_code }}</strong></td>
                                <td>{{ $item->nota_kirim_cab ?? '-' }}</td>
                                <td>{{ $item->retur_date ? \Carbon\Carbon::parse($item->retur_date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item->branch_code ?? '-' }}</td>
                                <td>{{ $item->branch_sender ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('ntb_retur.show', ['receive_code' => $item->receive_code]) }}"
                                        class="btn btn-sm btn-outline-primary" title="Detail">
                                        <i class="bi bi-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Belum ada data NTB Retur.
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
</x-layouts>
