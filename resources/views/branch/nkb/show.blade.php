<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('nkb.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar NKB
                    </a>
                    <a href="{{ route('nkb.print', ['number' => $nkb->number]) }}" target="_blank"
                        class="btn btn-outline-secondary btn-sm mb-2 ms-1">
                        <i class="bi bi-printer me-1"></i>Print
                    </a>
                    <strong>Detail NKB — {{ $nkb->number ?? '' }}</strong>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label small">Kode Nota Kirim</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $nkb->number ?? '' }}" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Cabang Pengirim</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $nkb->senderBranch->branch_name ?? $nkb->sender_code }}" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Cabang Tujuan</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $nkb->recipientBranch->branch_name ?? $nkb->recipient_code }}" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Tanggal</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $nkb->send_date ? $nkb->send_date->format('d/m/Y') : '' }}" readonly />
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label small">NPPB</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $nkb->nppb_code ?? '' }}" readonly />
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Jenis Buku / Eksemplar</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $nkb->total_type_books ?? 0 }} jenis, {{ $nkb->total_exemplar ?? 0 }} eksemplar" readonly />
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Keterangan</label>
                    <textarea class="form-control form-control-sm" rows="2" readonly>{{ trim(($nkb->note ?? '') . "\n" . ($nkb->note_more ?? '')) }}</textarea>
                </div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Buku</th>
                            <th class="text-center" style="width:70px">Koli</th>
                            <th class="text-center" style="width:80px">Isi koli</th>
                            <th class="text-center" style="width:80px">Eksemplar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($nkb->items ?? [] as $it)
                            <tr>
                                <td>{{ $it->book_code }} — {{ $it->book_name }}</td>
                                <td class="text-center">{{ $it->koli }}</td>
                                <td class="text-center">{{ $it->volume }}</td>
                                <td class="text-center">{{ $it->exp }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (!empty($stack))
                <a href="{{ route('preparation_notes.detail', ['stack' => $stack]) }}"
                    class="btn btn-outline-secondary btn-sm me-1">
                    <i class="bi bi-card-list me-1"></i>Detail Rencana (NPPB)
                </a>
            @endif
            <a href="{{ route('nkb.index') }}" class="btn btn-outline-secondary btn-sm">Kembali ke Daftar NKB</a>
        </div>
    </div>
</x-layouts>
