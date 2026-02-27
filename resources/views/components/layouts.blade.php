<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Dashboard Pusat - CRM Percetakan Buku</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ time() }}" />
    <style>
        /* Ensure Select2 containers have the same height as form controls (auto) */
        .select2-container--bootstrap-5 {
            height: 38px !important;
            line-height: auto !important;
        }

        .select2-container--bootstrap-5 .select2-selection {
            height: 38px !important;
            min-height: auto !important;
            max-height: auto !important;
            border: 1px solid #ced4da !important;
            border-radius: 0.375rem !important;
        }

        .select2-container--bootstrap-5 .select2-selection__rendered {
            line-height: auto !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            padding-left: 0.75rem !important;
            padding-right: 2rem !important;
            height: 32px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            vertical-align: middle !important;
        }

        .select2-container--bootstrap-5 .select2-selection__rendered * {
            vertical-align: middle !important;
        }

        .select2-container--bootstrap-5 .select2-selection__arrow {
            top: 0 !important;
            right: 1px !important;
            display: flex !important;
            align-items: center !important;
        }

        .select2-container--bootstrap-5 .select2-selection__clear {
            height: 38px !important;
            line-height: auto !important;
            margin-top: 0 !important;
            display: flex !important;
            align-items: center !important;
            cursor: pointer !important;
        }

        .select2-container--bootstrap-5 .select2-selection__rendered>span,
        .select2-container--bootstrap-5 .select2-selection__rendered {
            vertical-align: middle !important;
        }

        /* Ensure placeholder text is also centered */
        .select2-container--bootstrap-5 .select2-selection__placeholder {
            line-height: auto !important;
            vertical-align: middle !important;
        }

        /* Ensure form-control and form-select have consistent height */
        .form-control,
        .form-select {
            height: 38px !important;
        }

        * {
            font-family: monospace !important;
        }

        th {
            --bs-table-color-type: var(--bs-table-striped-color);
            --bs-table-bg-type: var(--bs-table-striped-bg);
        }

        td p,
        td span,
        td small,
        td strong {
            font-size: 0.75rem !important;
        }

        .card-header:first-child {
            border-radius: 10px 10px 0 0 !important;
        }

        .card-header:last-child {
            border-radius: 0 0 10px 10px !important;
        }

        .card {
            border-radius: 20px !important;
        }

        th,
        td {
            text-align: center;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 260px;
            border-radius: 0.9rem;
            background: radial-gradient(circle at top left, #ed1d2417, #ed1d2417);
            padding: 0.75rem;
        }
    </style>
    @stack('css')
    @if (isset($css))
        {{ $css }}
    @endif
</head>

<body>
    <div class="layout" id="layout">
        <!-- SIDEBAR -->
        <x-nav.sidebar.div />
        <!-- MAIN -->
        <div class="main">
            <!-- TOPBAR -->
            <header class="topbar">
                <div class="topbar-inner">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-link p-0 me-2" id="sidebarToggle" type="button"
                            title="Sembunyikan/Tampilkan sidebar">
                            <i class="bi bi-caret-left-square fs-4" id="sidebarToggleIcon"></i>
                        </button>
                        <div>
                            <h1 class="page-title">Dashboard Pusat</h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item small"><a href="#">Pusat</a></li>
                                    <li class="breadcrumb-item small active">Dashboard</li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="d-none d-md-flex align-items-center gap-2">
                            @php
                                $dateRange = session('date_range_global');
                                $showDateRangeModal = !$dateRange;
                                $activeCutoff = \App\Models\CutoffData::where('status', 'active')->first();
                            @endphp
                            <button class="btn btn-sm btn-primary rounded-pill" id="btnDateRange" data-bs-toggle="modal"
                                data-bs-target="#dateRangeGlobalModal">
                                <i class="bi bi-calendar3 me-1"></i>
                                @if ($dateRange)
                                    {{ \Carbon\Carbon::parse($dateRange['start_date'])->format('d M') }} -
                                    {{ \Carbon\Carbon::parse($dateRange['end_date'])->format('d M Y') }}
                                @elseif ($activeCutoff)
                                    <span class="text-light">Cutoff:
                                        @if ($activeCutoff->start_date)
                                            {{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d M') }} -
                                        @endif
                                        {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d M Y') }}
                                    </span>
                                @else
                                    Pilih Range
                                @endif
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2"
                                data-bs-toggle="modal" data-bs-target="#modalCabangDikelola"
                                title="Cabang yang dikelola">
                                <i class="bi bi-info-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill">
                                <i class="bi bi-globe me-1"></i> {{ auth()->user()->authority->title }}
                            </button>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-light d-flex align-items-center gap-2 rounded-pill px-2 py-1"
                                data-bs-toggle="dropdown">
                                <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                <div class="d-none d-sm-block text-start">
                                    <div class="small fw-semibold">{{ auth()->user()->name }}</div>
                                    <div class="small text-muted">{{ auth()->user()->authority->title }}</div>
                                </div>
                                <i class="bi bi-chevron-down small"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li class="dropdown-header small">Switch Role</li>
                                <li>
                                    <hr class="dropdown-divider" />
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('logout') }}"><i
                                            class="bi bi-box-arrow-right me-2"></i>Logout</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <!-- CONTENT -->
            <main class="content">
                {{ $slot }}
            </main>
        </div>
    </div>

    @include('components.nav.navbar.modal-cabang-dikelola')

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/js/app.js') }}?v={{ time() }}"></script>

    <script>
        // Prevent horizontal scroll on window only (not on table-responsive)
        // This doesn't interfere with vertical scrolling
        (function() {
            let ticking = false;

            function preventHorizontalScroll() {
                if (window.scrollX !== 0) {
                    window.scrollTo(0, window.scrollY);
                }
                ticking = false;
            }

            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(preventHorizontalScroll);
                    ticking = true;
                }
            }, {
                passive: true
            });
        })();

        // Initialize Select2 for all selects
        $(document).ready(function() {
            // Initialize Select2 for static selects (no AJAX)
            $('.select2-static').select2({
                theme: 'bootstrap-5',
                allowClear: true
            }).on('select2:open', function() {
                // Ensure height consistency after opening
                var $container = $(this).next('.select2-container');
                $container.css('height', 'auto');
                $container.find('.select2-selection').css({
                    'height': 'auto',
                    'min-height': 'auto'
                });
            });

            // Set height for Select2 containers after initialization
            setTimeout(function() {
                $('.select2-static').each(function() {
                    var $container = $(this).next('.select2-container');
                    $container.css('height', 'auto');
                    $container.find('.select2-selection').css({
                        'height': 'auto',
                        'min-height': 'auto'
                    });
                    $container.find('.select2-selection__rendered').css({
                        'line-height': 'auto',
                        'height': 'auto',
                        'display': 'flex',
                        'align-items': 'center'
                    });
                });
            }, 200);

            // Initialize Select2 for AJAX selects
            $('.select2-ajax').each(function() {
                var $select = $(this);
                var url = $select.data('url');
                var placeholder = $select.data('placeholder') || 'Pilih...';
                var selectedValue = $select.val();
                var selectedText = $select.find('option:selected').text();
                var selectId = $select.attr('id');

                // Initialize Select2
                $select.select2({
                    theme: 'bootstrap-5',
                    placeholder: placeholder,
                    allowClear: true,
                    ajax: {
                        url: url,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term || '',
                                page: params.page || 1
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.results || []
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 0
                }).on('select2:open', function() {
                    var $select = $(this);
                    var select2Data = $select.data('select2');

                    // Ensure height consistency after opening
                    var $container = $select.next('.select2-container');
                    $container.css('height', 'auto');
                    $container.find('.select2-selection').css({
                        'height': 'auto',
                        'min-height': 'auto'
                    });

                    // Load data when dropdown opens if empty
                    if (select2Data && (!$select.find('option').length || $select.find('option')
                            .length <= 1)) {
                        setTimeout(function() {
                            select2Data.trigger('query', {
                                term: ''
                            });
                        }, 100);
                    }
                });

                // Set height for Select2 container after initialization
                setTimeout(function() {
                    var $container = $select.next('.select2-container');
                    $container.css('height', 'auto');
                    $container.find('.select2-selection').css({
                        'height': 'auto',
                        'min-height': 'auto'
                    });
                    $container.find('.select2-selection__rendered').css({
                        'line-height': 'auto',
                        'height': 'auto',
                        'display': 'flex',
                        'align-items': 'center'
                    });
                }, 200);

                // If there's a pre-selected value, ensure it's displayed correctly
                if (selectedValue && selectedText && selectedValue !== '') {
                    // Remove existing option first to avoid duplicates
                    $select.find('option[value="' + selectedValue + '"]').remove();
                    // Add the selected option
                    var option = new Option(selectedText, selectedValue, true, true);
                    $select.append(option).trigger('change');
                }

                // Auto-fill related fields based on select ID
                $select.on('select2:select', function(e) {
                    var data = e.params.data;

                    // Auto-fill branch_name when branch_code is selected
                    if (selectId === 'branch_code' && data && data.branch_name) {
                        $('#branch_name').val(data.branch_name);
                    }

                    // Auto-fill book_name when book_code is selected
                    if (selectId === 'book_code' && data && data.book_name) {
                        $('#book_name').val(data.book_name);
                    }
                });
            });
        });
    </script>

    @stack('js')
    @if (isset($js))
        {{ $js }}
    @endif

    <!-- Date Range Global Modal -->
    @php
        $dateRangeGlobal = session('date_range_global');
        // Modal tidak wajib lagi - hanya muncul jika user klik tombol
        // Check if there's an active cutoff_data
$activeCutoff = \App\Models\CutoffData::where('status', 'active')->first();
// Don't show modal automatically - user can set date range manually if needed
        $showDateRangeGlobalModal = false;
    @endphp

    @if ($showDateRangeGlobalModal)
        <div class="modal fade show" id="dateRangeGlobalModal" tabindex="-1"
            aria-labelledby="dateRangeGlobalModalLabel" aria-modal="true" style="display: block;"
            data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="dateRangeGlobalModalLabel">Pilih Range Waktu</h5>
                    </div>
                    <form id="dateRangeGlobalForm">
                        @csrf
                        <div class="modal-body">
                            <p class="text-muted mb-3">Silakan pilih range waktu untuk menampilkan data di seluruh
                                aplikasi.
                            </p>
                            <div class="mb-3">
                                <label for="start_date_global" class="form-label">Dari Tanggal</label>
                                <input type="date" class="form-control" id="start_date_global" name="start_date"
                                    value="{{ date('Y-m-01') }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="end_date_global" class="form-label">Sampai Tanggal</label>
                                <input type="date" class="form-control" id="end_date_global" name="end_date"
                                    value="{{ date('Y-m-t') }}" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @else
        <!-- Modal untuk perubahan date range (bisa dibuka via button) -->
        <div class="modal fade" id="dateRangeGlobalModal" tabindex="-1" aria-labelledby="dateRangeGlobalModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="dateRangeGlobalModalLabel">Ubah Range Waktu</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <form id="dateRangeGlobalForm">
                        @csrf
                        <div class="modal-body">
                            <p class="text-muted mb-3">Silakan pilih range waktu untuk menampilkan data di seluruh
                                aplikasi.
                            </p>
                            @php
                                $defaultStartDate =
                                    $dateRangeGlobal['start_date'] ??
                                    ($activeCutoff ? $activeCutoff->start_date->format('Y-m-d') : date('Y-m-01'));
                                $defaultEndDate =
                                    $dateRangeGlobal['end_date'] ??
                                    ($activeCutoff ? $activeCutoff->end_date->format('Y-m-d') : date('Y-m-t'));
                            @endphp
                            <div class="mb-3">
                                <label for="start_date_global" class="form-label">Dari Tanggal</label>
                                <input type="date" class="form-control" id="start_date_global" name="start_date"
                                    value="{{ $defaultStartDate }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="end_date_global" class="form-label">Sampai Tanggal</label>
                                <input type="date" class="form-control" id="end_date_global" name="end_date"
                                    value="{{ $defaultEndDate }}" required>
                            </div>
                            @if ($activeCutoff && !$dateRangeGlobal)
                                <div class="alert alert-info alert-sm py-2 mb-0">
                                    <small>
                                        <i class="bi bi-info-circle"></i>
                                        Saat ini menggunakan cutoff data:
                                        {{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d/m/Y') }} -
                                        {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d/m/Y') }}
                                    </small>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <script>
        // Date Range Global Modal Handler
        $(document).ready(function() {
            // Handle form submit
            $('#dateRangeGlobalForm').on('submit', function(e) {
                e.preventDefault();

                const formData = {
                    start_date: $('#start_date_global').val(),
                    end_date: $('#end_date_global').val(),
                    _token: $('meta[name="csrf-token"]').attr('content')
                };

                $.ajax({
                    url: '{{ route('dashboard.set-date-range') }}',
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
                        }
                    },
                    error: function(xhr) {
                        const error = xhr.responseJSON?.message || 'Terjadi kesalahan';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error
                        });
                    }
                });
            });

            // Update button text when modal is shown (if date range exists)
            $('#dateRangeGlobalModal').on('show.bs.modal', function() {
                const startDate = $('#start_date_global').val();
                const endDate = $('#end_date_global').val();
                // Values are already set in the form
            });
        });
    </script>

</body>

</html>
