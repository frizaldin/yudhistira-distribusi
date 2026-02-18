<x-layouts>
    <!-- Filter Tahun -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>Dashboard Cabang</strong><br />
                    <small class="text-muted">{{ $branchInfo->branch_name ?? 'Cabang' }}
                        ({{ $branchInfo->branch_code ?? '' }})</small>
                </div>
                <form method="GET" action="{{ route('dashboard') }}" class="d-flex gap-2 align-items-center">
                    <label for="year" class="form-label mb-0">Filter Tahun:</label>
                    <select name="year" id="year" class="form-select form-select-sm select2-static"
                        style="width: auto;" onchange="this.form.submit()">
                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}" {{ ($year ?? date('Y')) == $y ? 'selected' : '' }}>
                                {{ $y }}
                            </option>
                        @endfor
                    </select>
                </form>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card card-kpi">
                <div class="card-body">
                    <h6>Total Pesanan (SP)</h6>
                    <div class="d-flex justify-content-between align-items-end mt-2">
                        <span class="kpi-value">{{ number_format($totalSp ?? 0, 0, ',', '.') }}</span>
                        <span class="badge bg-primary-subtle text-primary-emphasis kpi-badge">
                            <i class="bi bi-journal-text me-1"></i>SP
                        </span>
                    </div>
                    <small class="text-muted">Surat Permintaan</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-kpi">
                <div class="card-body">
                    <h6>Total Faktur</h6>
                    <div class="d-flex justify-content-between align-items-end mt-2">
                        <span class="kpi-value">{{ number_format($totalFaktur ?? 0, 0, ',', '.') }}</span>
                        <span class="badge bg-success-subtle text-success-emphasis kpi-badge">
                            <i class="bi bi-receipt me-1"></i>Faktur
                        </span>
                    </div>
                    <small class="text-muted">Penjualan</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-kpi">
                <div class="card-body">
                    <h6>Target Tercapai</h6>
                    <div class="d-flex justify-content-between align-items-end mt-2">
                        <span class="kpi-value">{{ number_format($achievementPercent ?? 0, 1) }}%</span>
                        <span
                            class="badge {{ ($achievementPercent ?? 0) >= 100 ? 'bg-success' : 'bg-warning' }}-subtle text-{{ ($achievementPercent ?? 0) >= 100 ? 'success' : 'warning' }}-emphasis kpi-badge">
                            <i
                                class="bi bi-{{ ($achievementPercent ?? 0) >= 100 ? 'check-circle' : 'graph-up-arrow' }} me-1"></i>Target
                        </span>
                    </div>
                    <small class="text-muted">Dari {{ number_format($totalTarget ?? 0, 0, ',', '.') }} Target</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-kpi">
                <div class="card-body">
                    <h6>Stock Cabang</h6>
                    <div class="d-flex justify-content-between align-items-end mt-2">
                        <span class="kpi-value">{{ number_format($totalStokCabang ?? 0, 0, ',', '.') }}</span>
                        <span class="badge bg-info-subtle text-info-emphasis kpi-badge">
                            <i class="bi bi-box-seam me-1"></i>Stok
                        </span>
                    </div>
                    <small class="text-muted">Total Stok</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Realisasi & Additional Stats -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Realisasi 2024</h6>
                    <h4 class="mb-0">{{ number_format($realisasi2024 ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Realisasi 2025</h6>
                    <h4 class="mb-0">{{ number_format($realisasi2025 ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Sisa SP</h6>
                    <h4 class="mb-0">{{ number_format($totalSisaSp ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">NKB Dari Pusat</h6>
                    <h4 class="mb-0">{{ number_format($totalNkb ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Rencana NPPB PUSAT CIAWI -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <strong><i class="bi bi-truck me-2"></i>Rencana NPPB Pusat Ciawi (Tahun
                        {{ $year ?? date('Y') }})</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-light rounded">
                                <h5 class="text-danger mb-2"><i class="bi bi-box-seam"></i> KOLI</h5>
                                <h3 class="mb-0">{{ number_format($totalNppbKoli ?? 0, 0, ',', '.') }}</h3>
                                <small class="text-muted">Total Koli</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-light rounded">
                                <h5 class="text-danger mb-2"><i class="bi bi-box"></i> PLS</h5>
                                <h3 class="mb-0">{{ number_format($totalNppbPls ?? 0, 0, ',', '.') }}</h3>
                                <small class="text-muted">Total PLS</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-light rounded">
                                <h5 class="text-danger mb-2"><i class="bi bi-box-arrow-up"></i> EXP</h5>
                                <h3 class="mb-0">{{ number_format($totalNppbExp ?? 0, 0, ',', '.') }}</h3>
                                <small class="text-muted">Total EXP</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Tables -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Tren Penjualan Cabang</strong><br />
                        <small class="text-muted">Bulanan</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="branchSalesChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Top 5 Produk Berdasarkan Faktur</strong>
                    <a href="{{ route('recap.index') }}" class="small text-decoration-none">Lihat detail rekap</a>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Kode Buku</th>
                                <th>Judul Buku</th>
                                <th class="text-end">Faktur</th>
                                <th class="text-end">Target</th>
                                <th class="text-end">Achievement</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topProducts ?? [] as $index => $product)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $product->book_code }}</strong></td>
                                    <td>{{ Str::limit($product->book_title ?? '', 40) }}</td>
                                    <td class="text-end">{{ number_format($product->total_faktur ?? 0, 0, ',', '.') }}
                                    </td>
                                    <td class="text-end">{{ number_format($product->target ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        <span
                                            class="badge {{ ($product->achievement ?? 0) >= 100 ? 'bg-success' : (($product->achievement ?? 0) >= 50 ? 'bg-warning' : 'bg-danger') }}">
                                            {{ number_format($product->achievement ?? 0, 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Belum ada data
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Top Produk Cabang</strong>
                    <a href="{{ route('recap.index') }}" class="small text-decoration-none">Detail</a>
                </div>
                <div class="card-body small">
                    @forelse($topProducts ?? [] as $index => $product)
                        <div class="{{ !$loop->last ? 'mb-3' : '' }}">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-truncate" style="max-width: 70%;"
                                    title="{{ $product->book_title ?? $product->book_code }}">
                                    {{ Str::limit($product->book_title ?? $product->book_code, 25) }}
                                </span>
                                <span>{{ number_format($product->achievement ?? 0, 1) }}% target</span>
                            </div>
                            <div class="progress" style="height: 6px">
                                @php
                                    $achievement = min($product->achievement ?? 0, 100);
                                    $progressColor =
                                        $achievement >= 80
                                            ? 'bg-success'
                                            : ($achievement >= 50
                                                ? 'bg-info'
                                                : 'bg-warning');
                                @endphp
                                <div class="progress-bar {{ $progressColor }}" style="width: {{ $achievement }}%">
                                </div>
                            </div>
                            <small class="text-muted">Faktur:
                                {{ number_format($product->total_faktur ?? 0, 0, ',', '.') }}</small>
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                            Belum ada data produk
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Ranking Segmen Buku</strong>
                    <a href="{{ route('recap.index') }}" class="small text-decoration-none">Detail</a>
                </div>
                <div class="card-body small">
                    @php
                        $badgeColors = ['bg-success', 'bg-primary', 'bg-info', 'bg-warning', 'bg-secondary'];
                        $maxFaktur = !empty($segmentRanking) ? max(array_values($segmentRanking)) : 1;
                        $segmentArray = !empty($segmentRanking) ? array_slice($segmentRanking, 0, 5, true) : [];
                    @endphp
                    @forelse($segmentArray as $index => $totalFakturSegmen)
                        @php
                            $segment = $index; // Key adalah segment name
                            $percentage = $maxFaktur > 0 ? round(($totalFakturSegmen / $maxFaktur) * 100) : 0;
                        @endphp
                        <div class="{{ $loop->index < 4 ? 'mb-3' : '' }}">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span
                                        class="badge {{ $badgeColors[$loop->index] ?? 'bg-secondary' }}-subtle text-{{ $badgeColors[$loop->index] ?? 'secondary' }}-emphasis"
                                        style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 0.7rem;">
                                        {{ $loop->iteration }}
                                    </span>
                                    <span><strong>{{ $segment ?? 'Lainnya' }}</strong></span>
                                </div>
                                <span
                                    class="text-{{ $badgeColors[$loop->index] ?? 'secondary' }}">{{ number_format($totalFakturSegmen, 0, ',', '.') }}</span>
                            </div>
                            <div class="progress" style="height: 6px">
                                <div class="progress-bar {{ $badgeColors[$loop->index] ?? 'bg-secondary' }}"
                                    style="width: {{ $percentage }}%"></div>
                            </div>
                            <small class="text-muted">Total Faktur</small>
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                            Belum ada data segmen
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <x-slot name="js">
        @php
            $chartLabels = $monthlySalesLabels ?? [
                'Jan',
                'Feb',
                'Mar',
                'Apr',
                'Mei',
                'Jun',
                'Jul',
                'Agu',
                'Sep',
                'Okt',
                'Nov',
                'Des',
            ];
            $chartData = $monthlySalesData ?? [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        @endphp
        <script>
            // Initialize chart dengan data real dari database
            (function() {
                function initChart() {
                    if (typeof Chart === "undefined") {
                        setTimeout(initChart, 100);
                        return;
                    }

                    const chartElement = document.getElementById("branchSalesChart");
                    if (!chartElement) {
                        return;
                    }

                    // Destroy existing chart if any
                    const existingChart = Chart.getChart(chartElement);
                    if (existingChart) {
                        existingChart.destroy();
                    }

                    // Data dari database
                    const chartLabels = @json($chartLabels);
                    const chartData = @json($chartData);

                    // Create new chart dengan data real
                    new Chart(chartElement, {
                        type: "bar",
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: "Penjualan Cabang",
                                data: chartData,
                                borderWidth: 2,
                                borderRadius: 8,
                                backgroundColor: "rgba(79, 70, 229, 0.18)",
                                borderColor: "rgba(79, 70, 229, 1)",
                            }, ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    enabled: true,
                                    callbacks: {
                                        label: function(context) {
                                            return 'Penjualan: ' + context.parsed.y.toLocaleString('id-ID');
                                        }
                                    }
                                },
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    grid: {
                                        color: "rgba(148,163,184,0.35)"
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return value.toLocaleString('id-ID');
                                        }
                                    }
                                },
                            },
                        },
                    });
                }

                // Tunggu DOM ready dan Chart.js loaded
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(initChart, 500);
                    });
                } else {
                    setTimeout(initChart, 500);
                }
            })();
        </script>
    </x-slot>
</x-layouts>
