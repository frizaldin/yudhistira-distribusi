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
                    @if (isset($activeCutoff) && $activeCutoff)
                        <span class="btn btn-sm btn-warning rounded-pill my-2 fw-bold">Cutoff:
                            @if ($activeCutoff->start_date)
                                {{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d M') }} -
                            @else
                                s.d.
                            @endif
                            {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d M Y') }}</span>
                        @endif
                </div>
                {{-- <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('nppb-central.create') }}" class="btn btn-primary btn-sm rounded-pill">
                        <i class="bi bi-plus-circle me-1"></i>Tambah Data
                    </a>
                </div> --}}
            </div>

            <!-- Select Warehouse/Area -->
            <div class="mb-3">
                <label for="select_warehouse_code" class="form-label">Pilih Warehouse/Area</label>
                <select id="select_warehouse_code" class="form-select select2-ajax" data-url="{{ route('api.warehouse-codes') }}"
                    data-placeholder="Pilih Warehouse/Area">
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
                        value="100" style="max-width: 120px;" title="Persentase batas: jika Persentase Kurang SP thd Stock Pusat di bawah nilai ini, Koli/Eceran/Total tidak dapat diisi (rencana kirim diblok)." />
                    <small class="text-muted">1–100. Rencana kirim hanya diizinkan jika (Kurang SP Nasional ÷ Stock Pusat × 100%) ≥ nilai ini. Jika di bawah, kolom Koli/Eceran/Total dikosongkan dan tidak dapat diisi.</small>
                </div>
            </div>

            <!-- Table Products (Hidden by default) -->
            <div id="products-table-container" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong id="selected-warehouse-name"></strong>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" id="btn-lihat-rumus" class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="modal" data-bs-target="#modalRumusNppb">
                            <i class="bi bi-calculator me-1"></i>Lihat Rumus
                        </button>
                        <button type="button" id="btn-export-data" class="btn btn-success btn-sm" title="Export data tabel ke CSV"
                            data-export-prefix="nppb-warehouse">
                            <i class="bi bi-download me-1"></i>Export Data
                        </button>
                        <button type="button" id="btn-save" class="btn btn-primary btn-sm">
                            <i class="bi bi-save me-1"></i>Simpan Data
                        </button>
                    </div>
                </div>

                <!-- Filter Kode Buku, Nama Buku, List Marketing, Urutan & Data Show -->
                <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" id="filter-book-code" class="form-control" style="max-width: 180px;"
                        placeholder="Kode buku..." />
                    <input type="text" id="filter-book-name" class="form-control" style="max-width: 220px;"
                        placeholder="Nama buku..." />
                    <select id="filter-marketing-list" class="form-select form-select-sm" style="width: auto; max-width: 200px;" title="Filter buku yang ditampilkan">
                        <option value="">Semua buku</option>
                        <option value="Y">List marketing saja</option>
                    </select>
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

                <!-- Legenda kolom: tampilkan/sembunyikan kolom -->
                <div class="mb-3">
                    <button class="btn btn-outline-secondary btn-sm collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#legend-columns-nppb" aria-expanded="false" aria-controls="legend-columns-nppb">
                        <i class="bi bi-list-ul me-1"></i>Legenda kolom (tampilkan/sembunyikan)
                    </button>
                    <div class="collapse mt-2" id="legend-columns-nppb">
                        <div class="card card-body py-2">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <button type="button" id="legend-show-all" class="btn btn-sm btn-outline-primary">Tampilkan semua</button>
                                <button type="button" id="legend-hide-all" class="btn btn-sm btn-outline-secondary">Sembunyikan semua</button>
                            </div>
                            <div id="legend-checkboxes" class="d-flex flex-wrap gap-3 gap-md-4">
                                <!-- Diisi oleh JS sesuai NPPB_COLUMNS -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0" id="nppb-products-table">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center sticky-col sticky-col-1" style="width: 50px;" data-col="no">NO</th>
                                <th class="text-left sticky-col sticky-col-2" data-col="kode-buku">Kode Buku</th>
                                <th class="text-center" style="width: 90px;" data-col="stock-pusat">Stock Pusat</th>
                                <th class="text-center" style="width: 120px;" data-col="stock-nasional">Stock Nasional</th>
                                <th class="text-center" style="width: 120px;" data-col="sp-nasional">SP Nasional</th>
                                <th class="text-center" style="width: 250px;" data-col="pct-stock-pusat-target">% Stock Pusat thd Target Nasional</th>
                                <th class="text-center" style="width: 220px;" data-col="pct-stock-pusat-sp">% Stock Pusat thd SP</th>
                                <th class="text-center" style="width: 175px;" data-col="total-eksemplar-nasional">Total Eksemplar Nasional</th>
                                <th class="text-center" style="width: 150px;" data-col="stock-teralokasikan">Stock Teralokasikan</th>
                                <th class="text-center" style="width: 100px;" data-col="maks-kirim">Maks. Kirim</th>
                                <th class="text-center" style="width: 100px;" data-col="sisa-kuota">Sisa Kuota</th>
                                <th class="text-center" style="width: 150px;" data-col="sisa-stock-pusat">Sisa Stock Pusat</th>
                                <th class="text-center" style="width: 80px;" data-col="sp">SP</th>
                                <th class="text-center" style="width: 80px;" data-col="faktur">Faktur</th>
                                <th class="text-center" style="width: 100px;" data-col="stock-cabang">Stock Cabang</th>
                                <th class="text-center" style="width: 100px;" data-col="kurang-sp">Kurang SP</th>
                                <th class="text-center" style="width: 100px;" data-col="pct-ftr-stk-vs-sp">% (Ftr+Stk+Kirim vs SP)</th>
                                <th class="text-center" style="width: 100px;" data-col="pct-ftr-stk-vs-target">% (Ftr+Stk+Kirim vs Target)</th>
                                <th class="text-center" style="width: 70px;" data-col="isi">Isi</th>
                                <th class="text-center" style="width: 80px;" data-col="koli">Koli</th>
                                <th class="text-center" style="width: 80px;" data-col="eceran">Eceran</th>
                                <th class="text-center" style="width: 90px;" data-col="total">Total</th>
                                <th class="text-center" style="width: 70px;" data-col="checklist">Checklist</th>
                            </tr>
                        </thead>
                        <tbody id="products-table-totals">
                            <tr id="row-totals" class="table-secondary fw-bold" style="display: none;">
                                <td class="text-center sticky-col sticky-col-1" data-col="no">—</td>
                                <td class="text-start sticky-col sticky-col-2" data-col="kode-buku">Total</td>
                                <td class="text-center" data-col="stock-pusat">—</td>
                                <td class="text-center" data-col="stock-nasional">—</td>
                                <td class="text-center" data-col="sp-nasional">—</td>
                                <td class="text-center" data-col="pct-stock-pusat-target">—</td>
                                <td class="text-center" data-col="pct-stock-pusat-sp">—</td>
                                <td class="text-center" data-col="total-eksemplar-nasional">—</td>
                                <td class="text-center" data-col="stock-teralokasikan">—</td>
                                <td class="text-center" data-col="maks-kirim">—</td>
                                <td class="text-center" data-col="sisa-kuota">—</td>
                                <td class="text-center" data-col="sisa-stock-pusat">—</td>
                                <td class="text-center" data-col="sp">0</td>
                                <td class="text-center" data-col="faktur">0</td>
                                <td class="text-center" data-col="stock-cabang">0</td>
                                <td class="text-center" data-col="kurang-sp">0</td>
                                <td class="text-center" data-col="pct-ftr-stk-vs-sp">—</td>
                                <td class="text-center" data-col="pct-ftr-stk-vs-target">—</td>
                                <td class="text-center" data-col="isi">—</td>
                                <td class="text-center" data-col="koli">0</td>
                                <td class="text-center" data-col="eceran">0</td>
                                <td class="text-center" data-col="total">0</td>
                                <td class="text-center" data-col="checklist">—</td>
                            </tr>
                        </tbody>
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
                    <p class="small alert alert-warning py-2 mb-3"><i class="bi bi-info-circle me-1"></i><strong>Catatan:</strong> Jika Stock Pusat kosong (0), maka inputan Isi, Koli, Eceran, dan Total akan kosong dan tidak dapat diisi.</p>
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
                                    <td><strong>Kurang SP Nasional</strong></td>
                                    <td>Total kekurangan SP seluruh cabang untuk kode buku tersebut. <strong>Rumus:</strong> max(0, <strong>SP Nasional − Faktur Nasional − Stock Cabang Nasional − Stock Pusat</strong>). Stock Cabang Nasional = stok cabang seluruh cabang + Intransit + NPPB yang sudah disetujui.</td>
                                </tr>
                                <tr>
                                    <td><strong>Persentase Kurang SP thd Stock Pusat</strong></td>
                                    <td><strong>Rumus:</strong> (Kurang SP Nasional ÷ Stock Pusat) × 100%. Nilai ini dibandingkan dengan <strong>Persentase Penentuan Rencana Kirim</strong>: jika persentase ini <strong>kurang dari</strong> nilai penentuan, maka kolom Koli, Eceran, dan Total untuk baris tersebut dikosongkan dan tidak dapat diisi (rencana kirim diblok).</td>
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
                                    <td>Nilai 1–100 (%). <strong>Pembatasan:</strong> Kurang SP Nasional (seluruh cabang) dan Stock Pusat dihitung per buku. Persentase SP thd Stock = (Kurang SP Nasional ÷ Stock Pusat) × 100%. Jika Persentase SP thd Stock <strong>kurang dari</strong> nilai penentuan ini, maka rencana kirim untuk baris tersebut tidak diizinkan: kolom Koli, Eceran, dan Total dikosongkan dan tidak dapat diisi. Contoh: Kurang SP Nasional = 700, Stock Pusat = 1000 → 70%. Penentuan = 80% → karena 70% &lt; 80%, rencana kirim diblok.</td>
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
            var NPPB_COLUMNS = [
                { key: 'no', label: 'NO' },
                { key: 'kode-buku', label: 'Kode Buku' },
                { key: 'stock-pusat', label: 'Stock Pusat' },
                { key: 'stock-nasional', label: 'Stock Nasional' },
                { key: 'sp-nasional', label: 'SP Nasional' },
                { key: 'pct-stock-pusat-target', label: '% Stock Pusat thd Target Nasional' },
                { key: 'pct-stock-pusat-sp', label: '% Stock Pusat thd SP' },
                { key: 'total-eksemplar-nasional', label: 'Total Eksemplar Nasional' },
                { key: 'stock-teralokasikan', label: 'Stock Teralokasikan' },
                { key: 'maks-kirim', label: 'Maks. Kirim' },
                { key: 'sisa-kuota', label: 'Sisa Kuota' },
                { key: 'sisa-stock-pusat', label: 'Sisa Stock Pusat' },
                { key: 'sp', label: 'SP' },
                { key: 'faktur', label: 'Faktur' },
                { key: 'stock-cabang', label: 'Stock Cabang' },
                { key: 'kurang-sp', label: 'Kurang SP' },
                { key: 'pct-ftr-stk-vs-sp', label: '% (Ftr+Stk+Kirim vs SP)' },
                { key: 'pct-ftr-stk-vs-target', label: '% (Ftr+Stk+Kirim vs Target)' },
                { key: 'isi', label: 'Isi' },
                { key: 'koli', label: 'Koli' },
                { key: 'eceran', label: 'Eceran' },
                { key: 'total', label: 'Total' },
                { key: 'checklist', label: 'Checklist' }
            ];
            var COLUMN_STORAGE_KEY = 'nppb-warehouse-columns';

            function getColumnVisibility() {
                var saved = null;
                try { saved = JSON.parse(localStorage.getItem(COLUMN_STORAGE_KEY)); } catch (e) {}
                var vis = {};
                NPPB_COLUMNS.forEach(function(c) { vis[c.key] = saved && saved[c.key] !== undefined ? !!saved[c.key] : true; });
                return vis;
            }
            function setColumnVisibility(key, visible) {
                var vis = getColumnVisibility();
                vis[key] = visible;
                try { localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(vis)); } catch (e) {}
            }
            function applyColumnVisibility() {
                var vis = getColumnVisibility();
                $('#nppb-products-table thead th[data-col]').each(function() {
                    var col = $(this).attr('data-col');
                    $(this).toggle(vis[col] !== false);
                });
                $('#nppb-products-table tbody td[data-col]').each(function() {
                    var col = $(this).attr('data-col');
                    $(this).toggle(vis[col] !== false);
                });
            }

            $(document).ready(function() {
                var $legend = $('#legend-checkboxes');
                NPPB_COLUMNS.forEach(function(c) {
                    var vis = getColumnVisibility();
                    var checked = vis[c.key] !== false;
                    $legend.append(
                        '<label class="form-check form-check-inline mb-0"><input type="checkbox" class="form-check-input legend-col-toggle" data-col="' + c.key + '"' + (checked ? ' checked' : '') + '> <span class="form-check-label">' + c.label + '</span></label>'
                    );
                });
                $(document).on('change', '.legend-col-toggle', function() {
                    var col = $(this).data('col');
                    var visible = $(this).is(':checked');
                    setColumnVisibility(col, visible);
                    applyColumnVisibility();
                });
                $('#legend-show-all').on('click', function() {
                    NPPB_COLUMNS.forEach(function(c) { setColumnVisibility(c.key, true); });
                    $('.legend-col-toggle').prop('checked', true);
                    applyColumnVisibility();
                });
                $('#legend-hide-all').on('click', function() {
                    NPPB_COLUMNS.forEach(function(c) { setColumnVisibility(c.key, false); });
                    $('.legend-col-toggle').prop('checked', false);
                    applyColumnVisibility();
                });
                applyColumnVisibility();

                // Wait for Select2 to be initialized by layouts.blade.php
                // Then add change event handler
                setTimeout(function() {
                    $('#select_warehouse_code').on('change', function() {
                        const warehouseCode = $(this).val();
                        if (warehouseCode) {
                            allProductsData = {}; // Reset data when warehouse changes
                            currentPage = 1;
                            currentSearchBookCode = '';
                            currentSearchBookName = '';
                            currentSort = $('#filter-sort').val() || '';
                            $('#filter-book-code').val('');
                            $('#filter-book-name').val('');
                            loadProducts(warehouseCode, 1, '', '', currentSort);
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
                        const warehouseCode = $('#select_warehouse_code').val();
                        if (warehouseCode) {
                            currentPage = 1;
                            currentSort = $(this).val() || '';
                            loadProducts(warehouseCode, 1, currentSearchBookCode, currentSearchBookName,
                                currentSort);
                        }
                    });

                    $('#filter-marketing-list').on('change', function() {
                        const warehouseCode = $('#select_warehouse_code').val();
                        if (warehouseCode) {
                            currentPage = 1;
                            loadProducts(warehouseCode, 1, currentSearchBookCode, currentSearchBookName, currentSort);
                        }
                    });

                    $('#filter-show').on('change', function() {
                        const warehouseCode = $('#select_warehouse_code').val();
                        if (warehouseCode) {
                            currentPage = 1;
                            currentPerPage = parseInt($(this).val()) || 100;
                            loadProducts(warehouseCode, 1, currentSearchBookCode, currentSearchBookName,
                                currentSort);
                        }
                    });

                    $('#input_persen_rencana_kirim').on('change blur', function() {
                        if (!usePercentage) return;
                        let v = parseInt($(this).val(), 10);
                        if (isNaN(v) || v < 1) v = 1;
                        if (v > 100) v = 100;
                        $(this).val(v);
                        const warehouseCode = $('#select_warehouse_code').val();
                        if (warehouseCode) {
                            currentPage = 1;
                            loadProducts(warehouseCode, 1, currentSearchBookCode, currentSearchBookName,
                                currentSort);
                        }
                    });

                    $('#btn-toggle-persen').on('click', function() {
                        usePercentage = !usePercentage;
                        updatePersenToggleUI();
                        const warehouseCode = $('#select_warehouse_code').val();
                        if (warehouseCode) {
                            currentPage = 1;
                            loadProducts(warehouseCode, 1, currentSearchBookCode, currentSearchBookName,
                                currentSort);
                        }
                    });
                }, 1000);
            });

            let currentWarehouseCode = '';
            let currentWarehouseName = '';
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

            function loadProducts(warehouseCode, page = 1, searchBookCode = '', searchBookName = '', sort = null, perPage = null) {
                currentWarehouseCode = warehouseCode;
                currentPage = page;
                currentSearchBookCode = searchBookCode;
                currentSearchBookName = searchBookName;
                if (sort !== null) currentSort = sort;
                if (perPage !== null) currentPerPage = perPage;
                else currentPerPage = parseInt($('#filter-show').val()) || 100;

                // Get warehouse name from select
                const selectedOption = $('#select_warehouse_code option:selected');
                currentWarehouseName = selectedOption.text() || warehouseCode;
                $('#selected-warehouse-name').text('Data untuk: ' + currentWarehouseName);

                // Show loading state
                $('#products-table-container').show();
                $('#pagination-container').hide();
                const percentage = parseInt($('#input_persen_rencana_kirim').val(), 10) || 100;
                const pct = usePercentage ? Math.max(1, Math.min(100, percentage)) : 100;

                $('#products-table-body').html(
                    '<tr><td colspan="23" class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>'
                );

                // Fetch products via AJAX
                $.ajax({
                    url: '{{ route('api.nppb-products-by-warehouse') }}',
                    method: 'GET',
                    data: {
                        warehouse_code: warehouseCode,
                        page: page,
                        search: (searchBookCode || searchBookName || '').trim(),
                        marketing_list_only: $('#filter-marketing-list').val() === 'Y' ? 1 : 0,
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
                                '<tr><td colspan="23" class="text-center py-4"><div class="text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Belum ada data produk.</div></td></tr>';
                            $('#pagination-container').hide();
                            $('#row-totals').hide();
                        } else {
                            const startNumber = (page - 1) * perPage + 1;
                            products.forEach(function(product, index) {
                                const stockPusat = Number(product.stock_pusat) || 0;
                                const noStock = stockPusat === 0;
                                const maksKirim = product.maksimal_total_eksemplar_nasional != null ? product.maksimal_total_eksemplar_nasional : 0;
                                const sisaKuota = product.sisa_kuota_eksemplar != null ? product.sisa_kuota_eksemplar : 0;
                                const sisaSp = parseFloat(product.sisa_sp) || 0; // Kurang SP
                                const allowRencana = product.allow_rencana_kirim !== false;
                                const existing = allProductsData[product.book_code];
                                let volume = parseFloat(product.volume_used) || 0; // Isi
                                let exp = product.exp || 0;
                                let koli = product.koli || 0;
                                let pls = product.pls || 0;
                                if (!allowRencana) {
                                    koli = 0;
                                    pls = 0;
                                    exp = 0;
                                } else if (existing) {
                                    volume = parseFloat(existing.volume_used) || volume;
                                    koli = parseFloat(existing.koli) || 0;
                                    pls = parseFloat(existing.pls) || 0;
                                    exp = parseFloat(existing.exp) || 0;
                                } else {
                                    if (noStock) {
                                        koli = 0;
                                        pls = 0;
                                        exp = 0;
                                    } else {
                                        if (exp === 0 && sisaSp > 0) {
                                            exp = sisaSp;
                                        }
                                        if (usePercentage && sisaKuota > 0) {
                                            exp = Math.min(exp, sisaKuota);
                                        }
                                        if (volume > 0) {
                                            koli = Math.floor(exp / volume);
                                            pls = Math.floor(exp % volume);
                                        } else {
                                            pls = exp;
                                        }
                                    }
                                }
                                const hadData = (koli > 0 || pls > 0 || exp > 0);
                                const checked = (existing && typeof existing.checked === 'boolean')
                                    ? existing.checked
                                    : hadData;
                                allProductsData[product.book_code] = {
                                    book_code: product.book_code,
                                    book_name: product.book_name,
                                    koli: koli,
                                    exp: exp,
                                    pls: pls,
                                    volume_used: volume,
                                    checked: checked
                                };

                                html += '<tr class="' + (product.row_highlight_yellow ? 'table-warning' : '') + '" data-book-code="' + (product.book_code || '') +
                                    '" data-sisa-sp="' + (product.sisa_sp || 0) + '" data-stock-pusat="' + stockPusat + '">';
                                html += '<td class="text-center sticky-col sticky-col-1" data-col="no">' + (startNumber +
                                    index) + '</td>';
                                html += '<td class="text-start sticky-col sticky-col-2" data-col="kode-buku"><code>' + (product
                                        .book_code || '-') +
                                    '</code><br><small class="text-muted">' + (product.book_name || '-') +
                                    '</small></td>';
                                html += '<td class="text-center" data-col="stock-pusat">' + formatNumber(product.stock_pusat ||
                                    0) + '</td>';
                                html += '<td class="text-center" data-col="stock-nasional">' + formatNumber(product.stock_nasional ||
                                    0) + '</td>';
                                html += '<td class="text-center" data-col="sp-nasional">' + formatNumber(product.sp_nasional ||
                                    0) + '</td>';
                                html += '<td class="text-center" data-col="pct-stock-pusat-target">' + (Number(product
                                    .pct_stock_pusat_target_nasional || 0).toFixed(2)) + '%</td>';
                                html += '<td class="text-center" data-col="pct-stock-pusat-sp">' + (Number(product.pct_stock_pusat_sp ||
                                    0).toFixed(2)) + '%</td>';
                                html += '<td class="text-center" data-col="total-eksemplar-nasional">' + formatNumber(product
                                    .stock_teralokasikan || 0) + '</td>';
                                html += '<td class="text-center" data-col="stock-teralokasikan">' + formatNumber(product
                                    .stock_teralokasikan || 0) + '</td>';
                                html += '<td class="text-center" data-col="maks-kirim" title="Persen × Stock Pusat">' + formatNumber(maksKirim) + '</td>';
                                html += '<td class="text-center" data-col="sisa-kuota" title="Maks. Kirim − Stock Teralokasikan">' + formatNumber(sisaKuota) + '</td>';
                                html += '<td class="text-center" data-col="sisa-stock-pusat">' + formatNumber(product
                                    .sisa_stock_pusat || 0) + '</td>';
                                html += '<td class="text-center" data-col="sp">' + formatNumber(product.sp || 0) +
                                    '</td>';
                                html += '<td class="text-center" data-col="faktur">' + formatNumber(product.faktur || 0) +
                                    '</td>';
                                html += '<td class="text-center" data-col="stock-cabang">' + formatNumber(product.stock_cabang ||
                                    0) + '</td>';
                                html += '<td class="text-center" data-col="kurang-sp"><strong>' + formatNumber(product.sisa_sp ||
                                    0) + '</strong></td>';
                                html += '<td class="text-center" data-col="pct-ftr-stk-vs-sp">' + (product.pct_faktur_stock_total_vs_sp != null ? (Number(product.pct_faktur_stock_total_vs_sp).toFixed(2) + '%') : '-') + '</td>';
                                html += '<td class="text-center" data-col="pct-ftr-stk-vs-target">' + (product.pct_faktur_stock_total_vs_target != null ? (Number(product.pct_faktur_stock_total_vs_target).toFixed(2) + '%') : '-') + '</td>';
                                html +=
                                    '<td class="text-center" data-col="isi"><input type="number" class="form-control form-control-sm text-center input-volume" value="' + volume +
                                    '" min="0" step="1" style="width: 70px; display: inline-block;" data-book-code="' +
                                    product.book_code + '"></td>';
                                const pctSpVsStock = product.pct_sp_vs_stock != null ? product.pct_sp_vs_stock : 0;
                                const disAttr = !allowRencana ? ' disabled readonly' : '';
                                const disTitle = !allowRencana ? ' title="Rencana kirim tidak diizinkan: Persentase Kurang SP thd Stock (' + pctSpVsStock + '%) di bawah batas (' + pct + '%)."' : '';
                                html +=
                                    '<td class="text-center" data-col="koli"><input type="number" class="form-control form-control-sm text-center input-koli" value="' + koli +
                                    '" min="0" step="1" style="width: 80px; display: inline-block;" data-book-code="' +
                                    product.book_code + '" data-allow-rencana="' + (allowRencana ? '1' : '0') + '"' + disAttr + disTitle + '></td>';
                                html +=
                                    '<td class="text-center" data-col="eceran"><input type="number" class="form-control form-control-sm text-center input-pls" value="' + pls +
                                    '" min="0" step="1" style="width: 80px; display: inline-block;" data-book-code="' +
                                    product.book_code + '"' + disAttr + disTitle + '></td>';
                                const expMax = allowRencana && !noStock && usePercentage && sisaKuota >= 0 ? sisaKuota : '';
                                html +=
                                    '<td class="text-center" data-col="total"><input type="number" class="form-control form-control-sm text-center input-exp" value="' + exp +
                                    '" min="0" step="1" ' + (expMax !== '' ? 'max="' + expMax + '"' : '') +
                                    ' style="width: 80px; display: inline-block;" data-book-code="' + product.book_code +
                                    '" data-sisa-kuota="' + sisaKuota + '" data-allow-rencana="' + (allowRencana ? '1' : '0') + '"' + disAttr + disTitle + '></td>';
                                html += '<td class="text-center align-middle" data-col="checklist"><input type="checkbox" class="input-check-save" data-book-code="' + product.book_code + '"' + (checked ? ' checked' : '') + ' title="Centang untuk menyimpan baris ini" style="cursor:pointer;width:1.1em;height:1.1em;"></td>';
                                html += '</tr>';
                            });

                            var totals = response.totals || {};
                            $('#row-totals').show();
                            $('#row-totals td[data-col="sp"]').text(formatNumber(totals.sp || 0));
                            $('#row-totals td[data-col="faktur"]').text(formatNumber(totals.faktur || 0));
                            $('#row-totals td[data-col="stock-cabang"]').text(formatNumber(totals.stock_cabang || 0));
                            $('#row-totals td[data-col="kurang-sp"]').text(formatNumber(totals.sisa_sp || 0));
                            $('#row-totals td[data-col="koli"]').text(formatNumber(totals.koli || 0));
                            $('#row-totals td[data-col="eceran"]').text(formatNumber(totals.pls || 0));
                            $('#row-totals td[data-col="total"]').text(formatNumber(totals.exp || 0));

                            // Generate pagination
                            generatePagination(page, lastPage);
                            $('#pagination-container').show();
                        }

                        $('#products-table-body').html(html);
                        applyColumnVisibility();
                    },
                    error: function(xhr, status, error) {
                        $('#products-table-body').html(
                                '<tr><td colspan="23" class="text-center py-4"><div class="text-danger">Error loading data: ' +
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
                    loadProducts(currentWarehouseCode, page, currentSearchBookCode, currentSearchBookName, currentSort);
                }
            });

            // Save current page input values to allProductsData before page change
            function saveCurrentPageData() {
                $('#products-table-body tr[data-book-code]').each(function() {
                    const $row = $(this);
                    const bookCode = $row.data('book-code');
                    if (!bookCode) return;
                    const koli = parseFloat($row.find('.input-koli').val()) || 0;
                    const exp = parseFloat($row.find('.input-exp').val()) || 0;
                    const pls = parseFloat($row.find('.input-pls').val()) || 0;
                    const volume = parseFloat($row.find('.input-volume').val()) || 0;
                    const checked = $row.find('.input-check-save').is(':checked');
                    if (!allProductsData[bookCode]) {
                        allProductsData[bookCode] = { book_code: bookCode, book_name: '', koli: 0, exp: 0, pls: 0, volume_used: 0, checked: false };
                    }
                    allProductsData[bookCode].koli = koli;
                    allProductsData[bookCode].exp = exp;
                    allProductsData[bookCode].pls = pls;
                    allProductsData[bookCode].volume_used = volume;
                    allProductsData[bookCode].checked = checked;
                });
            }

            // Fungsi untuk mengambil semua produk dari semua halaman
            function loadAllProducts(warehouseCode, searchBookCode = '', searchBookName = '') {
                return new Promise(function(resolve, reject) {
                    // Ambil halaman pertama untuk mendapatkan total pages
                    $.ajax({
                        url: '{{ route('api.nppb-products-by-warehouse') }}',
                        method: 'GET',
                        data: {
                            warehouse_code: warehouseCode,
                            page: 1,
                            search: (searchBookCode || searchBookName || '').trim(),
                            marketing_list_only: $('#filter-marketing-list').val() === 'Y' ? 1 : 0
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
                                        url: '{{ route('api.nppb-products-by-warehouse') }}',
                                        method: 'GET',
                                        data: {
                                            warehouse_code: warehouseCode,
                                            page: page,
                                            search: (searchBookCode || searchBookName || '').trim(),
                                            marketing_list_only: $('#filter-marketing-list').val() === 'Y' ? 1 : 0
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

            // Save button handler: simpan hanya data temporary (baris yang pernah di-checklist dari allProductsData), tanpa load seluruh halaman
            $(document).on('click', '#btn-save', function() {
                if (!currentWarehouseCode) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Pilih warehouse/area terlebih dahulu!',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                saveCurrentPageData();

                // Ambil hanya data yang di-checklist dari temporary (allProductsData), tanpa load semua halaman
                const products = Object.keys(allProductsData)
                    .filter(function(bookCode) {
                        return allProductsData[bookCode].checked === true;
                    })
                    .map(function(bookCode) {
                        const p = allProductsData[bookCode];
                        return {
                            book_code: p.book_code,
                            book_name: p.book_name || '',
                            koli: parseFloat(p.koli) || 0,
                            exp: parseFloat(p.exp) || 0,
                            pls: parseFloat(p.pls) || 0,
                            volume: parseFloat(p.volume_used) || 0
                        };
                    });

                if (products.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Centang minimal satu baris (checklist) untuk disimpan.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...');

                $.ajax({
                    url: '{{ route('api.nppb-products.save') }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        branch_code: currentWarehouseCode,
                        branch_name: currentWarehouseName,
                        products: products
                    }),
                    success: function(response) {
                        if (response.success) {
                            // Reset temporary untuk baris yang baru disimpan agar setelah reload pakai rumus awal (Isi dari central_stock_kolis, Koli/Eceran/Total dari Kurang SP)
                            products.forEach(function(p) {
                                delete allProductsData[p.book_code];
                            });
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message || 'Data berhasil disimpan!',
                                confirmButtonText: 'OK'
                            }).then(function() {
                                loadProducts(currentWarehouseCode, currentPage,
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
                            text: xhr.responseJSON?.message || error || 'Terjadi kesalahan saat menyimpan data',
                            confirmButtonText: 'OK'
                        });
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalHtml);
                    }
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
            function productToCsvRow(product, index, sisaKuota, maksKirim, koli, exp, pls, vol) {
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
                    (Number(product.pct_faktur_stock_total_vs_sp) || 0).toFixed(2),
                    (Number(product.pct_faktur_stock_total_vs_target) || 0).toFixed(2),
                    vol,
                    koli,
                    pls,
                    exp
                ].map(escapeCsvCell).join(',');
            }
            $(document).on('click', '#btn-export-data', function() {
                if (!currentWarehouseCode) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Pilih warehouse/area terlebih dahulu!',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                saveCurrentPageData();
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-1"></span>Mengambil data...');
                loadAllProducts(currentWarehouseCode, currentSearchBookCode, currentSearchBookName).then(
                    function(allProducts) {
                        allProducts.forEach(function(p) {
                            if (allProductsData[p.book_code]) {
                                p.koli = allProductsData[p.book_code].koli;
                                p.exp = allProductsData[p.book_code].exp;
                                p.pls = allProductsData[p.book_code].pls;
                                p.volume_used = allProductsData[p.book_code].volume_used ?? p.volume_used;
                            }
                        });
                        const headers = 'NO,Kode Buku,Nama Buku,Stock Pusat,Stock Nasional,SP Nasional,% Stock Pusat thd Target Nasional,% Stock Pusat thd SP,Total Eksemplar Nasional,Stock Teralokasikan,Maks. Kirim,Sisa Kuota,Sisa Stock Pusat,SP,Faktur,Stock Cabang,Kurang SP,% (Ftr+Stk+Kirim vs SP),% (Ftr+Stk+Kirim vs Target),Isi,Koli,Eceran,Total';
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
                            csvRows.push(productToCsvRow(product, index, sisaKuota, maksKirim, koli, exp, pls, vol));
                        });
                        const csv = '\uFEFF' + csvRows.join('\r\n');
                        const prefix = $btn.data('export-prefix') || 'nppb-warehouse';
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
                if (currentWarehouseCode) {
                    saveCurrentPageData();
                    currentPage = 1;
                    const searchBookCode = $('#filter-book-code').val().trim();
                    const searchBookName = $('#filter-book-name').val().trim();
                    loadProducts(currentWarehouseCode, 1, searchBookCode, searchBookName, currentSort);
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

            $(document).on('change', '.input-check-save', function() {
                const bookCode = $(this).data('book-code');
                if (allProductsData[bookCode]) allProductsData[bookCode].checked = $(this).is(':checked');
            });

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

            // Ketika Total Eksemplar diubah manual → clamp ke Sisa Kuota hanya jika persentase aktif dan sisa kuota > 0 (sama seperti nppb-central)
            $(document).on('change blur', '.input-exp', function() {
                const $row = $(this).closest('tr');
                const bookCode = $(this).data('book-code');
                const $input = $(this);
                let exp = parseFloat($input.val()) || 0;
                if (usePercentage) {
                    const sisaKuota = parseFloat($input.data('sisa-kuota'));
                    if (!isNaN(sisaKuota) && sisaKuota > 0 && exp > sisaKuota) {
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
