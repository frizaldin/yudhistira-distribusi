<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Rekapitulasi Cabang</strong><br />
                    <small class="text-muted">{{ $branchInfo->branch_name ?? 'Cabang' }} ({{ $branchInfo->branch_code ?? '' }})</small>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @php
                        $exportQuery = array_filter(['year' => request('year', $year ?? date('Y')), 'book_code' => request('book_code', $filterBookCode ?? '')]);
                        $exportRecapUrl = route('recap.export') . ($exportQuery ? '?' . http_build_query($exportQuery) : '');
                    @endphp
                    <a href="{{ $exportRecapUrl }}" class="btn btn-success btn-sm rounded-pill">
                        <i class="bi bi-download me-1"></i>Export Data
                    </a>
                    <button type="button" class="btn btn-outline-info btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalRumus">
                        <i class="bi bi-calculator me-1"></i>Lihat Rumus
                    </button>
                </div>
            </div>

            <!-- Summary Row -->
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm" style="font-size: 11px;">
                    <thead class="table-secondary">
                        <tr>
                            <th colspan="4" class="bg-success text-white text-center">PENJUALAN SMT 1 {{ $year }} / {{ $year + 1 }}</th>
                            <th class="bg-info text-white text-center">STOCK CABANG</th>
                            <th colspan="4" class="bg-warning text-white text-center">KETERSEDIAAN STOCK</th>
                            <th colspan="3" class="bg-danger text-white text-center">RENCANA NPPB PUSAT CIAWI</th>
                            <th colspan="2" class="bg-secondary text-white text-center">% STOCK THD</th>
                        </tr>
                        <tr>
                            <th class="bg-success text-white">TARGET</th>
                            <th class="bg-success text-white">SP</th>
                            <th class="bg-success text-white">FAKTUR</th>
                            <th class="bg-success text-white">SISA SP</th>
                            <th class="bg-info text-white"></th>
                            <th colspan="2" class="bg-warning text-white text-center">THD TARGET</th>
                            <th colspan="2" class="bg-warning text-white text-center">THD SP</th>
                            <th class="bg-danger text-white">KOLI</th>
                            <th class="bg-danger text-white">PLS</th>
                            <th class="bg-danger text-white">EXP</th>
                            <th class="bg-secondary text-white">TARGET</th>
                            <th class="bg-secondary text-white">SP</th>
                        </tr>
                        <tr>
                            <th class="bg-success text-white"></th>
                            <th class="bg-success text-white"></th>
                            <th class="bg-success text-white"></th>
                            <th class="bg-success text-white"></th>
                            <th class="bg-info text-white"></th>
                            <th class="bg-warning text-white">LEBIH</th>
                            <th class="bg-warning text-white">KURANG</th>
                            <th class="bg-warning text-white">LEBIH</th>
                            <th class="bg-warning text-white">KURANG</th>
                            <th class="bg-danger text-white"></th>
                            <th class="bg-danger text-white"></th>
                            <th class="bg-danger text-white"></th>
                            <th class="bg-secondary text-white"></th>
                            <th class="bg-secondary text-white"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-info fw-bold">
                            <td class="text-end">{{ number_format($branchTotals['target'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['sp'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['faktur'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['sisa_sp'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['stok_cabang'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['stok_thd_target_lebih'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['stok_thd_target_kurang'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['stok_thd_sp_lebih'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['stok_thd_sp_kurang'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['nppb_koli'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['nppb_pls'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['nppb_exp'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($branchTotals['pct_stok_thd_target'] ?? 0, 0, ',', '.') }}%</td>
                            <td class="text-end">{{ number_format($branchTotals['pct_stok_thd_sp'] ?? 0, 0, ',', '.') }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Detail Table -->
            <div class="table-responsive" style="max-height: 70vh;">
                <table class="table table-bordered table-sm" style="font-size: 11px;">
                    <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                        <!-- Row 1: Main Headers -->
                        <tr class="table-secondary">
                            <th rowspan="3" class="text-center align-middle" style="min-width: 50px;">KODE</th>
                            <th rowspan="3" class="text-center align-middle" style="min-width: 300px;">JUDUL BUKU</th>
                            <th colspan="4" class="text-center bg-success text-white">PENJUALAN SMT 1 {{ $year }} / {{ $year + 1 }}</th>
                            <th rowspan="3" class="text-center align-middle bg-info text-white">STOCK CABANG</th>
                            <th colspan="4" class="text-center bg-warning text-white">KETERSEDIAAN STOCK</th>
                            <th colspan="3" class="text-center bg-danger text-white">RENCANA NPPB PUSAT CIAWI</th>
                            <th colspan="2" class="text-center bg-secondary text-white">% STOCK THD</th>
                        </tr>
                        <!-- Row 2: Sub Headers -->
                        <tr class="table-secondary">
                            <th class="bg-success text-white">TARGET</th>
                            <th class="bg-success text-white">SP</th>
                            <th class="bg-success text-white">FAKTUR</th>
                            <th class="bg-success text-white">SISA SP</th>
                            <th colspan="2" class="bg-warning text-white text-center">THD TARGET</th>
                            <th colspan="2" class="bg-warning text-white text-center">THD SP</th>
                            <th class="bg-danger text-white">KOLI</th>
                            <th class="bg-danger text-white">PLS</th>
                            <th class="bg-danger text-white">EXP</th>
                            <th class="bg-secondary text-white">TARGET</th>
                            <th class="bg-secondary text-white">SP</th>
                        </tr>
                        <!-- Row 3: Final Sub Headers -->
                        <tr class="table-secondary">
                            <th class="bg-success text-white"></th>
                            <th class="bg-success text-white"></th>
                            <th class="bg-success text-white"></th>
                            <th class="bg-success text-white"></th>
                            <th class="bg-warning text-white">LEBIH</th>
                            <th class="bg-warning text-white">KURANG</th>
                            <th class="bg-warning text-white">LEBIH</th>
                            <th class="bg-warning text-white">KURANG</th>
                            <th class="bg-danger text-white"></th>
                            <th class="bg-danger text-white"></th>
                            <th class="bg-danger text-white"></th>
                            <th class="bg-secondary text-white"></th>
                            <th class="bg-secondary text-white"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $currentSegment = null;
                            $currentSubject = null;
                            $segmentBooks = [];
                        @endphp
                        @forelse($branchBooks ?? [] as $index => $book)
                            @php
                                $product = $products->get($book->book_code);
                                $segment = $product->book_segment ?? 'Lainnya';
                                $subject = $product->bid_study ?? 'Lainnya';

                                // Group by segment and subject
                                if ($currentSegment !== $segment) {
                                    // Print subtotal for previous segment/subject if exists
                                    if (!empty($segmentBooks)) {
                                        // Calculate subtotal
                                        $subtotal = [
                                            'target' => collect($segmentBooks)->sum('target'),
                                            'sp' => collect($segmentBooks)->sum('sp'),
                                            'faktur' => collect($segmentBooks)->sum('faktur'),
                                            'sisa_sp' => collect($segmentBooks)->sum('sisa_sp'),
                                            'stok_cabang' => collect($segmentBooks)->sum('stok_cabang'),
                                            'stok_thd_target_lebih' => collect($segmentBooks)->sum('stok_thd_target_lebih'),
                                            'stok_thd_target_kurang' => collect($segmentBooks)->sum('stok_thd_target_kurang'),
                                            'stok_thd_sp_lebih' => collect($segmentBooks)->sum('stok_thd_sp_lebih'),
                                            'stok_thd_sp_kurang' => collect($segmentBooks)->sum('stok_thd_sp_kurang'),
                                            'nppb_koli' => collect($segmentBooks)->sum('nppb_koli'),
                                            'nppb_pls' => collect($segmentBooks)->sum('nppb_pls'),
                                            'nppb_exp' => collect($segmentBooks)->sum('nppb_exp'),
                                        ];
                            @endphp
                                        <tr class="table-warning fw-bold">
                                            <td colspan="2" class="bg-warning-subtle">SUBTOTAL {{ $currentSubject }} {{ $currentSegment }}:</td>
                                            <td class="text-end">{{ number_format($subtotal['target'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['sp'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['faktur'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['sisa_sp'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['stok_cabang'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['stok_thd_target_lebih'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['stok_thd_target_kurang'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['stok_thd_sp_lebih'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['stok_thd_sp_kurang'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['nppb_koli'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['nppb_pls'], 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($subtotal['nppb_exp'], 0, ',', '.') }}</td>
                                            <td class="text-end">-</td>
                                            <td class="text-end">-</td>
                                        </tr>
                            @php
                                    }

                                    // New segment header
                                    if ($currentSegment !== $segment) {
                                        $currentSegment = $segment;
                                        $currentSubject = $subject;
                                        $segmentBooks = [];
                            @endphp
                                        <tr class="table-primary">
                                            <td colspan="16" class="bg-primary-subtle fw-bold">JENJANG {{ strtoupper($segment) }}</td>
                                        </tr>
                                        <tr class="table-info">
                                            <td colspan="16" class="bg-info-subtle fw-bold">{{ strtoupper($subject) }}</td>
                                        </tr>
                            @php
                                    }
                                }

                                $segmentBooks[] = $book;
                            @endphp
                            <tr>
                                <td>{{ $book->book_code }}</td>
                                <td>{{ $book->book_title }}</td>
                                <td class="text-end">{{ number_format($book->target ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->sp ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->faktur ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->sisa_sp ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->stok_cabang ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->stok_thd_target_lebih ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->stok_thd_target_kurang ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->stok_thd_sp_lebih ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->stok_thd_sp_kurang ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->nppb_koli ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->nppb_pls ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->nppb_exp ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($book->pct_stok_thd_target ?? 0, 0, ',', '.') }}%</td>
                                <td class="text-end">{{ number_format($book->pct_stok_thd_sp ?? 0, 0, ',', '.') }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="16" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Belum ada data
                                </td>
                            </tr>
                        @endforelse
                        @if(!empty($segmentBooks))
                            @php
                                $subtotal = [
                                    'target' => collect($segmentBooks)->sum('target'),
                                    'sp' => collect($segmentBooks)->sum('sp'),
                                    'faktur' => collect($segmentBooks)->sum('faktur'),
                                    'sisa_sp' => collect($segmentBooks)->sum('sisa_sp'),
                                    'stok_cabang' => collect($segmentBooks)->sum('stok_cabang'),
                                    'stok_thd_target_lebih' => collect($segmentBooks)->sum('stok_thd_target_lebih'),
                                    'stok_thd_target_kurang' => collect($segmentBooks)->sum('stok_thd_target_kurang'),
                                    'stok_thd_sp_lebih' => collect($segmentBooks)->sum('stok_thd_sp_lebih'),
                                    'stok_thd_sp_kurang' => collect($segmentBooks)->sum('stok_thd_sp_kurang'),
                                    'nppb_koli' => collect($segmentBooks)->sum('nppb_koli'),
                                    'nppb_pls' => collect($segmentBooks)->sum('nppb_pls'),
                                    'nppb_exp' => collect($segmentBooks)->sum('nppb_exp'),
                                ];
                            @endphp
                            <tr class="table-warning fw-bold">
                                <td colspan="2" class="bg-warning-subtle">SUBTOTAL {{ $currentSubject }} {{ $currentSegment }}:</td>
                                <td class="text-end">{{ number_format($subtotal['target'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['sp'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['faktur'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['sisa_sp'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['stok_cabang'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['stok_thd_target_lebih'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['stok_thd_target_kurang'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['stok_thd_sp_lebih'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['stok_thd_sp_kurang'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['nppb_koli'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['nppb_pls'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($subtotal['nppb_exp'], 0, ',', '.') }}</td>
                                <td class="text-end">-</td>
                                <td class="text-end">-</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
                            <li><strong>TARGET:</strong> Jumlah exemplar dari tabel <code>targets</code> per cabang (filter periode/cutoff)</li>
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
                        <p class="small mb-2">Dihitung <strong>per buku</strong> per cabang:</p>
                        <p class="small mb-1"><strong>THD TARGET:</strong></p>
                        <ul class="list-unstyled mb-2 small">
                            <li>• <strong>LEBIH:</strong> Jika stock &gt; target → stock − target</li>
                            <li>• <strong>KURANG:</strong> Jika stock &lt; target → target − stock</li>
                        </ul>
                        <p class="small mb-1"><strong>THD SP:</strong></p>
                        <ul class="list-unstyled mb-0 small">
                            <li>• <strong>LEBIH:</strong> Jika stock &gt; SP → stock − SP</li>
                            <li>• <strong>KURANG:</strong> Jika stock &lt; SP → SP − stock</li>
                        </ul>
                    </div>
                    <div class="mb-4">
                        <h6 class="text-danger fw-bold mb-2">RENCANA NPPB PUSAT CIAWI</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li><strong>KOLI, PLS, EXP:</strong> SUM dari tabel <code>nppb_central</code> per cabang (filter periode/cutoff)</li>
                        </ul>
                    </div>
                    <div class="mb-0">
                        <h6 class="text-secondary fw-bold mb-2">% STOCK THD</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li><strong>REAL:</strong> (Stock Cabang ÷ Realisasi Total) × 100</li>
                            <li><strong>TARGET:</strong> (Stock Cabang ÷ Target) × 100</li>
                            <li><strong>SP:</strong> (Stock Cabang ÷ SP) × 100</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts>
