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
                    @if (!empty($from) && $from === 'nkb')
                        <a href="{{ route('nkb.create') }}" class="btn btn-outline-secondary btn-sm mb-2">
                            <i class="bi bi-arrow-left me-1"></i>Kembali ke Pilih NPPB
                        </a>
                    @else
                        <a href="{{ route('preparation_notes.detail', ['stack' => $stack ?? '']) }}"
                            class="btn btn-outline-secondary btn-sm mb-2">
                            <i class="bi bi-arrow-left me-1"></i>Kembali ke Detail Rencana
                        </a>
                    @endif
                    <strong>Preview NKB — Pilih item yang akan dimasukkan</strong>
                </div>
            </div>

            <p class="text-muted small mb-3">(*) harus terisi</p>
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label small">Kode Nota Kirim</label>
                    <input type="text" class="form-control form-control-sm" value="(akan digenerate)" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Cabang Pengirim</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $document->senderBranch->branch_name ?? $document->sender_code }}" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Cabang Tujuan</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $document->recipientBranch->branch_name ?? $document->recipient_code }}" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Tanggal</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $document->send_date ? $document->send_date->format('d/m/Y') : '' }}" readonly />
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label small">NPPB</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $document->number ?? '' }}"
                        readonly />
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Keterangan (Note)</label>
                    <textarea class="form-control form-control-sm" rows="2" readonly>{{ $document->note ?? '' }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Keterangan Lanjutan (Note More)</label>
                    <textarea class="form-control form-control-sm" rows="2" readonly>{{ $document->note_more ?? '' }}</textarea>
                </div>
            </div>

            <form action="{{ route('preparation_notes.jadikan_nkb') }}" method="POST" id="form-jadikan-nkb">
                @csrf
                <input type="hidden" name="stack" value="{{ $stack ?? '' }}" />
                @if (!empty($from))
                    <input type="hidden" name="from" value="{{ $from }}" />
                @endif
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Nama Pembuat <span class="text-danger">*</span></label>
                        <input type="text" name="creator_name" class="form-control form-control-sm" maxlength="255" value="{{ old('creator_name', $document->creator_name ?? '') }}" required />
                        <small class="text-muted">Diambil dari NPPB, bisa diedit</small>
                        @error('creator_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Nama Dikenal <span class="text-danger">*</span></label>
                        <input type="text" name="known_name" class="form-control form-control-sm" maxlength="255" value="{{ old('known_name', $document->known_name ?? '') }}" required />
                        <small class="text-muted">Diambil dari NPPB, bisa diedit</small>
                        @error('known_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px" class="text-center">
                                    <input type="checkbox" id="preview-check-all" class="form-check-input" checked />
                                </th>
                                <th class="text-start">Buku</th>
                                <th class="text-center" style="width:70px">Koli</th>
                                <th class="text-center" style="width:80px">Isi koli</th>
                                <th class="text-center" style="width:80px">Eksemplar</th>
                                <th class="text-end" style="width:110px">Harga (Rp)</th>
                                <th class="text-end" style="width:110px">Total (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items ?? [] as $it)
                                @php
                                    $oldKoli = old('items.'.$it->id.'.koli', $it->koli);
                                    $oldVolume = old('items.'.$it->id.'.volume', $it->volume);
                                    $oldExp = old('items.'.$it->id.'.exp', $it->exp);
                                    $subtotal = $it->harga_buku ? $oldExp * $it->harga_buku : 0;
                                @endphp
                                <tr class="preview-item-row" data-harga="{{ $it->harga_buku ?? 0 }}">
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input item-nkb-check" name="row_ids[]"
                                            value="{{ $it->id }}" checked />
                                    </td>
                                    <td class="text-start">{{ $it->book_code }} — {{ $it->book_name }}</td>
                                    <td class="text-center">
                                        <input type="number" name="items[{{ $it->id }}][koli]" class="form-control form-control-sm text-center input-koli-preview" min="0" step="1" value="{{ $oldKoli }}" style="width:60px" />
                                    </td>
                                    <td class="text-center">
                                        <input type="number" name="items[{{ $it->id }}][volume]" class="form-control form-control-sm text-center input-volume-preview" min="0" step="1" value="{{ $oldVolume }}" style="width:60px" />
                                    </td>
                                    <td class="text-center">
                                        <input type="number" name="items[{{ $it->id }}][exp]" class="form-control form-control-sm text-center input-exp-preview" min="0" step="1" value="{{ $oldExp }}" style="width:70px" />
                                    </td>
                                    <td class="text-end">
                                        {{ $it->harga_buku ? number_format($it->harga_buku, 0, ',', '.') : '-' }}</td>
                                    <td class="text-end preview-subtotal">{{ $subtotal ? number_format($subtotal, 0, ',', '.') : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-2">
                    @if (!empty($from) && $from === 'nkb')
                        <a href="{{ route('nkb.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
                    @else
                        <a href="{{ route('preparation_notes.detail', ['stack' => $stack ?? '']) }}"
                            class="btn btn-outline-secondary btn-sm">Batal</a>
                    @endif
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Buat NKB
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('js')
        <script>
            $(function() {
                $('#preview-check-all').on('change', function() {
                    $('.item-nkb-check').prop('checked', this.checked);
                });
                $('#form-jadikan-nkb').on('submit', function(e) {
                    var n = $('.item-nkb-check:checked').length;
                    if (n === 0) {
                        e.preventDefault();
                        alert('Pilih minimal satu item.');
                        return false;
                    }
                });
                function updateSubtotal($row) {
                    var harga = parseFloat($row.data('harga')) || 0;
                    var exp = parseFloat($row.find('.input-exp-preview').val()) || 0;
                    var subtotal = exp * harga;
                    $row.find('.preview-subtotal').text(subtotal ? subtotal.toLocaleString('id-ID') : '-');
                }
                $(document).on('change input', '.input-koli-preview, .input-volume-preview, .input-exp-preview', function() {
                    updateSubtotal($(this).closest('tr'));
                });
            });
        </script>
    @endpush
</x-layouts>
