<x-layouts>
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-0"><strong>{{ $title }}</strong></h4>
            <p class="text-muted mb-0">Ringkasan data master, pesanan, dan rencana kirim</p>
        </div>
    </div>
    <style>
        .card-body h1,
        .card-body h2,
        .card-body h3,
        .card-body h4,
        .card-body h5,
        .card-body h6 {
            font-size: 1.5rem !important;
            font-weight: bolder !important;
        }

        .bg-light {
            --bs-bg-opacity: 1;
            background-color: rgb(237 29 36 / 6%) !important;
        }
    </style>
    <!-- Master Data Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><strong>Master Data</strong></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 justify-content-evenly">
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-journals" style="font-size: 2rem; color: #0d6efd;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalProduk, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Produk</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-building" style="font-size: 2rem; color: #198754;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalCabang, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Cabang</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-box-seam" style="font-size: 2rem; color: #f97316;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalStockPusat, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Stock Pusat</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-bullseye" style="font-size: 2rem; color: #dc3545;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalTarget, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Target</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-calendar" style="font-size: 2rem; color: #6f42c1;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalPeriode, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Periode</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pesanan Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><strong>Pesanan</strong></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 justify-content-evenly">
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-journal-text" style="font-size: 2rem; color: #0d6efd;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalPesanan, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Pesanan</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-cart-check" style="font-size: 2rem; color: #198754;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalSp, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total SP</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-receipt" style="font-size: 2rem; color: #f97316;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalFaktur, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Faktur</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-arrow-counterclockwise"
                                            style="font-size: 2rem; color: #dc3545;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalRet, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Retur</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-boxes" style="font-size: 2rem; color: #6f42c1;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalStockCabang, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Stock Cabang</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rencana Kirim Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><strong>Rencana Kirim (NPPB)</strong></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 col-lg-3">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-truck" style="font-size: 2rem; color: #0d6efd;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalNppb, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total NPPB</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-box-seam" style="font-size: 2rem; color: #f97316;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalNppbKoli, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Koli</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-bag" style="font-size: 2rem; color: #198754;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalNppbPls, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Eceran</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-book" style="font-size: 2rem; color: #6f42c1;"></i>
                                    </div>
                                    <h3 class="mb-1">{{ number_format($totalNppbExp, 0, ',', '.') }}</h3>
                                    <p class="text-muted mb-0 small">Total Eksemplar</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistik Tambahan Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><strong>Statistik Tambahan</strong></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0 me-3">
                                    <i class="bi bi-building-check" style="font-size: 1.5rem; color: #198754;"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">{{ number_format($totalBranchesWithTarget, 0, ',', '.') }}</h5>
                                    <small class="text-muted">Cabang dengan Target</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0 me-3">
                                    <i class="bi bi-building-check" style="font-size: 1.5rem; color: #0d6efd;"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">{{ number_format($totalBranchesWithPesanan, 0, ',', '.') }}
                                    </h5>
                                    <small class="text-muted">Cabang dengan Pesanan</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0 me-3">
                                    <i class="bi bi-building-check" style="font-size: 1.5rem; color: #f97316;"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">{{ number_format($totalBranchesWithNppb, 0, ',', '.') }}</h5>
                                    <small class="text-muted">Cabang dengan NPPB</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0 me-3">
                                    <i class="bi bi-journals" style="font-size: 1.5rem; color: #6f42c1;"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">{{ number_format($totalProductsWithTarget, 0, ',', '.') }}</h5>
                                    <small class="text-muted">Produk dengan Target</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                <div class="flex-shrink-0 me-3">
                                    <i class="bi bi-journals" style="font-size: 1.5rem; color: #dc3545;"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">{{ number_format($totalProductsWithStock, 0, ',', '.') }}</h5>
                                    <small class="text-muted">Produk dengan Stock</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ranking Cabang Section -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><strong>Ranking 10 Cabang dengan SP Terbanyak</strong></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">Rank</th>
                                    <th>Kode Cabang</th>
                                    <th>Nama Cabang</th>
                                    <th class="text-end">Total SP</th>
                                    <th class="text-end">Total Faktur</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topBranchesBySp ?? [] as $index => $branch)
                                    <tr>
                                        <td class="text-center">
                                            @if ($index == 0)
                                                <span class="badge bg-warning text-dark"
                                                    style="font-size: 0.875rem; padding: 0.4rem 0.6rem;">
                                                    <i class="bi bi-trophy-fill"></i> {{ $index + 1 }}
                                                </span>
                                            @elseif($index == 1)
                                                <span class="badge bg-secondary"
                                                    style="font-size: 0.875rem; padding: 0.4rem 0.6rem;">
                                                    <i class="bi bi-trophy-fill"></i> {{ $index + 1 }}
                                                </span>
                                            @elseif($index == 2)
                                                <span class="badge bg-danger"
                                                    style="font-size: 0.875rem; padding: 0.4rem 0.6rem;">
                                                    <i class="bi bi-trophy-fill"></i> {{ $index + 1 }}
                                                </span>
                                            @else
                                                <span class="badge bg-light text-dark"
                                                    style="font-size: 0.875rem; padding: 0.4rem 0.6rem;">
                                                    {{ $index + 1 }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $branch->branch_code ?? '-' }}</strong>
                                        </td>
                                        <td>{{ $branch->branch_name ?? '-' }}</td>
                                        <td class="text-end">
                                            <strong>{{ number_format($branch->total_sp ?? 0, 0, ',', '.') }}</strong>
                                        </td>
                                        <td class="text-end">
                                            {{ number_format($branch->total_faktur ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                            <p class="mb-0 mt-2">Belum ada data</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts>
