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
                    <strong>NKB Penyesuaian</strong><br />
                    <small class="text-muted">Daftar NKB untuk penyesuaian stok pusat</small>
                </div>
                <a href="{{ route('nkb_penyesuaian.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Tambah Data
                </a>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('nkb_penyesuaian.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari No. Nota, NPPB, SJ..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('nkb_penyesuaian.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. Nota Kirim</th>
                            <th>Tanggal</th>
                            <th>NPPB</th>
                            <th>SJ</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td><strong>{{ $item->nota_kirim_cab }}</strong></td>
                                <td>{{ $item->send_date ? \Carbon\Carbon::parse($item->send_date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item->nppb ?? '-' }}</td>
                                <td>{{ $item->sj ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('nkb_penyesuaian.show', ['nota_kirim_cab' => $item->nota_kirim_cab]) }}"
                                        class="btn btn-sm btn-outline-primary" title="Detail">
                                        <i class="bi bi-eye"></i> Detail
                                    </a>
                                    <form action="{{ route('nkb_penyesuaian.destroy', ['nota_kirim_cab' => $item->nota_kirim_cab]) }}" method="POST" class="d-inline form-delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete" title="Hapus">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Belum ada data NKB Penyesuaian.
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

    @push('js')
        <script>
            document.querySelectorAll('.btn-delete').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var form = this.closest('form');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Hapus NKB?',
                            text: 'Data NKB Penyesuaian ini akan dihapus permanen!',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Ya, hapus'
                        }).then(function(result) {
                            if (result.isConfirmed) form.submit();
                        });
                    } else if (confirm('Hapus NKB Penyesuaian ini?')) {
                        form.submit();
                    }
                });
            });
        </script>
    @endpush
</x-layouts>
