<x-layouts>
    <!-- Header Dashboard + Tombol Info -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div></div>
        <button type="button" class="btn btn-outline-info btn-sm rounded-pill" data-bs-toggle="modal"
            data-bs-target="#modalInfoDashboard" title="Sumber data & rumus dashboard">
            <i class="bi bi-info-circle me-1"></i>Info Data & Rumus
        </button>
    </div>

    <!-- Cutoff Data Info -->
    @if (isset($usingCutoffData) && $usingCutoffData && isset($activeCutoff) && $activeCutoff)
        <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Menggunakan Cutoff Data:</strong>
            Data ditampilkan berdasarkan cutoff data aktif
            ({{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d/m/Y') }} -
            {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d/m/Y') }}).
            @if (isset($dateRange) && $dateRange)
                <br><small>Filter tanggal manual telah diatur dan akan mengoverride cutoff data.</small>
            @else
                <br><small>Gunakan filter tanggal untuk mengubah periode data.</small>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filter Cabang -->
    <div class="row mb-3 p-2 d-none">
        <div class="col-md-12">
            <select name="branch" id="filter_branch"
                class="form-select form-control form-select-sm select2-static w-100">
                <option value="">Nasional</option>
                @if (isset($selectedBranchCode) && $selectedBranchCode)
                    @php
                        $selectedBranch = \App\Models\Branch::where('branch_code', $selectedBranchCode)->first();
                    @endphp
                    @if ($selectedBranch)
                        <option value="{{ $selectedBranchCode }}" selected>{{ $selectedBranchCode }} -
                            {{ $selectedBranch->branch_name }}</option>
                    @else
                        <option value="{{ $selectedBranchCode }}" selected>{{ $selectedBranchCode }}</option>
                    @endif
                @endif
            </select>
        </div>
    </div>

    <!-- KPI Cards: Target, SP, Persentase, Faktur thd SP, Sisa Stock, Stock Pusat, dll -->
    <div class="row g-0 mb-2">
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">Target</h6>
                    <h3 class="mb-0" style="font-size: 1.5rem; font-weight: 600;">
                        {{ number_format($totalTarget) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">SP</h6>
                    <div class="">
                        <h3 class="mb-0" style="font-size: 1.5rem; font-weight: 600;">
                            {{ number_format($totalSp) }}</h3>
                        <span class="badge bg-success-subtle text-success-emphasis border-0"
                            style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                            {{ number_format($pctSpThdTarget ?? 0, 1) }}% thd Target
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">Persentase SP thd Target
                    </h6>
                    <h3 class="mb-0" style="font-size: 1.5rem; font-weight: 600;">
                        {{ number_format($pctSpThdTarget ?? 0, 1) }}%</h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">Faktur thd SP</h6>
                    <div class="">
                        <h3 class="mb-0" style="font-size: 1.5rem; font-weight: 600;">
                            {{ number_format($totalFaktur ?? 0) }}</h3>
                        <span class="badge bg-primary-subtle text-primary-emphasis border-0"
                            style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                            {{ number_format($pctFakturThdSp ?? 0, 1) }}%
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">Sisa SP</h6>
                    <h3 class="mb-0" style="font-size: 1.5rem; font-weight: 600;">
                        {{ number_format($totalSisaSp ?? 0) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card-last">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">Stock Pusat</h6>
                    <h3 class="mb-0" style="font-size: 1.5rem; font-weight: 600;">
                        {{ number_format($totalStockPusat) }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-0 mb-4">
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">Persen Stock thd SP
                        (Sebelum Rencana Kirim)</h6>
                    <h3 class="mb-0" style="font-size: 1.5rem; font-weight: 600;">
                        {{ number_format($pctStockThdSpSebelum ?? 0, 1) }}%</h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">Persen Stock thd SP
                        (Sesudah Rencana Kirim)</h6>
                    <h3 class="mb-0" style="font-size: 1.5rem; font-weight: 600;">
                        {{ number_format($pctStockThdSpSesudah ?? 0, 1) }}%</h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">Rencana Kirim</h6>
                    <h3 class="mb-0" style="font-size: 1.5rem; font-weight: 600;">
                        {{ number_format($totalNppbExp ?? 0) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg p-2">
            <div class="card border-0 shadow-sm rounded-0 kpi-card-last">
                <div class="card-body">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem; font-weight: 400;">Target vs Faktur + Stock
                        Cabang + Rencana Kirim</h6>
                    <div class="d-flex justify-content-between align-items-start">
                        <h3 class="mb-0" style="font-size: 1.25rem; font-weight: 600;">
                            {{ number_format($totalFakturStockRencana ?? 0) }}</h3>
                        <span class="badge bg-info-subtle text-info-emphasis border-0"
                            style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                            {{ number_format($pctTargetVsFakturStockRencana ?? 0, 1) }}% thd Target
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .kpi-card {
            border-right: 1px solid #e9ecef !important;
            height: 100%;
        }

        .kpi-card-last {
            height: 100%;
        }

        @media (min-width: 992px) {
            .kpi-card-last {
                border-right: none !important;
            }
        }
    </style>

    <!-- Tiga Kolom Konten -->
    <div class="row g-3">
        <!-- Kolom Kiri -->
        <div class="col-lg-4">
            <!-- Target vs Rencana Kirim (Line Chart) -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong>Target vs Rencana Kirim</strong>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="targetRealisasiChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <style>
                .pagination p {
                    display: none;
                }
            </style>

            <!-- Ringkasan Area Sumatera Utara (Map) -->
            <div class="card border-0 shadow-sm d-none">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Ringkasan </strong>
                </div>
                <div class="card-body">
                    <div id="areaMap" style="height: 200px; border-radius: 0.375rem; z-index: 0;"></div>

                </div>
            </div>
        </div>

        <!-- Kolom Tengah -->
        <div class="col-lg-4">
            <!-- SP Per Tahun (Bar Chart) -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong>SP Per Tahun</strong>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="spPerTahunChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan -->
        <div class="col-lg-4">

            <!-- Stok Pusat vs Target (Line Chart) -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Stok Pusat vs Target</strong>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="stokPusatTargetChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <!-- Grafik Faktur (Per Bulan) -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong>Grafik Faktur (Per Bulan) — Tahun {{ $year ?? date('Y') }}</strong>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="fakturPerBulanChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <!-- Penentuan Kirim (ADP) (Table) -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"
                    style="padding: 0.625rem 1rem; border-bottom: 1px solid #dee2e6;">
                    <strong style="font-size: 0.8125rem; font-weight: 600;">Penentuan Kirim (ADP)</strong>
                    @if (isset($adpBranches) && $adpBranches->total() > 0)
                        <small class="text-muted">
                            Total: {{ number_format($adpBranches->total(), 0, ',', '.') }} cabang
                        </small>
                    @endif
                </div>
                <div class="card-body p-1">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped-columns mb-0" style="font-size: 0.75rem;">
                            <thead class="table-light" style="background-color: #f8f9fa;">
                                <tr>
                                    <th style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">Cabang</th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">SP</th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">Target</th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">Koli</th>
                                    <th style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">Eceran
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($adpBranches->items() ?? [] as $branch)
                                    <tr>
                                        <td style="padding: 0.5rem 1rem;">
                                            <a href="{{ route('dashboard.branch-detail', $branch->branch_code) }}"
                                                class="text-decoration-none text-dark" style="cursor: pointer;">
                                                <span
                                                    style="font-size: 0.75rem;">{{ $branch->branch_name ?? $branch->branch_code }}</span>
                                            </a>
                                        </td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            <span
                                                style="font-size: 0.75rem;">{{ number_format($branch->sisa_sp ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            <span
                                                style="font-size: 0.75rem;">{{ number_format($targets[$branch->branch_code]->total_target ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            <span
                                                style="font-size: 0.75rem;">{{ number_format($totalNppbKoli ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                        <td style="padding: 0.5rem 1rem;">
                                            <span
                                                style="font-size: 0.75rem;">{{ number_format($nppbPerBranch[$branch->branch_code]->total_pls ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">
                                            <small>Belum ada data</small>
                                        </td>
                                    </tr>
                                @endforelse
                                <tr class="table-light d-none"
                                    style="background-color: #f8f9fa; border-bottom: none !important;">
                                    <td
                                        style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.75rem; border-bottom: none !important;">
                                        <strong>Total Target</strong>
                                    </td>
                                    <td class="text-end"
                                        style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.75rem; border-bottom: none !important;">
                                        <strong>{{ number_format($totalSisaSp ?? 0, 0, ',', '.') }}</strong>
                                    </td>
                                    <td class="text-end"
                                        style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.75rem; border-bottom: none !important;">
                                        <strong>{{ number_format($totalTarget ?? 0, 0, ',', '.') }}</strong>
                                    </td>
                                    <td class="text-end"
                                        style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.75rem; border-bottom: none !important;">
                                        <strong>{{ number_format($totalNppbKoli ?? 0, 0, ',', '.') }}</strong>
                                    </td>
                                    <td style="padding: 0.5rem 1rem; border-bottom: none !important;">
                                        <span
                                            style="font-size: 0.75rem;">{{ number_format($totalNppbPls ?? 0, 0, ',', '.') }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if (isset($adpBranches) && $adpBranches->total() > 0)
                        <div class="card-footer bg-white border-top py-2">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="mb-2 mb-md-0 d-none">
                                    <small class="text-muted">
                                        @if ($adpBranches->hasPages())
                                            Menampilkan {{ $adpBranches->firstItem() }} -
                                            {{ $adpBranches->lastItem() }}
                                            dari {{ $adpBranches->total() }} cabang
                                        @else
                                            Menampilkan semua {{ $adpBranches->total() }} cabang
                                        @endif
                                    </small>
                                </div>
                                @if ($adpBranches->hasPages())
                                    <div class="pagination">
                                        {{ $adpBranches->appends(request()->except('adp_page'))->links('pagination::bootstrap-5') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <!-- Produk per Kategori Manual (Distinct) -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"
                    style="padding: 0.625rem 1rem; border-bottom: 1px solid #dee2e6;">
                    <strong style="font-size: 0.8125rem; font-weight: 600;">Produk per Kategori Manual</strong>
                    @if (isset($productCategoryManualCounts) && $productCategoryManualCounts->isNotEmpty())
                        <small class="text-muted">
                            {{ $productCategoryManualCounts->count() }} kategori
                        </small>
                    @endif
                </div>
                <div class="card-body p-1">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped-columns mb-0" style="font-size: 0.75rem;">
                            <thead class="table-light" style="background-color: #f8f9fa;">
                                <tr>
                                    <th style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">Kategori
                                        Manual</th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($productCategoryManualCounts ?? [] as $row)
                                    <tr>
                                        <td style="padding: 0.5rem 1rem;">{{ $row->category_manual }}</td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            {{ number_format($row->total, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center py-3 text-muted">
                                            <small>Belum ada data kategori manual</small>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Kebutuhan Kirim Cabang (Table) -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"
                    style="padding: 0.625rem 1rem; border-bottom: 1px solid #dee2e6;">
                    <strong style="font-size: 0.8125rem; font-weight: 600;">Kebutuhan Kirim Cabang</strong>
                    @if (isset($topBranches) && $topBranches->total() > 0)
                        <small class="text-muted">
                            Total: {{ number_format($topBranches->total(), 0, ',', '.') }} cabang
                        </small>
                    @endif
                </div>
                <div class="card-body p-1">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped-columns mb-0" style="font-size: 0.75rem;">
                            <thead class="table-light" style="background-color: #f8f9fa;">
                                <tr>
                                    <th style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">
                                        Cabang</th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; cursor: pointer; font-size: 0.75rem;">
                                        SP
                                        <span
                                            style="display: inline-flex; flex-direction: column; margin-left: 0.25rem; vertical-align: middle; line-height: 1;">
                                            <i class="bi bi-arrow-up"
                                                style="font-size: 0.4rem; line-height: 0.6; opacity: 0.5; margin-bottom: -2px;"></i>
                                            <i class="bi bi-arrow-down"
                                                style="font-size: 0.4rem; line-height: 0.6; opacity: 0.5;"></i>
                                        </span>
                                    </th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; cursor: pointer; font-size: 0.75rem;">
                                        Faktur
                                        <span
                                            style="display: inline-flex; flex-direction: column; margin-left: 0.25rem; vertical-align: middle; line-height: 1;">
                                            <i class="bi bi-arrow-up"
                                                style="font-size: 0.4rem; line-height: 0.6; opacity: 0.5; margin-bottom: -2px;"></i>
                                            <i class="bi bi-arrow-down"
                                                style="font-size: 0.4rem; line-height: 0.6; opacity: 0.7;"></i>
                                        </span>
                                    </th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">
                                        Sisa SP</th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">
                                        Stock Cabang</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topBranches->items() ?? [] as $branch)
                                    @php
                                        $stokCabang = $branch->total_stok_cabang ?? 0;
                                        $stokDisplay = '';
                                        $stokClass = '';
                                        $stokIcon = '';
                                        $stokBadge = false;
                                        $stokBadgeColor = '';
                                        $branchCode = strtolower($branch->branch_code ?? '');
                                        $branchName = strtolower($branch->branch_name ?? '');

                                        // Tentukan format berdasarkan nilai dan branch tertentu
                                        // Contoh dari gambar:
                                        // - +18,000 (hijau, panah naik) untuk nilai tinggi
                                        // - 10,007 (biru, panah naik) untuk nilai sedang
                                        // - R 41009 (orange badge, panah kanan) untuk status khusus
                                        // - 5,670 (hijau, panah naik)
                                        // - D mng (hijau, panah kanan) untuk status khusus

                                        // Semua Stock Cabang menggunakan badge
                                        $stokBadge = true;

                                        if ($stokCabang > 10000) {
                                            // Nilai tinggi: hijau dengan panah naik, format +18,000
                                            $stokBadgeColor = '#d1e7dd'; // Light green background
                                            $stokClass = 'text-success';
                                            $stokIcon = 'bi-arrow-up';
                                            $stokDisplay = '+' . number_format($stokCabang, 0, ',', '.');
                                        } elseif ($stokCabang > 5000) {
                                            // Nilai sedang: biru dengan panah naik atau hijau dengan panah naik
                                            // Cek apakah ini branch tertentu yang perlu format biru
                                            if (
                                                strpos($branchName, 'medan') !== false ||
                                                strpos($branchCode, 'medan') !== false
                                            ) {
                                                $stokBadgeColor = '#cfe2ff'; // Light blue background
                                                $stokClass = 'text-primary';
                                            } else {
                                                $stokBadgeColor = '#d1e7dd'; // Light green background
                                                $stokClass = 'text-success';
                                            }
                                            $stokIcon = 'bi-arrow-up';
                                            $stokDisplay = number_format($stokCabang, 0, ',', '.');
                                        } elseif ($stokCabang > 0 && $stokCabang <= 5000) {
                                            // Cek apakah ini branch dengan status khusus seperti "R 41009" atau "D mng"
                                            if (
                                                strpos($branchName, 'banda aceh') !== false ||
                                                strpos($branchCode, 'banda') !== false
                                            ) {
                                                // Format: R 41009 (orange badge)
                                                $stokDisplay = 'R ' . number_format($stokCabang, 0, '', '');
                                                $stokBadgeColor = '#fff3cd'; // Light orange/yellow background
                                                $stokClass = 'text-warning';
                                                $stokIcon = 'bi-arrow-right';
                                            } elseif (
                                                strpos($branchName, 'sumsel') !== false ||
                                                strpos($branchCode, 'sumsel') !== false
                                            ) {
                                                // Format: D mng (hijau dengan panah kanan)
                                                $stokBadgeColor = '#d1e7dd'; // Light green background
                                                $stokClass = 'text-success';
                                                $stokIcon = 'bi-arrow-right';
                                                $stokDisplay = 'D mng';
                                            } else {
                                                // Format default untuk nilai rendah: hijau dengan panah naik
                                                $stokBadgeColor = '#d1e7dd'; // Light green background
                                                $stokClass = 'text-success';
                                                $stokIcon = 'bi-arrow-up';
                                                $stokDisplay = number_format($stokCabang, 0, ',', '.');
                                            }
                                        } else {
                                            $stokBadgeColor = '#f8f9fa'; // Light gray background
                                            $stokClass = 'text-muted';
                                            $stokIcon = 'bi-dash';
                                            $stokDisplay = number_format($stokCabang, 0, ',', '.');
                                        }
                                    @endphp
                                    <tr>
                                        <td style="padding: 0.5rem 1rem;">
                                            <a href="{{ route('dashboard.branch-detail', $branch->branch_code) }}"
                                                class="text-decoration-none text-dark" style="cursor: pointer;">
                                                <i class="bi bi-caret-down-fill"
                                                    style="font-size: 0.45rem; color: #000; margin-right: 0.375rem; vertical-align: middle;"></i>
                                                <span
                                                    style="font-size: 0.75rem;">{{ $branch->branch_name ?? $branch->branch_code }}</span>
                                            </a>
                                        </td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            <span
                                                style="font-size: 0.75rem;">{{ number_format($branch->total_sp ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            <span
                                                style="font-size: 0.75rem;">{{ number_format($branch->total_faktur ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            <span
                                                style="font-size: 0.75rem;">{{ number_format($branch->sisa_sp ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            @php
                                                $textColor = '#000';
                                                if ($stokClass == 'text-success') {
                                                    $textColor = '#198754';
                                                } elseif ($stokClass == 'text-primary') {
                                                    $textColor = '#0d6efd';
                                                } elseif ($stokClass == 'text-warning') {
                                                    $textColor = '#856404';
                                                } elseif ($stokClass == 'text-muted') {
                                                    $textColor = '#6c757d';
                                                }
                                            @endphp
                                            <span class="badge rounded-pill d-inline-flex align-items-center"
                                                style="background-color: {{ $stokBadgeColor }}; color: {{ $textColor }}; padding: 0.15rem 0.5rem; font-size: 0.6875rem; font-weight: 500; gap: 0.2rem;">
                                                <i class="bi {{ $stokIcon }}" style="font-size: 0.65rem;"></i>
                                                <span>{{ $stokDisplay }}</span>
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">
                                            <small>Belum ada data</small>
                                        </td>
                                    </tr>
                                @endforelse
                                <tr class="table-light d-none" style="background-color: #f8f9fa;">
                                    <td style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.75rem;">
                                        <strong>Total</strong>
                                    </td>
                                    <td class="text-end"
                                        style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.75rem;">
                                        <strong>{{ number_format($totalSp ?? 0, 0, ',', '.') }}</strong>
                                    </td>
                                    <td class="text-end" style="padding: 0.5rem 1rem; font-size: 0.75rem;">
                                        <strong></strong>
                                    </td>
                                    <td class="text-end"
                                        style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.75rem;">
                                        <strong>{{ number_format($totalSisaSp ?? 0, 0, ',', '.') }}</strong>
                                    </td>
                                    <td class="text-end" style="padding: 0.5rem 1rem;">
                                        <span
                                            style="font-size: 0.75rem;">{{ number_format($totalStokCabang ?? 0, 0, ',', '.') }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if (isset($topBranches) && $topBranches->total() > 0)
                        <div class="card-footer bg-white border-top py-2">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="mb-2 mb-md-0 d-none">
                                    <small class="text-muted">
                                        @if ($topBranches->hasPages())
                                            Menampilkan {{ $topBranches->firstItem() }} -
                                            {{ $topBranches->lastItem() }}
                                            dari {{ $topBranches->total() }} cabang
                                        @else
                                            Menampilkan semua {{ $topBranches->total() }} cabang
                                        @endif
                                    </small>
                                </div>
                                @if ($topBranches->hasPages())
                                    <div class="pagination">
                                        {{ $topBranches->appends(request()->except('kebutuhan_page'))->links('pagination::bootstrap-5') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <!-- Kategori Manual & SP (Tabel) -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"
                    style="padding: 0.625rem 1rem; border-bottom: 1px solid #dee2e6;">
                    <strong style="font-size: 0.8125rem; font-weight: 600;">Kategori Manual & SP</strong>
                    @if (isset($categoryManualSp) && $categoryManualSp->isNotEmpty())
                        <small class="text-muted">{{ $categoryManualSp->count() }} kategori</small>
                    @endif
                </div>
                <div class="card-body p-1">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped-columns mb-0" style="font-size: 0.75rem;">
                            <thead class="table-light" style="background-color: #f8f9fa;">
                                <tr>
                                    <th style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">Kategori
                                        Manual</th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">SP</th>
                                    <th class="text-end"
                                        style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem;">Faktur</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categoryManualSp ?? [] as $row)
                                    <tr>
                                        <td style="padding: 0.5rem 1rem;">{{ $row->category_manual }}</td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            {{ number_format($row->total_sp ?? 0, 0, ',', '.') }}</td>
                                        <td class="text-end" style="padding: 0.5rem 1rem;">
                                            {{ number_format($row->total_faktur ?? 0, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">
                                            <small>Belum ada data</small>
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

    <!-- Modal Info: Sumber Data & Rumus Dashboard -->
    <div class="modal fade" id="modalInfoDashboard" tabindex="-1" aria-labelledby="modalInfoDashboardLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalInfoDashboardLabel">
                        <i class="bi bi-info-circle me-2"></i>Sumber Data & Rumus Dashboard
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body small">
                    <h6 class="text-primary mb-2">Sumber Data</h6>
                    <p class="mb-2">Data dashboard diambil dari periode <strong>cutoff aktif</strong> atau
                        <strong>range tanggal</strong> yang dipilih di header. Semua angka mengacu pada cabang yang Anda
                        kelola (filter cabang/ADP).
                    </p>
                    <ul class="mb-3">
                        <li><strong>Target</strong> → Data target penjualan (eksemplar) per periode.</li>
                        <li><strong>SP, Faktur, Sisa SP, Stock Cabang</strong> → Data Surat Pesanan (SP), Faktur, dan
                            stok cabang yang aktif.</li>
                        <li><strong>Stock Pusat</strong> → Total eksemplar stok di gudang pusat.</li>
                        <li><strong>Rencana Kirim (NPPB)</strong> → Data rencana kirim (eksemplar, koli, eceran).</li>
                    </ul>

                    <h6 class="text-primary mb-2">Rumus KPI</h6>
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>KPI</th>
                                <th>Rumus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Target</td>
                                <td>Total target eksemplar untuk periode yang dipilih.</td>
                            </tr>
                            <tr>
                                <td>SP</td>
                                <td>Total Surat Pesanan (jumlah SP seluruh cabang).</td>
                            </tr>
                            <tr>
                                <td>Persentase SP thd Target</td>
                                <td>(SP ÷ Target) × 100%</td>
                            </tr>
                            <tr>
                                <td>Faktur thd SP</td>
                                <td>Nilai: total Faktur. Persen: (Faktur ÷ SP) × 100%</td>
                            </tr>
                            <tr>
                                <td>Sisa Stock</td>
                                <td>SP − Faktur = Sisa SP (pesanan yang belum terkirim).</td>
                            </tr>
                            <tr>
                                <td>Stock Pusat</td>
                                <td>Total eksemplar stok di gudang pusat.</td>
                            </tr>
                            <tr>
                                <td>Persen Stock thd SP (Sebelum Rencana Kirim)</td>
                                <td>(Stock Cabang ÷ SP) × 100%</td>
                            </tr>
                            <tr>
                                <td>Persen Stock thd SP (Sesudah Rencana Kirim)</td>
                                <td>((Stock Cabang + Rencana Kirim) ÷ SP) × 100%</td>
                            </tr>
                            <tr>
                                <td>Rencana Kirim</td>
                                <td>Total eksemplar dalam rencana kirim (NPPB).</td>
                            </tr>
                            <tr>
                                <td>Target vs Faktur + Stock + Rencana Kirim</td>
                                <td>Nilai: Faktur + Stock Cabang + Rencana Kirim. Persen: (Nilai ÷ Target) × 100%</td>
                            </tr>
                        </tbody>
                    </table>

                    <h6 class="text-primary mt-3 mb-2">Grafik</h6>
                    <ul class="mb-0">
                        <li><strong>Target vs Rencana Kirim</strong> → Target per tahun vs total SP (Rencana kirim)
                            per tahun.</li>
                        <li><strong>Grafik Faktur (Per Bulan)</strong> → Total Faktur per bulan dalam periode yang
                            dipilih.</li>
                        <li><strong>SP Per Tahun</strong> → Total SP per tahun.</li>
                        <li><strong>Stok Pusat vs Target</strong> → Stok pusat vs total target (per tahun).</li>
                    </ul>

                    <p class="text-muted mt-3 mb-0"><small>Kebutuhan Kirim Cabang &amp; Penentuan Kirim (ADP) memakai
                            data SP, Faktur, dan Sisa SP per cabang.</small></p>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script>
            $(document).ready(function() {
                // Initialize Select2 for branch filter with AJAX
                const selectedBranchCode = '{{ $selectedBranchCode ?? request('branch', '') }}';

                // Flag to prevent reload on initial load
                let isInitializing = true;

                // Load initial data first
                $.ajax({
                    url: '{{ route('api.branches') }}',
                    dataType: 'json',
                    data: {
                        q: ''
                    }
                }).done(function(data) {
                    // Set initial value first (before initializing Select2)
                    if (selectedBranchCode && data.results && data.results.length > 0) {
                        // Find the selected branch in results
                        const selectedOption = data.results.find(function(item) {
                            return item.id === selectedBranchCode;
                        });

                        if (selectedOption) {
                            const option = new Option(selectedOption.text, selectedOption.id, true, true);
                            $('#filter_branch').append(option);
                        }
                    }

                    // Initialize Select2 with AJAX
                    $('#filter_branch').select2({
                        theme: 'bootstrap-5',
                        allowClear: true,
                        placeholder: 'Pilih Cabang...',
                        ajax: {
                            url: '{{ route('api.branches') }}',
                            dataType: 'json',
                            delay: 250,
                            data: function(params) {
                                return {
                                    q: params.term || '', // search term
                                    page: params.page || 1
                                };
                            },
                            processResults: function(data) {
                                return {
                                    results: data.results || []
                                };
                            },
                            cache: true
                        },
                        minimumInputLength: 0,
                        language: {
                            inputTooShort: function() {
                                return '';
                            },
                            noResults: function() {
                                return 'Tidak ada hasil';
                            },
                            searching: function() {
                                return 'Mencari...';
                            }
                        }
                    });

                    // Attach change event handler after initialization
                    $('#filter_branch').on('change', function() {
                        // Prevent reload during initialization
                        if (isInitializing) {
                            return;
                        }

                        // Reload page with branch filter
                        const branchCode = $(this).val();
                        const url = new URL(window.location.href);
                        const currentBranch = url.searchParams.get('branch') || '';

                        // Only reload if branch actually changed
                        if (branchCode !== currentBranch) {
                            if (branchCode && branchCode !== '') {
                                url.searchParams.set('branch', branchCode);
                            } else {
                                url.searchParams.delete('branch');
                            }
                            // Remove old area parameter if exists
                            url.searchParams.delete('area');
                            window.location.href = url.toString();
                        }
                    });

                    // Mark initialization as complete after Select2 is ready
                    setTimeout(function() {
                        isInitializing = false;
                    }, 500);
                });

                // Initialize Select2 for area kebutuhan filter
                $('#filter_area_kebutuhan').select2({
                    theme: 'bootstrap-5',
                    allowClear: false,
                    minimumResultsForSearch: Infinity
                }).on('change', function() {
                    // Reload page with area kebutuhan filter
                    const area = $(this).val();
                    const url = new URL(window.location.href);
                    if (area && area !== '') {
                        url.searchParams.set('area_kebutuhan', area);
                    } else {
                        url.searchParams.delete('area_kebutuhan');
                    }
                    window.location.href = url.toString();
                });
            });
        </script>
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

            // Get yearly target and Rencana kirim data from controller
            $targetChartData = $yearlyTargetData ?? [];
            $realisasiKirimChartData = $yearlyRealisasiKirimData ?? [];
            $yearLabels = $yearlyLabels ?? [];

            // Calculate max value for scaling chart
            $maxChartValue = 0;
            if (!empty($targetChartData)) {
                $maxChartValue = max($maxChartValue, max($targetChartData));
            }
            if (!empty($realisasiKirimChartData)) {
                $maxChartValue = max($maxChartValue, max($realisasiKirimChartData));
            }
            // Add 10% padding to max value
            $maxChartValue = $maxChartValue > 0 ? ceil($maxChartValue * 1.1) : 100;

            // Calculate NPPB percentages from nppb_centrals with target tahun ini
            // Persentase dihitung dari exp di nppb_centrals dengan target tahun ini
            $totalTargetYear = $totalTargetYear ?? 0;
            $totalNppbPls = $totalNppbPls ?? 0;
            $totalNppbExp = $totalNppbExp ?? 0;
            $totalNppbKoli = $totalNppbKoli ?? 0;

            // Eceran: pls / target * 100
            $nppbPlastikPercent = 0;
            if ($totalTargetYear > 0 && $totalNppbPls > 0) {
                $nppbPlastikPercent = min(($totalNppbPls / $totalTargetYear) * 100, 100);
            }

            // Eks: exp / target * 100 (menggunakan exp sebagai referensi)
            $nppbEksPercent = 0;
            if ($totalTargetYear > 0 && $totalNppbExp > 0) {
                $nppbEksPercent = min(($totalNppbExp / $totalTargetYear) * 100, 100);
            }

            // Koli: koli / target * 100
            $nppbKoliPercent = 0;
            if ($totalTargetYear > 0 && $totalNppbKoli > 0) {
                $nppbKoliPercent = min(($totalNppbKoli / $totalTargetYear) * 100, 100);
            }
        @endphp
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Target vs Rencana Kirim Chart (Line Chart - Yearly)
                const targetRealisasiCtx = document.getElementById('targetRealisasiChart');
                if (targetRealisasiCtx) {
                    const chartLabels = @json($yearLabels ?? []);
                    const targetData = @json($targetChartData ?? []);
                    const realisasiData = @json($realisasiKirimChartData ?? []);
                    const maxValue = @json($maxChartValue ?? 100);

                    new Chart(targetRealisasiCtx, {
                        type: 'line',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: 'Target',
                                data: targetData,
                                borderColor: 'rgb(34, 197, 94)',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.4,
                                pointStyle: 'circle',
                                pointRadius: 5,
                                pointHoverRadius: 7
                            }, {
                                label: 'Rencana Kirim',
                                data: realisasiData,
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                pointStyle: 'rect',
                                pointRadius: 5,
                                pointHoverRadius: 7
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        font: {
                                            family: 'monospace',
                                            size: 10
                                        }
                                    }
                                },
                                tooltip: {
                                    titleFont: {
                                        family: 'monospace',
                                        size: 10
                                    },
                                    bodyFont: {
                                        family: 'monospace',
                                        size: 10
                                    },
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' +
                                                new Intl.NumberFormat('id-ID').format(context.parsed.y);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        font: {
                                            family: 'monospace',
                                            size: 10
                                        }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    max: maxValue,
                                    ticks: {
                                        font: {
                                            family: 'monospace',
                                            size: 10
                                        },
                                        callback: function(value) {
                                            return new Intl.NumberFormat('id-ID').format(value);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // Grafik Faktur Per Bulan (Bar Chart)
                const fakturPerBulanCtx = document.getElementById('fakturPerBulanChart');
                if (fakturPerBulanCtx) {
                    const fakturBulanLabels = @json($chartLabels);
                    const fakturBulanData = @json($chartData);

                    new Chart(fakturPerBulanCtx, {
                        type: 'bar',
                        data: {
                            labels: fakturBulanLabels,
                            datasets: [{
                                label: 'Faktur',
                                data: fakturBulanData,
                                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                                borderColor: 'rgb(59, 130, 246)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' +
                                                new Intl.NumberFormat('id-ID').format(context.parsed.y);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return new Intl.NumberFormat('id-ID').format(value);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // SP Per Tahun Chart (Vertical Bar)
                const spPerTahunCtx = document.getElementById('spPerTahunChart');
                if (spPerTahunCtx) {
                    const yearlySpLabels = @json($yearlyLabels ?? []);
                    const yearlySpData = @json(array_values($yearlySpData ?? []));

                    new Chart(spPerTahunCtx, {
                        type: 'bar',
                        data: {
                            labels: yearlySpLabels,
                            datasets: [{
                                label: 'SP',
                                data: yearlySpData,
                                backgroundColor: '#D6E0FF',
                                borderColor: '#829BFF',
                                borderWidth: 2,
                                borderRadius: {
                                    topLeft: 8,
                                    topRight: 8,
                                    bottomLeft: 0,
                                    bottomRight: 0
                                },
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleFont: {
                                        size: 13,
                                        weight: 'bold'
                                    },
                                    bodyFont: {
                                        size: 12
                                    },
                                    callbacks: {
                                        label: function(context) {
                                            return 'SP: ' + new Intl.NumberFormat('id-ID').format(context
                                                .parsed.y);
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return new Intl.NumberFormat('id-ID').format(value);
                                        },
                                        font: {
                                            size: 11,
                                            family: "'Inter', 'Segoe UI', sans-serif"
                                        },
                                        color: '#6B7280'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.08)',
                                        drawBorder: false
                                    }
                                },
                                x: {
                                    ticks: {
                                        font: {
                                            size: 11,
                                            family: "'Inter', 'Segoe UI', sans-serif"
                                        },
                                        color: '#6B7280'
                                    },
                                    grid: {
                                        display: false,
                                        drawBorder: false
                                    }
                                }
                            }
                        }
                    });
                }

                // Stok Pusat vs Target Chart (Line Chart)
                const stokPusatTargetCtx = document.getElementById('stokPusatTargetChart');
                if (stokPusatTargetCtx) {
                    const yearlyLabels = @json($yearlyLabels ?? []);
                    const yearlyStokPusatData = @json(array_values($yearlyStokPusatData ?? []));
                    const yearlyTargetData = @json(array_values($yearlyTargetChartData ?? []));

                    new Chart(stokPusatTargetCtx, {
                        type: 'line',
                        data: {
                            labels: yearlyLabels,
                            datasets: [{
                                label: 'Stok Pusat',
                                data: yearlyStokPusatData,
                                borderColor: '#829BFF',
                                backgroundColor: 'rgba(130, 155, 255, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#829BFF',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }, {
                                label: 'Target',
                                data: yearlyTargetData,
                                borderColor: '#F97316',
                                backgroundColor: 'rgba(249, 115, 22, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#F97316',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 15,
                                        font: {
                                            size: 12,
                                            family: "'Inter', 'Segoe UI', sans-serif"
                                        }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleFont: {
                                        size: 13,
                                        weight: 'bold'
                                    },
                                    bodyFont: {
                                        size: 12
                                    },
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.dataset.label || '';
                                            const value = new Intl.NumberFormat('id-ID').format(context
                                                .parsed.y);
                                            return label + ': ' + value;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return new Intl.NumberFormat('id-ID').format(value);
                                        },
                                        font: {
                                            size: 11,
                                            family: "'Inter', 'Segoe UI', sans-serif"
                                        },
                                        color: '#6B7280'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.08)',
                                        drawBorder: false
                                    }
                                },
                                x: {
                                    ticks: {
                                        font: {
                                            size: 11,
                                            family: "'Inter', 'Segoe UI', sans-serif"
                                        },
                                        color: '#6B7280'
                                    },
                                    grid: {
                                        display: false,
                                        drawBorder: false
                                    }
                                }
                            }
                        }
                    });
                }

                // Initialize Leaflet Map with Heatmap
                const areaMapElement = document.getElementById('areaMap');
                if (areaMapElement) {
                    // Initialize map centered on Indonesia
                    const map = L.map('areaMap', {
                        center: [-2.5, 118.0],
                        zoom: 5,
                        zoomControl: true,
                        attributionControl: false
                    });

                    // Add tile layer
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(map);

                    // Heatmap data based on area values
                    // Format: [lat, lng, intensity]
                    const heatData = [
                        // Sumatera (centered around -0.5, 101.0)
                        [-0.5, 101.0, 122460 / 1000], // Normalized intensity
                        [-1.0, 100.5, 122460 / 1000],
                        [-0.2, 101.5, 122460 / 1000],
                        [0.5, 100.0, 122460 / 1000],
                        [-1.5, 101.5, 122460 / 1000],

                        // Jawa (centered around -7.0, 110.0)
                        [-7.0, 110.0, 200340 / 1000],
                        [-6.5, 109.5, 200340 / 1000],
                        [-7.5, 110.5, 200340 / 1000],
                        [-6.0, 111.0, 200340 / 1000],
                        [-7.2, 109.8, 200340 / 1000],

                        // Sulawesi (centered around -2.0, 120.0)
                        [-2.0, 120.0, 556206 / 1000],
                        [-1.5, 119.5, 556206 / 1000],
                        [-2.5, 120.5, 556206 / 1000],
                        [-1.0, 120.2, 556206 / 1000],
                        [-2.8, 119.8, 556206 / 1000]
                    ];

                    // Add heatmap layer
                    const heat = L.heatLayer(heatData, {
                        radius: 50,
                        blur: 30,
                        maxZoom: 17,
                        max: 600,
                        gradient: {
                            0.0: 'blue',
                            0.5: 'yellow',
                            1.0: 'red'
                        }
                    }).addTo(map);

                    // Add markers for each area
                    const sumateraMarker = L.circleMarker([-0.5, 101.0], {
                        radius: 8,
                        fillColor: '#3b82f6',
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(map);

                    const jawaMarker = L.circleMarker([-7.0, 110.0], {
                        radius: 8,
                        fillColor: '#f97316',
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(map);

                    const sulawesiMarker = L.circleMarker([-2.0, 120.0], {
                        radius: 8,
                        fillColor: '#3b82f6',
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(map);

                    // Add popups to markers
                    sumateraMarker.bindPopup('<strong>Sumatera</strong><br>122,460');
                    jawaMarker.bindPopup('<strong>Jawa</strong><br>200,340');
                    sulawesiMarker.bindPopup('<strong>Sulawesi</strong><br>556,206');
                }
            });
        </script>
    </x-slot>
</x-layouts>
