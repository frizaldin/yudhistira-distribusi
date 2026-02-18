@php
    $dateRange = session('date_range_global');
    $activeCutoff = \App\Models\CutoffData::where('status', 'active')->first();
    $user = auth()->user();
    $managedBranches = collect();
    if ($user) {
        $role = $user->authority_id ?? 1;
        if ($role === 2 && !empty($user->branch_code)) {
            $managedBranches = \App\Models\Branch::where('branch_code', $user->branch_code)->get();
        } elseif ($role === 3 && is_array($user->branch) && count($user->branch) > 0) {
            $managedBranches = \App\Models\Branch::whereIn('branch_code', $user->branch)->orderBy('branch_name')->get();
        }
    }
@endphp
<!-- Top Bar Start -->
<div class="topbar d-print-none">
    <div class="container-fluid">
        <nav class="topbar-custom d-flex justify-content-between" id="topbar-custom">


            <ul class="topbar-item list-unstyled d-inline-flex align-items-center mb-0">
                <li>
                    <button class="nav-link mobile-menu-btn nav-icon" id="togglemenu">
                        <i class="iconoir-menu"></i>
                    </button>
                </li>
                <li class="ms-2">
                    <strong>{!! badgeUserRole(auth()->user()->authority_id) !!}</strong>
                </li>
                {{-- Tanggal cutoff/range + icon info cabang dikelola --}}
                <li class="ms-2 d-none d-md-flex align-items-center gap-1">
                    @if ($dateRange)
                        <span class="small text-muted">
                            {{ \Carbon\Carbon::parse($dateRange['start_date'])->format('d M') }} -
                            {{ \Carbon\Carbon::parse($dateRange['end_date'])->format('d M Y') }}
                        </span>
                    @elseif ($activeCutoff)
                        <span class="small text-muted">
                            Cutoff:
                            @if ($activeCutoff->start_date)
                                {{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d M') }} -
                            @endif
                            {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d M Y') }}
                        </span>
                    @endif
                    <button type="button" class="btn btn-link btn-sm p-0 text-info lh-1" data-bs-toggle="modal"
                        data-bs-target="#modalCabangDikelola" title="Cabang yang dikelola">
                        <i class="bi bi-info-circle fs-5"></i>
                    </button>
                </li>
            </ul>
            <ul class="topbar-item list-unstyled d-inline-flex align-items-center mb-0">
                <li class="topbar-item">
                    <a class="nav-link nav-icon" href="javascript:void(0);" id="light-dark-mode">
                        <i class="iconoir-half-moon dark-mode"></i>
                        <i class="iconoir-sun-light light-mode"></i>
                    </a>
                </li>

                <li class="dropdown topbar-item">
                    @php
                        $notifications = $notification ?? collect();
                        $unreadCount = $notifications->count();
                    @endphp
                    <a class="nav-link dropdown-toggle arrow-none nav-icon position-relative" data-bs-toggle="dropdown"
                        href="#" role="button" aria-haspopup="false" aria-expanded="false" data-bs-offset="0,19">
                        <i class="iconoir-bell"></i>
                        @if ($unreadCount > 0)
                            <span class="badge bg-danger rounded-circle position-absolute"
                                style="top: -5px; right: -5px; font-size: 10px; min-width: 18px; height: 18px; padding: 2px 5px; line-height: 14px;">{{ $unreadCount }}</span>
                        @endif
                    </a>
                    <div class="dropdown-menu stop dropdown-menu-end dropdown-lg py-0">
                        <h5 class="dropdown-item-text m-0 py-3 d-flex justify-content-between align-items-center">
                            <span>Notifikasi</span>
                            <a href="{{ url('notifications') }}" class="btn btn-sm btn-link p-0 text-decoration-none"
                                title="Lihat Semua">
                                <i class="iconoir-eye fs-5"></i>
                            </a>
                        </h5>
                        <div class="ms-0" style="max-height:230px;" data-simplebar>
                            @if ($unreadCount > 0)
                                @foreach ($notifications as $notif)
                                    <a href="{{ url('notifications/read/' . $notif->id) }}"
                                        class="dropdown-item py-3 bg-light-hover">
                                        <small class="float-end text-muted ps-2">
                                            {{ $notif->sent_at ? \Carbon\Carbon::parse($notif->sent_at)->diffForHumans() : ($notif->created_at ? \Carbon\Carbon::parse($notif->created_at)->diffForHumans() : '-') }}
                                        </small>
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="flex-shrink-0 bg-primary-subtle text-primary thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="iconoir-bell fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-2 text-truncate">
                                                <h6 class="my-0 fw-semibold text-dark fs-13">
                                                    {{ $notif->title ?? 'Notifikasi' }}
                                                </h6>
                                                <small class="text-muted mb-0 d-block text-truncate"
                                                    style="max-width: 250px;" title="{{ $notif->message ?? '-' }}">
                                                    {{ $notif->message ?? '-' }}
                                                </small>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            @else
                                <div class="dropdown-item py-3 text-center text-muted">
                                    <i class="iconoir-inbox fs-4 d-block mb-2 opacity-50"></i>
                                    <span>Tidak ada notifikasi baru.</span>
                                </div>
                            @endif
                        </div>
                        <a href="{{ url('notifications') }}"
                            class="dropdown-item text-center text-dark fs-13 py-2 border-top">
                            <strong>Lihat Semua Notifikasi</strong> <i class="iconoir-arrow-right ms-1"></i>
                        </a>
                    </div>
                </li>

                <li class="dropdown topbar-item">
                    <a class="nav-link dropdown-toggle arrow-none nav-icon" data-bs-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" aria-expanded="false" data-bs-offset="0,19">
                        <img src="{{ auth()->user()->photo ? getImage(auth()->user()->photo) : asset('mdp-logo.png') }}"
                            alt="" class="thumb-md rounded-circle">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end py-0">
                        <div class="d-flex align-items-center dropdown-item py-2 bg-secondary-subtle">
                            <div class="flex-shrink-0">
                                <img src="{{ auth()->user()->photo ? getImage(auth()->user()->photo) : asset('mdp-logo.png') }}"
                                    alt="" class="thumb-md rounded-circle">
                            </div>
                            <div class="flex-grow-1 ms-2 text-truncate align-self-center">
                                <h6 class="my-0 fw-medium text-dark fs-13">{{ auth()->user()->name }}</h6>
                                <small class="text-muted mb-0">{{ auth()->user()->authority->title }}</small>
                            </div><!--end media-body-->
                        </div>
                        <div class="dropdown-divider mt-0"></div>
                        <small class="text-muted px-2 pb-1 d-block">Account</small>
                        <a class="dropdown-item" href="{{ url('profile') }}"><i
                                class="las la-user fs-18 me-1 align-text-bottom"></i> Profile</a>
                        <a class="dropdown-item" href="{{ url('password') }}"><i
                                class="las la-lock fs-18 me-1 align-text-bottom"></i> Security</a>
                        <div class="dropdown-divider mb-0"></div>
                        <a class="dropdown-item text-danger" href="{{ url('logout') }}"><i
                                class="las la-power-off fs-18 me-1 align-text-bottom"></i> Logout</a>
                    </div>
                </li>
            </ul><!--end topbar-nav-->
        </nav>
        <!-- end navbar-->
    </div>
</div>
<!-- Top Bar End -->

{{-- Modal: Cabang yang dikelola user --}}
<div class="modal fade" id="modalCabangDikelola" tabindex="-1" aria-labelledby="modalCabangDikelolaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCabangDikelolaLabel">
                    <i class="bi bi-building me-2"></i>Cabang yang Dikelola
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                @if ($user && ($user->authority_id ?? 1) == 1)
                    <p class="text-muted mb-0">Anda mengelola <strong>semua cabang</strong> (Superadmin).</p>
                @elseif ($managedBranches->isEmpty())
                    <p class="text-muted mb-0">Tidak ada cabang yang ditetapkan untuk akun Anda.</p>
                @else
                    <p class="small text-muted mb-2">Data yang tampil di seluruh menu hanya untuk cabang berikut:</p>
                    <ul class="list-group list-group-flush">
                        @foreach ($managedBranches as $b)
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span><strong>{{ $b->branch_code }}</strong> — {{ $b->branch_name ?? '-' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
