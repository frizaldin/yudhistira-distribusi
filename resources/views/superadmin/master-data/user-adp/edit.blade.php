<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <strong>Edit User ADP</strong><br />
                    <small class="text-muted">Edit data user ADP</small>
                </div>
                <a href="{{ route('user-adp.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>

            <form action="{{ route('user-adp.update', $user->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                            name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                            name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                            name="password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                    </div>

                    <div class="col-md-6">
                        <label for="authority_id" class="form-label">Authority</label>
                        <select class="form-select @error('authority_id') is-invalid @enderror" id="authority_id"
                            name="authority_id" required>
                            @foreach ($authorities ?? [] as $authority)
                                <option value="{{ $authority->id }}" {{ old('authority_id', $user->authority_id) == $authority->id ? 'selected' : '' }}>
                                    {{ $authority->name ?? $authority->id }}
                                </option>
                            @endforeach
                        </select>
                        @error('authority_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="branch" class="form-label">Otoritas Cabang</label>
                        <select class="form-select select2-multiple-branch @error('branch') is-invalid @enderror" id="branch"
                            name="branch[]" multiple data-placeholder="Pilih cabang...">
                            @php $userBranch = old('branch', $user->branch ?? []); @endphp
                            @foreach ($branches ?? [] as $b)
                                <option value="{{ $b->branch_code }}" {{ in_array($b->branch_code, $userBranch) ? 'selected' : '' }}>
                                    {{ $b->branch_code }} — {{ $b->branch_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Pilih cabang yang menjadi wewenang user ADP</small>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Simpan Perubahan
                        </button>
                        <a href="{{ route('user-adp.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('#branch').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Pilih cabang...',
                allowClear: true
            });
        });
    </script>
</x-layouts>
