<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <strong>Laporan Distribusi</strong><br />
                    <small class="text-muted">Rekapitulasi total eksemplar: NPPB, NKB, Surat Jalan, NTB</small>
                </div>
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('laporan-distribusi.index') }}" class="row g-2 mb-4">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control form-control-sm"
                        value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Tanggal Akhir</label>
                    <input type="date" name="end_date" class="form-control form-control-sm"
                        value="{{ $endDate }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm">Kode Buku (opsional)</label>
                    <input type="text" name="book_code" class="form-control form-control-sm"
                        placeholder="Cth: 00002111" value="{{ $filterBook }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search me-1"></i>Tampilkan
                    </button>
                </div>
            </form>

            {{-- Summary Cards --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-primary-subtle h-100">
                        <div class="card-body text-center py-3">
                            <div class="fs-2 fw-bold text-primary">{{ number_format($totalNppb) }}</div>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-truck me-1"></i>NPPB
                            </div>
                            <div class="text-muted" style="font-size:11px;">Nota Pengantar Pengiriman Buku</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-success-subtle h-100">
                        <div class="card-body text-center py-3">
                            <div class="fs-2 fw-bold text-success">{{ number_format($totalNkb) }}</div>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-file-earmark-text me-1"></i>NKB
                            </div>
                            <div class="text-muted" style="font-size:11px;">Nota Kirim Buku</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-warning-subtle h-100">
                        <div class="card-body text-center py-3">
                            <div class="fs-2 fw-bold text-warning">{{ number_format($totalSj) }}</div>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-truck-front me-1"></i>Surat Jalan
                            </div>
                            <div class="text-muted" style="font-size:11px;">Delivery Order</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-info-subtle h-100">
                        <div class="card-body text-center py-3">
                            <div class="fs-2 fw-bold text-info">{{ number_format($totalNtb) }}</div>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-box-arrow-in-down me-1"></i>NTB
                            </div>
                            <div class="text-muted" style="font-size:11px;">Nota Terima Buku</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Periode Info --}}
            <div class="alert alert-secondary py-2 mb-3" role="alert">
                <i class="bi bi-calendar-range me-1"></i>
                Periode:
                <strong>{{ \Carbon\Carbon::parse($startDate)->translatedFormat('d M Y') }}</strong>
                s/d
                <strong>{{ \Carbon\Carbon::parse($endDate)->translatedFormat('d M Y') }}</strong>
                @if ($filterBook)
                    &nbsp;|&nbsp; Buku: <strong>{{ $filterBook }}</strong>
                @endif
            </div>

            {{-- Per-Book Table --}}
            @if ($bookRows->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">No</th>
                                <th>Kode Buku</th>
                                <th>Nama Buku</th>
                                <th class="text-end">NPPB</th>
                                <th class="text-end">NKB</th>
                                <th class="text-end">NTB</th>
                                <th class="text-end">Total (NPPB+NKB+NTB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookRows as $i => $row)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td><code>{{ $row['book_code'] }}</code></td>
                                    <td>{{ $row['book_name'] }}</td>
                                    <td class="text-end">{{ number_format($row['nppb']) }}</td>
                                    <td class="text-end">{{ number_format($row['nkb']) }}</td>
                                    <td class="text-end">{{ number_format($row['ntb']) }}</td>
                                    <td class="text-end fw-semibold">
                                        {{ number_format($row['nppb'] + $row['nkb'] + $row['ntb']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td colspan="3">Total</td>
                                <td class="text-end">{{ number_format($totalNppb) }}</td>
                                <td class="text-end">{{ number_format($totalNkb) }}</td>
                                <td class="text-end">{{ number_format($totalNtb) }}</td>
                                <td class="text-end">{{ number_format($totalNppb + $totalNkb + $totalNtb) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Surat Jalan tidak ditampilkan per buku karena tidak menyimpan rincian per kode buku.
                    Total Surat Jalan: <strong>{{ number_format($totalSj) }}</strong> eksemplar.
                </small>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Tidak ada data pada periode ini.
                </div>
            @endif
        </div>
    </div>
</x-layouts>
