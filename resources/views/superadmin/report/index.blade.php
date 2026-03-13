<x-layouts>
    <div class="row mb-3">
        <div class="col-12">
            <h4 class="mb-0"><strong>Report NASIONAL</strong></h4>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-2">
                    <strong>{{ $branchCode ? $selectedBranchName ?? $branchCode : 'NASIONAL' }}{{ $branchCode ? ' (' . $branchCode . ')' : '' }}</strong>
                    — Tahun {{ $year ?? date('Y') }}
                </div>
                <div class="card-body p-0 overflow-auto">
                    <table class="table table-bordered table-sm mb-0 report-table">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" class="align-middle">Segment</th>
                                <th rowspan="2" class="align-middle">Kategori</th>
                                <th rowspan="2" class="align-middle text-end">Realisasi {{ $year - 1 }}</th>
                                <th colspan="2" class="text-center">TARGET</th>
                                <th rowspan="2" class="align-middle text-end">SP</th>
                                <th rowspan="2" class="align-middle text-center">%</th>
                                <th rowspan="2" class="align-middle text-end">Faktur</th>
                                <th rowspan="2" class="align-middle text-center">%</th>
                                <th rowspan="2" class="align-middle text-end">Stock Cabang</th>
                                <th rowspan="2" class="align-middle text-end">Stock B' SP</th>
                                <th rowspan="2" class="align-middle text-center">%</th>
                                <th colspan="2" class="text-center">Stock thd SP</th>
                                <th colspan="2" class="text-center">NPPB</th>
                            </tr>
                            <tr>
                                <th class="text-end">Cabang</th>
                                <th class="text-end">Pusat</th>
                                <th class="text-end">Lebih</th>
                                <th class="text-end">Kurang</th>
                                <th class="text-end">Eks</th>
                                <th class="text-center">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $currentSegment = null;
                                $segmentOrder = $segmentOrder ?? [];
                                $kategoriOrder = $kategoriOrder ?? [];
                            @endphp
                            @foreach ($segmentOrder as $seg)
                                @foreach ($kategoriOrder as $kat)
                                    @php
                                        $r = collect($rows ?? [])->first(
                                            fn($x) => ($x['segment'] ?? '') === $seg && ($x['kategori'] ?? '') === $kat,
                                        );
                                        if (!$r) {
                                            continue;
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $r['segment'] }}</td>
                                        <td>{{ $r['kategori'] }}</td>
                                        <td class="text-end">{{ number_format($r['realisasi_prev'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($r['target_cabang'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($r['target_pusat'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($r['sp'], 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $r['pct_sp'] }}%</td>
                                        <td class="text-end">{{ number_format($r['faktur'], 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $r['pct_faktur'] }}%</td>
                                        <td class="text-end">{{ number_format($r['stock_cabang'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($r['stock_b_sp'], 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $r['pct_stock_faktur'] }}%</td>
                                        <td class="text-end">{{ number_format($r['lebih'], 0, ',', '.') }}</td>
                                        <td class="text-end @if ($r['kurang'] < 0) text-danger @endif">
                                            @if ($r['kurang'] < 0)
                                                ({{ number_format(abs($r['kurang']), 0, ',', '.') }})
                                            @else
                                                {{ number_format($r['kurang'], 0, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($r['nppb_eks'], 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $r['pct_nppb'] }}%</td>
                                    </tr>
                                @endforeach
                                @php $tot = $totalsBySegment[$seg] ?? null; @endphp
                                @if ($tot)
                                    <tr class="table-primary fw-bold">
                                        <td>{{ $seg }}</td>
                                        <td>Total</td>
                                        <td class="text-end">{{ number_format($tot['realisasi_prev'], 0, ',', '.') }}
                                        </td>
                                        <td class="text-end">{{ number_format($tot['target_cabang'], 0, ',', '.') }}
                                        </td>
                                        <td class="text-end">{{ number_format($tot['target_pusat'], 0, ',', '.') }}
                                        </td>
                                        <td class="text-end">{{ number_format($tot['sp'], 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $tot['pct_sp'] }}%</td>
                                        <td class="text-end">{{ number_format($tot['faktur'], 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $tot['pct_faktur'] }}%</td>
                                        <td class="text-end">{{ number_format($tot['stock_cabang'], 0, ',', '.') }}
                                        </td>
                                        <td class="text-end">{{ number_format($tot['stock_b_sp'], 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $tot['pct_stock_faktur'] }}%</td>
                                        <td class="text-end">{{ number_format($tot['lebih'], 0, ',', '.') }}</td>
                                        <td class="text-end @if ($tot['kurang'] < 0) text-danger @endif">
                                            @if ($tot['kurang'] < 0)
                                                ({{ number_format(abs($tot['kurang']), 0, ',', '.') }})
                                            @else
                                                {{ number_format($tot['kurang'], 0, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($tot['nppb_eks'], 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $tot['pct_nppb'] }}%</td>
                                    </tr>
                                @endif
                            @endforeach
                            <tr class="table-dark fw-bold">
                                <td colspan="2">Grand Total</td>
                                <td class="text-end">{{ number_format($grand['realisasi_prev'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="text-end">{{ number_format($grand['target_cabang'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="text-end">{{ number_format($grand['target_pusat'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($grand['sp'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $grandPctSp ?? 0 }}%</td>
                                <td class="text-end">{{ number_format($grand['faktur'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $grandPctFaktur ?? 0 }}%</td>
                                <td class="text-end">{{ number_format($grand['stock_cabang'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($grand['stock_b_sp'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $grandPctStockFaktur ?? 0 }}%</td>
                                <td class="text-end">{{ number_format($grand['lebih'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end @if (($grand['kurang'] ?? 0) < 0) text-danger @endif">
                                    @if (($grand['kurang'] ?? 0) < 0)
                                        ({{ number_format(abs($grand['kurang'] ?? 0), 0, ',', '.') }})
                                    @else
                                        {{ number_format($grand['kurang'] ?? 0, 0, ',', '.') }}
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($grand['nppb_eks'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $grandPctNppb ?? 0 }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-primary text-white py-2">
                    <strong>DASHBOARD</strong>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Filter sama dengan tabel di samping (Area & Cabang).</p>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Area</label>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($areas ?? [] as $a)
                                @php
                                    $code = $a['code'] ?? '';
                                    $name = $a['name'] ?? ($a['code'] ?? 'Nasional');
                                    $areaHref = $code === '' ? url('report') . '?year=' . $year : url('report') . '?year=' . $year . '&area=' . urlencode($code) . '&branch=';
                                @endphp
                                <a href="{{ $areaHref }}"
                                    class="btn btn-sm {{ ($area ?? '') === $code ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $name }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Cabang</label>
                        <div id="reportCabangButtons" class="d-flex flex-wrap gap-1" data-year="{{ $year }}"
                            data-area="{{ $area ?? '' }}" data-selected-branch="{{ $branchCode ?? '' }}"
                            data-report-url="{{ url('report') }}"
                            data-branches-api-url="{{ url('api/report/branches-by-area') }}">
                            @if (!empty($area))
                                <span class="text-muted small" id="reportCabangLoading">Memuat cabang...</span>
                            @else
                                <span class="text-muted small">Pilih area dulu untuk melihat daftar cabang.</span>
                            @endif
                        </div>
                    </div>
                    <hr>
                    <p class="small fw-bold mb-2">Target vs SP vs Faktur per Segment</p>
                    <div class="chart-wrapper" style="height: 220px;">
                        <canvas id="chartSegment"></canvas>
                    </div>
                    <p class="small fw-bold mb-2 mt-3">Target / SP / Faktur per Kategori</p>
                    <div class="chart-wrapper" style="height: 220px;">
                        <canvas id="chartKategori"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .report-table {
            font-size: 0.75rem;
        }

        .report-table th,
        .report-table td {
            padding: 0.35rem 0.5rem;
            white-space: nowrap;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
        }
    </style>

    @php
        $chartSegmentLabels = $chartSegmentLabels ?? [];
        $chartSegmentTarget = $chartSegmentTarget ?? [];
        $chartSegmentSp = $chartSegmentSp ?? [];
        $chartSegmentFaktur = $chartSegmentFaktur ?? [];
        $chartKategoriTarget = $chartKategoriTarget ?? [];
        $chartKategoriSp = $chartKategoriSp ?? [];
        $chartKategoriFaktur = $chartKategoriFaktur ?? [];
    @endphp
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var segmentLabels = @json($chartSegmentLabels);
            var segmentTarget = @json($chartSegmentTarget);
            var segmentSp = @json($chartSegmentSp);
            var segmentFaktur = @json($chartSegmentFaktur);
            var kategoriOrder = @json($kategoriOrder ?? []);
            var kategoriTarget = @json($chartKategoriTarget);
            var kategoriSp = @json($chartKategoriSp);
            var kategoriFaktur = @json($chartKategoriFaktur);
            var kategoriColors = ['rgba(234, 179, 8, 0.8)', 'rgba(59, 130, 246, 0.8)', 'rgba(30, 64, 175, 0.8)',
                'rgba(34, 197, 94, 0.8)', 'rgba(249, 115, 22, 0.8)'
            ];

            if (document.getElementById('chartSegment')) {
                new Chart(document.getElementById('chartSegment'), {
                    type: 'bar',
                    data: {
                        labels: segmentLabels,
                        datasets: [{
                                label: 'Target',
                                data: segmentTarget,
                                backgroundColor: 'rgba(234, 179, 8, 0.8)',
                                borderColor: '#ca8a04'
                            },
                            {
                                label: 'SP',
                                data: segmentSp,
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                borderColor: '#2563eb'
                            },
                            {
                                label: 'Faktur',
                                data: segmentFaktur,
                                backgroundColor: 'rgba(30, 64, 175, 0.8)',
                                borderColor: '#1e40af'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            x: {
                                stacked: false
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(v) {
                                        return new Intl.NumberFormat('id-ID').format(v);
                                    }
                                }
                            }
                        }
                    }
                });
            }
            if (document.getElementById('chartKategori')) {
                var kategoriDatasets = (kategoriOrder || []).map(function(kat, i) {
                    return {
                        label: kat,
                        data: [kategoriTarget[i] || 0, kategoriSp[i] || 0, kategoriFaktur[i] || 0],
                        backgroundColor: kategoriColors[i % kategoriColors.length]
                    };
                });
                new Chart(document.getElementById('chartKategori'), {
                    type: 'bar',
                    data: {
                        labels: ['TARGET', 'SP', 'FAKTUR'],
                        datasets: kategoriDatasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            x: {
                                stacked: false
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(v) {
                                        return new Intl.NumberFormat('id-ID').format(v);
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Filter Cabang: load branches by area via AJAX dan render sebagai button (bentuk sama dengan Area)
            var cabangEl = document.getElementById('reportCabangButtons');
            if (cabangEl) {
                var areaCode = cabangEl.getAttribute('data-area') || '';
                var yearVal = cabangEl.getAttribute('data-year') || '';
                var selectedBranch = cabangEl.getAttribute('data-selected-branch') || '';
                var reportUrl = cabangEl.getAttribute('data-report-url') || '';
                var apiUrl = cabangEl.getAttribute('data-branches-api-url') || '';
                if (areaCode && apiUrl) {
                    fetch(apiUrl + '?warehouse_code=' + encodeURIComponent(areaCode))
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(data) {
                            var loading = document.getElementById('reportCabangLoading');
                            if (loading) loading.remove();
                            cabangEl.innerHTML = '';
                            var semuaBtn = document.createElement('a');
                            semuaBtn.href = reportUrl + '?year=' + encodeURIComponent(yearVal) + '&area=' +
                                encodeURIComponent(areaCode) + '&branch=';
                            semuaBtn.className = 'btn btn-sm ' + (selectedBranch ? 'btn-outline-secondary' :
                                'btn-primary');
                            semuaBtn.textContent = 'Semua';
                            cabangEl.appendChild(semuaBtn);
                            (data.branches || []).forEach(function(b) {
                                var btn = document.createElement('a');
                                btn.href = reportUrl + '?year=' + encodeURIComponent(yearVal) +
                                    '&area=' + encodeURIComponent(areaCode) + '&branch=' +
                                    encodeURIComponent(b.branch_code);
                                btn.className = 'btn btn-sm ' + (b.branch_code === selectedBranch ?
                                    'btn-primary' : 'btn-outline-secondary');
                                btn.textContent = b.branch_code + ' - ' + (b.branch_name && b
                                    .branch_name.length > 25 ? b.branch_name.substring(0, 25) +
                                    '…' : (b.branch_name || b.branch_code));
                                cabangEl.appendChild(btn);
                            });
                        })
                        .catch(function() {
                            var loading = document.getElementById('reportCabangLoading');
                            if (loading) loading.textContent = 'Gagal memuat cabang.';
                        });
                }
            }
        });
    </script>
</x-layouts>
