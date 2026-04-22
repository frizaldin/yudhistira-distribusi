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
                    <a href="{{ route('delivery-orders.show', $deliveryOrder->id) }}" class="btn btn-outline-primary btn-sm ms-1">
                        <i class="bi bi-eye me-1"></i>Detail
                    </a>
                </div>
                <strong>Edit Surat Jalan — {{ $deliveryOrder->number }}</strong>
            </div>

            <form action="{{ route('delivery-orders.update', $deliveryOrder->id) }}" method="POST" id="formSuratJalan">
                @csrf
                @method('PUT')

                <div class="row g-2 mb-3">
                    <div class="col-md-2">
                        <label class="form-label small">No. Surat Jalan</label>
                        <input type="text" name="number" class="form-control form-control-sm" value="{{ old('number', $deliveryOrder->number) }}" required />
                        @error('number')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Cabang Pengirim</label>
                        <select name="sender_code" class="form-select form-select-sm" required>
                            <option value="">-- Pilih --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->branch_code }}" {{ old('sender_code', $deliveryOrder->sender_code) == $b->branch_code ? 'selected' : '' }}>{{ $b->branch_code }} — {{ $b->branch_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Cabang Tujuan</label>
                        <select name="recipient_code" class="form-select form-select-sm" required>
                            <option value="">-- Pilih --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->branch_code }}" {{ old('recipient_code', $deliveryOrder->recipient_code) == $b->branch_code ? 'selected' : '' }}>{{ $b->branch_code }} — {{ $b->branch_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Tanggal</label>
                        <input type="date" name="date" class="form-control form-control-sm" value="{{ old('date', $deliveryOrder->date ? $deliveryOrder->date->format('Y-m-d') : '') }}" />
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small">Expedisi</label>
                        <input type="text" name="expedition" class="form-control form-control-sm" value="{{ old('expedition', $deliveryOrder->expedition) }}" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Plat No.</label>
                        <input type="text" name="plate_number" class="form-control form-control-sm" value="{{ old('plate_number', $deliveryOrder->plate_number) }}" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Supir 1</label>
                        <input type="text" name="drivers[0]" class="form-control form-control-sm" value="{{ old('drivers.0', ($deliveryOrder->drivers ?? [])[0] ?? '') }}" placeholder="Nama supir" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Supir 2</label>
                        <input type="text" name="drivers[1]" class="form-control form-control-sm" value="{{ old('drivers.1', ($deliveryOrder->drivers ?? [])[1] ?? '') }}" placeholder="Nama supir" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Telepon Supir</label>
                        <input type="text" name="driver_phone" class="form-control form-control-sm" value="{{ old('driver_phone', $deliveryOrder->driver_phone) }}" />
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Keterangan</label>
                    <textarea name="note" class="form-control form-control-sm" rows="2">{{ old('note', $deliveryOrder->note) }}</textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Nama Pembuat <span class="text-danger">*</span></label>
                        <input type="text" name="creator_name" class="form-control form-control-sm" maxlength="255" value="{{ old('creator_name', $deliveryOrder->creator_name ?? '') }}" required />
                        @error('creator_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Diketahui Oleh <span class="text-danger">*</span></label>
                        <input type="text" name="known_name" class="form-control form-control-sm" maxlength="255" value="{{ old('known_name', $deliveryOrder->known_name ?? '') }}" required />
                        @error('known_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Nama Penerima</label>
                        <input type="text" name="recipient_name" class="form-control form-control-sm" maxlength="255" value="{{ old('recipient_name', $deliveryOrder->recipient_name ?? '') }}" />
                        @error('recipient_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">No Telepon Penerima</label>
                        <input type="text" name="recipient_phone" class="form-control form-control-sm" maxlength="50" value="{{ old('recipient_phone', $deliveryOrder->recipient_phone ?? '') }}" placeholder="08xxxxxxxxxx" />
                        @error('recipient_phone')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Alamat Penerima</label>
                        <input type="text" name="recipient_address" class="form-control form-control-sm" value="{{ old('recipient_address', $deliveryOrder->recipient_address ?? '') }}" />
                        @error('recipient_address')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small">Koli</label>
                        <input type="number" id="input_koli" name="koli" class="form-control form-control-sm" min="0" value="{{ old('koli', $deliveryOrder->koli ?? '') }}" />
                        @error('koli')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Pack</label>
                        <input type="number" id="input_pack" name="pack" class="form-control form-control-sm" min="0" value="{{ old('pack', $deliveryOrder->pack ?? '') }}" />
                        @error('pack')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Terbilang</label>
                        <input type="text" id="input_terbilang" name="terbilang" class="form-control form-control-sm" maxlength="500" value="{{ old('terbilang', $deliveryOrder->terbilang ?? '') }}" placeholder="Otomatis terisi..." />
                        @error('terbilang')<div class="text-danger small">{{ $message }}</div>@enderror
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
                                $oldItems = old('items');
                                if ($oldItems === null) {
                                    $oldItems = $deliveryOrder->items->map(fn($i) => ['nkb_id' => $i->nkb_id, 'koli' => $i->koli, 'ex' => $i->ex, 'total_ex' => $i->total_ex])->toArray();
                                }
                                if (empty($oldItems)) $oldItems = [['nkb_id' => '', 'koli' => 0, 'ex' => 0, 'total_ex' => 0]];
                            @endphp
                            @foreach($oldItems as $idx => $oi)
                                <tr class="item-row">
                                    <td>
                                        <select name="items[{{ $idx }}][nkb_id]" class="form-select form-select-sm nkb-select" required>
                                            <option value="">-- Pilih NKB --</option>
                                            @foreach($nkbs as $n)
                                                <option value="{{ $n->id }}" {{ (old('items.'.$idx.'.nkb_id') ?? ($oi['nkb_id'] ?? '')) == $n->id ? 'selected' : '' }}>{{ $n->number }}</option>
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

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                    </button>
                    <a href="{{ route('delivery-orders.show', $deliveryOrder->id) }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <template id="row-template">
        <tr class="item-row">
            <td>
                <select name="items[__INDEX__][nkb_id]" class="form-select form-select-sm nkb-select" required>
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
        document.addEventListener('DOMContentLoaded', function() {
            // ── Terbilang ─────────────────────────────────────────────────────
            var satuanTb = ['','Satu','Dua','Tiga','Empat','Lima','Enam','Tujuh','Delapan','Sembilan',
                            'Sepuluh','Sebelas','Dua Belas','Tiga Belas','Empat Belas','Lima Belas',
                            'Enam Belas','Tujuh Belas','Delapan Belas','Sembilan Belas'];
            var puluhanTb = ['','Sepuluh','Dua Puluh','Tiga Puluh','Empat Puluh','Lima Puluh',
                             'Enam Puluh','Tujuh Puluh','Delapan Puluh','Sembilan Puluh'];
            function terbilangAngka(n) {
                n = Math.abs(parseInt(n)) || 0;
                if (n === 0) return 'Nol';
                if (n < 20) return satuanTb[n];
                if (n < 100) { var p = Math.floor(n/10), s = n%10; return puluhanTb[p]+(s?' '+satuanTb[s]:''); }
                if (n < 1000) { var r = Math.floor(n/100); return (r===1?'Seratus':satuanTb[r]+' Ratus')+(n%100?' '+terbilangAngka(n%100):''); }
                if (n < 1000000) { var r2 = Math.floor(n/1000); return (r2===1?'Seribu':terbilangAngka(r2)+' Ribu')+(n%1000?' '+terbilangAngka(n%1000):''); }
                return n.toString();
            }
            function updateTerbilang() {
                var koli = parseInt(document.getElementById('input_koli').value) || 0;
                var pack = parseInt(document.getElementById('input_pack').value) || 0;
                var parts = [];
                if (koli > 0) parts.push(terbilangAngka(koli) + ' Koli');
                if (pack > 0) parts.push(terbilangAngka(pack) + ' Pack');
                document.getElementById('input_terbilang').value = parts.join(' ');
            }
            document.getElementById('input_koli').addEventListener('input', updateTerbilang);
            document.getElementById('input_pack').addEventListener('input', updateTerbilang);
            // ─────────────────────────────────────────────────────────────────

            let rowIndex = document.querySelectorAll('#items-tbody .item-row').length;

            document.getElementById('btnAddRow').addEventListener('click', function() {
                const tpl = document.getElementById('row-template');
                const html = tpl.innerHTML.replace(/__INDEX__/g, rowIndex);
                document.getElementById('items-tbody').insertAdjacentHTML('beforeend', html);
                rowIndex++;
            });

            document.getElementById('items-tbody').addEventListener('click', function(e) {
                if (e.target.closest('.remove-row')) {
                    const row = e.target.closest('tr');
                    if (document.querySelectorAll('#items-tbody .item-row').length > 1) {
                        row.remove();
                    }
                }
            });
        });
    </script>
    @endpush
</x-layouts>
