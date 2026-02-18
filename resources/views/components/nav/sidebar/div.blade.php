<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo">
            <img src="{{ asset('image/logo.png') }}" alt="Logo" />
        </div>
    </div>

    <div class="sidebar-nav">
        <div class="sidebar-title">Main</div>
        {{-- <x-nav.sidebar.menudropdown icon="iconoir-group" :menu="[
            ['url' => url('/user/superadmin'), 'key' => 'user.superadmin', 'icon' => 'iconoir-user'],
            ['url' => url('/user/admin'), 'key' => 'user.admin', 'icon' => 'iconoir-user'],
            ['url' => url('/user/finance'), 'key' => 'user.finance', 'icon' => 'iconoir-user'],
            ['url' => url('/user/therapist'), 'key' => 'user.therapist', 'icon' => 'iconoir-user'],
            ['url' => url('/user/coordinator'), 'key' => 'user.coordinator', 'icon' => 'iconoir-user'],
            ['url' => url('/user/parent'), 'key' => 'user.parent', 'icon' => 'iconoir-user'],
            ['url' => url('/user/owner'), 'key' => 'user.owner', 'icon' => 'iconoir-user'],
        ]" :authority="$authority"
            titlegroup="Manajemen User" /> --}}

        <x-nav.sidebar.menu url="{{ url('dashboard') }}" key="dashboard" icon="bi bi-speedometer" :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('rangkuman') }}" key="rangkuman" icon="bi bi-journal" :authority="$authority" />

        @if ((auth()->user()->authority_id ?? null) == 1 || (auth()->user()->authority_id ?? null) == 4)
            <div class="sidebar-title">Data Master</div>
        @endif
        <x-nav.sidebar.menu url="{{ url('product') }}" key="product" icon="bi bi-journals" :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('branch') }}" key="branch" icon="bi bi-building" :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('central-stock') }}" key="central_stock" icon="bi bi-journal"
            :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('target') }}" key="target" icon="bi bi-bullseye" :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('period') }}" key="period" icon="bi bi-calendar" :authority="$authority" />


        <div class="sidebar-title">Operasional</div>
        <x-nav.sidebar.menu url="{{ url('staging') }}" key="staging" icon="bi bi-journal" :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('pesanan') }}" key="pesanan" icon="bi bi-journal" :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('sp_v_stock') }}" key="sp_v_stock" icon="bi bi-journal" :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('sp_v_target') }}" key="sp_v_target" icon="bi bi-bullseye"
            :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('nppb-central') }}" key="nppb-central" icon="bi bi-truck" :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('nppb-warehouse') }}" key="nppb-warehouse" icon="bi bi-truck"
            :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('recap') }}" key="recap" icon="bi bi-journal" :authority="$authority" />

        @if ((auth()->user()->authority_id ?? null) == 1 || (auth()->user()->authority_id ?? null) == 4)
            <div class="sidebar-title">Manajemen User</div>
        @endif
        <x-nav.sidebar.menu url="{{ url('user-pusat') }}" key="user-pusat" icon="bi bi-person-badge"
            :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('user-cabang') }}" key="user-cabang" icon="bi bi-people" :authority="$authority" />
        <x-nav.sidebar.menu url="{{ url('user-adp') }}" key="user-adp" icon="bi bi-person-badge" :authority="$authority" />
    </div>
    <div class="sidebar-footer d-none">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <span>Role:</span>
            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                Pusat
            </span>
        </div>
        {{-- <small>Terakhir login: 02 Des 2025 09:00</small> --}}
    </div>
</aside>
