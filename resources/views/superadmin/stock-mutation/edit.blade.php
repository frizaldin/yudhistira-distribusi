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
                    <strong>Edit Mutasi</strong><br />
                    <small class="text-muted">Ubah header & daftar buku (maks 25). Total eksemplar = (koli × isi koli) + eceran per buku.</small>
                </div>
                <a href="{{ route('mutasi.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>

            <form action="{{ route('mutasi.update', $item) }}" method="POST" id="formMutasi">
                @csrf
                @method('PUT')

                {{-- ====== HEADER MUTASI ====== --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="nama_pt_produksi" class="form-label">Nama PT produksi</label>
                        <input type="text" name="nama_pt_produksi" id="nama_pt_produksi"
                            class="form-control @error('nama_pt_produksi') is-invalid @enderror"
                            value="{{ old('nama_pt_produksi', $item->nama_pt_produksi) }}" />
                        @error('nama_pt_produksi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="tanggal_penerimaan" class="form-label">Tanggal penerimaan</label>
                        <input type="date" name="tanggal_penerimaan" id="tanggal_penerimaan"
                            class="form-control @error('tanggal_penerimaan') is-invalid @enderror"
                            value="{{ old('tanggal_penerimaan', $item->tanggal_penerimaan ? $item->tanggal_penerimaan->format('Y-m-d') : '') }}" />
                        @error('tanggal_penerimaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="nama_penerima" class="form-label">Nama penerima</label>
                        <input type="text" name="nama_penerima" id="nama_penerima"
                            class="form-control @error('nama_penerima') is-invalid @enderror"
                            value="{{ old('nama_penerima', $item->nama_penerima) }}" />
                        @error('nama_penerima')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="nomor_surat_jalan" class="form-label">Nomor surat jalan</label>
                        <input type="text" name="nomor_surat_jalan" id="nomor_surat_jalan"
                            class="form-control @error('nomor_surat_jalan') is-invalid @enderror"
                            value="{{ old('nomor_surat_jalan', $item->nomor_surat_jalan) }}" />
                        @error('nomor_surat_jalan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="nomor_jo" class="form-label">Nomor JO (job order)</label>
                        <input type="text" name="nomor_jo" id="nomor_jo"
                            class="form-control @error('nomor_jo') is-invalid @enderror"
                            value="{{ old('nomor_jo', $item->nomor_jo) }}" />
                        @error('nomor_jo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <input type="text" name="keterangan" id="keterangan"
                            class="form-control @error('keterangan') is-invalid @enderror"
                            value="{{ old('keterangan', $item->keterangan) }}" />
                        @error('keterangan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- ====== TABEL BUKU ====== --}}
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Detail Buku <span class="text-muted fw-normal fs-6">(Hanya bisa menghapus baris)</span></strong>
                </div>

                @error('items')
                    <div class="alert alert-danger py-2">{{ $message }}</div>
                @enderror

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0" id="tabelBuku">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th style="min-width:280px">Kode / Judul Buku <span class="text-danger">*</span></th>
                                <th style="width:100px" class="text-end">Koli <span class="text-danger">*</span></th>
                                <th style="width:110px" class="text-end">Isi Koli <span class="text-danger">*</span></th>
                                <th style="width:100px" class="text-end">Eceran <span class="text-danger">*</span></th>
                                <th style="width:130px" class="text-end">Total Eks.</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="bodyBuku">
                            @php
                                $existingItems = old('items')
                                    ? collect(old('items'))->map(fn($r) => (object)[
                                        'book_code'   => $r['book_code'] ?? '',
                                        'koli'        => $r['koli'] ?? 0,
                                        'isi_koli'    => $r['isi_koli'] ?? 0,
                                        'eceran'      => $r['eceran'] ?? 0,
                                        'total_eksemplar' => 0,
                                        'product'     => null,
                                      ])
                                    : $item->items;
                            @endphp
                            @foreach ($existingItems as $idx => $baris)
                            <tr class="baris-buku">
                                <td class="text-center text-muted nomor-baris">{{ $idx + 1 }}</td>
                                <td>
                                    <input type="hidden" name="items[{{ $idx }}][book_code]" value="{{ $baris->book_code }}" />
                                    <div class="px-2">{{ $baris->book_code }}{{ ($baris->product->book_title ?? '') ? ' — ' . $baris->product->book_title : '' }}</div>
                                </td>
                                <td>
                                    <input type="hidden" name="items[{{ $idx }}][koli]" class="calc-koli" value="{{ $baris->koli }}" />
                                    <div class="px-2 text-end">{{ number_format($baris->koli, 0, ',', '.') }}</div>
                                </td>
                                <td>
                                    <input type="hidden" name="items[{{ $idx }}][isi_koli]" class="calc-isi" value="{{ $baris->isi_koli }}" />
                                    <div class="px-2 text-end">{{ number_format($baris->isi_koli, 0, ',', '.') }}</div>
                                </td>
                                <td>
                                    <input type="hidden" name="items[{{ $idx }}][eceran]" class="calc-eceran" value="{{ $baris->eceran }}" />
                                    <div class="px-2 text-end">{{ number_format($baris->eceran, 0, ',', '.') }}</div>
                                </td>
                                <td class="text-end fw-semibold total-baris text-primary">
                                    {{ number_format($baris->total_eksemplar) }}
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-baris"
                                        title="Hapus baris ini">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="5" class="text-end fw-semibold">Total Keseluruhan</td>
                                <td class="text-end fw-bold text-success" id="totalKeseluruhan">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Simpan perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('js')
    <script>
    $(document).ready(function () {
        const MAX_BARIS = 25;

        function hitungBaris(row) {
            const k = parseInt(row.querySelector('.calc-koli').value, 10) || 0;
            const i = parseInt(row.querySelector('.calc-isi').value, 10) || 0;
            const e = parseInt(row.querySelector('.calc-eceran').value, 10) || 0;
            const t = k * i + e;
            row.querySelector('.total-baris').textContent = t.toLocaleString('id-ID');
            return t;
        }

        function hitungSemua() {
            let grand = 0;
            document.querySelectorAll('.baris-buku').forEach(function (row) {
                grand += hitungBaris(row);
            });
            document.getElementById('totalKeseluruhan').textContent = grand.toLocaleString('id-ID');
        }

        function renomor() {
            document.querySelectorAll('.baris-buku').forEach(function (row, idx) {
                row.querySelector('.nomor-baris').textContent = idx + 1;
                row.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
                });
            });
        }

        function bindBaris(row) {
            row.querySelector('.btn-hapus-baris').addEventListener('click', function () {
                if (document.querySelectorAll('.baris-buku').length <= 1) {
                    alert('Minimal harus ada 1 baris buku.');
                    return;
                }
                row.remove();
                renomor();
                hitungSemua();
            });
        }

        document.querySelectorAll('.baris-buku').forEach(function (row) {
            bindBaris(row);
        });
        hitungSemua();
    });
    </script>
    @endpush
</x-layouts>
