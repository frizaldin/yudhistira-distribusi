@php
    $user = auth()->user();
    $managedBranches = collect();
    if ($user) {
        $role = $user->authority_id ?? 1;
        if ($role === 2 && !empty($user->branch_code)) {
            $managedBranches = \App\Models\Branch::where('branch_code', $user->branch_code)->get();
        } elseif ($role === 3 && is_array($user->branch) && count($user->branch) > 0) {
            $managedBranches = \App\Models\Branch::whereIn('branch_code', $user->branch)->orderBy('branch_name')->get();
        }
    }
@endphp
<div class="modal fade" id="modalCabangDikelola" tabindex="-1" aria-labelledby="modalCabangDikelolaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCabangDikelolaLabel">
                    <i class="bi bi-building me-2"></i>Cabang yang Dikelola
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                @if ($user && ($user->authority_id ?? 1) == 1)
                    <p class="text-muted mb-0">Anda mengelola <strong>semua cabang</strong> (Superadmin).</p>
                @elseif ($managedBranches->isEmpty())
                    <p class="text-muted mb-0">Tidak ada cabang yang ditetapkan untuk akun Anda.</p>
                @else
                    <p class="small text-muted mb-2">Data yang tampil di seluruh menu hanya untuk cabang berikut:</p>
                    <ul class="list-group list-group-flush">
                        @foreach ($managedBranches as $b)
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span><strong>{{ $b->branch_code }}</strong> — {{ $b->branch_name ?? '-' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
