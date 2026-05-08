<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Detail Nota Penghapusan</h5>
                <a href="{{ route('erase_item.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="200">No. Referensi (erase_code)</th>
                            <td>: <strong>{{ $data->erase_code }}</strong></td>
                        </tr>                        <tr>
                            <th>Cabang (branch_code)</th>
                            <td>: {{ $data->branch_code ?? '-' }}</td>
                        </tr>                        <tr>
                            <th>Tgl Transaksi (trans_date)</th>
                            <td>: {{ $data->trans_date ? \Carbon\Carbon::parse($data->trans_date)->format('d/m/Y') : '-' }}</td>
                        </tr>                        <tr>
                            <th>Tgl Edit (edit_date)</th>
                            <td>: {{ $data->edit_date ? \Carbon\Carbon::parse($data->edit_date)->format('d/m/Y') : '-' }}</td>
                        </tr>                        <tr>
                            <th>Kode Karyawan (empl_code)</th>
                            <td>: {{ $data->empl_code ?? '-' }}</td>
                        </tr>                        <tr>
                            <th>Kepala Gudang (whouse_head)</th>
                            <td>: {{ $data->whouse_head ?? '-' }}</td>
                        </tr>                        <tr>
                            <th>User ID (user_id)</th>
                            <td>: {{ $data->user_id ?? '-' }}</td>
                        </tr>                        <tr>
                            <th>Printed (printed)</th>
                            <td>: {{ $data->printed ?? '-' }}</td>
                        </tr>                        <tr>
                            <th>Info (info)</th>
                            <td>: {{ $data->info ?? '-' }}</td>
                        </tr>                        <tr>
                            <th>Status (status)</th>
                            <td>: {{ $data->status ?? '-' }}</td>
                        </tr>                    </table>
                </div>
            </div>

            <h6 class="mb-3">Item Buku</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle" style="white-space: nowrap;">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kode Buku</th>
                                <th>Harga Buku</th>
                                <th>Cabang</th>
                                <th>Eksemplar</th>
                                <th>Koli</th>
                                <th>Total Eks</th>
                                <th>Volume</th>
                                
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->book_code ?? '-' }}</td>
                                    <td>Rp {{ number_format($item->book_price ?? 0, 0, ',', '.') }}</td>
                                    <td>{{ $item->branch_code ?? '-' }}</td>
                                    <td>{{ $item->exemplar ?? '-' }}</td>
                                    <td>{{ $item->koli ?? '-' }}</td>
                                    <td>{{ $item->total_exemplar ?? '-' }}</td>
                                    <td>{{ $item->volume ?? '-' }}</td>
                                    
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center text-muted">Tidak ada item buku.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts>