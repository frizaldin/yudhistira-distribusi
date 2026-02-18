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
                    <strong>User ADP</strong><br />
                    <small class="text-muted">Manajemen user ADP (authority_id = 3)</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('user-adp.create') }}" class="btn btn-primary btn-sm rounded-pill">
                        <i class="bi bi-plus-circle me-1"></i>Tambah User
                    </a>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('user-adp.index') }}">
                <div class="col-md-10">
                    <input type="text" class="form-control" name="search" placeholder="Cari nama atau email"
                        value="{{ request('search') }}" />
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-secondary w-100" style="height: 38px;">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">NO</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Authority</th>
                            <th>Otoritas Cabang</th>
                            <th>Status</th>
                            <th style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $index => $user)
                            <tr>
                                <td>{{ $users->firstItem() + $index }}</td>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary-emphasis">
                                        {{ $user->authority->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $branchCodes = $user->branch ?? [];
                                    @endphp
                                    @if (count($branchCodes) > 0)
                                        @foreach ($branchCodes as $code)
                                            @php $br = isset($branchesMap) ? $branchesMap->get($code) : null; @endphp
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis me-1">
                                                {{ $br ? $br->branch_code . ' - ' . $br->branch_name : $code }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success-emphasis">Aktif</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('user-adp.edit', $user->id) }}"
                                            class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="if(confirm('Yakin ingin menghapus user ini?')) { document.getElementById('delete-form-{{ $user->id }}').submit(); }">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <form id="delete-form-{{ $user->id }}"
                                            action="{{ route('user-adp.destroy', $user->id) }}" method="POST"
                                            style="display: none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Belum ada data user ADP. <a href="{{ route('user-adp.create') }}">Tambah
                                            user baru</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="mt-3">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts>
