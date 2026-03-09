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
                    <a href="{{ route('nkb.show', ['number' => $nkb->number]) }}" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Detail NKB
                    </a>
                    <strong>Edit NKB — {{ $nkb->number ?? '' }}</strong>
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

            <form action="{{ route('nkb.update', ['number' => $nkb->number]) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Buku</th>
                                <th class="text-center" style="width:90px">Koli</th>
                                <th class="text-center" style="width:90px">Isi koli</th>
                                <th class="text-center" style="width:90px">Eksemplar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($nkb->items ?? [] as $it)
                                <tr>
                                    <td>{{ $it->book_code }} — {{ $it->book_name }}</td>
                                    <td class="text-center">
                                        <input type="number" name="items[{{ $it->id }}][koli]" class="form-control form-control-sm text-center" min="0" step="1" value="{{ old('items.'.$it->id.'.koli', $it->koli) }}" required />
                                    </td>
                                    <td class="text-center">
                                        <input type="number" name="items[{{ $it->id }}][volume]" class="form-control form-control-sm text-center" min="0" step="1" value="{{ old('items.'.$it->id.'.volume', $it->volume) }}" required />
                                    </td>
                                    <td class="text-center">
                                        <input type="number" name="items[{{ $it->id }}][exp]" class="form-control form-control-sm text-center" min="0" step="1" value="{{ old('items.'.$it->id.'.exp', $it->exp) }}" required />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('nkb.show', ['number' => $nkb->number]) }}" class="btn btn-outline-secondary btn-sm">Batal</a>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts>
