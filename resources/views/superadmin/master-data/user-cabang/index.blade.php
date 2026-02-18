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
                    <strong>User Cabang</strong><br />
                    <small class="text-muted">Manajemen user untuk cabang</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('user-cabang.create') }}" class="btn btn-primary btn-sm rounded-pill">
                        <i class="bi bi-plus-circle me-1"></i>Tambah User
                    </a>
                </div>
            </div>

            <form class="row g-2 mb-3" method="GET" action="{{ route('user-cabang.index') }}">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Cari nama, email, atau kode cabang"
                        value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <select name="branch_code" id="filter_branch_code" class="form-select select2-ajax" data-url="{{ route('api.branches') }}" data-placeholder="Semua Cabang">
                        @if(request('branch_code'))
                            <option value="{{ request('branch_code') }}" selected>{{ request('branch_code') }}</option>
                        @endif
                    </select>
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
                            <th>Kode Cabang</th>
                            <th>Nama Cabang</th>
                            <th>Authority</th>
                            <th>Status</th>
                            <th style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $index => $user)
                            @php
                                $branch = \App\Models\Branch::where('branch_code', $user->branch_code)->first();
                            @endphp
                            <tr>
                                <td>{{ $users->firstItem() + $index }}</td>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td>{{ $user->email }}</td>
                                <td><code>{{ $user->branch_code ?? '-' }}</code></td>
                                <td>{{ $branch->branch_name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary-emphasis">
                                        {{ $user->authority->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success-emphasis">Aktif</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('user-cabang.edit', $user->id) }}"
                                            class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="if(confirm('Yakin ingin menghapus user ini?')) { document.getElementById('delete-form-{{ $user->id }}').submit(); }">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <form id="delete-form-{{ $user->id }}"
                                            action="{{ route('user-cabang.destroy', $user->id) }}" method="POST"
                                            style="display: none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Belum ada data user cabang. <a href="{{ route('user-cabang.create') }}">Tambah
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
