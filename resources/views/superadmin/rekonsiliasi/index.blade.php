<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">Laporan Rekonsiliasi NKB & NTB</h5>

            <form action="{{ route('rekonsiliasi.index') }}" method="GET" class="mb-3 d-flex flex-wrap gap-2">
                <div class="input-group input-group-sm w-auto">
                    <input type="text" name="search" class="form-control" placeholder="Cari NKB / Surat Jalan..."
                        value="{{ request('search') }}" style="max-width: 250px;">
                </div>
                <div class="input-group input-group-sm w-auto">
                    <select name="status" class="form-select">
                        <option value="">-- Semua Status --</option>
                        <option value="match" {{ request('status') == 'match' ? 'selected' : '' }}>Match (Sesuai)</option>
                        <option value="selisih" {{ request('status') == 'selisih' ? 'selected' : '' }}>Ada Selisih</option>
                        <option value="belum" {{ request('status') == 'belum' ? 'selected' : '' }}>Belum Diterima</option>
                    </select>
                </div>
                <button class="btn btn-outline-secondary btn-sm" type="submit">
                    <i class="bi bi-filter"></i> Filter
                </button>
                @if (request()->hasAny(['search', 'status']) && (request('search') != '' || request('status') != ''))
                    <a href="{{ route('rekonsiliasi.index') }}" class="btn btn-outline-danger btn-sm" title="Clear Search">
                        <i class="bi bi-x"></i> Reset
                    </a>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light text-center">
                        <tr>
                            <th rowspan="2" class="align-middle">No</th>
                            <th rowspan="2" class="align-middle">No. Surat Jalan (NKB)</th>
                            <th rowspan="2" class="align-middle">Tanggal Kirim</th>
                            <th rowspan="2" class="align-middle">Tanggal Terima</th>
                            <th colspan="3">Total Eksemplar</th>
                            <th rowspan="2" class="align-middle">Status</th>
                            <th rowspan="2" class="align-middle">Aksi</th>
                        </tr>
                        <tr>
                            <th>Dikirim (NKB)</th>
                            <th>Diterima (NTB)</th>
                            <th>Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $i => $item)
                            @php
                                $kirim = (int) $item->total_kirim;
                                $terima = (int) $item->total_terima;
                                $selisih = $terima - $kirim;
                                
                                if ($terima == 0) {
                                    $badgeClass = 'bg-warning text-dark';
                                    $statusText = 'Belum Diterima';
                                } elseif ($selisih == 0) {
                                    $badgeClass = 'bg-success';
                                    $statusText = 'Match';
                                } else {
                                    $badgeClass = 'bg-danger';
                                    $statusText = 'Selisih';
                                }
                            @endphp
                            <tr>
                                <td class="text-center">{{ $items->firstItem() + $i }}</td>
                                <td>{{ $item->nota_kirim_cab }}</td>
                                <td class="text-center">{{ $item->send_date ? $item->send_date->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ optional($item->receiveNote)->retur_date ? $item->receiveNote->retur_date->format('d/m/Y') : '-' }}</td>
                                <td class="text-end">{{ number_format($kirim, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($terima, 0, ',', '.') }}</td>
                                <td class="text-end text-{{ $selisih == 0 ? 'success' : ($terima == 0 ? 'muted' : 'danger fw-bold') }}">
                                    {{ $terima == 0 ? '-' : number_format($selisih, 0, ',', '.') }}
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('rekonsiliasi.show', $item->nota_kirim_cab) }}"
                                        class="btn btn-sm btn-outline-info py-0 px-2" title="Lihat Rekonsiliasi">
                                        <i class="bi bi-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-3 text-muted">Belum ada data NKB / Tidak ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $items->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</x-layouts>
