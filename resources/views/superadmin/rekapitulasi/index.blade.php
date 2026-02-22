<x-layouts>
    @php
        $queueJalan = isset($recap_from_cache) && $recap_from_cache === true;
    @endphp
    <script>
        (function() {
            var queueJalan = @json($queueJalan);
            if (queueJalan) {
                console.log('[Rekap] Queue jalan: data dilayani dari cache (job queue diproses di server).');
            } else {
                console.log(
                    '[Rekap] Queue tidak dipakai: data dilayani dari proses sync (tanpa queue). Atur cron di server: * * * * * cd /path-project && php artisan schedule:run'
                    );
            }
        })();
    </script>
    <!-- Cutoff Data Info -->
    @if (isset($activeCutoff) && $activeCutoff)
        <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Menggunakan Cutoff Data:</strong>
            Data ditampilkan berdasarkan cutoff data aktif
            @if ($activeCutoff->start_date)
                ({{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d/m/Y') }} -
                {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d/m/Y') }}).
            @else
                (s.d. {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d/m/Y') }})
                .
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Rekapitulasi</strong><br />
                    <small class="text-muted">Laporan rekapitulasi penjualan dan stok per cabang</small>
                    @if (isset($activeCutoff) && $activeCutoff)
                        <br><small class="text-info">
                            Periode: @if ($activeCutoff->start_date)
                                {{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d/m/Y') }} -
                            @else
                                s.d.
                            @endif
                            {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d/m/Y') }}
                        </small>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @php
                        $exportQuery = array_filter(['year' => request('year', date('Y')), 'book_code' => request('book_code', $filterBookCode ?? '')]);
                        $exportRecapUrl = route('recap.export') . ($exportQuery ? '?' . http_build_query($exportQuery) : '');
                    @endphp
                    <a href="{{ $exportRecapUrl }}" class="btn btn-success btn-sm rounded-pill">
                        <i class="bi bi-download me-1"></i>Export Data
                    </a>
                    <button type="button" class="btn btn-outline-info btn-sm rounded-pill" data-bs-toggle="modal"
                        data-bs-target="#modalRumus">
                        <i class="bi bi-calculator me-1"></i>Lihat Rumus
                    </button>
                </div>
            </div>

            {{-- Filter Kode Buku (hanya data sesuai kode buku & cutoff aktif) --}}
            <form method="GET" action="{{ route('recap.index') }}" class="mb-3 row g-2 align-items-end">
                <div class="col-auto">
                    <label for="filter_book_code" class="form-label small mb-0">Kode Buku</label>
                    <input type="text" id="filter_book_code" name="book_code" class="form-control form-control-sm"
                        placeholder="Kode buku (kosongkan = semua)"
                        value="{{ request('book_code', $filterBookCode ?? '') }}" style="min-width: 180px;" />
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('recap.index') }}" class="btn btn-outline-secondary btn-sm">Tampilkan Semua</a>
                </div>
                @if (!empty($filterBookCode ?? ''))
                    <div class="col-auto">
                        <span class="badge bg-secondary">Filter: <strong>{{ $filterBookCode }}</strong>
                            @if (!empty($filterBookTitle ?? ''))
                                — {{ Str::limit($filterBookTitle, 40) }}
                            @endif
                        </span>
                    </div>
                @endif
            </form>

            <div class="table-responsive" style="max-height: 80vh;">
                <table class="table table-bordered table-sm" id="rekapTable" style="font-size: 11px;">
                    <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                        <!-- Header Row 1: Main Groups -->
                        <tr class="table-secondary">
                            <th rowspan="3" class="text-center align-middle" style="min-width: 50px;">NO</th>
                            <th rowspan="3" class="text-center align-middle" style="min-width: 200px;">CABANG </th>
                            <th colspan="4" class="text-center bg-success text-white">PENJUALAN SMT 1 2026 / 2027
                            </th>
                            <th rowspan="3" class="text-center align-middle bg-info text-white"
                                style="min-width: 120px;">NKB DARI PUSAT A </th>
                            <th rowspan="3" class="text-center align-middle bg-info text-white"
                                style="min-width: 120px;">STOCK CABANG </th>
                            <th colspan="4" class="text-center bg-warning text-white">KETERSEDIAAN STOCK</th>
                            <th colspan="3" class="text-center bg-danger text-white">RENCANA NPPB PUSAT CIAWI</th>
                            <th colspan="3" class="text-center bg-secondary text-white">% STOCK THD</th>
                        </tr>
                        <!-- Header Row 2: Sub Groups -->
                        <tr class="table-secondary">
                            <th class="text-center bg-success text-white">TARGET </th>
                            <th class="text-center bg-success text-white">SP </th>
                            <th class="text-center bg-success text-white">FAKTUR </th>
                            <th class="text-center bg-success text-white">SISA SP
                            </th>
                            <th colspan="2" class="text-center bg-warning text-white">THD TARGET</th>
                            <th colspan="2" class="text-center bg-warning text-white">THD SP</th>
                            <th class="text-center bg-danger text-white">KOLI </th>
                            <th class="text-center bg-danger text-white">PLS </th>
                            <th class="text-center bg-danger text-white">EXP </th>
                            <th class="text-center bg-secondary text-white">REAL </th>
                            <th class="text-center bg-secondary text-white">TARGET
                            </th>
                            <th class="text-center bg-secondary text-white">SP </th>
                        </tr>
                        <!-- Header Row 3: Final Column Names (LEBIH & KURANG for THD TARGET & THD SP) -->
                        <tr class="table-secondary">
                            <!-- NO dan CABANG sudah rowspan 3, tidak perlu di row 3 -->
                            <th class="text-center bg-success text-white"></th>
                            <!-- TARGET (PENJUALAN) -->
                            <th class="text-center bg-success text-white"></th>
                            <!-- SP (PENJUALAN) -->
                            <th class="text-center bg-success text-white"></th>
                            <!-- FAKTUR (PENJUALAN) -->
                            <th class="text-center bg-success text-white"></th>
                            <!-- SISA SP (PENJUALAN) -->
                            <!-- NKB DARI PUSAT A sudah rowspan 3, tidak perlu di row 3 -->
                            <!-- STOCK CABANG sudah rowspan 3, tidak perlu di row 3 -->
                            <!-- THD TARGET: 2 kolom LEBIH dan KURANG -->
                            <th class="text-center bg-warning text-white">LEBIH</th>
                            <th class="text-center bg-warning text-white">KURANG</th>
                            <!-- THD SP: 2 kolom LEBIH dan KURANG -->
                            <th class="text-center bg-warning text-white">LEBIH</th>
                            <th class="text-center bg-warning text-white">KURANG</th>
                            <th class="text-center bg-danger text-white"></th>
                            <!-- KOLI (RENCANA NPPB) -->
                            <th class="text-center bg-danger text-white"></th>
                            <!-- PLS (RENCANA NPPB) -->
                            <th class="text-center bg-danger text-white"></th>
                            <!-- EXP (RENCANA NPPB) -->
                            <th class="text-center bg-secondary text-white"></th>
                            <!-- REAL (% STOCK THD) -->
                            <th class="text-center bg-secondary text-white"></th>
                            <!-- TARGET (% STOCK THD) -->
                            <th class="text-center bg-secondary text-white"></th>
                            <!-- SP (% STOCK THD) -->
                            <!-- % sudah rowspan 3, tidak perlu di row 3 -->
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $rowNumber = 1;
                        @endphp

                        <!-- NASIONAL ROW -->
                        <tr class="table-primary text-white fw-bold">
                            <td class="text-center">{{ $rowNumber++ }}</td>
                            <td class="fw-bold">NASIONAL</td>
                            <td class="text-end">{{ number_format($nasional['target'] ?? 0) }}</td>
                            <td class="text-end">{{ number_format($nasional['total_sp'] ?? 0) }}</td>
                            <td class="text-end">{{ number_format($nasional['total_faktur'] ?? 0) }}</td>
                            <td class="text-end">
                                {{ number_format($nasional['sisa_sp'] ?? 0) }}
                            </td>
                            <td class="text-end">{{ number_format($nasional['total_nkb'] ?? 0) }}</td>
                            <td class="text-end">{{ number_format($nasional['total_stok_cabang'] ?? 0) }}
                            </td>
                            @php
                                $thdTargetLebih = $nasional['thd_target_lebih'] ?? 0;
                                $thdTargetKurang = $nasional['thd_target_kurang'] ?? 0;
                                $thdSpLebih = $nasional['thd_sp_lebih'] ?? 0;
                                $thdSpKurang = $nasional['thd_sp_kurang'] ?? 0;
                            @endphp
                            <td class="text-end">
                                {{ $thdTargetLebih > 0 ? number_format($thdTargetLebih) : '-' }}</td>
                            <td class="text-end text-danger">
                                {{ $thdTargetKurang > 0 ? '(' . number_format($thdTargetKurang) . ')' : '-' }}
                            </td>
                            <td class="text-end">{{ $thdSpLebih > 0 ? number_format($thdSpLebih) : '-' }}
                            </td>
                            <td class="text-end text-danger">
                                {{ $thdSpKurang > 0 ? '(' . number_format($thdSpKurang) . ')' : '-' }}
                            </td>
                            <td class="text-end">{{ number_format($nasional['total_nppb_koli'] ?? 0) }}</td>
                            <td class="text-end">{{ number_format($nasional['total_nppb_pls'] ?? 0) }}</td>
                            <td class="text-end">{{ number_format($nasional['total_nppb_exp'] ?? 0) }}</td>
                            <td class="text-end">-</td>
                            <td class="text-end">-</td>
                            <td class="text-end">-</td>
                        </tr>

                        @foreach ($areas as $areaName => $area)
                            @foreach ($area['branches'] as $branch)
                                <!-- BRANCH ROW -->
                                <tr>
                                    <td class="text-center">{{ $rowNumber++ }}</td>
                                    <td class="ps-4">
                                        <a href="{{ route('recap.detail', ['branch_code' => $branch->branch_code]) }}" class="text-decoration-none fw-medium">
                                            {{ $branch->branch_name ?? $branch->branch_code }}
                                        </a>
                                    </td>
                                    <td class="text-end">{{ number_format($branch->target ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($branch->total_sp ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($branch->total_faktur ?? 0) }}
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($branch->sisa_sp ?? 0) }}
                                    </td>
                                    <td class="text-end">{{ number_format($branch->total_nkb ?? 0) }}
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($branch->total_stok_cabang ?? 0) }}</td>
                                    @php
                                        $branchThdTargetLebih = $branch->thd_target_lebih ?? 0;
                                        $branchThdTargetKurang = $branch->thd_target_kurang ?? 0;
                                        $branchThdSpLebih = $branch->thd_sp_lebih ?? 0;
                                        $branchThdSpKurang = $branch->thd_sp_kurang ?? 0;
                                    @endphp
                                    <td class="text-end">
                                        {{ $branchThdTargetLebih > 0 ? number_format($branchThdTargetLebih) : '-' }}
                                    </td>
                                    <td class="text-end text-danger">
                                        {{ $branchThdTargetKurang > 0 ? '(' . number_format($branchThdTargetKurang) . ')' : '-' }}
                                    </td>
                                    <td class="text-end">
                                        {{ $branchThdSpLebih > 0 ? number_format($branchThdSpLebih) : '-' }}
                                    </td>
                                    <td class="text-end text-danger">
                                        {{ $branchThdSpKurang > 0 ? '(' . number_format($branchThdSpKurang) . ')' : '-' }}
                                    </td>
                                    <td class="text-end">{{ number_format($branch->nppb_koli ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($branch->nppb_pls ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($branch->nppb_exp ?? 0) }}</td>
                                    @php
                                        // % STOCK THD calculations for branch
                                        $branchRealisasiTotal = 0; // Placeholder - data historis belum ada
                                        $branchPercentReal =
                                            $branchRealisasiTotal > 0
                                                ? round(
                                                    (($branch->total_stok_cabang ?? 0) / $branchRealisasiTotal) * 100,
                                                )
                                                : 0;
                                        $branchPercentTarget =
                                            ($branch->target ?? 0) > 0
                                                ? round(
                                                    (($branch->total_stok_cabang ?? 0) / ($branch->target ?? 1)) * 100,
                                                )
                                                : 0;
                                        $branchPercentSp =
                                            ($branch->total_sp ?? 0) > 0
                                                ? round(
                                                    (($branch->total_stok_cabang ?? 0) / ($branch->total_sp ?? 1)) *
                                                        100,
                                                )
                                                : 0;
                                    @endphp
                                    <td class="text-end">{{ $branchPercentReal > 0 ? $branchPercentReal . '%' : '-' }}
                                    </td>
                                    <td class="text-end">
                                        {{ $branchPercentTarget > 0 ? $branchPercentTarget . '%' : '-' }}</td>
                                    <td class="text-end">{{ $branchPercentSp > 0 ? $branchPercentSp . '%' : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        /* Specific styles for rekapitulasi table */
        #rekapTable thead th {
            border: 1px solid #dee2e6;
            white-space: nowrap;
            box-shadow: unset !important;
        }

        #rekapTable tbody td {
            border: 1px solid #dee2e6;
            white-space: nowrap;
        }

        .table-primary {
            background-color: #0d6efd !important;
        }

        .table-warning {
            background-color: #ffc107 !important;
        }

        .text-danger {
            color: #dc3545 !important;
        }
    </style>

    <!-- Modal Rumus -->
    <div class="modal fade" id="modalRumus" tabindex="-1" aria-labelledby="modalRumusLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRumusLabel">
                        <i class="bi bi-calculator me-2"></i>Rumus yang Digunakan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <h6 class="text-success fw-bold mb-2">PENJUALAN</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li><strong>TARGET:</strong> Jumlah exemplar dari tabel <code>targets</code> per cabang
                                (filter periode/cutoff)</li>
                            <li><strong>SP:</strong> SUM(ex_sp) dari <code>sp_branches</code></li>
                            <li><strong>FAKTUR:</strong> SUM(ex_ftr) dari <code>sp_branches</code></li>
                            <li><strong>SISA SP:</strong> SP − FAKTUR</li>
                        </ul>
                    </div>
                    <div class="mb-4">
                        <h6 class="text-info fw-bold mb-2">STOCK & NKB</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li><strong>NKB DARI PUSAT A:</strong> SUM(ex_rec_pst) dari <code>sp_branches</code></li>
                            <li><strong>STOCK CABANG:</strong> SUM(ex_stock) dari <code>sp_branches</code></li>
                        </ul>
                    </div>
                    <div class="mb-4">
                        <h6 class="text-warning fw-bold mb-2">KETERSEDIAAN STOCK</h6>
                        <p class="small mb-2">Dihitung <strong>per buku</strong> per cabang, lalu dijumlah:</p>
                        <p class="small mb-1"><strong>THD TARGET:</strong></p>
                        <ul class="list-unstyled mb-2 small">
                            <li>• <strong>LEBIH:</strong> Jika stock &gt; target → Σ(stock − target)</li>
                            <li>• <strong>KURANG:</strong> Jika stock &lt; target → Σ(target − stock)</li>
                        </ul>
                        <p class="small mb-1"><strong>THD SP:</strong></p>
                        <ul class="list-unstyled mb-0 small">
                            <li>• <strong>LEBIH:</strong> Jika stock &gt; SP → Σ(stock − SP)</li>
                            <li>• <strong>KURANG:</strong> Jika stock &lt; SP → Σ(SP − stock)</li>
                        </ul>
                    </div>
                    <div class="mb-4">
                        <h6 class="text-danger fw-bold mb-2">RENCANA NPPB PUSAT CIAWI</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li><strong>KOLI, PLS, EXP:</strong> SUM dari tabel <code>nppb_central</code> per cabang
                                (filter periode/cutoff)</li>
                        </ul>
                    </div>
                    <div class="mb-0">
                        <h6 class="text-secondary fw-bold mb-2">% STOCK THD</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li><strong>REAL:</strong> (Stock Cabang ÷ Realisasi Total) × 100 <span
                                    class="text-muted">(placeholder)</span></li>
                            <li><strong>TARGET:</strong> (Stock Cabang ÷ Target) × 100</li>
                            <li><strong>SP:</strong> (Stock Cabang ÷ SP) × 100</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts>
