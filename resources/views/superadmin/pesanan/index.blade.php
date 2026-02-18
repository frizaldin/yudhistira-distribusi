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
                    <strong>Data Pesanan</strong><br />
                    <small class="text-muted">Data pesanan per cabang dan produk</small>
                </div>
                <div class="d-flex flex-wrap gap-2 d-none">
                    <button type="button" class="btn btn-primary btn-sm rounded-pill" id="btn-sync">
                        <i class="bi bi-arrow-repeat me-1"></i>Synchronize
                    </button>
                    <button type="button" class="btn btn-danger btn-sm rounded-pill" id="btn-clear-and-sync">
                        <i class="bi bi-trash me-1"></i>Clear All & Synchronize
                    </button>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('pesanan.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari kode cabang atau kode buku" value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="branch" placeholder="Kode Cabang"
                        value="{{ request('branch') }}" />
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('pesanan.index') }}" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>NO</th>
                            <th>Kode Cabang</th>
                            <th>Kode Buku</th>
                            <th>SP</th>
                            <th>Faktur</th>
                            <th>Ret</th>
                            <th>Rec Pusat</th>
                            <th>Rec Gudang</th>
                            <th>Stock</th>
                            <th>Stok Pusat</th>
                            <th>Sisa SP</th>
                            <th>Tanggal Transaksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders ?? [] as $index => $order)
                            <tr>
                                <td>{{ ($orders->currentPage() - 1) * $orders->perPage() + $index + 1 }}</td>
                                <td><code>{{ $order->branch_code ?? '-' }}</code></td>
                                <td><code>{{ $order->book_code ?? '-' }}</code></td>
                                <td>{{ number_format($order->ex_sp) }}</td>
                                <td>{{ number_format($order->ex_ftr) }}</td>
                                <td>{{ number_format($order->ex_ret) }}</td>
                                <td>{{ number_format($order->ex_rec_pst) }}</td>
                                <td>{{ number_format($order->ex_rec_gdg) }}</td>
                                <td>{{ number_format($order->ex_stock) }}</td>
                                <td>{{ number_format($order->stock_pusat ?? 0) }}</td>
                                <td><strong>{{ number_format($order->sisa_sp ?? 0) }}</strong></td>
                                <td>{{ $order->trans_date ? \Carbon\Carbon::parse($order->trans_date)->format('d/m/Y') : '-' }}
                                </td>
                                {{-- <td >
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                        data-bs-target="#pesananModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" data-action="delete-row">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td> --}}
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-4">
                                    <p class="text-muted mb-0">Belum ada data pesanan. Silakan sinkronisasi data dari
                                        staging.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($orders) && $orders->hasPages())
                <div class="mt-3">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Import -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Data Pesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('pesanan.import') }}" method="POST" enctype="multipart/form-data">
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
                                Format file: .xlsx atau .xls (maks 10MB). Pastikan struktur kolom sesuai: order_code,
                                book_code, book_title, stok, jual, ret, pesanan, nt, nk, ntb, nkb, stok_1, jual_1,
                                ret_1, pesanan_1, nt_1, nk_1, ntb_1, nkb_1, branch_code, branch_name
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <strong><i class="bi bi-info-circle me-2"></i>Petunjuk:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Pastikan file Excel memiliki header kolom sesuai struktur di atas</li>
                                <li><strong>Hanya data yang memiliki book_code akan diimport ke database</strong></li>
                                <li>Data dengan kombinasi book_code + branch_code yang sama akan diabaikan (tidak
                                    duplikat)</li>
                                <li>Baris yang berisi header atau separator akan otomatis diabaikan</li>
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
                // Synchronize button
                $('#btn-sync').on('click', function() {
                    Swal.fire({
                        title: 'Sinkronisasi Data',
                        text: 'Apakah Anda yakin ingin melakukan sinkronisasi data dari staging?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Ya, Sinkronisasi',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: '{{ route('pesanan.synchronize') }}',
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil!',
                                        text: 'Sinkronisasi data sedang diproses di background. Data akan disinkronkan secara bertahap. Silakan refresh halaman beberapa saat kemudian untuk melihat hasil.',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        location.reload();
                                    });
                                },
                                error: function(xhr) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: xhr.responseJSON?.message ||
                                            'Terjadi kesalahan saat sinkronisasi',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        }
                    });
                });

                // Clear All & Synchronize button
                $('#btn-clear-and-sync').on('click', function() {
                    Swal.fire({
                        title: 'Hapus Semua & Sinkronisasi',
                        text: 'PERINGATAN: Tindakan ini akan menghapus semua data pesanan yang ada, kemudian melakukan sinkronisasi dari staging. Apakah Anda yakin?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, Hapus & Sinkronisasi',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: '{{ route('pesanan.clear-and-sync') }}',
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil!',
                                        text: response.message ||
                                            'Semua data pesanan telah dihapus. Sinkronisasi data sedang diproses di background.',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        location.reload();
                                    });
                                },
                                error: function(xhr) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: xhr.responseJSON?.message ||
                                            'Terjadi kesalahan saat sinkronisasi',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        }
                    });
                });
            });
        </script>
    </x-slot>
</x-layouts>
