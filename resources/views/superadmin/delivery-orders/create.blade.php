<x-layouts>
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
                    <a href="{{ route('delivery-orders.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </a>
                </div>
                <strong>Tambah Surat Jalan</strong>
            </div>

            <form action="{{ route('delivery-orders.store') }}" method="POST" id="formSuratJalan">
                @csrf

                <div class="row g-2 mb-3">
                    <div class="col-md-2">
                        <label class="form-label small">No. Surat Jalan</label>
                        <input type="text" name="number" class="form-control form-control-sm"
                            value="{{ old('number', $nextNumber ?? '') }}" required />
                        @error('number')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Cabang Pengirim</label>
                        <select name="sender_code" class="form-select form-select-sm select2-static" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->branch_code }}"
                                    {{ old('sender_code', 'PS00') == $b->branch_code ? 'selected' : '' }}>
                                    {{ $b->branch_code }} — {{ $b->branch_name }}</option>
                            @endforeach
                        </select>
                        @error('sender_code')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Cabang Tujuan</label>
                        <select name="recipient_code" id="recipient_code"
                            class="form-select form-select-sm select2-static" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->branch_code }}" data-address="{{ $b->address }}"
                                    {{ old('recipient_code') == $b->branch_code ? 'selected' : '' }}>
                                    {{ $b->branch_code }} — {{ $b->branch_name }}</option>
                            @endforeach
                        </select>
                        @error('recipient_code')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Dibuat Oleh</label>
                        <input type="text" class="form-control form-control-sm"
                            value="{{ auth()->user()->name ?? '' }}" readonly />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Tanggal</label>
                        <input type="date" name="date" class="form-control form-control-sm"
                            value="{{ old('date', date('Y-m-d')) }}" />
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small">Expedisi</label>
                        <input type="text" name="expedition" class="form-control form-control-sm"
                            value="{{ old('expedition') }}" placeholder="Nama ekspedisi" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Plat No.</label>
                        <input type="text" name="plate_number" class="form-control form-control-sm"
                            value="{{ old('plate_number') }}" placeholder="B 1234 XY" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Supir 1</label>
                        <input type="text" name="drivers[0]" class="form-control form-control-sm"
                            value="{{ old('drivers.0') }}" placeholder="Nama supir" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Supir 2</label>
                        <input type="text" name="drivers[1]" class="form-control form-control-sm"
                            value="{{ old('drivers.1') }}" placeholder="Nama supir" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Telepon Supir</label>
                        <input type="text" name="driver_phone" class="form-control form-control-sm"
                            value="{{ old('driver_phone') }}" />
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Keterangan</label>
                    <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Catatan pengiriman">{{ old('note') }}</textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Nama Pembuat <span class="text-danger">*</span></label>
                        <select name="creator_name" class="form-select form-select-sm select2-static" required>
                            <option value="">-- Pilih Pembuat --</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->empl_name }}"
                                    {{ old('creator_name') == $emp->empl_name ? 'selected' : '' }}>
                                    {{ $emp->empl_code }} - {{ $emp->empl_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('creator_name')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Diketahui Oleh <span class="text-danger">*</span></label>
                        <select name="known_name" class="form-select form-select-sm select2-static" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->empl_name }}"
                                    {{ old('known_name') == $emp->empl_name ? 'selected' : '' }}>
                                    {{ $emp->empl_code }} - {{ $emp->empl_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('known_name')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Nama Penerima</label>
                        <select name="recipient_name" id="recipient_name_select"
                            class="form-select form-select-sm select2-static">
                            <option value="">-- Pilih Penerima --</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->empl_name }}" data-phone="{{ $emp->phone_no }}"
                                    data-address="{{ $emp->address }}"
                                    {{ old('recipient_name') == $emp->empl_name ? 'selected' : '' }}>
                                    {{ $emp->empl_code }} - {{ $emp->empl_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('recipient_name')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">No Telepon Penerima</label>
                        <input type="text" name="recipient_phone" id="recipient_phone"
                            class="form-control form-control-sm" maxlength="50" value="{{ old('recipient_phone') }}"
                            placeholder="08xxxxxxxxxx" />
                        @error('recipient_phone')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Alamat Penerima</label>
                        <input type="text" name="recipient_address" id="recipient_address"
                            class="form-control form-control-sm" value="{{ old('recipient_address') }}" />
                        @error('recipient_address')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small">Koli</label>
                        <input type="number" id="input_koli" name="koli" class="form-control form-control-sm"
                            min="0" value="{{ old('koli') }}" />
                        @error('koli')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Pack</label>
                        <input type="number" id="input_pack" name="pack" class="form-control form-control-sm"
                            min="0" value="{{ old('pack') }}" />
                        @error('pack')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Terbilang</label>
                        <input type="text" id="input_terbilang" name="terbilang"
                            class="form-control form-control-sm" maxlength="500" value="{{ old('terbilang') }}"
                            placeholder="Otomatis terisi..." />
                        @error('terbilang')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr />
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Detail Item (NKB)</strong>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
                        <i class="bi bi-plus me-1"></i>Tambah Baris
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">NKB</th>
                                <th class="text-center" style="width:100px">Koli</th>
                                <th class="text-center" style="width:80px">EX.</th>
                                <th class="text-center" style="width:120px">Total EX</th>
                                <th class="text-center" style="width:80px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody">
                            @php
                                $oldItems = old('items', []);
                                if (empty($oldItems)) {
                                    $oldItems = [['nkb_id' => '', 'koli' => 0, 'ex' => 0, 'total_ex' => 0]];
                                }
                            @endphp
                            @foreach ($oldItems as $idx => $oi)
                                <tr class="item-row">
                                    <td>
                                        <select name="items[{{ $idx }}][nkb_id]"
                                            class="form-select form-select-sm nkb-select select2-nkb" required
                                            data-recipient-filter="1">
                                            <option value="">-- Pilih NKB --</option>
                                            @foreach ($nkbs as $n)
                                                <option value="{{ $n->id }}"
                                                    data-recipient="{{ $n->recipient_code ?? '' }}"
                                                    {{ (old('items.' . $idx . '.nkb_id') ?? ($oi['nkb_id'] ?? '')) == $n->id ? 'selected' : '' }}>
                                                    {{ $n->number }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[{{ $idx }}][koli]"
                                            class="form-control form-control-sm text-center"
                                            value="{{ old('items.' . $idx . '.koli', $oi['koli'] ?? 0) }}"
                                            min="0" step="1" required /></td>
                                    <td><input type="number" name="items[{{ $idx }}][ex]"
                                            class="form-control form-control-sm text-center"
                                            value="{{ old('items.' . $idx . '.ex', $oi['ex'] ?? 0) }}" min="0"
                                            step="1" required /></td>
                                    <td><input type="number" name="items[{{ $idx }}][total_ex]"
                                            class="form-control form-control-sm text-center"
                                            value="{{ old('items.' . $idx . '.total_ex', $oi['total_ex'] ?? 0) }}"
                                            min="0" step="1" required /></td>
                                    <td class="text-center"><button type="button"
                                            class="btn btn-sm btn-outline-danger remove-row"><i
                                                class="bi bi-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('items')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Simpan Surat Jalan
                    </button>
                    <a href="{{ route('delivery-orders.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <template id="row-template">
        <tr class="item-row">
            <td>
                <select name="items[__INDEX__][nkb_id]" class="form-select form-select-sm nkb-select select2-nkb"
                    required data-recipient-filter="1">
                    <option value="">-- Pilih NKB --</option>
                    @foreach ($nkbs as $n)
                        <option value="{{ $n->id }}" data-recipient="{{ $n->recipient_code ?? '' }}">
                            {{ $n->number }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" name="items[__INDEX__][koli]" class="form-control form-control-sm text-center"
                    value="0" min="0" step="1" required /></td>
            <td><input type="number" name="items[__INDEX__][ex]" class="form-control form-control-sm text-center"
                    value="0" min="0" step="1" required /></td>
            <td><input type="number" name="items[__INDEX__][total_ex]"
                    class="form-control form-control-sm text-center" value="0" min="0" step="1"
                    required /></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i
                        class="bi bi-trash"></i></button></td>
        </tr>
    </template>

    @push('js')
        <script>
            var nkbsList = @json($nkbs->map(fn($n) => ['id' => $n->id, 'number' => $n->number, 'recipient_code' => $n->recipient_code ?? '']));
            $(function() {
                // ── Terbilang ─────────────────────────────────────────────────────
                var satuanTb = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan',
                    'Sepuluh', 'Sebelas', 'Dua Belas', 'Tiga Belas', 'Empat Belas', 'Lima Belas',
                    'Enam Belas', 'Tujuh Belas', 'Delapan Belas', 'Sembilan Belas'
                ];
                var puluhanTb = ['', 'Sepuluh', 'Dua Puluh', 'Tiga Puluh', 'Empat Puluh', 'Lima Puluh',
                    'Enam Puluh', 'Tujuh Puluh', 'Delapan Puluh', 'Sembilan Puluh'
                ];

                function terbilangAngka(n) {
                    n = Math.abs(parseInt(n)) || 0;
                    if (n === 0) return 'Nol';
                    if (n < 20) return satuanTb[n];
                    if (n < 100) {
                        var p = Math.floor(n / 10),
                            s = n % 10;
                        return puluhanTb[p] + (s ? ' ' + satuanTb[s] : '');
                    }
                    if (n < 1000) {
                        var r = Math.floor(n / 100);
                        return (r === 1 ? 'Seratus' : satuanTb[r] + ' Ratus') + (n % 100 ? ' ' + terbilangAngka(n %
                            100) : '');
                    }
                    if (n < 1000000) {
                        var r2 = Math.floor(n / 1000);
                        return (r2 === 1 ? 'Seribu' : terbilangAngka(r2) + ' Ribu') + (n % 1000 ? ' ' + terbilangAngka(
                            n % 1000) : '');
                    }
                    return n.toString();
                }

                function updateTerbilang() {
                    var koli = parseInt($('#input_koli').val()) || 0;
                    var pack = parseInt($('#input_pack').val()) || 0;
                    var parts = [];
                    if (koli > 0) parts.push(terbilangAngka(koli) + ' Koli');
                    if (pack > 0) parts.push(terbilangAngka(pack) + ' Pack');
                    $('#input_terbilang').val(parts.join(' '));
                }
                $('#input_koli, #input_pack').on('input change', updateTerbilang);
                // ─────────────────────────────────────────────────────────────────

                var nkbDetailUrlTemplate = '{{ route('api.nkb.detail', ['id' => ':id']) }}';

                function fillRowFromNkb($row, nkbId) {
                    if (!nkbId) return;
                    var $cells = $row.find('td');
                    var $koli = $cells.eq(1).find('input');
                    var $ex = $cells.eq(2).find('input');
                    var $totalEx = $cells.eq(3).find('input');
                    $koli.prop('readonly', true).addClass('loading');
                    $ex.prop('readonly', true).addClass('loading');
                    $totalEx.prop('readonly', true).addClass('loading');
                    var url = nkbDetailUrlTemplate.replace(':id', nkbId);
                    console.log('[NKB] fetch detail', {
                        nkbId: nkbId,
                        url: url
                    });
                    $.ajax({
                            url: url,
                            type: 'GET',
                            dataType: 'json',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        })
                        .done(function(res) {
                            console.log('[NKB] API response', res);
                            if (res && res.success) {
                                $koli.val(res.koli);
                                $ex.val(res.ex);
                                $totalEx.val(res.total_ex);
                            } else {
                                $koli.val(0);
                                $ex.val(0);
                                $totalEx.val(0);
                            }
                            // Pastikan select NKB tetap menampilkan pilihan (Select2 bisa ke-reset setelah isi input)
                            var $select = $row.find('select.nkb-select');
                            if ($select.length && nkbId) {
                                $row.data('nkb-restore-display', true);
                                var $opt = $select.find('option[value="' + nkbId + '"]');
                                var optText = $opt.length ? $opt.text() : '';
                                $select.val(nkbId);
                                if (optText) {
                                    $select.next('.select2-container').find('.select2-selection__rendered').text(
                                        optText);
                                }
                                $select.trigger('change');
                                setTimeout(function() {
                                    $row.removeData('nkb-restore-display');
                                    if ($select.val() !== nkbId) {
                                        $select.val(nkbId);
                                        if (optText) $select.next('.select2-container').find(
                                            '.select2-selection__rendered').text(optText);
                                    }
                                }, 50);
                            }
                        })
                        .fail(function(xhr, status, err) {
                            console.log('[NKB] API fail', {
                                xhr: xhr,
                                status: status,
                                err: err
                            });
                            $koli.val(0);
                            $ex.val(0);
                            $totalEx.val(0);
                        })
                        .always(function() {
                            $koli.prop('readonly', false).removeClass('loading');
                            $ex.prop('readonly', false).removeClass('loading');
                            $totalEx.prop('readonly', false).removeClass('loading');
                        });
                }

                function onNkbChange() {
                    var $select = $(this);
                    var nkbId = $select.val();
                    var $row = $select.closest('tr');
                    if (nkbId) {
                        fillRowFromNkb($row, nkbId);
                    } else {
                        $row.find('td').eq(1).find('input').val(0);
                        $row.find('td').eq(2).find('input').val(0);
                        $row.find('td').eq(3).find('input').val(0);
                    }
                }

                function initSelect2Nkb($el) {
                    if (!$el.length) return;
                    if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
                    $el.select2({
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                    $el.off('change.nkb change select2:select').on('change.nkb change select2:select', function(e) {
                        console.log('[NKB] onchange triggered', {
                            type: e.type,
                            params: e.params,
                            val: $(this).val()
                        });
                        var $row = $(this).closest('tr');
                        if ($row.data('nkb-restore-display')) return; // skip saat hanya restore tampilan
                        var nkbId = (e.params && e.params.data && e.params.data.id != null) ? String(e.params
                            .data.id) : $(this).val();
                        if (nkbId) {
                            console.log('[NKB] filling row for nkb_id:', nkbId);
                            fillRowFromNkb($row, nkbId);
                        } else {
                            console.log('[NKB] nkb cleared, reset to 0');
                            $row.find('td').eq(1).find('input').val(0);
                            $row.find('td').eq(2).find('input').val(0);
                            $row.find('td').eq(3).find('input').val(0);
                        }
                    });
                }

                function filterNkbByRecipient() {
                    var recipient = $('#recipient_code').val() || '';
                    var allowed = (recipient === '') ? nkbsList : nkbsList.filter(function(n) {
                        return (n.recipient_code || '') === recipient;
                    });
                    $('#items-tbody select.nkb-select[data-recipient-filter="1"]').each(function() {
                        var $sel = $(this);
                        var curVal = $sel.val();
                        $sel.find('option').remove();
                        $sel.append($('<option value="">-- Pilih NKB --</option>'));
                        allowed.forEach(function(n) {
                            var opt = $('<option></option>').attr('value', n.id).text(n.number);
                            if (String(n.id) === String(curVal)) opt.prop('selected', true);
                            $sel.append(opt);
                        });
                        if (curVal && allowed.every(function(n) {
                                return String(n.id) !== String(curVal);
                            })) {
                            $sel.closest('tr').find('td').eq(1).find('input').val(0);
                            $sel.closest('tr').find('td').eq(2).find('input').val(0);
                            $sel.closest('tr').find('td').eq(3).find('input').val(0);
                        }
                        if ($sel.hasClass('select2-hidden-accessible')) {
                            $sel.select2('destroy');
                        }
                        initSelect2Nkb($sel);
                    });
                }

                $('#recipient_code').on('change', function() {
                    filterNkbByRecipient();
                });

                $('#recipient_code').on('change.address', function() {
                    var $opt = $(this).find('option:selected');
                    $('#recipient_address').val($opt.data('address') || '');
                });
                if ($('#recipient_code').val()) {
                    var $selOpt = $('#recipient_code').find('option:selected');
                    $('#recipient_address').val($selOpt.data('address') || '');
                }

                $('#recipient_name_select').on('change', function() {
                    var $opt = $(this).find('option:selected');
                    $('#recipient_phone').val($opt.data('phone') || '');
                });
                if ($('#recipient_name_select').val()) {
                    $('#recipient_name_select').trigger('change');
                }

                $('#items-tbody select.select2-nkb').each(function() {
                    initSelect2Nkb($(this));
                });
                filterNkbByRecipient();

                // Isi otomatis untuk baris yang sudah punya NKB terpilih (mis. setelah validasi gagal)
                $('#items-tbody .item-row').each(function() {
                    var nkbId = $(this).find('select.nkb-select').val();
                    if (nkbId) fillRowFromNkb($(this), nkbId);
                });

                $('#btnAddRow').on('click', function() {
                    var tpl = $('#row-template').html();
                    var html = tpl.replace(/__INDEX__/g, rowIndex);
                    $('#items-tbody').append(html);
                    var $newSelect = $('#items-tbody tr:last-child select.select2-nkb');
                    initSelect2Nkb($newSelect);
                    filterNkbByRecipient();
                    rowIndex++;
                });

                $('#items-tbody').on('click', '.remove-row', function() {
                    var row = $(this).closest('tr');
                    if ($('#items-tbody .item-row').length > 1) {
                        row.find('select.select2-nkb').each(function() {
                            if ($(this).hasClass('select2-hidden-accessible')) $(this).select2(
                                'destroy');
                        });
                        row.remove();
                    }
                });
            });
        </script>
    @endpush
</x-layouts>
