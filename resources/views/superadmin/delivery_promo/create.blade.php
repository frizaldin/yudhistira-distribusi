<x-layouts>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Tambah Nota Promosi</strong><br />
                    <small class="text-muted">Form penambahan data nota promosi baru.</small>
                </div>
                <a href="{{ route('delivery_promo.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>

            <form action="{{ route('delivery_promo.store') }}" method="POST" id="formDeliveryPromo">
                @csrf

                {{-- ====== HEADER ====== --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="nota_kirim_promo" class="form-label">Nota Kirim / Referensi <span
                                class="text-danger">*</span></label>
                        <input type="text" name="nota_kirim_promo" id="nota_kirim_promo"
                            class="form-control @error('nota_kirim_promo') is-invalid @enderror"
                            value="{{ old('nota_kirim_promo') }}" required />
                        @error('nota_kirim_promo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="branch_sender" class="form-label">Cabang Pengirim</label>
                        <select name="branch_sender" id="branch_sender"
                            class="form-select @error('branch_sender') is-invalid @enderror">
                            <option value="">-- Pilih Cabang --</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->branch_code }}"
                                    {{ old('branch_sender') == $branch->branch_code ? 'selected' : '' }}>
                                    {{ $branch->branch_code }} - {{ $branch->branch_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_sender')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="send_date" class="form-label">Tanggal Kirim</label>
                        <input type="date" name="send_date" id="send_date"
                            class="form-control @error('send_date') is-invalid @enderror"
                            value="{{ old('send_date', date('Y-m-d')) }}" />
                        @error('send_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3 d-none">
                        <label for="sales_code" class="form-label">Kode Sales</label>
                        <input type="text" name="sales_code" id="sales_code"
                            class="form-control @error('sales_code') is-invalid @enderror"
                            value="{{ old('sales_code') }}" />
                        @error('sales_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="deliver_by" class="form-label">Pengirim</label>
                        <input type="text" name="deliver_by" id="deliver_by"
                            class="form-control @error('deliver_by') is-invalid @enderror"
                            value="{{ old('deliver_by') }}" />
                        @error('deliver_by')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="approve_by" class="form-label">Disetujui Oleh</label>
                        <select name="approve_by" id="approve_by"
                            class="form-select select2-static @error('approve_by') is-invalid @enderror">
                            <option value="">-- Pilih Disetujui Oleh --</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->empl_name }}"
                                    {{ old('approve_by') == $emp->empl_name ? 'selected' : '' }}>
                                    {{ $emp->empl_code }} - {{ $emp->empl_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('approve_by')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="whouse_head" class="form-label">Kepala Gudang</label>
                        <select name="whouse_head" id="whouse_head"
                            class="form-select select2-static @error('whouse_head') is-invalid @enderror">
                            <option value="">-- Pilih Kepala Gudang --</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->empl_name }}"
                                    {{ old('whouse_head') == $emp->empl_name ? 'selected' : '' }}>
                                    {{ $emp->empl_code }} - {{ $emp->empl_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('whouse_head')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-12">
                        <label for="info" class="form-label">Keterangan / Info</label>
                        <input type="text" name="info" id="info"
                            class="form-control @error('info') is-invalid @enderror" value="{{ old('info') }}" />
                        @error('info')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- ====== TABEL BUKU ====== --}}
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Detail Buku</strong>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnTambahBaris">
                        <i class="bi bi-plus-circle me-1"></i>Tambah Baris
                    </button>
                </div>

                @error('items')
                    <div class="alert alert-danger py-2">{{ $message }}</div>
                @enderror

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0" id="tabelBuku">
                        <thead class="table-light text-center">
                            <tr>
                                <th style="width:40px">#</th>
                                <th style="min-width:250px" class="text-start">Kode Buku <span
                                        class="text-danger">*</span></th>
                                <th style="width:100px" class="text-end">Koli <span class="text-danger">*</span></th>
                                <th style="width:100px" class="text-end">Isi Koli <span class="text-danger">*</span>
                                </th>
                                <th style="width:100px" class="text-end">Pls/Exp <span class="text-danger">*</span>
                                </th>
                                <th style="width:110px" class="text-end">Total Eks.</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="bodyBuku">
                            @php $oldItems = old('items', [[]]); @endphp
                            @foreach ($oldItems as $idx => $oldItem)
                                <tr class="baris-buku">
                                    <td class="text-center text-muted nomor-baris">{{ $idx + 1 }}</td>
                                    <td>
                                        <select name="items[{{ $idx }}][book_code]"
                                            class="form-select form-select-sm select2-ajax-item"
                                            data-url="{{ route('api.products') }}" data-placeholder="Pilih buku"
                                            required>
                                            @if (!empty($oldItem['book_code']))
                                                <option value="{{ $oldItem['book_code'] }}" selected>
                                                    {{ $oldItem['book_code'] }}</option>
                                            @endif
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="items[{{ $idx }}][koli]"
                                            class="form-control form-control-sm text-end calc-koli" min="0"
                                            step="1" value="{{ $oldItem['koli'] ?? 0 }}" required />
                                    </td>
                                    <td>
                                        <input type="number" name="items[{{ $idx }}][volume]"
                                            class="form-control form-control-sm text-end calc-isi" min="0"
                                            step="1" value="{{ $oldItem['volume'] ?? 0 }}" required />
                                    </td>
                                    <td>
                                        <input type="number" name="items[{{ $idx }}][exemplar]"
                                            class="form-control form-control-sm text-end calc-eceran" min="0"
                                            step="1" value="{{ $oldItem['exemplar'] ?? 0 }}" required />
                                    </td>
                                    <td class="text-end fw-semibold total-baris text-primary">0</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-baris"
                                            title="Hapus baris ini">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="5" class="text-end fw-semibold">Total Keseluruhan</td>
                                <td class="text-end fw-bold text-success" id="totalKeseluruhan">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('js')
        <script>
            $(document).ready(function() {
                function hitungBaris(row) {
                    const k = parseInt(row.querySelector('.calc-koli').value, 10) || 0;
                    const i = parseInt(row.querySelector('.calc-isi').value, 10) || 0;
                    const e = parseInt(row.querySelector('.calc-eceran').value, 10) || 0;
                    const t = (k * i) + e;
                    row.querySelector('.total-baris').textContent = t.toLocaleString('id-ID');
                    return t;
                }

                function hitungSemua() {
                    let grand = 0;
                    document.querySelectorAll('.baris-buku').forEach(function(row) {
                        grand += hitungBaris(row);
                    });
                    document.getElementById('totalKeseluruhan').textContent = grand.toLocaleString('id-ID');
                }

                function renomor() {
                    document.querySelectorAll('.nomor-baris').forEach(function(el, idx) {
                        el.textContent = idx + 1;
                    });
                    document.querySelectorAll('.baris-buku').forEach(function(row, idx) {
                        row.querySelectorAll('[name]').forEach(function(el) {
                            el.name = el.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
                        });
                    });
                }

                function initSelect2Baris(row) {
                    var sel = row.querySelector('.select2-ajax-item');
                    if (!sel || typeof $ === 'undefined') return;
                    var url = sel.dataset.url;
                    var placeholder = sel.dataset.placeholder || 'Pilih buku';
                    $(sel).select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: placeholder,
                        allowClear: true,
                        minimumInputLength: 0,
                        ajax: {
                            url: url,
                            dataType: 'json',
                            delay: 300,
                            data: function(params) {
                                return {
                                    q: params.term,
                                    page: params.page || 1
                                };
                            },
                            processResults: function(data) {
                                return {
                                    results: (data.results || []).map(function(item) {
                                        return {
                                            id: item.id,
                                            text: item.text
                                        };
                                    })
                                };
                            }
                        }
                    });
                }

                document.querySelectorAll('.baris-buku').forEach(function(row) {
                    bindBaris(row);
                    initSelect2Baris(row);
                });
                hitungSemua();

                function bindBaris(row) {
                    row.querySelectorAll('.calc-koli, .calc-isi, .calc-eceran').forEach(function(inp) {
                        inp.addEventListener('input', hitungSemua);
                    });
                    row.querySelector('.btn-hapus-baris').addEventListener('click', function() {
                        if (document.querySelectorAll('.baris-buku').length <= 1) {
                            alert('Minimal harus ada 1 baris buku.');
                            return;
                        }
                        row.remove();
                        renomor();
                        hitungSemua();
                    });
                }

                document.getElementById('btnTambahBaris').addEventListener('click', function() {
                    const idx = document.querySelectorAll('.baris-buku').length;
                    const tr = document.createElement('tr');
                    tr.className = 'baris-buku';
                    tr.innerHTML =
                        '<td class="text-center text-muted nomor-baris">' + (idx + 1) + '</td>' +
                        '<td>' +
                        '<select name="items[' + idx + '][book_code]"' +
                        ' class="form-select form-select-sm select2-ajax-item"' +
                        ' data-url="{{ route('api.products') }}"' +
                        ' data-placeholder="Pilih buku" required></select>' +
                        '</td>' +
                        '<td><input type="number" name="items[' + idx +
                        '][koli]" class="form-control form-control-sm text-end calc-koli" min="0" step="1" value="0" required /></td>' +
                        '<td><input type="number" name="items[' + idx +
                        '][volume]" class="form-control form-control-sm text-end calc-isi" min="0" step="1" value="0" required /></td>' +
                        '<td><input type="number" name="items[' + idx +
                        '][exemplar]" class="form-control form-control-sm text-end calc-eceran" min="0" step="1" value="0" required /></td>' +
                        '<td class="text-end fw-semibold total-baris text-primary">0</td>' +
                        '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-hapus-baris" title="Hapus baris ini"><i class="bi bi-trash"></i></button></td>';

                    document.getElementById('bodyBuku').appendChild(tr);
                    bindBaris(tr);
                    initSelect2Baris(tr);
                    renomor();
                });
            });
        </script>
    @endpush
</x-layouts>
