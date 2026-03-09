<x-layouts>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h4 class="mb-0"><strong>{{ $title }}</strong></h4>
                    <small>Ini Adalah Data Terbaru Bersumber Dari Staging YGI</small>
                </div>
                <button type="button" class="btn btn-primary btn-sync-all" id="btnSyncAll">
                    <i class="bi bi-arrow-repeat me-1"></i> Sinkron Semua
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach ($stagingData ?? [] as $item)
            <div class="col-md-6 col-lg-4 mb-4" data-staging-key="{{ $item['key'] }}">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi {{ $item['icon'] ?? 'bi-journal' }}"
                                        style="font-size: 2rem; color: var(--bs-{{ $item['color'] ?? 'primary' }});"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">{{ $item['name'] }}</h5>
                                    <small class="text-muted">Table: {{ $item['table'] }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Total Data di Staging:</span>
                                <strong class="text-{{ $item['color'] ?? 'primary' }} staging-count"
                                    data-key="{{ $item['key'] }}">
                                    <span class="count-placeholder text-muted small">...</span>
                                </strong>
                            </div>

                            @if (isset($item['last_sync']) && $item['last_sync'])
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Terakhir Sinkron:</span>
                                    <small class="text-muted">
                                        {{ \Carbon\Carbon::parse($item['last_sync'])->format('d/m/Y H:i:s') }}
                                    </small>
                                </div>
                            @else
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Terakhir Sinkron:</span>
                                    <small class="text-muted">Belum pernah</small>
                                </div>
                            @endif

                            <!-- Progress Section (always present, hidden when not running) -->
                            <div class="mb-2 progress-section" data-type="{{ $item['key'] }}"
                                style="display: {{ isset($item['progress']) && $item['is_running'] ? 'block' : 'none' }};">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted">Progress:</small>
                                    <small class="text-muted progress-text">
                                        {{ $item['progress']['processed'] ?? 0 }} /
                                        {{ $item['progress']['total'] ?? 0 }}
                                        ({{ number_format($item['progress']['percentage'] ?? 0, 1) }}%)
                                    </small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-{{ $item['color'] ?? 'primary' }}"
                                        role="progressbar" style="width: {{ $item['progress']['percentage'] ?? 0 }}%">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-1 progress-stats">
                                    <small class="text-success progress-created">Created: {{ $item['progress']['created'] ?? 0 }}</small>
                                    <small class="text-info progress-updated">Updated: {{ $item['progress']['updated'] ?? 0 }}</small>
                                    <small class="text-danger progress-errors" style="display: {{ ($item['progress']['errors'] ?? 0) > 0 ? 'inline' : 'none' }};">Errors: {{ $item['progress']['errors'] ?? 0 }}</small>
                                </div>
                            </div>
                            <div class="alert-completed-placeholder mb-2"></div>

                            @if (isset($item['progress']) && ($item['progress']['status'] ?? '') === 'completed')
                                <div class="alert alert-success alert-sm py-2 mb-2">
                                    <small>
                                        <i class="bi bi-check-circle"></i> Sinkronisasi selesai
                                    </small>
                                </div>
                            @elseif (isset($item['progress']) && ($item['progress']['status'] ?? '') === 'error')
                                <div class="alert alert-danger alert-sm py-2 mb-2">
                                    <small>
                                        <i class="bi bi-exclamation-circle"></i> Terjadi error
                                    </small>
                                </div>
                            @endif
                        </div>

                        <div class="d-grid">
                            <button type="button"
                                class="btn btn-{{ $item['color'] ?? 'primary' }} btn-sm synchronize-btn"
                                data-type="{{ $item['key'] }}" data-name="{{ $item['name'] }}">
                                <i class="bi bi-arrow-repeat"></i> Synchronize
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Cutoff Data Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><strong>Cutoff Data</strong></h5>
                        <span class="text-muted small">
                            Cutoff data digunakan untuk membatasi data yang akan ditampilkan dan di kelola.</span>
                        <button type="button" class="btn btn-primary btn-sm" id="btnAddCutoffData">
                            <i class="bi bi-plus-circle"></i> Cutoff Data
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Form Cutoff Data (Hidden by default) -->
                    <div id="cutoffDataForm" style="display: none;" class="mb-4">
                        <form id="formCutoffData">
                            <div class="row">
                                <div class="col-md-5">
                                    <label for="start_date" class="form-label">Tanggal Awal (opsional)</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date">
                                    <small class="text-muted">Kosongkan = data s.d. Tanggal Akhir</small>
                                </div>
                                <div class="col-md-5">
                                    <label for="end_date" class="form-label">Tanggal Akhir <span
                                            class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-success mb-1 w-100">
                                        <i class="bi bi-check-circle"></i> Cutoff Data
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <button type="button" class="btn btn-secondary btn-sm" id="btnCancelCutoffData">
                                        <i class="bi bi-x-circle"></i> Batal
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Table Cutoff Data -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cutoffDatas ?? [] as $index => $cutoffData)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $cutoffData->start_date ? \Carbon\Carbon::parse($cutoffData->start_date)->format('d/m/Y') : '—' }}
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($cutoffData->end_date)->format('d/m/Y') }}</td>
                                        <td>
                                            <span
                                                class="badge bg-{{ $cutoffData->status === 'active' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($cutoffData->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div
                                                class="d-flex flex-wrap align-items-center justify-content-evenly gap-2">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input toggle-cutoff" type="checkbox"
                                                        data-id="{{ $cutoffData->id }}"
                                                        {{ $cutoffData->status === 'active' ? 'checked' : '' }}>
                                                    <label class="form-check-label small">
                                                        {{ $cutoffData->status === 'active' ? 'Active' : 'Inactive' }}
                                                    </label>
                                                </div>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary btn-edit-cutoff"
                                                    data-id="{{ $cutoffData->id }}"
                                                    data-start="{{ $cutoffData->start_date ? $cutoffData->start_date->format('Y-m-d') : '' }}"
                                                    data-end="{{ $cutoffData->end_date->format('Y-m-d') }}"
                                                    title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-danger btn-delete-cutoff"
                                                    data-id="{{ $cutoffData->id }}" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Tidak ada data cutoff</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal Edit Cutoff -->
                    <div class="modal fade" id="modalEditCutoff" tabindex="-1"
                        aria-labelledby="modalEditCutoffLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalEditCutoffLabel"><i
                                            class="bi bi-pencil me-2"></i>Edit Cutoff</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <form id="formEditCutoff">
                                    <div class="modal-body">
                                        <input type="hidden" id="edit_cutoff_id" name="id">
                                        <div class="mb-3">
                                            <label for="edit_start_date" class="form-label">Tanggal Awal
                                                (opsional)</label>
                                            <input type="date" class="form-control" id="edit_start_date"
                                                name="start_date">
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_end_date" class="form-label">Tanggal Akhir <span
                                                    class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="edit_end_date"
                                                name="end_date" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="js">
        <script>
            $(document).ready(function() {
                // Lazy-load staging counts (agar halaman langsung tampil, count di-fetch via AJAX)
                $.get('{{ route('staging.counts') }}')
                    .done(function(counts) {
                        $('.staging-count').each(function() {
                            const key = $(this).data('key');
                            const n = counts[key] != null ? counts[key] : 0;
                            $(this).find('.count-placeholder').replaceWith(
                                Number(n).toLocaleString('id-ID')
                            );
                        });
                    })
                    .fail(function() {
                        $('.count-placeholder').text('?');
                    });

                // Poll progress setiap 1 detik dan update DOM (progress bar bergerak tanpa reload)
                let progressPollInterval = null;
                const PROGRESS_POLL_MS = 1000;

                function updateCardProgress(card, progress) {
                    if (!progress) return;
                    const pct = (progress.percentage != null) ? progress.percentage : 0;
                    const processed = progress.processed != null ? progress.processed : 0;
                    const total = progress.total != null ? progress.total : 0;
                    const created = progress.created != null ? progress.created : 0;
                    const updated = progress.updated != null ? progress.updated : 0;
                    const errors = progress.errors != null ? progress.errors : 0;

                    card.find('.progress-bar').css('width', pct + '%');
                    card.find('.progress-text').text(processed + ' / ' + total + ' (' + Number(pct).toFixed(1) + '%)');
                    card.find('.progress-created').text('Created: ' + created);
                    card.find('.progress-updated').text('Updated: ' + updated);
                    const errEl = card.find('.progress-errors');
                    if (errors > 0) {
                        errEl.text('Errors: ' + errors).show();
                    } else {
                        errEl.hide();
                    }

                    const status = (progress.status || '').toLowerCase();
                    if (status === 'completed' || status === 'failed' || status === 'error') {
                        card.find('.progress-section').attr('data-polling-done', '1');
                        card.find('.progress-bar').css('width', '100%').removeClass('progress-bar-animated');
                        card.find('.progress-text').text(total + ' / ' + total + ' (100.0%)');
                        const placeholder = card.find('.alert-completed-placeholder');
                        if (placeholder.length && !placeholder.children().length) {
                            const isError = (status === 'failed' || status === 'error');
                            placeholder.html(
                                '<div class="alert alert-' + (isError ? 'danger' : 'success') + ' alert-sm py-2 mb-2">' +
                                '<small><i class="bi bi-' + (isError ? 'exclamation-circle' : 'check-circle') + '"></i> ' +
                                (isError ? 'Terjadi error' : 'Sinkronisasi selesai') + '</small></div>'
                            );
                        }
                        card.find('.synchronize-btn').prop('disabled', false).html('<i class="bi bi-arrow-repeat"></i> Synchronize');
                    }
                }

                function pollProgressOnce() {
                    let anyActive = false;
                    $('.progress-section[data-type]').each(function() {
                        const section = $(this);
                        if (section.css('display') === 'none' || section.attr('data-polling-done')) return;
                        const type = section.data('type');
                        anyActive = true;
                        $.get('{{ route('staging.progress') }}', { type: type }).done(function(response) {
                            const card = section.closest('.card');
                            if (response.success && response.progress) {
                                updateCardProgress(card, response.progress);
                            }
                        });
                    });
                    if (!anyActive && progressPollInterval) {
                        clearInterval(progressPollInterval);
                        progressPollInterval = null;
                    }
                }

                function startProgressPolling() {
                    if (progressPollInterval) return;
                    progressPollInterval = setInterval(function() {
                        $('.progress-section[data-type]').each(function() {
                            const section = $(this);
                            if (section.css('display') === 'none' || section.attr('data-polling-done')) return;
                            const type = section.data('type');
                            $.get('{{ route('staging.progress') }}', { type: type }).done(function(response) {
                                const card = section.closest('.card');
                                if (response.success && response.progress) {
                                    updateCardProgress(card, response.progress);
                                }
                            });
                        });
                        // Stop interval when no section needs polling
                        const stillPolling = $('.progress-section[data-type]').filter(function() {
                            return $(this).css('display') !== 'none' && !$(this).attr('data-polling-done');
                        }).length;
                        if (stillPolling === 0 && progressPollInterval) {
                            clearInterval(progressPollInterval);
                            progressPollInterval = null;
                        }
                    }, PROGRESS_POLL_MS);
                }

                function stopProgressPolling() {
                    if (progressPollInterval) {
                        clearInterval(progressPollInterval);
                        progressPollInterval = null;
                    }
                }


                // Tombol Sinkron Semua
                $('#btnSyncAll').on('click', function() {
                    const btn = $(this);
                    if (btn.prop('disabled')) return;

                    Swal.fire({
                        title: 'Sinkron Semua?',
                        html: 'Semua data staging akan disinkronkan (Buku, Cabang, Periode, Stok Pusat, Target, Sp Cabang, Nota Kirim). Proses mungkin memakan waktu.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Sinkron Semua',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (!result.isConfirmed) return;

                        btn.prop('disabled', true).html(
                            '<span class="spinner-border spinner-border-sm me-1"></span>Memulai...');

                        $.ajax({
                            url: '{{ route('staging.synchronize-all') }}',
                            method: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                if (response.success) {
                                    btn.html('<i class="bi bi-check-circle"></i> Dimulai');
                                    $('.progress-section').show();
                                    $('.progress-section').removeAttr('data-polling-done');
                                    startProgressPolling();
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil',
                                        text: response.message
                                    });
                                    setTimeout(function() {
                                        btn.prop('disabled', false).html(
                                            '<i class="bi bi-arrow-repeat me-1"></i> Sinkron Semua'
                                        );
                                    }, 2000);
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: response.message ||
                                            'Terjadi kesalahan'
                                    });
                                    btn.prop('disabled', false).html(
                                        '<i class="bi bi-arrow-repeat me-1"></i> Sinkron Semua'
                                    );
                                }
                            },
                            error: function(xhr) {
                                const msg = xhr.responseJSON?.message ||
                                    'Terjadi kesalahan';
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: msg
                                });
                                btn.prop('disabled', false).html(
                                    '<i class="bi bi-arrow-repeat me-1"></i> Sinkron Semua'
                                );
                            }
                        });
                    });
                });

                // Synchronize button
                $('.synchronize-btn').on('click', function() {
                    const type = $(this).data('type');
                    const name = $(this).data('name');
                    const btn = $(this);

                    if (btn.prop('disabled')) {
                        return;
                    }

                    btn.prop('disabled', true).html(
                        '<span class="spinner-border spinner-border-sm me-1"></span>Starting...');

                    $.ajax({
                        url: '{{ route('staging.synchronize') }}',
                        method: 'POST',
                        data: {
                            type: type,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                btn.html('<i class="bi bi-check-circle"></i> Started');

                                // Show progress section immediately
                                const card = btn.closest('.card');
                                const progressSection = card.find('.progress-section[data-type="' +
                                    type + '"]');
                                if (progressSection.length) {
                                    progressSection.show().removeAttr('data-polling-done');
                                    card.find('.alert-completed-placeholder').empty();
                                    const progressBar = card.find('.progress-bar');
                                    const progressText = card.find('.progress-text');
                                    if (progressBar.length) {
                                        progressBar.css('width', '0%').addClass('progress-bar-animated');
                                    }
                                    if (progressText.length) {
                                        progressText.text('0 / 0 (0.0%)');
                                    }
                                    card.find('.progress-created').text('Created: 0');
                                    card.find('.progress-updated').text('Updated: 0');
                                    card.find('.progress-errors').hide();
                                }

                                // Poll progress setiap 1 detik dan update progress bar tanpa reload
                                startProgressPolling();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'Terjadi kesalahan'
                                });
                                btn.prop('disabled', false).html(
                                    '<i class="bi bi-arrow-repeat"></i> Synchronize');
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.message || 'Terjadi kesalahan';
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: errorMsg
                            });
                            btn.prop('disabled', false).html(
                                '<i class="bi bi-arrow-repeat"></i> Synchronize');
                        }
                    });
                });

                // Saat load, jika ada progress section yang visible (job masih jalan), mulai polling
                if ($('.progress-section[data-type]').filter(function() { return $(this).css('display') !== 'none'; }).length) {
                    startProgressPolling();
                }

                // Cutoff Data Functions
                $('#btnAddCutoffData').on('click', function() {
                    $('#cutoffDataForm').slideDown();
                    $(this).hide();
                });

                $('#btnCancelCutoffData').on('click', function() {
                    $('#cutoffDataForm').slideUp();
                    $('#formCutoffData')[0].reset();
                    $('#btnAddCutoffData').show();
                });

                // Submit form cutoff data
                $('#formCutoffData').on('submit', function(e) {
                    e.preventDefault();

                    const formData = {
                        start_date: $('#start_date').val() || '',
                        end_date: $('#end_date').val(),
                        _token: '{{ csrf_token() }}'
                    };

                    $.ajax({
                        url: '{{ route('staging.cutoff-data.store') }}',
                        method: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: 'Cutoff data berhasil disimpan.',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'Terjadi kesalahan'
                                });
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.message || 'Terjadi kesalahan';
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: errorMsg
                            });
                        }
                    });
                });

                // Edit cutoff: buka modal dan isi data
                $(document).on('click', '.btn-edit-cutoff', function() {
                    const id = $(this).data('id');
                    const start = $(this).data('start') || '';
                    const end = $(this).data('end') || '';
                    $('#edit_cutoff_id').val(id);
                    $('#edit_start_date').val(start);
                    $('#edit_end_date').val(end);
                    new bootstrap.Modal(document.getElementById('modalEditCutoff')).show();
                });

                // Submit form edit cutoff
                $('#formEditCutoff').on('submit', function(e) {
                    e.preventDefault();
                    const id = $('#edit_cutoff_id').val();
                    const formData = {
                        start_date: $('#edit_start_date').val() || '',
                        end_date: $('#edit_end_date').val(),
                        _token: '{{ csrf_token() }}',
                        _method: 'PUT'
                    };
                    $.ajax({
                        url: '{{ route('staging.cutoff-data.update', ['id' => ':id']) }}'.replace(
                            ':id', id),
                        method: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                bootstrap.Modal.getInstance(document.getElementById(
                                    'modalEditCutoff')).hide();
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: response.message ||
                                        'Cutoff data berhasil diubah.',
                                    confirmButtonText: 'OK'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'Terjadi kesalahan'
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Terjadi kesalahan'
                            });
                        }
                    });
                });

                // Hapus cutoff (dengan konfirmasi)
                $(document).on('click', '.btn-delete-cutoff', function() {
                    const id = $(this).data('id');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Hapus Cutoff?',
                        text: 'Data cutoff ini akan dihapus. Lanjutkan?',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#dc3545'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: '{{ route('staging.cutoff-data.destroy', ['id' => ':id']) }}'
                                    .replace(':id', id),
                                method: 'POST',
                                data: {
                                    _token: '{{ csrf_token() }}',
                                    _method: 'DELETE'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Berhasil!',
                                            text: response.message ||
                                                'Cutoff data berhasil dihapus.',
                                            confirmButtonText: 'OK'
                                        }).then(() => location.reload());
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: response.message ||
                                                'Terjadi kesalahan'
                                        });
                                    }
                                },
                                error: function(xhr) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: xhr.responseJSON?.message ||
                                            'Terjadi kesalahan'
                                    });
                                }
                            });
                        }
                    });
                });

                // Toggle cutoff data status
                $(document).on('change', '.toggle-cutoff', function() {
                    const id = $(this).data('id');
                    const isChecked = $(this).is(':checked');
                    const checkbox = $(this);

                    // Disable checkbox while processing
                    checkbox.prop('disabled', true);

                    $.ajax({
                        url: '{{ route('staging.cutoff-data.toggle', ['id' => ':id']) }}'.replace(
                            ':id', id),
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload page to show updated status
                                location.reload();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'Terjadi kesalahan'
                                });
                                // Revert checkbox state
                                checkbox.prop('checked', !isChecked);
                                checkbox.prop('disabled', false);
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.message || 'Terjadi kesalahan';
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: errorMsg
                            });
                            // Revert checkbox state
                            checkbox.prop('checked', !isChecked);
                            checkbox.prop('disabled', false);
                        }
                    });
                });
            });
        </script>
    </x-slot>
</x-layouts>
