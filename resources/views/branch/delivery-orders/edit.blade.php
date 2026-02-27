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
                    <div class="col-md-3">
                        <label class="form-label small">Supir</label>
                        <input type="text" name="driver" class="form-control form-control-sm" value="{{ old('driver', $deliveryOrder->driver) }}" />
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
