<x-layouts>
    <div class="mb-3">
        <a href="{{ route('recap.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Rekapitulasi
        </a>
    </div>

    @if (isset($activeCutoff) && $activeCutoff)
        <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Cutoff Data:</strong>
            @if ($activeCutoff->start_date)
                {{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d/m/Y') }} –
                {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d/m/Y') }}
            @else
                s.d. {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d/m/Y') }}
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <strong>Detail Rekapitulasi: {{ $branch->branch_name ?? $branch->branch_code }}</strong>
                    <br><small class="text-muted">Semua buku dari master data beserta stock, SP, target cabang berdasarkan cutoff</small>
                </div>
                <div>
                    @php
                        $exportQuery = array_filter(['book_code' => $filterBookCode ?? '', 'book_name' => $filterBookName ?? '']);
                        $exportUrl = route('recap.detail.export', ['branch_code' => $branch->branch_code]) . ($exportQuery ? '?' . http_build_query($exportQuery) : '');
                    @endphp
                    <a href="{{ $exportUrl }}" class="btn btn-success btn-sm">
                        <i class="bi bi-download me-1"></i>Export Data
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('recap.detail', ['branch_code' => $branch->branch_code]) }}" class="row g-2 mb-3 align-items-end">
                <div class="col-auto">
                    <label for="filter_book_code" class="form-label small mb-0">Kode Buku</label>
                    <input type="text" id="filter_book_code" name="book_code" class="form-control form-control-sm" placeholder="Kode buku..."
                        value="{{ $filterBookCode ?? '' }}" style="min-width: 160px;" />
                </div>
                <div class="col-auto">
                    <label for="filter_book_name" class="form-label small mb-0">Nama Buku</label>
                    <input type="text" id="filter_book_name" name="book_name" class="form-control form-control-sm" placeholder="Nama buku..."
                        value="{{ $filterBookName ?? '' }}" style="min-width: 200px;" />
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="{{ route('recap.detail', ['branch_code' => $branch->branch_code]) }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive" style="max-height: 75vh;">
                <table class="table table-bordered table-sm table-hover" id="recapDetailTable" style="font-size: 11px;">
                    <thead style="position: sticky; top: 0; background: #f8f9fa; z-index: 10;">
                        <tr class="table-secondary">
                            <th class="text-center" style="width: 50px;">NO</th>
                            <th class="text-start" style="min-width: 120px;">Kode Buku</th>
                            <th class="text-start" style="min-width: 200px;">Nama Buku</th>
                            <th class="text-center bg-success text-white">TARGET</th>
                            <th class="text-center bg-success text-white">SP</th>
                            <th class="text-center bg-success text-white">FAKTUR</th>
                            <th class="text-center bg-success text-white">SISA SP</th>
                            <th class="text-center bg-info text-white">STOCK CABANG</th>
                            <th class="text-center bg-warning text-white">THD TARGET<br>LEBIH</th>
                            <th class="text-center bg-warning text-white">THD TARGET<br>KURANG</th>
                            <th class="text-center bg-warning text-white">THD SP<br>LEBIH</th>
                            <th class="text-center bg-warning text-white">THD SP<br>KURANG</th>
                            <th class="text-center bg-danger text-white">KOLI</th>
                            <th class="text-center bg-danger text-white">PLS</th>
                            <th class="text-center bg-danger text-white">EXP</th>
                            <th class="text-center bg-secondary text-white">% THD TARGET</th>
                            <th class="text-center bg-secondary text-white">% THD SP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $index => $r)
                            <tr>
                                <td class="text-center">{{ isset($paginator) ? $paginator->firstItem() + $index : $index + 1 }}</td>
                                <td class="text-start"><code>{{ $r->book_code }}</code></td>
                                <td class="text-start">{{ Str::limit($r->book_title, 50) }}</td>
                                <td class="text-end">{{ number_format($r->target) }}</td>
                                <td class="text-end">{{ number_format($r->sp) }}</td>
                                <td class="text-end">{{ number_format($r->faktur) }}</td>
                                <td class="text-end">{{ number_format($r->sisa_sp) }}</td>
                                <td class="text-end">{{ number_format($r->stock_cabang) }}</td>
                                <td class="text-end">{{ $r->thd_target_lebih > 0 ? number_format($r->thd_target_lebih) : '-' }}</td>
                                <td class="text-end text-danger">{{ $r->thd_target_kurang > 0 ? '(' . number_format($r->thd_target_kurang) . ')' : '-' }}</td>
                                <td class="text-end">{{ $r->thd_sp_lebih > 0 ? number_format($r->thd_sp_lebih) : '-' }}</td>
                                <td class="text-end text-danger">{{ $r->thd_sp_kurang > 0 ? '(' . number_format($r->thd_sp_kurang) . ')' : '-' }}</td>
                                <td class="text-end">{{ number_format($r->nppb_koli) }}</td>
                                <td class="text-end">{{ number_format($r->nppb_pls) }}</td>
                                <td class="text-end">{{ number_format($r->nppb_exp) }}</td>
                                <td class="text-end">{{ $r->pct_stock_target > 0 ? $r->pct_stock_target . '%' : '-' }}</td>
                                <td class="text-end">{{ $r->pct_stock_sp > 0 ? $r->pct_stock_sp . '%' : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="17" class="text-center py-4 text-muted">Tidak ada data buku. Ubah filter atau kosongkan untuk tampilkan semua dari master.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (isset($paginator) && $paginator->hasPages())
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} buku</small>
                    <div>{{ $paginator->withQueryString()->links() }}</div>
                </div>
            @endif
        </div>
    </div>
</x-layouts>
