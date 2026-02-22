@foreach($topBranches ?? [] as $branch)
@php
    $stokCabang = $branch->total_stok_cabang ?? 0;
    $stokDisplay = '';
    $stokClass = '';
    $stokIcon = '';
    $stokBadgeColor = '';
    $branchCode = strtolower($branch->branch_code ?? '');
    $branchName = strtolower($branch->branch_name ?? '');
    if ($stokCabang > 10000) {
        $stokBadgeColor = '#d1e7dd';
        $stokClass = 'text-success';
        $stokIcon = 'bi-arrow-up';
        $stokDisplay = '+' . number_format($stokCabang, 0, ',', '.');
    } elseif ($stokCabang > 5000) {
        if (strpos($branchName, 'medan') !== false || strpos($branchCode, 'medan') !== false) {
            $stokBadgeColor = '#cfe2ff';
            $stokClass = 'text-primary';
        } else {
            $stokBadgeColor = '#d1e7dd';
            $stokClass = 'text-success';
        }
        $stokIcon = 'bi-arrow-up';
        $stokDisplay = number_format($stokCabang, 0, ',', '.');
    } elseif ($stokCabang > 0 && $stokCabang <= 5000) {
        if (strpos($branchName, 'banda aceh') !== false || strpos($branchCode, 'banda') !== false) {
            $stokDisplay = 'R ' . number_format($stokCabang, 0, '', '');
            $stokBadgeColor = '#fff3cd';
            $stokClass = 'text-warning';
            $stokIcon = 'bi-arrow-right';
        } elseif (strpos($branchName, 'sumsel') !== false || strpos($branchCode, 'sumsel') !== false) {
            $stokBadgeColor = '#d1e7dd';
            $stokClass = 'text-success';
            $stokIcon = 'bi-arrow-right';
            $stokDisplay = 'D mng';
        } else {
            $stokBadgeColor = '#d1e7dd';
            $stokClass = 'text-success';
            $stokIcon = 'bi-arrow-up';
            $stokDisplay = number_format($stokCabang, 0, ',', '.');
        }
    } else {
        $stokBadgeColor = '#f8f9fa';
        $stokClass = 'text-muted';
        $stokIcon = 'bi-dash';
        $stokDisplay = number_format($stokCabang, 0, ',', '.');
    }
    $textColor = '#000';
    if ($stokClass == 'text-success') $textColor = '#198754';
    elseif ($stokClass == 'text-primary') $textColor = '#0d6efd';
    elseif ($stokClass == 'text-warning') $textColor = '#856404';
    elseif ($stokClass == 'text-muted') $textColor = '#6c757d';
@endphp
<tr>
    <td style="padding: 0.5rem 1rem;">
        <a href="{{ route('dashboard.branch-detail', $branch->branch_code) }}"
            class="text-decoration-none text-dark" style="cursor: pointer;">
            <i class="bi bi-caret-down-fill"
                style="font-size: 0.45rem; color: #000; margin-right: 0.375rem; vertical-align: middle;"></i>
            <span style="font-size: 0.75rem;">{{ $branch->branch_name ?? $branch->branch_code }}</span>
        </a>
    </td>
    <td class="text-end" style="padding: 0.5rem 1rem;">
        <span style="font-size: 0.75rem;">{{ number_format($branch->total_sp ?? 0, 0, ',', '.') }}</span>
    </td>
    <td class="text-end" style="padding: 0.5rem 1rem;">
        <span style="font-size: 0.75rem;">{{ number_format($branch->total_faktur ?? 0, 0, ',', '.') }}</span>
    </td>
    <td class="text-end" style="padding: 0.5rem 1rem;">
        <span style="font-size: 0.75rem;">{{ number_format($branch->sisa_sp ?? 0, 0, ',', '.') }}</span>
    </td>
    <td class="text-end" style="padding: 0.5rem 1rem;">
        <span class="badge rounded-pill d-inline-flex align-items-center"
            style="background-color: {{ $stokBadgeColor }}; color: {{ $textColor }}; padding: 0.15rem 0.5rem; font-size: 0.6875rem; font-weight: 500; gap: 0.2rem;">
            <i class="bi {{ $stokIcon }}" style="font-size: 0.65rem;"></i>
            <span>{{ $stokDisplay }}</span>
        </span>
    </td>
</tr>
@endforeach
