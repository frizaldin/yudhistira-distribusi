<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('nkb.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar NKB
                    </a>
                    <strong>Tambah NKB — Pilih NPPB</strong>
                </div>
            </div>
            <p class="text-muted small mb-3">Pilih NPPB yang belum memiliki NKB. Setelah itu Anda akan memilih item mana saja yang akan dimasukkan ke NKB.</p>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. NPPB</th>
                            <th>Pengirim</th>
                            <th>Tujuan</th>
                            <th>Tanggal</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents ?? [] as $doc)
                            @php
                                $stack = $stackByDocId[$doc->id] ?? null;
                            @endphp
                            <tr>
                                <td><code>{{ $doc->number }}</code></td>
                                <td>{{ $doc->senderBranch->branch_name ?? $doc->sender_code }}</td>
                                <td>{{ $doc->recipientBranch->branch_name ?? $doc->recipient_code }}</td>
                                <td>{{ $doc->send_date ? $doc->send_date->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">
                                    @if ($stack)
                                        <a href="{{ route('preparation_notes.preview_nkb_page', ['stack' => $stack, 'from' => 'nkb']) }}"
                                            class="btn btn-sm btn-primary">
                                            <i class="bi bi-file-earmark-plus me-1"></i>Buat NKB
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Tidak ada NPPB yang belum memiliki NKB. Semua NPPB yang disetujui sudah dibuat NKB-nya.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts>
