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
                    <strong>Master Data Periode</strong><br />
                    <small class="text-muted">Data periode per cabang</small>
                </div>
                <div class="d-flex flex-wrap gap-2 d-none">
                    <form action="{{ route('period.synchronize') }}" method="POST" class="d-inline"
                        id="synchronizeForm">
                        @csrf
                        <button type="button" class="btn btn-primary btn-sm rounded-pill" id="btnSynchronize">
                            <i class="bi bi-arrow-repeat me-1"></i>Synchronize
                        </button>
                    </form>

                    <form action="{{ route('period.clear-and-sync') }}" method="POST" class="d-inline"
                        id="clearAndSyncForm">
                        @csrf
                        <button type="button" class="btn btn-danger btn-sm rounded-pill" id="btnClearAndSync">
                            <i class="bi bi-trash me-1"></i>Clear All & Sync
                        </button>
                    </form>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('period.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari kode periode, nama, atau cabang" value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="branch_code" placeholder="Kode Cabang"
                        value="{{ request('branch_code') }}" />
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('period.index') }}" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Periode</th>
                            <th>Nama Periode</th>
                            <th>Dari Tanggal</th>
                            <th>Sampai Tanggal</th>
                            <th>Periode Sebelumnya</th>
                            <th>Status</th>
                            <th>Kode Cabang</th>
                            <th>Nama Cabang</th>
                            <th>Tanggal Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($periodes ?? [] as $periode)
                            <tr>
                                <td><code>{{ $periode->period_code }}</code></td>
                                <td>{{ $periode->period_name }}</td>
                                <td>{{ $periode->from_date ? \Carbon\Carbon::parse($periode->from_date)->format('d/m/Y') : '-' }}
                                </td>
                                <td>{{ $periode->to_date ? \Carbon\Carbon::parse($periode->to_date)->format('d/m/Y') : '-' }}
                                </td>
                                <td>{{ $periode->period_before ?? '-' }}</td>
                                <td>
                                    @if ($periode->status === 1 || $periode->status === true)
                                        <span class="badge bg-success">Aktif</span>
                                    @elseif($periode->status === 0 || $periode->status === false)
                                        <span class="badge bg-secondary">Tidak Aktif</span>
                                    @else
                                        <span class="badge bg-secondary">-</span>
                                    @endif
                                </td>
                                <td>{{ $periode->branch_code ?? '-' }}</td>
                                <td>{{ $periode->branch->branch_name ?? '-' }}</td>
                                <td>{{ $periode->tanggal_aktif ? \Carbon\Carbon::parse($periode->tanggal_aktif)->format('d/m/Y') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <p class="text-muted mb-0">Belum ada data periode.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($periodes) && $periodes->hasPages())
                <div class="mt-3">
                    {{ $periodes->links('pagination::bootstrap-5') }}
                </div>
            @endif
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
                        html: '<p>Apakah Anda yakin ingin menghapus <strong>SEMUA</strong> data periode terlebih dahulu, kemudian melakukan sinkronisasi ulang?</p><p class="text-danger"><small>Tindakan ini tidak dapat dibatalkan!</small></p>',
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
