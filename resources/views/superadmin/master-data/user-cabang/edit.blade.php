<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <strong>Edit User Cabang</strong><br />
                    <small class="text-muted">Edit data user cabang</small>
                </div>
                <a href="{{ route('user-cabang.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>

            <form action="{{ route('user-cabang.update', $user->id) }}" method="POST">
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
                        <label for="authority_id" class="form-label">Authority <span class="text-danger">*</span></label>
                        <select class="form-select @error('authority_id') is-invalid @enderror" id="authority_id"
                            name="authority_id" required>
                            <option value="">Pilih Authority</option>
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

                    <div class="col-md-6">
                        <label for="branch_code" class="form-label">Kode Cabang <span class="text-danger">*</span></label>
                        <select class="form-select select2-ajax @error('branch_code') is-invalid @enderror" id="branch_code"
                            name="branch_code" data-url="{{ route('api.branches') }}" data-placeholder="Pilih Cabang" required>
                            @if($user->branch_code)
                                <option value="{{ $user->branch_code }}" selected>
                                    {{ $user->branch_code }} - {{ \App\Models\Branch::where('branch_code', $user->branch_code)->first()->branch_name ?? $user->branch_code }}
                                </option>
                            @endif
                        </select>
                        @error('branch_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="branch_name" class="form-label">Nama Cabang</label>
                        <input type="text" class="form-control" id="branch_name" name="branch_name" 
                            value="{{ \App\Models\Branch::where('branch_code', $user->branch_code)->first()->branch_name ?? '' }}" readonly>
                        <small class="text-muted">Akan terisi otomatis setelah memilih kode cabang</small>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Simpan Perubahan
                        </button>
                        <a href="{{ route('user-cabang.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts>
