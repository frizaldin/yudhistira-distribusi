<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('branch.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Data Cabang
                    </a>
                </div>
                <strong>History NKB — {{ $branch->branch_code }}</strong>
            </div>
            <p class="text-muted small mb-3">{{ $branch->branch_name }}</p>

            <form method="GET" action="{{ route('branch.nkb-history', ['branch_code' => $branch->branch_code]) }}" class="row g-2 mb-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Tanggal Awal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date', $start_date ?? '') }}" />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Tanggal Akhir</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date', $end_date ?? '') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2 align-items-bottom">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('branch.nkb-history', ['branch_code' => $branch->branch_code]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>

            @if ($nkbs->isEmpty())
                <p class="text-muted mb-0">Tidak ada NKB untuk cabang ini.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. NKB</th>
                                <th>Pengirim</th>
                                <th>Tujuan</th>
                                <th>Tanggal</th>
                                <th class="text-end">Jenis Buku</th>
                                <th class="text-end">Eksemplar</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($nkbs as $nkb)
                                @php
                                    $doc = $nkb->document;
                                    $sender = $nkb->sender_code ?? $doc?->sender_code ?? '-';
                                    $recipient = $nkb->recipient_code ?? $doc?->recipient_code ?? '-';
                                    $sendDate = $nkb->send_date ?? $doc?->send_date;
                                @endphp
                                <tr>
                                    <td><code>{{ $nkb->number }}</code></td>
                                    <td>{{ $sender }}</td>
                                    <td>{{ $recipient }}</td>
                                    <td>{{ $sendDate ? $sendDate->format('d/m/Y') : '-' }}</td>
                                    <td class="text-end">{{ $nkb->total_type_books ?? '-' }}</td>
                                    <td class="text-end">{{ $nkb->total_exemplar ?? 0 }}</td>
                                    <td>
                                        <a href="{{ route('nkb.show', ['number' => $nkb->number]) }}" class="btn btn-sm btn-outline-primary" title="Lihat NKB">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts>
