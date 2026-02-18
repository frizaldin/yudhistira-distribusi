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

    <!-- Progress Indicator -->
    <div class="card mb-3" id="syncProgressCard" style="display: none;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div>
                    <strong>Sinkronisasi Data Produk</strong>
                    <small class="text-muted d-block" id="syncStatus">Memproses...</small>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary" id="syncPercentage">0%</span>
                </div>
            </div>
            <div class="progress" style="height: 25px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                    id="syncProgressBar" style="width: 0%">0%</div>
            </div>
            <div class="mt-2 small text-muted">
                <span id="syncDetails">Memproses 0 dari 0 data...</span>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Data Produk</strong><br />
                    <small class="text-muted">Buku paket, LKS, referensi </small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalImportCategorySerial">
                        <i class="bi bi-upload me-1"></i>Import Kategori & Serial
                    </button>
                    <div class="d-none">
                    <form action="{{ route('product.synchronize') }}" method="POST" class="d-inline"
                        id="synchronizeForm">
                        @csrf
                        <button type="button" class="btn btn-primary btn-sm rounded-pill" id="btnSynchronize">
                            <i class="bi bi-arrow-repeat me-1"></i>Synchronize
                        </button>
                    </form>

                    <form action="{{ route('product.clear-and-sync') }}" method="POST" class="d-inline"
                        id="clearAndSyncForm">
                        @csrf
                        <button type="button" class="btn btn-danger btn-sm rounded-pill" id="btnClearAndSync">
                            <i class="bi bi-trash me-1"></i>Clear All & Sync
                        </button>
                    </form>
                </div>
                </div>
            </div>

            <!-- Modal Import Kategori & Serial -->
            <div class="modal fade" id="modalImportCategorySerial" tabindex="-1" aria-labelledby="modalImportCategorySerialLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalImportCategorySerialLabel">Import Kategori & Serial</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="{{ route('product.import-category-serial') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="modal-body">
                                <p class="text-muted small">Upload file Excel identifikasi buku. Hanya kolom <strong>KODE</strong> (A), <strong>SERIAL</strong> (G), dan <strong>KATEGORI</strong> (H) yang dipakai untuk meng-update field <code>serial</code> dan <code>category_manual</code> pada produk yang sudah ada. Field lain tidak diubah.</p>
                                <div class="mb-3">
                                    <label for="fileCategorySerial" class="form-label">File Excel (xlsx / xls)</label>
                                    <input type="file" class="form-control" id="fileCategorySerial" name="file" accept=".xlsx,.xls" required />
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Upload & Proses di Background</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('product.index') }}">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" placeholder="Cari nama produk"
                        value="{{ request('search') }}" />
                </div>
                <div class="col-md-2">
                    <select class="form-select select2-static" name="jenis">
                        <option value="">Jenis Produk</option>
                        <option value="Buku Paket" {{ request('jenis') == 'Buku Paket' ? 'selected' : '' }}>Buku Paket
                        </option>
                        <option value="LKS" {{ request('jenis') == 'LKS' ? 'selected' : '' }}>LKS</option>
                        <option value="Referensi" {{ request('jenis') == 'Referensi' ? 'selected' : '' }}>Referensi
                        </option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select select2-static" name="jenjang">
                        <option value="">Jenjang</option>
                        <option value="SD" {{ request('jenjang') == 'SD' ? 'selected' : '' }}>SD</option>
                        <option value="SMP" {{ request('jenjang') == 'SMP' ? 'selected' : '' }}>SMP</option>
                        <option value="SMA" {{ request('jenjang') == 'SMA' ? 'selected' : '' }}>SMA</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select select2-static" name="status">
                        <option value="">Status</option>
                        <option value="Aktif" {{ request('status') == 'Aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="Nonaktif" {{ request('status') == 'Nonaktif' ? 'selected' : '' }}>Nonaktif
                        </option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('product.index') }}" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Produk</th>
                            <th>Jenis</th>
                            <th>Jenjang</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products ?? [] as $product)
                            <tr>
                                <td>{{ $product->book_code }}</td>
                                <td>{{ $product->book_title }}</td>
                                <td>{{ $product->category ?? '-' }}</td>
                                <td>{{ $product->jenjang ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('product.detail', ['book_code' => $product->book_code]) }}" class="btn btn-sm btn-outline-primary" title="Detail stok & SP per cabang">
                                        <i class="bi bi-eye me-1"></i>Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="text-muted mb-0">Belum ada data produk. Silakan lakukan synchronize dari
                                        PostgreSQL.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($products) && $products->hasPages())
                <div class="mt-3">
                    {{ $products->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <x-slot name="js">
        <script>
            $(document).ready(function() {
                let syncProgressInterval = null;

                // Check initial progress on page load and show if running
                checkSyncProgress();

                // Auto-poll every 2 seconds to check for running sync
                setInterval(function() {
                    checkSyncProgress();
                }, 2000);

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
                            // Show progress indicator
                            $('#syncProgressCard').show();
                            $('#syncProgressBar').css('width', '0%').text('0%');
                            $('#syncPercentage').text('0%');
                            $('#syncDetails').text('Memulai sinkronisasi...');
                            $('#syncStatus').text('Memproses...');
                            $('#syncProgressBar').removeClass('bg-success bg-danger').addClass(
                                'bg-primary progress-bar-animated');

                            // Submit form - polling will continue via setInterval
                            $('#synchronizeForm').submit();
                        }
                    });
                });

                $('#btnClearAndSync').on('click', function() {
                    Swal.fire({
                        title: 'Hapus Semua Data & Sinkronisasi',
                        html: '<p>Apakah Anda yakin ingin menghapus <strong>SEMUA</strong> data produk terlebih dahulu, kemudian melakukan sinkronisasi ulang?</p><p class="text-danger"><small>Tindakan ini tidak dapat dibatalkan!</small></p>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, hapus semua & sync',
                        cancelButtonText: 'Batal'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            // Show progress indicator
                            $('#syncProgressCard').show();
                            $('#syncProgressBar').css('width', '0%').text('0%');
                            $('#syncPercentage').text('0%');
                            $('#syncDetails').text(
                                'Menghapus data lama, kemudian memulai sinkronisasi...');
                            $('#syncStatus').text('Memproses...');
                            $('#syncProgressBar').removeClass('bg-success bg-danger').addClass(
                                'bg-primary progress-bar-animated');

                            // Submit form - polling will continue via setInterval
                            $('#clearAndSyncForm').submit();
                        }
                    });
                });


                function checkSyncProgress() {
                    $.ajax({
                        url: '{{ route('product.sync-progress') }}',
                        type: 'GET',
                        success: function(response) {
                            // Show and update card if status is running, completed, or failed
                            if (response.status === 'running') {
                                $('#syncProgressCard').show();

                                const percentage = response.percentage || 0;
                                $('#syncProgressBar').css('width', percentage + '%').text(percentage
                                    .toFixed(1) + '%');
                                $('#syncPercentage').text(percentage.toFixed(1) + '%');

                                const details =
                                    `Memproses ${response.processed} dari ${response.total} data (Created: ${response.created}, Updated: ${response.updated}, Errors: ${response.errors})`;
                                $('#syncDetails').text(details);
                                $('#syncStatus').text('Memproses...');
                                $('#syncProgressBar').removeClass('bg-success bg-danger').addClass(
                                    'bg-primary progress-bar-animated');

                            } else if (response.status === 'completed') {
                                $('#syncProgressCard').show();

                                const percentage = response.percentage || 0;
                                $('#syncProgressBar').css('width', percentage + '%').text(percentage
                                    .toFixed(1) + '%');
                                $('#syncPercentage').text(percentage.toFixed(1) + '%');

                                const details =
                                    `Memproses ${response.processed} dari ${response.total} data (Created: ${response.created}, Updated: ${response.updated}, Errors: ${response.errors})`;
                                $('#syncDetails').text(details);
                                $('#syncStatus').text('Selesai!');
                                $('#syncProgressBar').removeClass('bg-primary progress-bar-animated')
                                    .addClass('bg-success');

                                // Hide progress after 5 seconds
                                setTimeout(function() {
                                    $('#syncProgressCard').fadeOut();
                                }, 5000);

                            } else if (response.status === 'failed') {
                                $('#syncProgressCard').show();

                                const percentage = response.percentage || 0;
                                $('#syncProgressBar').css('width', percentage + '%').text(percentage
                                    .toFixed(1) + '%');
                                $('#syncPercentage').text(percentage.toFixed(1) + '%');

                                const details =
                                    `Memproses ${response.processed} dari ${response.total} data (Created: ${response.created}, Updated: ${response.updated}, Errors: ${response.errors})`;
                                $('#syncDetails').text(details);
                                $('#syncStatus').text('Gagal: ' + (response.error_message ||
                                    'Unknown error'));
                                $('#syncProgressBar').removeClass('bg-primary progress-bar-animated')
                                    .addClass('bg-danger');

                            } else if (response.status === 'idle') {
                                // Only hide if card is visible and status is idle (no sync running)
                                if ($('#syncProgressCard').is(':visible')) {
                                    // Don't hide immediately, wait a bit to make sure
                                }
                            }
                        },
                        error: function() {
                            console.error('Error checking sync progress');
                            // Keep showing progress card on error (might be network issue)
                        }
                    });
                }
            });
        </script>
    </x-slot>
</x-layouts>
