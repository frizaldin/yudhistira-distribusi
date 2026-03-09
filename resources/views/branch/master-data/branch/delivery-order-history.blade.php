<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('branch.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Data Cabang
                    </a>
                </div>
                <strong>History Surat Jalan — {{ $branch->branch_code }}</strong>
            </div>
            <p class="text-muted small mb-3">{{ $branch->branch_name }}</p>

            @if ($deliveryOrders->isEmpty())
                <p class="text-muted mb-0">Tidak ada Surat Jalan untuk cabang ini.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. Surat Jalan</th>
                                <th>Pengirim</th>
                                <th>Tujuan</th>
                                <th>Tanggal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryOrders as $do)
                                <tr>
                                    <td><code>{{ $do->number }}</code></td>
                                    <td>{{ $do->sender_code ?? ($do->senderBranch?->branch_name ?? $do->sender_code) }}</td>
                                    <td>{{ $do->recipient_code ?? ($do->recipientBranch?->branch_name ?? $do->recipient_code) }}</td>
                                    <td>{{ $do->date ? $do->date->format('d/m/Y') : '-' }}</td>
                                    <td>
                                        <a href="{{ route('delivery-orders.show', ['id' => $do->id]) }}" class="btn btn-sm btn-outline-primary" title="Lihat Surat Jalan">
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
