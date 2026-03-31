<x-layouts>
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Mutasi</strong><br />
                    <small class="text-muted">Penambahan eksemplar ke stock pusat</small>
                </div>
                <a href="{{ route('mutasi.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Tambah Mutasi
                </a>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('mutasi.index') }}">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari kode buku, PT produksi, no. SJ, no. JO..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('mutasi.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Kode buku</th>
                            <th class="text-end">Koli</th>
                            <th class="text-end">Isi koli</th>
                            <th class="text-end">Eceran</th>
                            <th class="text-end">Total eksemplar</th>
                            <th>Nama PT produksi</th>
                            <th>Nomor SJ</th>
                            <th>No. JO</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items ?? [] as $row)
                            <tr>
                                <td>{{ $row->tanggal_penerimaan ? $row->tanggal_penerimaan->format('d/m/Y') : '-' }}</td>
                                <td><code>{{ $row->book_code }}</code></td>
                                <td class="text-end">{{ number_format($row->koli) }}</td>
                                <td class="text-end">{{ number_format($row->isi_koli) }}</td>
                                <td class="text-end">{{ number_format($row->eceran) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($row->total_eksemplar) }}</td>
                                <td>{{ $row->nama_pt_produksi ?? '-' }}</td>
                                <td>{{ $row->nomor_surat_jalan ?? '-' }}</td>
                                <td>{{ $row->nomor_jo ?? '-' }}</td>
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('mutasi.edit', $row) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                    <form method="POST" action="{{ route('mutasi.destroy', $row) }}"
                                        class="d-inline form-hapus-mutasi"
                                        data-book="{{ $row->book_code }}"
                                        data-total="{{ number_format($row->total_eksemplar) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-1 btn-hapus-mutasi">
                                            <i class="bi bi-trash me-1"></i>Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">Belum ada data mutasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($items) && $items->hasPages())
                <div class="mt-3">{{ $items->links() }}</div>
            @endif
        </div>
    </div>

    @push('js')
        <script>
            document.querySelectorAll('.btn-hapus-mutasi').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var form = this.closest('form');
                    var book = form.dataset.book || '';
                    var total = form.dataset.total || '';
                    var msg = 'Hapus mutasi untuk buku ' + book + ' (total ' + total + ' eksemplar)? Stock pusat akan berkurang sesuai jumlah ini.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Hapus mutasi?',
                            text: msg,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Ya, hapus',
                            cancelButtonText: 'Batal'
                        }).then(function(result) {
                            if (result.isConfirmed) {
                                form.submit();
                            }
                        });
                    } else if (confirm(msg)) {
                        form.submit();
                    }
                });
            });
        </script>
    @endpush
</x-layouts>
