<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Laporan Rekonsiliasi NKB &amp; NTB</h5>
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalImportNtb">
                    <i class="bi bi-upload me-1"></i> Import NTB
                </button>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show py-2" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i> {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                    <i class="bi bi-x-circle me-1"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            <form action="{{ route('rekonsiliasi.index') }}" method="GET" class="mb-3 d-flex flex-wrap gap-2">
                <div class="input-group input-group-sm w-auto">
                    <input type="text" name="search" class="form-control" placeholder="Cari NKB / Surat Jalan..."
                        value="{{ request('search') }}" style="max-width: 250px;">
                </div>
                <div class="input-group input-group-sm w-auto">
                    <select name="status" class="form-select">
                        <option value="">-- Semua Status --</option>
                        <option value="match" {{ request('status') == 'match' ? 'selected' : '' }}>Match (Sesuai)
                        </option>
                        <option value="selisih" {{ request('status') == 'selisih' ? 'selected' : '' }}>Ada Selisih
                        </option>
                        <option value="belum" {{ request('status') == 'belum' ? 'selected' : '' }}>Belum Diterima
                        </option>
                    </select>
                </div>
                <div class="input-group input-group-sm" style="width: 300px;">
                    <select name="branch" class="form-select select2-static" data-placeholder="-- Semua Cabang --">
                        <option value="">-- Semua Cabang --</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->branch_code }}" {{ request('branch') == $branch->branch_code ? 'selected' : '' }}>
                                {{ $branch->branch_code }} - {{ $branch->branch_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-outline-secondary btn-sm" type="submit">
                    <i class="bi bi-filter"></i> Filter
                </button>
                @if (request()->hasAny(['search', 'status', 'branch']) && (request('search') != '' || request('status') != '' || request('branch') != ''))
                    <a href="{{ route('rekonsiliasi.index') }}" class="btn btn-outline-danger btn-sm"
                        title="Clear Search">
                        <i class="bi bi-x"></i> Reset
                    </a>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light text-center">
                        <tr>
                            <th rowspan="2" class="align-middle">No</th>
                            <th rowspan="2" class="align-middle">Nota Kirim Barang</th>
                            <th rowspan="2" class="align-middle">Tanggal Kirim</th>
                            <th rowspan="2" class="align-middle">Tanggal Terima</th>
                            <th colspan="3">Total Eksemplar</th>
                            <th rowspan="2" class="align-middle">Status</th>
                            <th rowspan="2" class="align-middle">Aksi</th>
                        </tr>
                        <tr>
                            <th>Dikirim (NKB)</th>
                            <th>Diterima (NTB)</th>
                            <th>Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $i => $item)
                            @php
                                $kirim = (int) $item->total_exemplar;
                                $terima = (int) $item->total_terima;
                                $selisih = $terima - $kirim;

                                if ($terima == 0) {
                                    $badgeClass = 'bg-warning text-dark';
                                    $statusText = 'Belum Diterima';
                                } elseif ($selisih == 0) {
                                    $badgeClass = 'bg-success';
                                    $statusText = 'Match';
                                } else {
                                    $badgeClass = 'bg-danger';
                                    $statusText = 'Selisih';
                                }
                            @endphp
                            <tr>
                                <td class="text-center">{{ $items->firstItem() + $i }}</td>
                                <td>{{ $item->number }}</td>
                                <td class="text-center">
                                    {{ $item->send_date ? \Carbon\Carbon::parse($item->send_date)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="text-center">
                                    {{ $item->retur_date ? \Carbon\Carbon::parse($item->retur_date)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="text-end">{{ number_format($kirim, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($terima, 0, ',', '.') }}</td>
                                <td
                                    class="text-end text-{{ $selisih == 0 ? 'success' : ($terima == 0 ? 'muted' : 'danger fw-bold') }}">
                                    {{ $terima == 0 ? '-' : number_format($selisih, 0, ',', '.') }}
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('rekonsiliasi.show', $item->number) }}"
                                        class="btn btn-sm btn-outline-info py-0 px-2" title="Lihat Rekonsiliasi">
                                        <i class="bi bi-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-3 text-muted">Belum ada data NKB / Tidak
                                    ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $items->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    {{-- Modal Import NTB --}}
    <div class="modal fade" id="modalImportNtb" tabindex="-1" aria-labelledby="modalImportNtbLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImportNtbLabel"><i class="bi bi-upload me-2"></i>Import Data NTB dari Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('rekonsiliasi.import-ntb') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info py-2 mb-3">
                            <small>
                                <strong>Format Kolom Excel (baris pertama = header):</strong><br>
                                <code>NOTA_KIRIM | BRANCH_COD | BRANCH_SEN | SEND_DATE | DELIVER_BY | APPROVE_INFO | (kosong) | BOOK_COD | BOOK_PRICE | KOL | EXEMPLAR | TOTAL_EXEM | VOLUME</code>
                            </small>
                        </div>
                        <div class="mb-3">
                            <label for="import_file" class="form-label">Pilih File <span class="text-danger">*</span></label>
                            <input type="file" name="file" id="import_file" class="form-control @error('file') is-invalid @enderror"
                                accept=".xlsx,.xls,.csv" required />
                            <div class="form-text">Format: .xlsx, .xls, atau .csv | Maks 5 MB</div>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>Kolom</th>
                                        <th>Header di Excel</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>A</td><td><code>NOTA_KIRIM</code></td><td>Nomor NKB / Surat Jalan</td></tr>
                                    <tr><td>B</td><td><code>BRANCH_COD</code></td><td>Kode Cabang Penerima</td></tr>
                                    <tr><td>C</td><td><code>BRANCH_SEN</code></td><td>Kode Cabang Pengirim</td></tr>
                                    <tr><td>D</td><td><code>SEND_DATE</code></td><td>Tanggal Kirim</td></tr>
                                    <tr><td>E</td><td><code>DELIVER_BY</code></td><td>Diabaikan</td></tr>
                                    <tr><td>F</td><td><code>APPROVE_INFO</code></td><td>Keterangan/Info</td></tr>
                                    <tr><td>G</td><td>—</td><td>Diabaikan</td></tr>
                                    <tr><td>H</td><td><code>BOOK_COD</code></td><td>Kode Buku</td></tr>
                                    <tr><td>I</td><td><code>BOOK_PRICE</code></td><td>Harga Buku</td></tr>
                                    <tr><td>J</td><td><code>KOL</code></td><td>Jumlah Koli</td></tr>
                                    <tr><td>K</td><td><code>EXEMPLAR</code></td><td>Eksemplar Eceran</td></tr>
                                    <tr><td>L</td><td><code>TOTAL_EXEM</code></td><td>Total Eksemplar</td></tr>
                                    <tr><td>M</td><td><code>VOLUME</code></td><td>Volume</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload me-1"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts>
