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
                    <strong>Data Stok Pusat</strong><br />
                    <small class="text-muted">Stok pusat per cabang dan produk</small>
                </div>
                <div class="d-flex flex-wrap gap-2 d-none">
                    <form action="{{ route('central-stock.synchronize') }}" method="POST" class="d-inline"
                        id="synchronizeForm">
                        @csrf
                        <button type="button" class="btn btn-primary btn-sm rounded-pill" id="btnSynchronize">
                            <i class="bi bi-arrow-repeat me-1"></i>Synchronize
                        </button>
                    </form>

                    <form action="{{ route('central-stock.clear-and-sync') }}" method="POST" class="d-inline"
                        id="clearAndSyncForm">
                        @csrf
                        <button type="button" class="btn btn-danger btn-sm rounded-pill" id="btnClearAndSync">
                            <i class="bi bi-trash me-1"></i>Clear All & Sync
                        </button>
                    </form>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('central-stock.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari kode buku, judul, atau kode cabang" value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="branch" placeholder="Kode Cabang"
                        value="{{ request('branch') }}" />
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('central-stock.index') }}" class="btn btn-outline-secondary"
                        style="height: 38px;">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Cabang</th>
                            <th>Nama Cabang</th>
                            <th>Kode Buku / Judul Buku</th>
                            <th>Exemplar</th>
                            {{-- <th class="text-end">Aksi</th> --}}
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stocks ?? [] as $stock)
                            <tr>
                                <td>{{ $stock->branch_code }}</td>
                                <td>{{ $stock->branch->branch_name ?? '-' }}</td>
                                <td>
                                    <code>{{ $stock->book_code }}</code>
                                    @if ($stock->product && $stock->product->book_title)
                                        <br>
                                        <small
                                            class="text-muted">{{ Str::limit($stock->product->book_title, 50) }}</small>
                                    @endif
                                </td>
                                <td>{{ number_format($stock->exemplar ?? 0, 0, ',', '.') }}</td>
                                {{-- <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                        data-bs-target="#stockModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" data-action="delete-row">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td> --}}
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <p class="text-muted mb-0">Belum ada data stok pusat. Silakan lakukan synchronize
                                        dari
                                        PostgreSQL.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($stocks) && $stocks->hasPages())
                <div class="mt-3">
                    {{ $stocks->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Import -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Data Stok Pusat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('central-stock.import') }}" method="POST" enctype="multipart/form-data">
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
                                Format file: .xlsx atau .xls (maks 10MB). Pastikan struktur kolom sesuai: branch_code,
                                book_code, koli_besar, eks_besar, total_besar, koli_kecil, eks_kecil, total_kecil,
                                judulbuku, brach_name
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <strong><i class="bi bi-info-circle me-2"></i>Petunjuk:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Pastikan file Excel memiliki header kolom: branch_code, book_code, koli_besar,
                                    eks_besar, total_besar, koli_kecil, eks_kecil, total_kecil, judulbuku, brach_name
                                </li>
                                <li><strong>Hanya data yang memiliki branch_code dan book_code akan diimport ke
                                        database</strong></li>
                                <li>Baris yang berisi header akan otomatis diabaikan</li>
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
            let syncPollingInterval = null;

            function checkSyncProgress() {
                $.ajax({
                    url: '{{ route('central-stock.sync-progress') }}',
                    method: 'GET',
                    success: function(response) {
                        if (response.status === 'completed') {
                            // Stop polling
                            if (syncPollingInterval) {
                                clearInterval(syncPollingInterval);
                                syncPollingInterval = null;
                            }

                            // Close loading modal if open
                            Swal.close();

                            // Show success alert
                            Swal.fire({
                                icon: 'success',
                                title: 'Sinkronisasi Selesai!',
                                html: `
                                    <p>Sinkronisasi data stok pusat telah selesai.</p>
                                    <div class="text-start mt-3">
                                        <small>
                                            <strong>Detail:</strong><br>
                                            Total: ${response.total || 0} records<br>
                                            Diproses: ${response.processed || 0} records<br>
                                            Dibuat: ${response.created || 0} records<br>
                                            Diupdate: ${response.updated || 0} records<br>
                                            ${response.koli_created ? 'Koli Dibuat: ' + response.koli_created + ' records<br>' : ''}
                                            ${response.koli_updated ? 'Koli Diupdate: ' + response.koli_updated + ' records<br>' : ''}
                                            ${response.errors > 0 ? '<span class="text-danger">Error: ' + response.errors + '</span>' : ''}
                                        </small>
                                    </div>
                                `,
                                confirmButtonText: 'OK',
                                allowOutsideClick: false
                            }).then(function() {
                                // Clear cache progress and reload
                                $.ajax({
                                    url: '{{ route('central-stock.sync-progress') }}?clear=1',
                                    method: 'GET',
                                    complete: function() {
                                        window.location.href =
                                            '{{ route('central-stock.index') }}';
                                    }
                                });
                            });
                        } else if (response.status === 'failed') {
                            // Stop polling
                            if (syncPollingInterval) {
                                clearInterval(syncPollingInterval);
                                syncPollingInterval = null;
                            }

                            // Close loading modal if open
                            Swal.close();

                            // Show error alert
                            Swal.fire({
                                icon: 'error',
                                title: 'Sinkronisasi Gagal!',
                                html: `
                                    <p>Terjadi kesalahan saat sinkronisasi data stok pusat.</p>
                                    ${response.error_message ? '<p class="text-danger"><small>' + response.error_message + '</small></p>' : ''}
                                `,
                                confirmButtonText: 'OK'
                            }).then(function() {
                                // Clear cache progress and session storage, then reload
                                $.ajax({
                                    url: '{{ route('central-stock.sync-progress') }}?clear=1',
                                    method: 'GET',
                                    complete: function() {
                                        sessionStorage.removeItem(alertShownKey);
                                        window.location.href =
                                            '{{ route('central-stock.index') }}';
                                    }
                                });
                            });
                        }
                        // If status is 'running' or 'idle', continue polling
                    },
                    error: function(xhr, status, error) {
                        console.error('Error checking sync progress:', error);
                    }
                });
            }

            function startSyncPolling() {
                // Clear any existing interval
                if (syncPollingInterval) {
                    clearInterval(syncPollingInterval);
                }

                // Start polling every 3 seconds
                syncPollingInterval = setInterval(checkSyncProgress, 3000);

                // Also check immediately
                checkSyncProgress();
            }

            $(document).ready(function() {
                // Check if alert already shown for this page load (prevent duplicate alerts on refresh)
                const alertShownKey = 'central_stock_alert_shown';
                const alertAlreadyShown = sessionStorage.getItem(alertShownKey);

                // Only check sync if there's a session success message AND alert wasn't shown for this page load
                @if (session('success') &&
                        (str_contains(session('success'), 'Sinkronisasi') || str_contains(session('success'), 'sinkronisasi')))
                    // If alert already shown for this page load, skip to prevent loop
                    if (alertAlreadyShown === 'true') {
                        // Alert already shown, clear session storage and reload
                        sessionStorage.removeItem(alertShownKey);
                        window.location.href = '{{ route('central-stock.index') }}';
                        return;
                    }

                    // Check sync status first
                    $.ajax({
                        url: '{{ route('central-stock.sync-progress') }}',
                        method: 'GET',
                        success: function(response) {

                            if (response.status === 'completed') {
                                // Mark alert as shown for this page load
                                sessionStorage.setItem(alertShownKey, 'true');

                                // Sync already completed, show alert once and stop
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sinkronisasi Selesai!',
                                    html: `
                                        <p>Sinkronisasi data stok pusat telah selesai.</p>
                                        <div class="text-start mt-3">
                                            <small>
                                                <strong>Detail:</strong><br>
                                                Total: ${response.total || 0} records<br>
                                                Diproses: ${response.processed || 0} records<br>
                                                Dibuat: ${response.created || 0} records<br>
                                                Diupdate: ${response.updated || 0} records<br>
                                                ${response.koli_created ? 'Koli Dibuat: ' + response.koli_created + ' records<br>' : ''}
                                                ${response.koli_updated ? 'Koli Diupdate: ' + response.koli_updated + ' records<br>' : ''}
                                                ${response.errors > 0 ? '<span class="text-danger">Error: ' + response.errors + '</span>' : ''}
                                            </small>
                                        </div>
                                    `,
                                    confirmButtonText: 'OK',
                                    allowOutsideClick: false
                                }).then(function() {
                                    // Clear cache progress and session storage, then reload
                                    $.ajax({
                                        url: '{{ route('central-stock.sync-progress') }}?clear=1',
                                        method: 'GET',
                                        complete: function() {
                                            sessionStorage.removeItem(alertShownKey);
                                            window.location.href =
                                                '{{ route('central-stock.index') }}';
                                        }
                                    });
                                });
                            } else if (response.status === 'failed') {
                                // Mark alert as shown for this page load
                                sessionStorage.setItem(alertShownKey, 'true');

                                // Sync failed, show alert once
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Sinkronisasi Gagal!',
                                    html: `
                                        <p>Terjadi kesalahan saat sinkronisasi data stok pusat.</p>
                                        ${response.error_message ? '<p class="text-danger"><small>' + response.error_message + '</small></p>' : ''}
                                    `,
                                    confirmButtonText: 'OK'
                                }).then(function() {
                                    // Clear cache progress and session storage, then reload
                                    $.ajax({
                                        url: '{{ route('central-stock.sync-progress') }}?clear=1',
                                        method: 'GET',
                                        complete: function() {
                                            sessionStorage.removeItem(alertShownKey);
                                            window.location.href =
                                                '{{ route('central-stock.index') }}';
                                        }
                                    });
                                });
                            } else if (response.status === 'running') {
                                // Sync still running, show loading and start polling
                                Swal.fire({
                                    title: 'Memproses...',
                                    text: '{{ session('success') }}',
                                    icon: 'info',
                                    allowOutsideClick: false,
                                    showConfirmButton: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
                                // Start polling
                                startSyncPolling();
                            } else {
                                // Status idle or unknown, clear cache and session storage, then reload
                                $.ajax({
                                    url: '{{ route('central-stock.sync-progress') }}?clear=1',
                                    method: 'GET',
                                    complete: function() {
                                        sessionStorage.removeItem(alertShownKey);
                                        window.location.href =
                                            '{{ route('central-stock.index') }}';
                                    }
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error checking sync progress:', error);
                        }
                    });
                @endif

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
                            // Submit form (will redirect, polling will start after redirect)
                            $('#synchronizeForm').submit();
                        }
                    });
                });

                $('#btnClearAndSync').on('click', function() {
                    Swal.fire({
                        title: 'Hapus Semua Data & Sinkronisasi',
                        html: '<p>Apakah Anda yakin ingin menghapus <strong>SEMUA</strong> data stok pusat terlebih dahulu, kemudian melakukan sinkronisasi ulang?</p><p class="text-danger"><small>Tindakan ini tidak dapat dibatalkan!</small></p>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, hapus semua & sync',
                        cancelButtonText: 'Batal'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            // Submit form (will redirect, polling will start after redirect)
                            $('#clearAndSyncForm').submit();
                        }
                    });
                });
            });
        </script>
    </x-slot>
</x-layouts>
