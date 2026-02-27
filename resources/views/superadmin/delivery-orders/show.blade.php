<x-layouts>
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('delivery-orders.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Surat Jalan
                    </a>
                    <a href="{{ route('delivery-orders.edit', $deliveryOrder->id) }}" class="btn btn-outline-primary btn-sm mb-2 ms-1">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    <a href="{{ route('delivery-orders.print', $deliveryOrder->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm mb-2 ms-1">
                        <i class="bi bi-printer me-1"></i>Cetak
                    </a>
                </div>
                <strong>Surat Jalan — {{ $deliveryOrder->number }}</strong>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label small">Cabang Pengirim</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $deliveryOrder->senderBranch->branch_name ?? $deliveryOrder->sender_code }}" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Cabang Tujuan</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $deliveryOrder->recipientBranch->branch_name ?? $deliveryOrder->recipient_code }}" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Dibuat Oleh</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $deliveryOrder->creator->name ?? '-' }}" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Tanggal</label>
                    <input type="text" class="form-control form-control-sm"
                        value="{{ $deliveryOrder->date ? $deliveryOrder->date->format('d/m/Y') : '-' }}" readonly />
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label small">Expedisi</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $deliveryOrder->expedition ?? '-' }}" readonly />
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Plat No.</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $deliveryOrder->plate_number ?? '-' }}" readonly />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Supir</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $deliveryOrder->driver ?? '-' }}" readonly />
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Telepon Supir</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $deliveryOrder->driver_phone ?? '-' }}" readonly />
                </div>
            </div>
            @if(!empty($deliveryOrder->note))
                <div class="mb-3">
                    <label class="form-label small">Keterangan</label>
                    <textarea class="form-control form-control-sm" rows="2" readonly>{{ $deliveryOrder->note }}</textarea>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">NKB</th>
                            <th class="text-center">Cabang</th>
                            <th class="text-center" style="width:100px">Koli</th>
                            <th class="text-center" style="width:80px">EX.</th>
                            <th class="text-center" style="width:120px">Total EX</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveryOrder->items as $row)
                            <tr>
                                <td class="text-center"><code>{{ $row->nkb->number ?? $row->nkb_id }}</code></td>
                                <td class="text-center">{{ $row->nkb && $row->nkb->recipientBranch ? $row->nkb->recipientBranch->branch_name : ($row->nkb->recipient_code ?? '-') }}</td>
                                <td class="text-center">{{ number_format($row->koli, 0, ',', '.') }}</td>
                                <td class="text-center">{{ number_format($row->ex, 0, ',', '.') }}</td>
                                <td class="text-center">{{ number_format($row->total_ex, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts>
