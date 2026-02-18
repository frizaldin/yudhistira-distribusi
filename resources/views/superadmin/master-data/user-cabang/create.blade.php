<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <strong>Tambah User Cabang</strong><br />
                    <small class="text-muted">Tambah user baru untuk cabang</small>
                </div>
                <a href="{{ route('user-cabang.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>

            <form action="{{ route('user-cabang.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                            name="name" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                            name="email" value="{{ old('email') }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                            name="password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Minimal 8 karakter</small>
                    </div>

                    <div class="col-md-6">
                        <label for="authority_id" class="form-label">Authority <span class="text-danger">*</span></label>
                        <select class="form-select @error('authority_id') is-invalid @enderror" id="authority_id"
                            name="authority_id" required>
                            <option value="">Pilih Authority</option>
                            @foreach ($authorities ?? [] as $authority)
                                <option value="{{ $authority->id }}" {{ old('authority_id') == $authority->id ? 'selected' : '' }}>
                                    {{ $authority->name ?? $authority->id }}
                                </option>
                            @endforeach
                        </select>
                        @error('authority_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="branch_code" class="form-label">Kode Cabang <span class="text-danger">*</span></label>
                        <select class="form-select select2-ajax @error('branch_code') is-invalid @enderror" id="branch_code"
                            name="branch_code" data-url="{{ route('api.branches') }}" data-placeholder="Pilih Cabang" required>
                        </select>
                        @error('branch_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="branch_name" class="form-label">Nama Cabang</label>
                        <input type="text" class="form-control" id="branch_name" name="branch_name" readonly>
                        <small class="text-muted">Akan terisi otomatis setelah memilih kode cabang</small>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Simpan
                        </button>
                        <a href="{{ route('user-cabang.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts>
