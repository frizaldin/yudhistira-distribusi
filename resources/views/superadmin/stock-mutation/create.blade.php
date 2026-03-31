<x-layouts>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Tambah Mutasi</strong><br />
                    <small class="text-muted">Total eksemplar = (koli × isi koli) + eceran, masuk ke stock pusat</small>
                </div>
                <a href="{{ route('mutasi.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>

            <form action="{{ route('mutasi.store') }}" method="POST" id="formMutasi">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="book_code" class="form-label">Kode buku <span class="text-danger">*</span></label>
                        <select name="book_code" id="book_code"
                            class="form-select select2-ajax @error('book_code') is-invalid @enderror"
                            data-url="{{ route('api.products') }}" data-placeholder="Pilih buku" required>
                            @if (old('book_code'))
                                <option value="{{ old('book_code') }}" selected>{{ old('book_code') }}</option>
                            @endif
                        </select>
                        @error('book_code')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="koli" class="form-label">Koli <span class="text-danger">*</span></label>
                        <input type="number" name="koli" id="koli" min="0" step="1"
                            class="form-control mutasi-calc @error('koli') is-invalid @enderror"
                            value="{{ old('koli', 0) }}" required />
                        @error('koli')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <label for="isi_koli" class="form-label">Isi koli <span class="text-danger">*</span></label>
                        <input type="number" name="isi_koli" id="isi_koli" min="0" step="1"
                            class="form-control mutasi-calc @error('isi_koli') is-invalid @enderror"
                            value="{{ old('isi_koli', 0) }}" required />
                        @error('isi_koli')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <label for="eceran" class="form-label">Eceran <span class="text-danger">*</span></label>
                        <input type="number" name="eceran" id="eceran" min="0" step="1"
                            class="form-control mutasi-calc @error('eceran') is-invalid @enderror"
                            value="{{ old('eceran', 0) }}" required />
                        @error('eceran')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Total eksemplar (otomatis)</label>
                        <input type="text" class="form-control" id="total_eksemplar_display" readonly value="0" />
                    </div>

                    <div class="col-md-4">
                        <label for="nama_pt_produksi" class="form-label">Nama PT produksi</label>
                        <input type="text" name="nama_pt_produksi" id="nama_pt_produksi"
                            class="form-control @error('nama_pt_produksi') is-invalid @enderror"
                            value="{{ old('nama_pt_produksi') }}" />
                        @error('nama_pt_produksi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="tanggal_penerimaan" class="form-label">Tanggal penerimaan</label>
                        <input type="date" name="tanggal_penerimaan" id="tanggal_penerimaan"
                            class="form-control @error('tanggal_penerimaan') is-invalid @enderror"
                            value="{{ old('tanggal_penerimaan') }}" />
                        @error('tanggal_penerimaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="nama_penerima" class="form-label">Nama penerima</label>
                        <input type="text" name="nama_penerima" id="nama_penerima"
                            class="form-control @error('nama_penerima') is-invalid @enderror"
                            value="{{ old('nama_penerima') }}" />
                        @error('nama_penerima')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="nomor_surat_jalan" class="form-label">Nomor surat jalan</label>
                        <input type="text" name="nomor_surat_jalan" id="nomor_surat_jalan"
                            class="form-control @error('nomor_surat_jalan') is-invalid @enderror"
                            value="{{ old('nomor_surat_jalan') }}" />
                        @error('nomor_surat_jalan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="nomor_jo" class="form-label">Nomor JO (job order)</label>
                        <input type="text" name="nomor_jo" id="nomor_jo"
                            class="form-control @error('nomor_jo') is-invalid @enderror"
                            value="{{ old('nomor_jo') }}" />
                        @error('nomor_jo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <textarea name="keterangan" id="keterangan" rows="3"
                            class="form-control @error('keterangan') is-invalid @enderror">{{ old('keterangan') }}</textarea>
                        @error('keterangan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            function recalc() {
                var k = parseInt(document.getElementById('koli').value, 10) || 0;
                var i = parseInt(document.getElementById('isi_koli').value, 10) || 0;
                var e = parseInt(document.getElementById('eceran').value, 10) || 0;
                var t = k * i + e;
                document.getElementById('total_eksemplar_display').value = t.toLocaleString('id-ID');
            }
            document.querySelectorAll('.mutasi-calc').forEach(function(el) {
                el.addEventListener('input', recalc);
                el.addEventListener('change', recalc);
            });
            recalc();
        })();
    </script>
</x-layouts>
