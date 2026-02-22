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

    <style>
        .table-responsive {
            max-height: 650px;
            overflow: auto;
        }

        /* ===== HEADER BASE ===== */
        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 20;
            vertical-align: middle !important;
            /* header normal */
        }

        /* ===== STICKY COLUMN BASE ===== */
        .sticky-col {
            position: sticky;
            background: #fff !important;
        }

        /* ===== STICKY BODY ===== */
        tbody .sticky-col,
        thead .sticky-col {
            z-index: 10;
            background: #ffc107 !important;
            box-shadow: unset !important;
        }

        tbody .sticky-col,
        thead .sticky-col {
            color: #fff !important;
            border-color: #fff !important;
        }

        /* ===== STICKY HEADER COLUMN (INI KUNCI) ===== */
        thead .sticky-col {
            z-index: 999 !important;
            background: #f8f9fa;
        }

        /* ===== KOLOM NO ===== */
        .sticky-col-1 {
            left: 0;
            width: 50px;
        }

        /* ===== KOLOM KODE BUKU ===== */
        .sticky-col-2 {
            left: 50px;
            /* HARUS = width NO */
            min-width: 200px;
            box-shadow: 3px 0 6px -3px rgba(0, 0, 0, .3);
        }
    </style>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Rencana Kirim (NPPB Pusat Ciawi)</strong><br />
                    <small class="text-muted">Data rencana pengiriman dari pusat ke cabang</small><br>
                    <span class="btn btn-sm btn-warning rounded-pill my-2 fw-bold">Cutoff:
                        @if ($activeCutoff->start_date)
                            {{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d M') }} -
                        @else
                            s.d.
                        @endif
                        {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d M Y') }}</span>
                </div>
                {{-- <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('nppb-central.create') }}" class="btn btn-primary btn-sm rounded-pill">
                        <i class="bi bi-plus-circle me-1"></i>Tambah Data
                    </a>
                </div> --}}
            </div>

            <!-- Select Branch -->
            <div class="mb-3">
                <label for="select_branch_code" class="form-label">Pilih Cabang</label>
                <select id="select_branch_code" class="form-select select2-ajax" data-url="{{ route('api.branches') }}"
                    data-placeholder="Pilih Cabang">
                </select>
            </div>

            <!-- Persentase Penentuan Rencana Kirim -->
            <div class="mb-4">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <label for="input_persen_rencana_kirim" class="form-label mb-0">Persentase Penentuan Rencana Kirim (%)</label>
                    <button type="button" id="btn-toggle-persen" class="btn btn-sm btn-outline-warning" title="Klik untuk menonaktifkan/mengaktifkan batasan persentase">
                        <i class="bi bi-toggle-on me-1"></i><span id="label-toggle-persen">Nonaktifkan Persentase</span>
                    </button>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2" id="wrap-persen-input">
                    <input type="number" id="input_persen_rencana_kirim" class="form-control" min="1" max="100"
                        value="100" style="max-width: 120px;" title="Batasan total eksemplar nasional = persen ini × Stock Pusat. Sisa kuota = Maks. Kirim − Stock Teralokasikan." />
                    <small class="text-muted">1–100. Diterapkan langsung ke Koli, Eceran, Total. Total maksimal = Sisa Kuota.</small>
                </div>
            </div>

            <!-- Table Products (Hidden by default) -->
            <div id="products-table-container" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong id="selected-branch-name"></strong>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" id="btn-lihat-rumus" class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="modal" data-bs-target="#modalRumusNppb">
                            <i class="bi bi-calculator me-1"></i>Lihat Rumus
                        </button>
                        <button type="button" id="btn-export-data" class="btn btn-success btn-sm" title="Export data tabel ke CSV"
                            data-export-prefix="nppb-central">
                            <i class="bi bi-download me-1"></i>Export Data
                        </button>
                        <button type="button" id="btn-save" class="btn btn-primary btn-sm">
                            <i class="bi bi-save me-1"></i>Simpan Data
                        </button>
                    </div>
                </div>

                <!-- Filter Kode Buku, Nama Buku, Urutan & Data Show -->
                <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" id="filter-book-code" class="form-control" style="max-width: 180px;"
                        placeholder="Kode buku..." />
                    <input type="text" id="filter-book-name" class="form-control" style="max-width: 220px;"
                        placeholder="Nama buku..." />
                    <select id="filter-sort" class="form-select form-select-sm" style="width: auto; max-width: 220px;">
                        <option value="">Urutkan berdasarkan...</option>
                        <option value="sp_desc">SP Terbanyak</option>
                        <option value="sp_asc">SP Tersedikit</option>
                        <option value="exp_desc">Eksemplar Terbanyak</option>
                        <option value="exp_asc">Eksemplar Tersedikit</option>
                        <option value="sisa_sp_desc">Kurang SP Terbanyak</option>
                        <option value="sisa_sp_asc">Kurang SP Tersedikit</option>
                    </select>
                    <select id="filter-show" class="form-select form-select-sm" style="width: auto; max-width: 120px;">
                        <option value="50">50</option>
                        <option value="100" selected>100</option>
                        <option value="150">150</option>
                        <option value="250">250</option>
                        <option value="500">500</option>
                    </select>
                    <small class="text-muted">data per halaman</small>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center sticky-col sticky-col-1" style="width: 50px;">NO</th>
                                <th class="text-left sticky-col sticky-col-2">Kode Buku</th>
                                <th class="text-center" style="width: 90px;">Stock Pusat</th>
                                <th class="text-center" style="width: 120px;">Stock Nasional</th>
                                <th class="text-center" style="width: 120px;">SP Nasional</th>
                                <th class="text-center" style="width: 250px;">% Stock Pusat thd Target Nasional</th>
                                <th class="text-center" style="width: 220px;">% Stock Pusat thd SP</th>
                                <th class="text-center" style="width: 175px;">Total Eksemplar Nasional</th>
                                <th class="text-center" style="width: 150px;">Stock Teralokasikan</th>
                                <th class="text-center" style="width: 100px;">Maks. Kirim</th>
                                <th class="text-center" style="width: 100px;">Sisa Kuota</th>
                                <th class="text-center" style="width: 150px;">Sisa Stock Pusat</th>
                                <th class="text-center" style="width: 80px;">SP</th>
                                <th class="text-center" style="width: 80px;">Faktur</th>
                                <th class="text-center" style="width: 100px;">Stock Cabang</th>
                                <th class="text-center" style="width: 100px;">Kurang SP</th>
                                <th class="text-center" style="width: 70px;">Isi</th>
                                <th class="text-center" style="width: 80px;">Koli</th>
                                <th class="text-center" style="width: 80px;">Eceran</th>
                                <th class="text-center" style="width: 90px;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="products-table-body">
                            <!-- Data akan diisi via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Page navigation" class="mt-3" id="pagination-container" style="display: none;">
                    <ul class="pagination pagination-sm justify-content-end mb-0" id="pagination">
                        <!-- Pagination will be generated by JavaScript -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Modal Rumus NPPB -->
    <div class="modal fade" id="modalRumusNppb" tabindex="-1" aria-labelledby="modalRumusNppbLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRumusNppbLabel">
                        <i class="bi bi-calculator me-2"></i>Rumus Perhitungan NPPB (Rencana Kirim Pusat)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Berikut penjelasan kolom dan rumus yang digunakan pada halaman
                        NPPB. Semua angka mengacu pada periode cutoff yang aktif.</p>
                    <div class="small">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%;">Kolom</th>
                                    <th>Penjelasan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Stock Pusat</strong></td>
                                    <td>Jumlah stok buku di gudang pusat untuk kode buku tersebut.</td>
                                </tr>
                                <tr>
                                    <td><strong>Stock Nasional</strong></td>
                                    <td>Total stok buku di semua cabang untuk kode buku tersebut.</td>
                                </tr>
                                <tr>
                                    <td><strong>SP Nasional</strong></td>
                                    <td>Total Surat Pesanan dari semua cabang untuk kode buku tersebut.</td>
                                </tr>
                                <tr>
                                    <td><strong>% Stock Pusat / Target Nasional</strong></td>
                                    <td>Persentase perbandingan antara Stock Pusat dengan Target Nasional untuk kode
                                        buku tersebut. Semakin besar persentasenya, semakin besar stok pusat dibanding
                                        target nasional.</td>
                                </tr>
                                <tr>
                                    <td><strong>% Stock Pusat / SP</strong></td>
                                    <td>Persentase perbandingan antara Stock Pusat dengan SP (untuk cabang yang dipilih)
                                        pada kode buku tersebut.</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Eksemplar Nasional</strong></td>
                                    <td>Total eksemplar dari seluruh cabang (jumlah rencana kirim yang sudah
                                        dialokasikan ke semua cabang untuk kode buku tersebut).</td>
                                </tr>
                                <tr>
                                    <td><strong>Stock Teralokasikan</strong></td>
                                    <td>Total eksemplar yang sudah dialokasikan untuk dikirim ke seluruh cabang (rencana
                                        kirim yang sudah tercatat).</td>
                                </tr>
                                <tr>
                                    <td><strong>Persentase Penentuan Rencana Kirim</strong></td>
                                    <td>Nilai 1–100 (%) yang membatasi total eksemplar nasional. Hanya persen ini dari Stock Pusat yang boleh dialokasikan sebagai rencana kirim. Contoh: 70% artinya total rencana kirim nasional maksimal = 70% × Stock Pusat.</td>
                                </tr>
                                <tr>
                                    <td><strong>Maks. Kirim</strong></td>
                                    <td>Maksimal total eksemplar nasional yang boleh dikirim, dihitung sebagai: <strong>Persentase × Stock Pusat</strong>. Contoh: Persentase = 70, Stock Pusat = 4000 → Maks. Kirim = 70% × 4000 = 2800.</td>
                                </tr>
                                <tr>
                                    <td><strong>Sisa Kuota</strong></td>
                                    <td>Sisa eksemplar yang masih boleh dialokasikan, dihitung sebagai: <strong>Maks. Kirim − Stock Teralokasikan</strong>. Contoh: Maks. Kirim = 2800, Stock Teralokasikan = 1000 → Sisa Kuota = 1800. Input kolom Total (eksemplar untuk cabang ini) tidak boleh melebihi Sisa Kuota.</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="bg-light small">
                                        <strong>Contoh perhitungan:</strong> Persentase = 70%, Stock Pusat = 4000, SP Nasional = 5000, Stock Nasional = 2300, Stock Teralokasikan = 1000.<br>
                                        → Maks. Kirim = 70% × 4000 = <strong>2800</strong>.<br>
                                        → Sisa Kuota = 2800 − 1000 = <strong>1800</strong> (maksimal total eksemplar yang masih boleh diisi untuk cabang/cabang-cabang).
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Sisa Stock Pusat</strong></td>
                                    <td>Stock Pusat dikurangi Stock Teralokasikan. Artinya sisa stok pusat yang belum
                                        dialokasikan untuk kirim.</td>
                                </tr>
                                <tr>
                                    <td><strong>SP</strong></td>
                                    <td>Surat Pesanan untuk cabang yang Anda pilih.</td>
                                </tr>
                                <tr>
                                    <td><strong>Faktur</strong></td>
                                    <td>Jumlah yang sudah terkirim (sudah difaktur) untuk cabang yang Anda pilih.</td>
                                </tr>
                                <tr>
                                    <td><strong>Stock Cabang</strong></td>
                                    <td>Stok buku ditambah dengan Intransit di cabang yang Anda pilih.</td>
                                </tr>
                                <tr>
                                    <td><strong>Kurang SP</strong></td>
                                    <td>Kekurangan Surat Pesanan untuk cabang tersebut. Diambil dari SP dikurangi
                                        Faktur. Jika stok cabang sudah menutupi kekurangan itu, maka Kurang SP = 0. Jika
                                        belum, dihitung dari selisih setelah dikurangi stok cabang dan stock pusat
                                        (nilai tidak negatif).</td>
                                </tr>
                                <tr>
                                    <td><strong>Isi</strong></td>
                                    <td>Banyak eksemplar per koli (isi per kardus/koli). Bisa dari data master atau
                                        diisi manual.</td>
                                </tr>
                                <tr>
                                    <td><strong>Koli</strong></td>
                                    <td>Jumlah koli (kardus). Diisi otomatis: Total Eksemplar dibagi Isi, dibulatkan ke
                                        bawah.</td>
                                </tr>
                                <tr>
                                    <td><strong>Eceran</strong></td>
                                    <td>Sisa eksemplar yang tidak genap satu koli. Diisi otomatis dari sisa pembagian
                                        Total Eksemplar dengan Isi.</td>
                                </tr>
                                <tr>
                                    <td><strong>Total</strong></td>
                                    <td>Total eksemplar yang akan dikirim ke cabang ini. Awalnya mengikuti Kurang SP,
                                        bisa diedit. Harus memenuhi: Total = Koli × Isi + Eceran.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script>
            $(document).ready(function() {
                // Wait for Select2 to be initialized by layouts.blade.php
                // Then add change event handler
                setTimeout(function() {
                    $('#select_branch_code').on('change', function() {
                        const branchCode = $(this).val();
                        if (branchCode) {
                            allProductsData = {}; // Reset data when branch changes
                            currentPage = 1;
                            currentSearchBookCode = '';
                            currentSearchBookName = '';
                            currentSort = $('#filter-sort').val() || '';
                            $('#filter-book-code').val('');
                            $('#filter-book-name').val('');
                            loadProducts(branchCode, 1, '', '', currentSort);
                        } else {
                            $('#products-table-container').hide();
                            $('#products-table-body').empty();
                            $('#pagination-container').hide();
                            allProductsData = {};
                            currentSearchBookCode = '';
                            currentSearchBookName = '';
                            currentSort = '';
                            $('#filter-book-code').val('');
                            $('#filter-book-name').val('');
                            $('#filter-sort').val('');
                        }
                    });

                    $('#filter-sort').on('change', function() {
                        const branchCode = $('#select_branch_code').val();
                        if (branchCode) {
                            currentPage = 1;
                            currentSort = $(this).val() || '';
                            loadProducts(branchCode, 1, currentSearchBookCode, currentSearchBookName,
                                currentSort);
                        }
                    });

                    $('#filter-show').on('change', function() {
                        const branchCode = $('#select_branch_code').val();
                        if (branchCode) {
                            currentPage = 1;
                            currentPerPage = parseInt($(this).val()) || 100;
                            loadProducts(branchCode, 1, currentSearchBookCode, currentSearchBookName,
                                currentSort);
                        }
                    });

                    $('#input_persen_rencana_kirim').on('change blur', function() {
                        if (!usePercentage) return;
                        let v = parseInt($(this).val(), 10);
                        if (isNaN(v) || v < 1) v = 1;
                        if (v > 100) v = 100;
                        $(this).val(v);
                        const branchCode = $('#select_branch_code').val();
                        if (branchCode) {
                            currentPage = 1;
                            loadProducts(branchCode, 1, currentSearchBookCode, currentSearchBookName,
                                currentSort);
                        }
                    });

                    $('#btn-toggle-persen').on('click', function() {
                        usePercentage = !usePercentage;
                        updatePersenToggleUI();
                        const branchCode = $('#select_branch_code').val();
                        if (branchCode) {
                            currentPage = 1;
                            loadProducts(branchCode, 1, currentSearchBookCode, currentSearchBookName,
                                currentSort);
                        }
                    });
                }, 1000);
            });

            let currentBranchCode = '';
            let currentBranchName = '';
            let currentPage = 1;
            let lastPage = 1;
            let totalData = 0;
            let allProductsData = {}; // Store all products data across pages for saving
            let currentSearchBookCode = '';
            let currentSearchBookName = '';
            let currentSort = '';
            let currentPerPage = 100;
            let usePercentage = true; // true = batasan persentase aktif, false = nonaktif (tanpa batas)

            function updatePersenToggleUI() {
                if (usePercentage) {
                    $('#input_persen_rencana_kirim').prop('disabled', false).removeClass('bg-light');
                    $('#btn-toggle-persen').removeClass('btn-outline-secondary').addClass('btn-outline-warning');
                    $('#btn-toggle-persen i').removeClass('bi-toggle-off').addClass('bi-toggle-on');
                    $('#label-toggle-persen').text('Nonaktifkan Persentase');
                    $('#wrap-persen-input').show();
                } else {
                    $('#input_persen_rencana_kirim').prop('disabled', true).addClass('bg-light');
                    $('#btn-toggle-persen').removeClass('btn-outline-warning').addClass('btn-outline-secondary');
                    $('#btn-toggle-persen i').removeClass('bi-toggle-on').addClass('bi-toggle-off');
                    $('#label-toggle-persen').text('Aktifkan Persentase');
                    $('#wrap-persen-input').show();
                }
            }

            function loadProducts(branchCode, page = 1, searchBookCode = '', searchBookName = '', sort = null, perPage = null) {
                currentBranchCode = branchCode;
                currentPage = page;
                currentSearchBookCode = searchBookCode;
                currentSearchBookName = searchBookName;
                if (sort !== null) currentSort = sort;
                if (perPage !== null) currentPerPage = perPage;
                else currentPerPage = parseInt($('#filter-show').val()) || 100;

                // Get branch name from select
                const selectedOption = $('#select_branch_code option:selected');
                currentBranchName = selectedOption.text() || branchCode;
                $('#selected-branch-name').text('Data untuk: ' + currentBranchName);

                // Show loading state
                $('#products-table-container').show();
                $('#pagination-container').hide();
                const percentage = parseInt($('#input_persen_rencana_kirim').val(), 10) || 100;
                const pct = usePercentage ? Math.max(1, Math.min(100, percentage)) : 100;

                $('#products-table-body').html(
                    '<tr><td colspan="20" class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>'
                );

                // Fetch products via AJAX
                $.ajax({
                    url: '{{ route('api.nppb-products') }}',
                    method: 'GET',
                    data: {
                        branch_code: branchCode,
                        page: page,
                        search_book_code: searchBookCode,
                        search_book_name: searchBookName,
                        sort: currentSort,
                        per_page: currentPerPage,
                        percentage: pct
                    },
                    success: function(response) {
                        const products = response.results || [];
                        lastPage = response.last_page || 1;
                        totalData = response.total || 0;
                        const perPage = response.per_page || 150;

                        // Debug: log first product to check koli and volume_used
                        if (products.length > 0) {
                            console.log('First product data:', {
                                book_code: products[0].book_code,
                                koli: products[0].koli,
                                exp: products[0].exp,
                                volume_used: products[0].volume_used
                            });
                        }

                        let html = '';

                        if (products.length === 0) {
                            html =
                                '<tr><td colspan="20" class="text-center py-4"><div class="text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Belum ada data produk.</div></td></tr>';
                            $('#pagination-container').hide();
                        } else {
                            const startNumber = (page - 1) * perPage + 1;
                            products.forEach(function(product, index) {
                                const maksKirim = product.maksimal_total_eksemplar_nasional != null ? product.maksimal_total_eksemplar_nasional : 0;
                                const sisaKuota = product.sisa_kuota_eksemplar != null ? product.sisa_kuota_eksemplar : 0;
                                const volume = parseFloat(product.volume_used) || 0;
                                let exp = product.exp || 0;
                                let koli = product.koli || 0;
                                let pls = product.pls || 0;
                                if (usePercentage && sisaKuota >= 0) {
                                    exp = Math.min(exp, sisaKuota);
                                    if (volume > 0) {
                                        koli = Math.floor(exp / volume);
                                        pls = exp % volume;
                                    } else {
                                        pls = exp;
                                    }
                                }
                                allProductsData[product.book_code] = {
                                    book_code: product.book_code,
                                    book_name: product.book_name,
                                    koli: koli,
                                    exp: exp,
                                    pls: pls,
                                    volume_used: product.volume_used || 0
                                };

                                html += '<tr data-book-code="' + (product.book_code || '') +
                                    '" data-sisa-sp="' + (product.sisa_sp || 0) + '">';
                                html += '<td class="text-center sticky-col sticky-col-1">' + (startNumber +
                                    index) + '</td>';
                                html += '<td class="text-start sticky-col sticky-col-2"><code>' + (product
                                        .book_code || '-') +
                                    '</code><br><small class="text-muted">' + (product.book_name || '-') +
                                    '</small></td>';
                                html += '<td class="text-center">' + formatNumber(product.stock_pusat ||
                                    0) + '</td>';
                                html += '<td class="text-center">' + formatNumber(product.stock_nasional ||
                                    0) + '</td>';
                                html += '<td class="text-center">' + formatNumber(product.sp_nasional ||
                                    0) + '</td>';
                                html += '<td class="text-center">' + (Number(product
                                    .pct_stock_pusat_target_nasional || 0).toFixed(2)) + '%</td>';
                                html += '<td class="text-center">' + (Number(product.pct_stock_pusat_sp ||
                                    0).toFixed(2)) + '%</td>';
                                html += '<td class="text-center">' + formatNumber(product
                                    .stock_teralokasikan || 0) + '</td>';
                                html += '<td class="text-center">' + formatNumber(product
                                    .stock_teralokasikan || 0) + '</td>';
                                html += '<td class="text-center" title="Persen × Stock Pusat">' + formatNumber(maksKirim) + '</td>';
                                html += '<td class="text-center" title="Maks. Kirim − Stock Teralokasikan">' + formatNumber(sisaKuota) + '</td>';
                                html += '<td class="text-center">' + formatNumber(product
                                    .sisa_stock_pusat || 0) + '</td>';
                                html += '<td class="text-center">' + formatNumber(product.sp || 0) +
                                    '</td>';
                                html += '<td class="text-center">' + formatNumber(product.faktur || 0) +
                                    '</td>';
                                html += '<td class="text-center">' + formatNumber(product.stock_cabang ||
                                    0) + '</td>';
                                html += '<td class="text-center"><strong>' + formatNumber(product.sisa_sp ||
                                    0) + '</strong></td>';
                                html +=
                                    '<td class="text-center"><input type="number" class="form-control form-control-sm text-center input-volume" value="' +
                                    (product.volume_used || 0) +
                                    '" min="0" step="1" style="width: 70px; display: inline-block;" data-book-code="' +
                                    product.book_code + '"></td>';
                                html +=
                                    '<td class="text-center"><input type="number" class="form-control form-control-sm text-center input-koli" value="' +
                                    koli +
                                    '" min="0" step="1" style="width: 80px; display: inline-block;" data-book-code="' +
                                    product.book_code + '"></td>';
                                html +=
                                    '<td class="text-center"><input type="number" class="form-control form-control-sm text-center input-pls" value="' +
                                    pls +
                                    '" min="0" step="1" style="width: 80px; display: inline-block;" data-book-code="' +
                                    product.book_code + '"></td>';
                                const expMax = usePercentage && sisaKuota >= 0 ? sisaKuota : '';
                                html +=
                                    '<td class="text-center"><input type="number" class="form-control form-control-sm text-center input-exp" value="' +
                                    exp +
                                    '" min="0" step="1" ' + (expMax !== '' ? 'max="' + expMax + '"' : '') +
                                    ' style="width: 80px; display: inline-block;" data-book-code="' + product.book_code +
                                    '" data-sisa-kuota="' + sisaKuota + '"></td>';
                                html += '</tr>';
                            });

                            // Generate pagination
                            generatePagination(page, lastPage);
                            $('#pagination-container').show();
                        }

                        $('#products-table-body').html(html);
                    },
                    error: function(xhr, status, error) {
$('#products-table-body').html(
                                '<tr><td colspan="20" class="text-center py-4"><div class="text-danger">Error loading data: ' +
                            error + '</div></td></tr>'
                        );
                        $('#pagination-container').hide();
                    }
                });
            }

            function generatePagination(currentPage, lastPage) {
                let paginationHtml = '';

                // Previous button
                paginationHtml += '<li class="page-item ' + (currentPage === 1 ? 'disabled' : '') + '">';
                paginationHtml += '<a class="page-link" href="#" data-page="' + (currentPage - 1) + '">Previous</a>';
                paginationHtml += '</li>';

                // Page numbers
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(lastPage, currentPage + 2);

                if (startPage > 1) {
                    paginationHtml += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
                    if (startPage > 2) {
                        paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for (let i = startPage; i <= endPage; i++) {
                    paginationHtml += '<li class="page-item ' + (i === currentPage ? 'active' : '') + '">';
                    paginationHtml += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
                    paginationHtml += '</li>';
                }

                if (endPage < lastPage) {
                    if (endPage < lastPage - 1) {
                        paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    paginationHtml += '<li class="page-item"><a class="page-link" href="#" data-page="' + lastPage + '">' +
                        lastPage + '</a></li>';
                }

                // Next button
                paginationHtml += '<li class="page-item ' + (currentPage === lastPage ? 'disabled' : '') + '">';
                paginationHtml += '<a class="page-link" href="#" data-page="' + (currentPage + 1) + '">Next</a>';
                paginationHtml += '</li>';

                $('#pagination').html(paginationHtml);
            }

            // Pagination click handler
            $(document).on('click', '#pagination .page-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page && page !== currentPage && page >= 1 && page <= lastPage) {
                    // Save current page data before loading new page
                    saveCurrentPageData();
                    loadProducts(currentBranchCode, page, currentSearchBookCode, currentSearchBookName, currentSort);
                }
            });

            // Save current page input values to allProductsData before page change
            function saveCurrentPageData() {
                $('#products-table-body tr[data-book-code]').each(function() {
                    const $row = $(this);
                    const bookCode = $row.data('book-code');
                    const koli = parseFloat($row.find('.input-koli').val()) || 0;
                    const exp = parseFloat($row.find('.input-exp').val()) || 0;
                    const pls = parseFloat($row.find('.input-pls').val()) || 0;
                    const volume = parseFloat($row.find('.input-volume').val()) || 0;

                    if (allProductsData[bookCode]) {
                        allProductsData[bookCode].koli = koli;
                        allProductsData[bookCode].exp = exp;
                        allProductsData[bookCode].pls = pls;
                        allProductsData[bookCode].volume_used = volume;
                    }
                });
            }

            // Fungsi untuk mengambil semua produk dari semua halaman
            function loadAllProducts(branchCode, searchBookCode = '', searchBookName = '') {
                return new Promise(function(resolve, reject) {
                    // Ambil halaman pertama untuk mendapatkan total pages
                    $.ajax({
                        url: '{{ route('api.nppb-products') }}',
                        method: 'GET',
                        data: {
                            branch_code: branchCode,
                            page: 1,
                            search_book_code: searchBookCode,
                            search_book_name: searchBookName
                        },
                        success: function(firstResponse) {
                            const firstProducts = firstResponse.results || [];
                            const totalPages = firstResponse.last_page || 1;

                            if (totalPages <= 1) {
                                // Hanya 1 halaman, langsung return
                                resolve(firstProducts);
                                return;
                            }

                            // Ambil semua halaman secara paralel
                            const pagePromises = [];
                            for (let page = 2; page <= totalPages; page++) {
                                pagePromises.push(
                                    $.ajax({
                                        url: '{{ route('api.nppb-products') }}',
                                        method: 'GET',
                                        data: {
                                            branch_code: branchCode,
                                            page: page,
                                            search_book_code: searchBookCode,
                                            search_book_name: searchBookName
                                        }
                                    })
                                );
                            }

                            // Tunggu semua request selesai
                            Promise.all(pagePromises).then(function(responses) {
                                let allProducts = firstProducts;
                                responses.forEach(function(resp) {
                                    allProducts = allProducts.concat(resp.results || []);
                                });
                                resolve(allProducts);
                            }).catch(reject);
                        },
                        error: reject
                    });
                });
            }

            // Save button handler (delegated event)
            $(document).on('click', '#btn-save', function() {
                if (!currentBranchCode) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Pilih cabang terlebih dahulu!',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Save current page data first
                saveCurrentPageData();

                // Disable button and show loading
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan data...');

                // Ambil semua produk dari semua halaman
                loadAllProducts(currentBranchCode, currentSearchBookCode, currentSearchBookName).then(function(
                    allProductsFromServer) {
                    // Merge data dari server dengan data yang sudah di-edit
                    // Prioritas: data yang di-edit (dari allProductsData) > data dari server
                    allProductsFromServer.forEach(function(product) {
                        // Jika sudah ada di allProductsData (sudah di-edit), gunakan data yang sudah di-edit
                        if (allProductsData[product.book_code]) {
                            product.koli = allProductsData[product.book_code].koli;
                            product.exp = allProductsData[product.book_code].exp;
                            product.pls = allProductsData[product.book_code].pls;
                            product.volume = allProductsData[product.book_code].volume_used ?? product
                                .volume;
                        }
                    });

                    // Convert ke array untuk dikirim ke backend
                    // Simpan semua data yang memiliki nilai != 0 (setidaknya salah satu dari koli, exp, atau pls)
                    const products = allProductsFromServer
                        .map(function(p) {
                            return {
                                book_code: p.book_code,
                                book_name: p.book_name,
                                koli: parseFloat(p.koli) || 0,
                                exp: parseFloat(p.exp) || 0,
                                pls: parseFloat(p.pls) || 0,
                                volume: parseFloat(p.volume) || 0
                            };
                        })
                        .filter(function(p) {
                            // Hanya kirim data yang memiliki nilai != 0 (bukan hanya yang di-edit)
                            return p.koli != 0 || p.exp != 0 || p.pls != 0;
                        });

                    // Update button text
                    $btn.html('<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...');

                    // Save via AJAX using JSON to avoid max_input_vars limit
                    $.ajax({
                        url: '{{ route('api.nppb-products.save') }}',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'Content-Type': 'application/json'
                        },
                        data: JSON.stringify({
                            branch_code: currentBranchCode,
                            branch_name: currentBranchName,
                            products: products
                        }),
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: response.message || 'Data berhasil disimpan!',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    // Clear cache dan reload current page to reflect saved data
                                    allProductsData = {};
                                    loadProducts(currentBranchCode, currentPage,
                                        currentSearchBookCode, currentSearchBookName,
                                        currentSort);
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'Gagal menyimpan data',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: xhr.responseJSON?.message || error ||
                                    'Terjadi kesalahan saat menyimpan data',
                                confirmButtonText: 'OK'
                            });
                        },
                        complete: function() {
                            $btn.prop('disabled', false).html(originalHtml);
                        }
                    });
                }).catch(function(error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan saat mengambil data: ' + error,
                        confirmButtonText: 'OK'
                    });
                    $btn.prop('disabled', false).html(originalHtml);
                });
            });

            function formatNumber(num) {
                return new Intl.NumberFormat('id-ID').format(num);
            }

            // Export seluruh data (semua halaman) ke CSV
            function escapeCsvCell(val) {
                if (val == null) return '';
                val = String(val).trim();
                if (/[,"\r\n]/.test(val)) return '"' + val.replace(/"/g, '""') + '"';
                return val;
            }
            function productToCsvRow(product, index, usePct, sisaKuota, maksKirim, koli, exp, pls, vol) {
                return [
                    index + 1,
                    product.book_code || '',
                    product.book_name || '',
                    product.stock_pusat != null ? product.stock_pusat : 0,
                    product.stock_nasional != null ? product.stock_nasional : 0,
                    product.sp_nasional != null ? product.sp_nasional : 0,
                    (Number(product.pct_stock_pusat_target_nasional) || 0).toFixed(2),
                    (Number(product.pct_stock_pusat_sp) || 0).toFixed(2),
                    product.stock_teralokasikan != null ? product.stock_teralokasikan : 0,
                    product.stock_teralokasikan != null ? product.stock_teralokasikan : 0,
                    maksKirim,
                    sisaKuota,
                    product.sisa_stock_pusat != null ? product.sisa_stock_pusat : 0,
                    product.sp != null ? product.sp : 0,
                    product.faktur != null ? product.faktur : 0,
                    product.stock_cabang != null ? product.stock_cabang : 0,
                    product.sisa_sp != null ? product.sisa_sp : 0,
                    vol,
                    koli,
                    pls,
                    exp
                ].map(escapeCsvCell).join(',');
            }
            $(document).on('click', '#btn-export-data', function() {
                if (!currentBranchCode) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Pilih cabang terlebih dahulu!',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                saveCurrentPageData();
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-1"></span>Mengambil data...');
                const percentage = parseInt($('#input_persen_rencana_kirim').val(), 10) || 100;
                const pct = usePercentage ? Math.max(1, Math.min(100, percentage)) : 100;
                loadAllProducts(currentBranchCode, currentSearchBookCode, currentSearchBookName).then(
                    function(allProducts) {
                        allProducts.forEach(function(p) {
                            if (allProductsData[p.book_code]) {
                                p.koli = allProductsData[p.book_code].koli;
                                p.exp = allProductsData[p.book_code].exp;
                                p.pls = allProductsData[p.book_code].pls;
                                p.volume_used = allProductsData[p.book_code].volume_used ?? p.volume_used;
                            }
                        });
                        const headers = 'NO,Kode Buku,Nama Buku,Stock Pusat,Stock Nasional,SP Nasional,% Stock Pusat thd Target Nasional,% Stock Pusat thd SP,Total Eksemplar Nasional,Stock Teralokasikan,Maks. Kirim,Sisa Kuota,Sisa Stock Pusat,SP,Faktur,Stock Cabang,Kurang SP,Isi,Koli,Eceran,Total';
                        const csvRows = [headers];
                        allProducts.forEach(function(product, index) {
                            const maksKirim = product.maksimal_total_eksemplar_nasional != null ?
                                product.maksimal_total_eksemplar_nasional : 0;
                            const sisaKuota = product.sisa_kuota_eksemplar != null ?
                                product.sisa_kuota_eksemplar : 0;
                            const koli = product.koli != null ? product.koli : 0;
                            const exp = product.exp != null ? product.exp : 0;
                            const pls = product.pls != null ? product.pls : 0;
                            const vol = parseFloat(product.volume_used) || 0;
                            csvRows.push(productToCsvRow(product, index, usePercentage, sisaKuota, maksKirim,
                                koli, exp, pls, vol));
                        });
                        const csv = '\uFEFF' + csvRows.join('\r\n');
                        const prefix = $btn.data('export-prefix') || 'nppb-central';
                        const filename = prefix + '-export-' + new Date().toISOString().slice(0, 10) + '.csv';
                        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = filename;
                        link.click();
                        URL.revokeObjectURL(link.href);
                        if (allProducts.length === 0) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Tidak ada data',
                                text: 'Tidak ada data untuk diexport.',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                icon: 'success',
                                title: 'Export selesai',
                                text: allProducts.length + ' baris berhasil diexport.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    }
                ).catch(function(err) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Gagal mengambil data: ' + (err && err.message ? err.message : err),
                        confirmButtonText: 'OK'
                    });
                }).finally(function() {
                    $btn.prop('disabled', false).html(originalHtml);
                });
            });

            // Server-side search with debounce (kode buku & nama buku terpisah)
            let searchTimeout;

            function applyFilterSearch() {
                if (currentBranchCode) {
                    saveCurrentPageData();
                    currentPage = 1;
                    const searchBookCode = $('#filter-book-code').val().trim();
                    const searchBookName = $('#filter-book-name').val().trim();
                    loadProducts(currentBranchCode, 1, searchBookCode, searchBookName, currentSort);
                }
            }
            $(document).on('keyup', '#filter-book-code, #filter-book-name', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(applyFilterSearch, 500);
            });

            // Ketika Volume diubah: Koli = floor(Kurang SP / Volume), Eceran = Kurang SP % Volume
            function recalcFromVolume($row) {
                const bookCode = $row.data('book-code');
                const sisaSp = parseFloat($row.data('sisa-sp')) || 0;
                const volume = parseFloat($row.find('.input-volume').val()) || 0;

                const koli = volume > 0 ? Math.floor(sisaSp / volume) : 0;
                const eceran = volume > 0 ? sisaSp % volume : sisaSp;
                const totalEksemplar = sisaSp;

                $row.find('.input-koli').val(koli);
                $row.find('.input-pls').val(eceran);
                $row.find('.input-exp').val(totalEksemplar);

                if (allProductsData[bookCode]) {
                    allProductsData[bookCode].koli = koli;
                    allProductsData[bookCode].pls = eceran;
                    allProductsData[bookCode].exp = totalEksemplar;
                    allProductsData[bookCode].volume_used = volume;
                }
            }

            // Ketika Koli diubah: Total Eksemplar = Koli × Volume, Eceran = sisa jika Total < Kurang SP
            function recalcFromKoli($row) {
                const bookCode = $row.data('book-code');
                const sisaSp = parseFloat($row.data('sisa-sp')) || 0;
                const koli = parseFloat($row.find('.input-koli').val()) || 0;
                const volume = parseFloat($row.find('.input-volume').val()) || 0;

                const totalEksemplar = koli * volume;
                const eceran = Math.max(0, sisaSp - totalEksemplar);
                const finalTotal = totalEksemplar + eceran;

                $row.find('.input-pls').val(eceran);
                $row.find('.input-exp').val(finalTotal);

                if (allProductsData[bookCode]) {
                    allProductsData[bookCode].koli = koli;
                    allProductsData[bookCode].pls = eceran;
                    allProductsData[bookCode].exp = finalTotal;
                    allProductsData[bookCode].volume_used = volume;
                }
            }

            $(document).on('change blur input', '.input-volume', function() {
                const $row = $(this).closest('tr');
                if ($row.data('book-code')) recalcFromVolume($row);
            });

            $(document).on('change blur input', '.input-koli', function() {
                const $row = $(this).closest('tr');
                if ($row.data('book-code')) recalcFromKoli($row);
            });

            // Ketika Eceran diubah → hanya Total Eksemplar yang berubah: Total = Koli×Volume + Eceran
            $(document).on('change blur input', '.input-pls', function() {
                const $row = $(this).closest('tr');
                const bookCode = $row.data('book-code');
                if (!bookCode) return;

                const koli = parseFloat($row.find('.input-koli').val()) || 0;
                const volume = parseFloat($row.find('.input-volume').val()) || 0;
                const pls = parseFloat($row.find('.input-pls').val()) || 0;

                const totalEksemplar = koli * volume + pls;
                $row.find('.input-exp').val(totalEksemplar);

                if (allProductsData[bookCode]) {
                    allProductsData[bookCode].koli = koli;
                    allProductsData[bookCode].pls = pls;
                    allProductsData[bookCode].exp = totalEksemplar;
                    allProductsData[bookCode].volume_used = volume;
                }
            });

            // Ketika Total Eksemplar diubah manual → clamp ke Sisa Kuota hanya jika persentase aktif
            $(document).on('change blur', '.input-exp', function() {
                const bookCode = $(this).data('book-code');
                const $row = $(this).closest('tr');
                const $input = $(this);
                let exp = parseFloat($input.val()) || 0;
                if (usePercentage) {
                    const sisaKuota = parseFloat($input.data('sisa-kuota'));
                    if (!isNaN(sisaKuota) && sisaKuota >= 0 && exp > sisaKuota) {
                        exp = sisaKuota;
                        $input.val(exp);
                    }
                }
                const koli = parseFloat($row.find('.input-koli').val()) || 0;
                const pls = parseFloat($row.find('.input-pls').val()) || 0;
                const volume = parseFloat($row.find('.input-volume').val()) || 0;

                if (allProductsData[bookCode]) {
                    allProductsData[bookCode].koli = koli;
                    allProductsData[bookCode].exp = exp;
                    allProductsData[bookCode].pls = pls;
                    allProductsData[bookCode].volume_used = volume;
                }
            });
        </script>
    </x-slot>
</x-layouts>
