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
                    <a href="{{ route('preparation_notes.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Rencana Kirim
                    </a>
                    <strong>Detail Rencana Kirim: <code>{{ $stack ?? '' }}</code></strong>
                    @if (!empty($creator_name))
                        <span class="text-muted ms-2">— Dibuat oleh: <strong>{{ $creator_name }}</strong></span>
                    @endif
                    <br />
                    @if (!empty($has_document))
                        <small class="text-muted">Daftar lengkap baris NPPB — data sudah disetujui, tidak dapat
                            diedit.</small>
                    @else
                        <small class="text-muted">Daftar lengkap baris NPPB untuk rencana kirim ini (Isi, Koli, Eceran,
                            Total dapat diedit)</small>
                    @endif
                </div>
                @if (isset($rows) && $rows->isNotEmpty())
                    <div class="d-flex gap-2">
                        @if (empty($has_document))
                            <button type="submit" form="form-detail-update" class="btn btn-primary btn-sm">
                                <i class="bi bi-save me-1"></i>Simpan Perubahan
                            </button>
                        @endif
                        @if (empty($has_document))
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#modalApproveRencana">
                                <i class="bi bi-check-circle me-1"></i>Approve Rencana
                            </button>
                        @else
                            <span class="badge bg-success align-self-center">Sudah disetujui</span>
                            <a href="{{ route('preparation_notes.export_nota', ['stack' => $stack ?? '', 'print' => 1]) }}"
                                target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-printer me-1"></i>Print Nota
                            </a>
                            @if (!empty($existing_nkb))
                                <a href="{{ route('preparation_notes.view_nkb', ['number' => $existing_nkb->number]) }}"
                                    class="btn btn-outline-primary btn-sm ms-1">
                                    <i class="bi bi-eye me-1"></i>Lihat NKB
                                </a>
                            @else
                                <a href="{{ route('preparation_notes.preview_nkb_page', ['stack' => $stack ?? '']) }}"
                                    class="btn btn-outline-primary btn-sm ms-1">
                                    <i class="bi bi-file-earmark-plus me-1"></i>Jadikan NKB
                                </a>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            @if (empty($has_document))
                <form id="form-detail-update" method="POST" action="{{ route('preparation_notes.detail.update') }}">
                    @csrf
                    <input type="hidden" name="stack" value="{{ $stack ?? '' }}" />
            @endif
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Kode Cabang</th>
                            <th>Nama Cabang</th>
                            <th>Kode Buku</th>
                            <th>Nama Buku</th>
                            <th class="text-center">Isi</th>
                            <th class="text-center">Koli</th>
                            <th class="text-center">Eceran</th>
                            <th class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows ?? [] as $row)
                            <tr>
                                <td>{{ $row->date ? \Carbon\Carbon::parse($row->date)->format('d/m/Y') : '-' }}</td>
                                <td><code>{{ $row->branch_code }}</code></td>
                                <td>{{ $row->branch_name ?? '-' }}</td>
                                <td><code>{{ $row->book_code }}</code></td>
                                <td>{{ $row->book_name ?? '-' }}</td>
                                @if (empty($has_document))
                                    <td class="text-end">
                                        <input type="hidden" name="rows[{{ $row->id }}][id]"
                                            value="{{ $row->id }}" form="form-detail-update" />
                                        <input type="number" name="rows[{{ $row->id }}][volume]"
                                            value="{{ $row->volume }}" form="form-detail-update"
                                            class="form-control form-control-sm text-end" min="0" step="1"
                                            style="width: 80px; display: inline-block;" />
                                    </td>
                                    <td class="text-end">
                                        <input type="number" name="rows[{{ $row->id }}][koli]"
                                            value="{{ $row->koli }}" form="form-detail-update"
                                            class="form-control form-control-sm text-end" min="0" step="1"
                                            style="width: 80px; display: inline-block;" />
                                    </td>
                                    <td class="text-end">
                                        <input type="number" name="rows[{{ $row->id }}][pls]"
                                            value="{{ $row->pls }}" form="form-detail-update"
                                            class="form-control form-control-sm text-end" min="0" step="1"
                                            style="width: 80px; display: inline-block;" />
                                    </td>
                                    <td class="text-end">
                                        <input type="number" name="rows[{{ $row->id }}][exp]"
                                            value="{{ $row->exp }}" form="form-detail-update"
                                            class="form-control form-control-sm text-end" min="0" step="1"
                                            style="width: 80px; display: inline-block;" />
                                    </td>
                                @else
                                    <td class="text-end">{{ number_format($row->volume ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($row->koli ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($row->pls ?? 0) }}</td>
                                    <td class="text-end">{{ number_format($row->exp ?? 0) }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    Tidak ada data untuk stack ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (empty($has_document))
                </form>
            @endif
        </div>
    </div>

    {{-- Modal Approve Rencana --}}
    @if (isset($rows) && $rows->isNotEmpty() && empty($has_document))
        <div class="modal fade" id="modalApproveRencana" tabindex="-1" aria-labelledby="modalApproveRencanaLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('preparation_notes.approve_rencana') }}">
                        @csrf
                        <input type="hidden" name="stack" value="{{ $stack ?? '' }}" />
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalApproveRencanaLabel">Approve Rencana Kirim</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Isi data dokumen NPPB. Semua baris dalam stack ini akan
                                dihubungkan ke dokumen ini.</p>

                            <div class="mb-2">
                                <label class="form-label">Catatan <span class="text-danger">*</span></label>
                                <textarea name="note" class="form-control form-control-sm" rows="2" required>{{ old('note') }}</textarea>
                                @error('note')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                                <small><i>Contoh: Kepada, Pemegang Stock Gudang U/P Destiana Mohon Segera Disiapkan
                                        Barang Barang SBB</i></small>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Pengirim <span class="text-danger">*</span></label>
                                <select name="sender_code" id="approve_sender_code"
                                    class="form-select form-select-sm select2-approve-modal w-100" required>
                                    <option value="">-- Pilih Pengirim --</option>
                                    @foreach ($branches ?? [] as $b)
                                        <option value="{{ $b->branch_code }}"
                                            {{ old('sender_code') == $b->branch_code ? 'selected' : '' }}>
                                            {{ $b->branch_code }} - {{ $b->branch_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sender_code')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Penerima (Cabang) <span class="text-danger">*</span></label>
                                <select name="recipient_code" id="approve_recipient_code"
                                    class="form-select form-select-sm select2-approve-modal w-100" required>
                                    <option value="">-- Pilih Cabang Penerima --</option>
                                    @foreach ($branches ?? [] as $b)
                                        <option value="{{ $b->branch_code }}"
                                            {{ old('recipient_code') == $b->branch_code ? 'selected' : '' }}>
                                            {{ $b->branch_code }} - {{ $b->branch_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('recipient_code')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Tanggal Kirim <span class="text-danger">*</span></label>
                                <input type="date" name="send_date" class="form-control form-control-sm"
                                    value="{{ old('send_date', date('Y-m-d')) }}" required />
                                @error('send_date')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">Total Jenis Buku <span
                                            class="text-danger">*</span></label>
                                    <input type="number" name="total_type_books" readonly
                                        class="form-control form-control-sm" min="0"
                                        value="{{ old('total_type_books', $total_type_books ?? 0) }}" required />
                                    @error('total_type_books')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Total Eksemplar <span
                                            class="text-danger">*</span></label>
                                    <input type="number" name="total_exemplar" readonly
                                        class="form-control form-control-sm" min="0"
                                        value="{{ old('total_exemplar', $total_exemplar ?? 0) }}" required />
                                    @error('total_exemplar')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Catatan Tambahan <span class="text-danger">*</span></label>
                                <textarea name="note_more" class="form-control form-control-sm" rows="2" required>{{ old('note_more') }}</textarea>
                                @error('note_more')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                                <small><i>Contoh : Tolong Kirim Buku Secepatnya</i></small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success btn-sm"><i
                                    class="bi bi-check-circle me-1"></i>Simpan & Setujui</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

@push('js')
            <script>
                $(function() {
                    var $modal = $('#modalApproveRencana');
                    if (!$modal.length) return;
                    var opts = {
                        theme: 'bootstrap-5',
                        width: '100%',
                        allowClear: true,
                        dropdownParent: $modal
                    };
                    $modal.on('shown.bs.modal', function() {
                        var $s = $('#approve_sender_code'),
                            $r = $('#approve_recipient_code');
                        if (!$s.length || !$r.length) return;
                        if ($s.hasClass('select2-hidden-accessible')) $s.select2('destroy');
                        if ($r.hasClass('select2-hidden-accessible')) $r.select2('destroy');
                        $s.select2(opts);
                        $r.select2(opts);
                    }).on('hidden.bs.modal', function() {
                        var $s = $('#approve_sender_code'),
                            $r = $('#approve_recipient_code');
                        if ($s.hasClass('select2-hidden-accessible')) $s.select2('destroy');
                        if ($r.hasClass('select2-hidden-accessible')) $r.select2('destroy');
                    });
                });
            </script>
        @endpush
    @endif
</x-layouts>
