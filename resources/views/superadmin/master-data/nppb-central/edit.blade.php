<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <div>
                    <strong>Edit Rencana Kirim</strong><br />
                    <small class="text-muted">Edit data rencana pengiriman dari pusat ke cabang</small>
                </div>
                <div>
                    <a href="{{ route('nppb-central.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </a>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('nppb-central.update', $nppbCentral->id) }}" method="POST" id="nppbForm">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="branch_code" class="form-label">Kode Cabang <span class="text-danger">*</span></label>
                        <select name="branch_code" id="branch_code" class="form-select select2-ajax @error('branch_code') is-invalid @enderror" data-url="{{ route('api.branches') }}" data-placeholder="Pilih Cabang" required>
                            @if(old('branch_code', $nppbCentral->branch_code))
                                <option value="{{ old('branch_code', $nppbCentral->branch_code) }}" selected>
                                    {{ old('branch_code', $nppbCentral->branch_code) }} - {{ old('branch_name', $nppbCentral->branch_name) }}
                                </option>
                            @endif
                        </select>
                        @error('branch_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="branch_name" class="form-label">Nama Cabang</label>
                        <input type="text" class="form-control @error('branch_name') is-invalid @enderror"
                            id="branch_name" name="branch_name" value="{{ old('branch_name', $nppbCentral->branch_name) }}"
                            placeholder="Otomatis terisi dari kode cabang">
                        @error('branch_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">Kosongkan untuk mengisi otomatis dari kode cabang</small>
                    </div>

                    <div class="col-md-6">
                        <label for="book_code" class="form-label">Kode Buku <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('book_code') is-invalid @enderror"
                            id="book_code" name="book_code" value="{{ old('book_code', $nppbCentral->book_code) }}" required>
                        @error('book_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="book_name" class="form-label">Nama Buku</label>
                        <input type="text" class="form-control @error('book_name') is-invalid @enderror"
                            id="book_name" name="book_name" value="{{ old('book_name', $nppbCentral->book_name) }}">
                        @error('book_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="koli" class="form-label">KOLI</label>
                        <input type="number" class="form-control @error('koli') is-invalid @enderror"
                            id="koli" name="koli" value="{{ old('koli', $nppbCentral->koli ?? 0) }}" min="0" step="1">
                        @error('koli')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="pls" class="form-label">PLS</label>
                        <input type="number" class="form-control @error('pls') is-invalid @enderror"
                            id="pls" name="pls" value="{{ old('pls', $nppbCentral->pls ?? 0) }}" min="0" step="1">
                        @error('pls')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="exp" class="form-label">EXP</label>
                        <input type="number" class="form-control @error('exp') is-invalid @enderror"
                            id="exp" name="exp" value="{{ old('exp', $nppbCentral->exp ?? 0) }}" min="0" step="1">
                        @error('exp')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="date" class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('date') is-invalid @enderror"
                            id="date" name="date" value="{{ old('date', $nppbCentral->date ? \Carbon\Carbon::parse($nppbCentral->date)->format('Y-m-d') : date('Y-m-d')) }}" required>
                        @error('date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" id="submitBtn" onclick="return handleSubmit(event);">
                        <i class="bi bi-save me-1"></i>Update
                    </button>
                    <a href="{{ route('nppb-central.index') }}" class="btn btn-outline-secondary" id="cancelBtn">
                        <i class="bi bi-x me-1"></i>Batal
                    </a>
                </div>
                
                <!-- Loading Overlay -->
                <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="background: rgba(0,0,0,0.5); z-index: 9999;">
                    <div class="d-flex justify-content-center align-items-center h-100">
                        <div class="text-center text-white">
                            <div class="spinner-border mb-3" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mb-0">Mohon Tunggu Hingga Proses Selesai</p>
                            <p class="mb-0">Mohon Jangan Tinggalkan Halaman Ini Ketika Proses Sedang Berlangsung</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('js')
    <script>
        window.isSubmitting = false;
        
        window.handleSubmit = function(e) {
            e.preventDefault();
            
            if (window.isSubmitting) {
                return false;
            }
            
            // Show SweetAlert confirmation
            Swal.fire({
                title: 'Konfirmasi',
                html: 'Mohon Tunggu Hingga Proses Selesai, dan Mohon Jangan Tinggalkan Halaman Ini Ketika Proses Sedang Berlangsung',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.isSubmitting = true;
                    
                    // Show loading overlay
                    var overlay = document.getElementById('loadingOverlay');
                    if (overlay) {
                        overlay.classList.remove('d-none');
                    }
                    
                    // Disable submit button
                    var submitBtn = document.getElementById('submitBtn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
                    }
                    
                    var cancelBtn = document.getElementById('cancelBtn');
                    if (cancelBtn) {
                        cancelBtn.classList.add('disabled');
                        cancelBtn.style.pointerEvents = 'none';
                    }
                    
                    // Submit form
                    document.getElementById('nppbForm').submit();
                }
            });
            
            return false;
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            // Additional event handler to ensure auto-fill works
            if (typeof jQuery !== 'undefined') {
                setTimeout(function() {
                    // Auto-fill book_name when book_code is selected
                    $('#book_code').on('select2:select', function (e) {
                        var data = e.params.data;
                        if (data && data.book_name) {
                            $('#book_name').val(data.book_name);
                        }
                    });

                    // Auto-fill branch_name when branch_code is selected
                    $('#branch_code').on('select2:select', function (e) {
                        var data = e.params.data;
                        if (data && data.branch_name) {
                            $('#branch_name').val(data.branch_name);
                        }
                    });
                }, 1000);
            }
            
            // Also handle form submit event as backup
            var form = document.getElementById('nppbForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!window.isSubmitting) {
                        e.preventDefault();
                        
                        Swal.fire({
                            title: 'Konfirmasi',
                            html: 'Mohon Tunggu Hingga Proses Selesai, dan Mohon Jangan Tinggalkan Halaman Ini Ketika Proses Sedang Berlangsung',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Ya, Lanjutkan',
                            cancelButtonText: 'Batal',
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.isSubmitting = true;
                                
                                var overlay = document.getElementById('loadingOverlay');
                                if (overlay) {
                                    overlay.classList.remove('d-none');
                                }
                                
                                var submitBtn = document.getElementById('submitBtn');
                                if (submitBtn) {
                                    submitBtn.disabled = true;
                                }
                                
                                var cancelBtn = document.getElementById('cancelBtn');
                                if (cancelBtn) {
                                    cancelBtn.classList.add('disabled');
                                    cancelBtn.style.pointerEvents = 'none';
                                }
                                
                                // Submit form
                                form.submit();
                            }
                        });
                        
                        return false;
                    }
                    return true;
                });
            }
            
            // Prevent leaving page during submission
            window.addEventListener('beforeunload', function(e) {
                if (window.isSubmitting) {
                    e.preventDefault();
                    e.returnValue = 'Mohon Tunggu Hingga Proses Selesai, dan Mohon Jangan Tinggalkan Halaman Ini Ketika Proses Sedang Berlangsung';
                    return e.returnValue;
                }
            });
            
            // Handle navigation clicks
            var cancelBtn = document.getElementById('cancelBtn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    if (window.isSubmitting) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Peringatan',
                            text: 'Mohon Tunggu Hingga Proses Selesai, dan Mohon Jangan Tinggalkan Halaman Ini Ketika Proses Sedang Berlangsung',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                });
            }
        });
    </script>
    @endpush
</x-layouts>
