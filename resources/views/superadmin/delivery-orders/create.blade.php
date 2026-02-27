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
                        <input type="text" name="number" class="form-control form-control-sm" value="{{ old('number', $nextNumber ?? '') }}" required />
                        @error('number')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Cabang Pengirim</label>
                        <select name="sender_code" class="form-select form-select-sm select2-static" required>
                            <option value="">-- Pilih --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->branch_code }}" {{ old('sender_code') == $b->branch_code ? 'selected' : '' }}>{{ $b->branch_code }} — {{ $b->branch_name }}</option>
                            @endforeach
                        </select>
                        @error('sender_code')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Cabang Tujuan</label>
                        <select name="recipient_code" class="form-select form-select-sm select2-static" required>
                            <option value="">-- Pilih --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->branch_code }}" {{ old('recipient_code') == $b->branch_code ? 'selected' : '' }}>{{ $b->branch_code }} — {{ $b->branch_name }}</option>
                            @endforeach
                        </select>
                        @error('recipient_code')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Dibuat Oleh</label>
                        <input type="text" class="form-control form-control-sm" value="{{ auth()->user()->name ?? '' }}" readonly />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Tanggal</label>
                        <input type="date" name="date" class="form-control form-control-sm" value="{{ old('date', date('Y-m-d')) }}" />
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small">Expedisi</label>
                        <input type="text" name="expedition" class="form-control form-control-sm" value="{{ old('expedition') }}" placeholder="Nama ekspedisi" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Plat No.</label>
                        <input type="text" name="plate_number" class="form-control form-control-sm" value="{{ old('plate_number') }}" placeholder="B 1234 XY" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Supir</label>
                        <input type="text" name="driver" class="form-control form-control-sm" value="{{ old('driver') }}" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Telepon Supir</label>
                        <input type="text" name="driver_phone" class="form-control form-control-sm" value="{{ old('driver_phone') }}" />
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Keterangan</label>
                    <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Catatan pengiriman">{{ old('note') }}</textarea>
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
                                if (empty($oldItems)) $oldItems = [['nkb_id' => '', 'koli' => 0, 'ex' => 0, 'total_ex' => 0]];
                            @endphp
                            @foreach($oldItems as $idx => $oi)
                                <tr class="item-row">
                                    <td>
                                        <select name="items[{{ $idx }}][nkb_id]" class="form-select form-select-sm nkb-select select2-nkb" required>
                                            <option value="">-- Pilih NKB --</option>
                                            @foreach($nkbs as $n)
                                                <option value="{{ $n->id }}" {{ (old('items.'.$idx.'.nkb_id') ?? $oi['nkb_id'] ?? '') == $n->id ? 'selected' : '' }}>{{ $n->number }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[{{ $idx }}][koli]" class="form-control form-control-sm text-center" value="{{ old('items.'.$idx.'.koli', $oi['koli'] ?? 0) }}" min="0" step="1" required /></td>
                                    <td><input type="number" name="items[{{ $idx }}][ex]" class="form-control form-control-sm text-center" value="{{ old('items.'.$idx.'.ex', $oi['ex'] ?? 0) }}" min="0" step="1" required /></td>
                                    <td><input type="number" name="items[{{ $idx }}][total_ex]" class="form-control form-control-sm text-center" value="{{ old('items.'.$idx.'.total_ex', $oi['total_ex'] ?? 0) }}" min="0" step="1" required /></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('items')<div class="text-danger small">{{ $message }}</div>@enderror

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
                <select name="items[__INDEX__][nkb_id]" class="form-select form-select-sm nkb-select select2-nkb" required>
                    <option value="">-- Pilih NKB --</option>
                    @foreach($nkbs as $n)
                        <option value="{{ $n->id }}">{{ $n->number }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" name="items[__INDEX__][koli]" class="form-control form-control-sm text-center" value="0" min="0" step="1" required /></td>
            <td><input type="number" name="items[__INDEX__][ex]" class="form-control form-control-sm text-center" value="0" min="0" step="1" required /></td>
            <td><input type="number" name="items[__INDEX__][total_ex]" class="form-control form-control-sm text-center" value="0" min="0" step="1" required /></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
        </tr>
    </template>

    @push('js')
    <script>
        $(function() {
            var rowIndex = $('#items-tbody .item-row').length;
            var nkbDetailUrlTemplate = '{{ route("api.nkb.detail", ["id" => ":id"]) }}';

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
                console.log('[NKB] fetch detail', { nkbId: nkbId, url: url });
                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
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
                                $select.next('.select2-container').find('.select2-selection__rendered').text(optText);
                            }
                            $select.trigger('change');
                            setTimeout(function() {
                                $row.removeData('nkb-restore-display');
                                if ($select.val() !== nkbId) {
                                    $select.val(nkbId);
                                    if (optText) $select.next('.select2-container').find('.select2-selection__rendered').text(optText);
                                }
                            }, 50);
                        }
                    })
                    .fail(function(xhr, status, err) {
                        console.log('[NKB] API fail', { xhr: xhr, status: status, err: err });
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
                $el.select2({ theme: 'bootstrap-5', width: '100%' });
                $el.off('change.nkb change select2:select').on('change.nkb change select2:select', function(e) {
                    console.log('[NKB] onchange triggered', { type: e.type, params: e.params, val: $(this).val() });
                    var $row = $(this).closest('tr');
                    if ($row.data('nkb-restore-display')) return; // skip saat hanya restore tampilan
                    var nkbId = (e.params && e.params.data && e.params.data.id != null) ? String(e.params.data.id) : $(this).val();
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

            $('#items-tbody select.select2-nkb').each(function() {
                initSelect2Nkb($(this));
            });

            // Isi otomatis untuk baris yang sudah punya NKB terpilih (mis. setelah validasi gagal)
            $('#items-tbody .item-row').each(function() {
                var nkbId = $(this).find('select.nkb-select').val();
                if (nkbId) fillRowFromNkb($(this), nkbId);
            });

            $('#btnAddRow').on('click', function() {
                var tpl = $('#row-template').html();
                var html = tpl.replace(/__INDEX__/g, rowIndex);
                $('#items-tbody').append(html);
                initSelect2Nkb($('#items-tbody tr:last-child select.select2-nkb'));
                rowIndex++;
            });

            $('#items-tbody').on('click', '.remove-row', function() {
                var row = $(this).closest('tr');
                if ($('#items-tbody .item-row').length > 1) {
                    row.find('select.select2-nkb').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
                    });
                    row.remove();
                }
            });
        });
    </script>
    @endpush
</x-layouts>
