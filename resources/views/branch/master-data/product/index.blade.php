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
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Data Produk</strong><br />
                    <small class="text-muted">Buku paket, LKS, referensi </small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalImportWarehouse">
                        <i class="bi bi-building me-1"></i>Identifikasi Gudang
                    </button>
                </div>
            </div>

            <div class="modal fade" id="modalImportWarehouse" tabindex="-1" aria-labelledby="modalImportWarehouseLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalImportWarehouseLabel">Identifikasi Gudang</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="{{ route('product.import-warehouse') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="modal-body">
                                <p class="text-muted small mb-2">Kolom <strong>A</strong> = <code>urutan</code>, <strong>B</strong> = kode buku, <strong>D</strong> = gudang (<code>books.warehouse</code>).</p>
                                <div class="mb-3">
                                    <label for="fileWarehouse" class="form-label">File (xlsx / xls / csv)</label>
                                    <input type="file" class="form-control" id="fileWarehouse" name="file" accept=".xlsx,.xls,.csv" required />
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Upload &amp; Perbarui</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal Info Buku -->
            <div class="modal fade" id="modalInfoBuku" tabindex="-1" aria-labelledby="modalInfoBukuLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalInfoBukuLabel">Info Detail Buku</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-sm table-bordered mb-0">
                                <tbody>
                                    <tr><th style="width: 30%" class="bg-light">Kode Buku</th><td id="info_book_code"></td></tr>
                                    <tr><th class="bg-light">Judul Buku</th><td id="info_book_title"></td></tr>
                                    <tr><th class="bg-light">Harga Jual</th><td id="info_sale_price"></td></tr>
                                    <tr><th class="bg-light">Penulis</th><td id="info_writer"></td></tr>
                                    <tr><th class="bg-light">Halaman</th><td id="info_pages"></td></tr>
                                    <tr><th class="bg-light">ISBN</th><td id="info_isbn"></td></tr>
                                    <tr><th class="bg-light">Tipe Buku</th><td id="info_book_tipe"></td></tr>
                                    <tr><th class="bg-light">Ukuran Kertas</th><td id="info_paper_size"></td></tr>
                                    <tr><th class="bg-light">Kode Kertas</th><td id="info_paper_code"></td></tr>
                                    <tr><th class="bg-light">Kode Warna</th><td id="info_c_color_code"></td></tr>
                                    <tr><th class="bg-light">Jenjang</th><td id="info_jenjang"></td></tr>
                                    <tr><th class="bg-light">Kategori</th><td id="info_category"></td></tr>
                                    <tr><th class="bg-light">Kategori Manual</th><td id="info_category_manual"></td></tr>
                                    <tr><th class="bg-light">Serial</th><td id="info_serial"></td></tr>
                                    <tr><th class="bg-light">Gudang</th><td id="info_warehouse"></td></tr>
                                    <tr><th class="bg-light">Mulok</th><td id="info_mulok"></td></tr>
                                    <tr><th class="bg-light">Aktif</th><td id="info_aktif"></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('product.index') }}">
                <div class="col-md-2">
                    <input type="text" class="form-control" name="search_book_code" placeholder="Kode buku"
                        value="{{ request('search_book_code') }}" />
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control" name="search" placeholder="Cari nama produk"
                        value="{{ request('search') }}" />
                </div>
                <div class="col-md-2">
                    <select class="form-select select2-static" name="marketing_list" title="Filter list marketing">
                        <option value="">Semua</option>
                        <option value="Y" {{ request('marketing_list') === 'Y' ? 'selected' : '' }}>List marketing saja</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select select2-static" name="jenis">
                        <option value="">Jenis Produk</option>
                        <option value="Buku Paket" {{ request('jenis') == 'Buku Paket' ? 'selected' : '' }}>Buku Paket</option>
                        <option value="LKS" {{ request('jenis') == 'LKS' ? 'selected' : '' }}>LKS</option>
                        <option value="Referensi" {{ request('jenis') == 'Referensi' ? 'selected' : '' }}>Referensi</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <select class="form-select select2-static" name="jenjang">
                        <option value="">Jenjang</option>
                        <option value="SD" {{ request('jenjang') == 'SD' ? 'selected' : '' }}>SD</option>
                        <option value="SMP" {{ request('jenjang') == 'SMP' ? 'selected' : '' }}>SMP</option>
                        <option value="SMA" {{ request('jenjang') == 'SMA' ? 'selected' : '' }}>SMA</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <select class="form-select select2-static" name="status">
                        <option value="">Status</option>
                        <option value="Aktif" {{ request('status') == 'Aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="Nonaktif" {{ request('status') == 'Nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('product.index') }}" class="btn btn-outline-secondary" style="height: 38px;">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Produk</th>
                            <th>Jenis</th>
                            <th>Jenjang</th>
                            <th>Gudang</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products ?? [] as $product)
                            <tr>
                                <td>{{ $product->book_code }}</td>
                                <td>{{ $product->book_title }}</td>
                                <td>{{ $product->category ?? '-' }}</td>
                                <td>{{ $product->jenjang ?? '-' }}</td>
                                <td>{{ $product->warehouse ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('product.detail', ['book_code' => $product->book_code]) }}" class="btn btn-sm btn-outline-primary" title="Detail stok & SP per cabang">
                                        <i class="bi bi-eye me-1"></i>Detail Stok
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-info btn-detail-buku"
                                        data-book="{{ json_encode($product) }}" title="Info Buku">
                                        <i class="bi bi-info-circle me-1"></i>Info Buku
                                    </button>
                                    <a href="{{ route('product.nkb-history', ['book_code' => $product->book_code]) }}" class="btn btn-sm btn-outline-secondary" title="History NKB untuk buku ini">
                                        <i class="bi bi-journal-text me-1"></i>History NKB
                                    </a>
                                    <a href="{{ route('product.delivery-order-history', ['book_code' => $product->book_code]) }}" class="btn btn-sm btn-outline-secondary" title="History Surat Jalan untuk buku ini">
                                        <i class="bi bi-truck me-1"></i>History SJ
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="text-muted mb-0">Belum ada data produk.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($products) && $products->hasPages())
                <div class="mt-3">
                    {{ $products->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</x-layouts>
