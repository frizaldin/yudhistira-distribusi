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
                    <strong>NKB (Nota Kirim Barang)</strong><br />
                    <small class="text-muted">Daftar NKB</small>
                </div>
                <a href="{{ route('nkb.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Buat NKB Baru
                </a>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('nkb.index') }}">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="search"
                        placeholder="Cari nomor NKB, NPPB, pengirim, tujuan..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <a href="{{ route('nkb.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. NKB</th>
                            <th>NPPB</th>
                            <th>Pengirim</th>
                            <th>Tujuan</th>
                            <th>Tanggal</th>
                            <th class="text-end">Jenis / Ex</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items ?? [] as $item)
                            <tr>
                                <td><code>{{ $item->number }}</code></td>
                                <td><code>{{ $item->nppb_code ?? '-' }}</code></td>
                                <td>{{ $item->senderBranch->branch_name ?? $item->sender_code }}</td>
                                <td>{{ $item->recipientBranch->branch_name ?? $item->recipient_code }}</td>
                                <td>{{ $item->send_date ? $item->send_date->format('d/m/Y') : '-' }}</td>
                                <td class="text-end">{{ $item->total_type_books ?? 0 }} /
                                    {{ number_format($item->total_exemplar ?? 0) }}</td>
                                <td class="text-center">
                                    <a href="{{ route('nkb.show', ['number' => $item->number]) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>Detail
                                    </a>
                                    <a href="{{ route('nkb.print', ['number' => $item->number]) }}" target="_blank"
                                        class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-printer me-1"></i>Print
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Belum ada data NKB.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($items) && $items->hasPages())
                <nav class="mt-3" aria-label="Paginasi">
                    {{ $items->links() }}
                </nav>
            @endif
        </div>
    </div>
</x-layouts>
