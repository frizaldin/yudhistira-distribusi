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
                    <strong>Master Data Target</strong><br />
                    <small class="text-muted">Data target penjualan per cabang dan tahun</small>
                </div>
                <div class="d-flex flex-wrap gap-2 d-none">
                    <form action="{{ route('target.synchronize') }}" method="POST" class="d-inline"
                        id="synchronizeForm">
                        @csrf
                        <button type="button" class="btn btn-primary btn-sm rounded-pill" id="btnSynchronize">
                            <i class="bi bi-arrow-repeat me-1"></i>Synchronize
                        </button>
                    </form>

                    <form action="{{ route('target.clear-and-sync') }}" method="POST" class="d-inline"
                        id="clearAndSyncForm">
                        @csrf
                        <button type="button" class="btn btn-danger btn-sm rounded-pill" id="btnClearAndSync">
                            <i class="bi bi-trash me-1"></i>Clear All & Sync
                        </button>
                    </form>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('target.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari kode cabang, buku, atau period" value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="branch_code" placeholder="Kode Cabang"
                        value="{{ request('branch_code') }}" />
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('target.index') }}" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">NO</th>
                            <th>Kode Cabang</th>
                            <th>Nama Cabang</th>
                            <th>Kode Buku</th>
                            <th>Judul Buku</th>
                            <th>Period Code</th>
                            <th class="text-end">Exemplar</th>
                            {{-- <th style="width: 100px;">Aksi</th> --}}
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($targets as $index => $target)
                            <tr>
                                <td>{{ $targets->firstItem() + $index }}</td>
                                <td><strong>{{ $target->branch_code }}</strong></td>
                                <td>{{ $target->branch->branch_name ?? '-' }}</td>
                                <td><code>{{ $target->book_code }}</code></td>
                                <td>{{ Str::limit($target->product->book_title ?? '-', 50) }}</td>
                                <td>{{ $target->period_code }}</td>
                                <td class="text-end">{{ number_format($target->exemplar ?? 0, 0, ',', '.') }}</td>
                                {{-- <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="if(confirm('Yakin ingin menghapus data ini?')) { document.getElementById('delete-form-{{ $target->id }}').submit(); }">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <form id="delete-form-{{ $target->id }}"
                                            action="{{ route('target.destroy', $target->id) }}" method="POST"
                                            style="display: none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </div>
                                </td> --}}
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Belum ada data target
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $targets->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <!-- Modal Import -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Data Target</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('target.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="file" class="form-label">Pilih File Excel</label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror"
                                id="file" name="file" accept=".xlsx,.xls" required>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Format file: .xlsx atau .xls (maks 100MB). Pastikan file memiliki header "CAB.
                                [NAMA_CABANG]" di baris 1,
                                kolom KODE di kolom A, JUDUL BUKU di kolom B, dan TARGET di kolom E.
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <strong><i class="bi bi-info-circle me-2"></i>Petunjuk:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Pastikan file Excel memiliki header <strong>"CAB. [NAMA_CABANG]"</strong> di baris 1
                                    (misalnya: "CAB. JOGJA")</li>
                                <li>Kolom <strong>A = KODE</strong> (kode buku, seperti F1311111)</li>
                                <li>Kolom <strong>B = JUDUL BUKU</strong> (nama buku)</li>
                                <li>Kolom <strong>E = TARGET</strong> (nilai target)</li>
                                <li><strong>Nama cabang di header harus terdaftar di tabel Branch</strong></li>
                                <li>Data dengan nama cabang yang tidak ada di tabel Branch akan diabaikan</li>
                                <li>Jika data dengan BRANCH_CODE, BOOK_CODE, dan YEAR yang sama sudah ada, akan
                                    di-update</li>
                                <li>Import akan diproses di background per 100 data untuk menghindari timeout</li>
                                <li>Untuk file besar (10,000+ data), proses import mungkin memakan waktu beberapa menit
                                </li>
                            </ul>
                        </div>


                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Import</button>
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
                        html: '<p>Apakah Anda yakin ingin menghapus <strong>SEMUA</strong> data target terlebih dahulu, kemudian melakukan sinkronisasi ulang?</p><p class="text-danger"><small>Tindakan ini tidak dapat dibatalkan!</small></p>',
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
