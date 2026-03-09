<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('product.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Data Produk
                    </a>
                </div>
                <strong>History NKB — {{ $product->book_code }}</strong>
            </div>
            <p class="text-muted small mb-3">{{ $product->book_title }}</p>

            @if ($items->isEmpty())
                <p class="text-muted mb-0">Tidak ada NKB untuk buku ini.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. NKB</th>
                                <th>Pengirim</th>
                                <th>Tujuan</th>
                                <th>Tanggal</th>
                                <th class="text-end">Koli</th>
                                <th class="text-end">Eksemplar</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                @php
                                    $nkb = $item->nkb;
                                    $doc = $nkb?->document;
                                    $sender = $nkb?->sender_code ?? $doc?->sender_code ?? '-';
                                    $recipient = $nkb?->recipient_code ?? $doc?->recipient_code ?? '-';
                                    $sendDate = $nkb?->send_date ?? $doc?->send_date;
                                @endphp
                                <tr>
                                    <td><code>{{ $nkb->number ?? $item->nkb_code }}</code></td>
                                    <td>{{ $sender }}</td>
                                    <td>{{ $recipient }}</td>
                                    <td>{{ $sendDate ? $sendDate->format('d/m/Y') : '-' }}</td>
                                    <td class="text-end">{{ (int) $item->koli }}</td>
                                    <td class="text-end">{{ (int) $item->exp }}</td>
                                    <td>
                                        @if ($nkb)
                                            <a href="{{ route('nkb.show', ['number' => $nkb->number]) }}" class="btn btn-sm btn-outline-primary" title="Lihat NKB">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        @endif
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
