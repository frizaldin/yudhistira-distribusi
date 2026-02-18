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
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item"><a href="{{ route('product.index') }}">Data Produk</a></li>
                            <li class="breadcrumb-item active">Detail per Cabang</li>
                        </ol>
                    </nav>
                    <h5 class="mb-1">Detail Produk: {{ $product->book_code }}</h5>
                    <p class="text-muted mb-0 small">{{ $product->book_title }}</p>
                    @if ($product->category ?? $product->jenjang)
                        <span class="badge bg-secondary mt-1">{{ $product->category ?? '-' }}</span>
                        <span class="badge bg-light text-dark mt-1">{{ $product->jenjang ?? '-' }}</span>
                    @endif
                </div>
                <a href="{{ route('product.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Produk
                </a>
            </div>

            <form method="GET" action="{{ route('product.detail', ['book_code' => $product->book_code]) }}"
                id="filterForm">
                <div class="row g-2 mb-3 align-items-end flex-wrap">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Tanggal Cutoff</label>
                        <select name="cutoff_id" class="form-select form-select-sm"
                            style="width: auto; min-width: 220px;">
                            @foreach ($cutoffs ?? [] as $c)
                                <option value="{{ $c->id }}"
                                    {{ isset($cutoff) && $cutoff->id == $c->id ? 'selected' : '' }}>
                                    @if ($c->start_date){{ $c->start_date->format('d/m/Y') }} – @else s.d. @endif{{ $c->end_date->format('d/m/Y') }}
                                    @if ($c->status === 'active')
                                        <span class="text-success">(aktif)</span>
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0">Filter Nama Cabang</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                            placeholder="Kode atau nama cabang" value="{{ request('search', $searchBranch ?? '') }}"
                            style="min-width: 160px;">
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0">Sortir</label>
                        <select name="sort_by" class="form-select form-select-sm"
                            style="width: auto; min-width: 140px;">
                            <option value="branch_code" {{ ($sortBy ?? '') === 'branch_code' ? 'selected' : '' }}>Kode
                                Cabang</option>
                            <option value="branch_name" {{ ($sortBy ?? '') === 'branch_name' ? 'selected' : '' }}>Nama
                                Cabang</option>
                            <option value="stock_cabang" {{ ($sortBy ?? '') === 'stock_cabang' ? 'selected' : '' }}>
                                Stock Cabang</option>
                            <option value="sp" {{ ($sortBy ?? '') === 'sp' ? 'selected' : '' }}>SP</option>
                            <option value="faktur" {{ ($sortBy ?? '') === 'faktur' ? 'selected' : '' }}>Faktur</option>
                            <option value="nppb_exp" {{ ($sortBy ?? '') === 'nppb_exp' ? 'selected' : '' }}>NPPB Eks
                            </option>
                            <option value="nppb_koli" {{ ($sortBy ?? '') === 'nppb_koli' ? 'selected' : '' }}>NPPB Koli
                            </option>
                            <option value="intransit" {{ ($sortBy ?? '') === 'intransit' ? 'selected' : '' }}>Intransit
                            </option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0">Arah</label>
                        <select name="sort_dir" class="form-select form-select-sm" style="width: auto;">
                            <option value="asc" {{ ($sortDir ?? 'asc') === 'asc' ? 'selected' : '' }}>A–Z /
                                Kecil–Besar</option>
                            <option value="desc" {{ ($sortDir ?? '') === 'desc' ? 'selected' : '' }}>Z–A /
                                Besar–Kecil</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary ">
                            <i class="bi bi-funnel me-1"></i>Terapkan
                        </button>
                        <a href="{{ route('product.detail', ['book_code' => $product->book_code]) }}?cutoff_id={{ $cutoff->id ?? '' }}"
                            class="btn btn-outline-secondary ">Reset</a>
                    </div>
                    <div class="col-auto ms-auto">
                        <span class="badge bg-info">Stock Pusat (total):
                            <strong>{{ number_format($centralStockTotal) }}</strong> eksemplar</span>
                    </div>
                </div>
            </form>

            <p class="small text-muted mb-2">
                Periode: @if ($cutoff->start_date)
                    <strong>{{ $cutoff->start_date->format('d/m/Y') }}</strong> s/d
                @else
                    s.d.
                @endif
                <strong>{{ $cutoff->end_date->format('d/m/Y') }}</strong>
            </p>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Cabang</th>
                            <th class="text-center">Stock Cabang</th>
                            <th class="text-center">SP</th>
                            <th class="text-center">Faktur</th>
                            <th class="text-center">NPPB Eks</th>
                            <th class="text-center">NPPB Koli</th>
                            <th class="text-center">Intransit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totStockCabang = 0;
                            $totSp = 0;
                            $totFaktur = 0;
                            $totNppbExp = 0;
                            $totNppbKoli = 0;
                            $totIntransit = 0;
                        @endphp
                        @foreach ($branches ?? [] as $no => $branch)
                            @php
                                $d = $branchData[$branch->branch_code] ?? [
                                    'sp' => 0,
                                    'faktur' => 0,
                                    'stock_cabang' => 0,
                                    'nppb_exp' => 0,
                                    'nppb_koli' => 0,
                                    'nppb_pls' => 0,
                                    'intransit' => 0,
                                ];
                                $totStockCabang += $d['stock_cabang'];
                                $totSp += $d['sp'];
                                $totFaktur += $d['faktur'];
                                $totNppbExp += $d['nppb_exp'];
                                $totNppbKoli += $d['nppb_koli'];
                                $totIntransit += $d['intransit'];
                            @endphp
                            <tr>
                                <td>{{ $no + 1 }}</td>
                                <td>
                                    <span class="fw-medium">{{ $branch->branch_code }}</span>
                                    <br><small class="text-muted">{{ $branch->branch_name ?? '-' }}</small>
                                </td>
                                <td class="text-center">{{ number_format($d['stock_cabang']) }}</td>
                                <td class="text-center">{{ number_format($d['sp']) }}</td>
                                <td class="text-center">{{ number_format($d['faktur']) }}</td>
                                <td class="text-center">{{ number_format($d['nppb_exp']) }}</td>
                                <td class="text-center">{{ number_format($d['nppb_koli']) }}</td>
                                <td class="text-center">{{ number_format($d['intransit']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-center">Total</th>
                            <th class="text-center">{{ number_format($totStockCabang) }}</th>
                            <th class="text-center">{{ number_format($totSp) }}</th>
                            <th class="text-center">{{ number_format($totFaktur) }}</th>
                            <th class="text-center">{{ number_format($totNppbExp) }}</th>
                            <th class="text-center">{{ number_format($totNppbKoli) }}</th>
                            <th class="text-center">{{ number_format($totIntransit) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-layouts>
