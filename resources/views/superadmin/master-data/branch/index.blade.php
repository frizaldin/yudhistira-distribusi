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
                    <strong>Data Cabang</strong><br />
                    <small class="text-muted">Master data cabang dan gudang pusat</small>
                </div>
                <div class="d-flex flex-wrap gap-2 d-none">
                    <form action="{{ route('branch.synchronize') }}" method="POST" class="d-inline"
                        id="synchronizeForm">
                        @csrf
                        <button type="button" class="btn btn-primary btn-sm rounded-pill" id="btnSynchronize">
                            <i class="bi bi-arrow-repeat me-1"></i>Synchronize
                        </button>
                    </form>

                    <form action="{{ route('branch.clear-and-sync') }}" method="POST" class="d-inline"
                        id="clearAndSyncForm">
                        @csrf
                        <button type="button" class="btn btn-danger btn-sm rounded-pill" id="btnClearAndSync">
                            <i class="bi bi-trash me-1"></i>Clear All & Sync
                        </button>
                    </form>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('branch.index') }}">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="search" placeholder="Cari kode atau nama cabang"
                        value="{{ request('search') }}" />
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('branch.index') }}" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Cabang</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branches ?? [] as $branch)
                            <tr>
                                <td>{{ $branch->branch_code }}</td>
                                <td>{{ $branch->branch_name }}</td>
                                <td>
                                    <a href="{{ route('dashboard.branch-detail', $branch->branch_code) }}"
                                        class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                        <i class="bi bi-eye me-1"></i>Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-4">
                                    <p class="text-muted mb-0">Belum ada data cabang. Silakan lakukan synchronize dari
                                        PostgreSQL.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($branches) && $branches->hasPages())
                <div class="mt-3">
                    {{ $branches->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Import -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Data Cabang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('branch.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="file" class="form-label">Pilih File Excel</label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror"
                                id="file" name="file" accept=".xlsx,.xls" required>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Format file: .xlsx atau .xls (maks 10MB). Pastikan struktur kolom sesuai: NO, KODE, NAMA
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <strong><i class="bi bi-info-circle me-2"></i>Petunjuk:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Pastikan file Excel memiliki header kolom: NO, KODE, NAMA</li>
                                <li><strong>Hanya data yang memiliki KODE akan diimport ke database</strong></li>
                                <li>Baris yang berisi "AREA" atau header lainnya akan otomatis diabaikan</li>
                                <li>Import akan diproses di background per 100 data untuk menghindari timeout</li>
                                <li>Untuk file besar, proses import mungkin memakan waktu beberapa menit</li>
                            </ul>
                        </div>


                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Import Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script>
            $(document).ready(function() {
                $('#btnSynchronize').on('click', function() {
                    Swal.fire({
                        title: 'Sinkronisasi Data',
                        text: 'Apakah Anda yakin ingin melakukan sinkronisasi data dari Staging?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Ya, synchronize',
                        cancelButtonText: 'Batal'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            $('#synchronizeForm').submit();
                        }
                    });
                });

                $('#btnClearAndSync').on('click', function() {
                    Swal.fire({
                        title: 'Hapus Semua Data & Sinkronisasi',
                        html: '<p>Apakah Anda yakin ingin menghapus <strong>SEMUA</strong> data cabang terlebih dahulu, kemudian melakukan sinkronisasi ulang?</p><p class="text-danger"><small>Tindakan ini tidak dapat dibatalkan!</small></p>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, hapus semua & sync',
                        cancelButtonText: 'Batal'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            $('#clearAndSyncForm').submit();
                        }
                    });
                });
            });
        </script>
    </x-slot>
</x-layouts>
