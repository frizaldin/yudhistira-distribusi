@foreach($adpBranches ?? [] as $branch)
<tr>
    <td style="padding: 0.5rem 1rem;">
        <a href="{{ route('dashboard.branch-detail', $branch->branch_code) }}"
            class="text-decoration-none text-dark" style="cursor: pointer;">
            <span style="font-size: 0.75rem;">{{ $branch->branch_name ?? $branch->branch_code }}</span>
        </a>
    </td>
    <td class="text-end" style="padding: 0.5rem 1rem;">
        <span style="font-size: 0.75rem;">{{ number_format($branch->sisa_sp ?? 0, 0, ',', '.') }}</span>
    </td>
    <td class="text-end" style="padding: 0.5rem 1rem;">
        <span style="font-size: 0.75rem;">{{ number_format(isset($targets[$branch->branch_code]) ? $targets[$branch->branch_code]->total_target : 0, 0, ',', '.') }}</span>
    </td>
    <td class="text-end" style="padding: 0.5rem 1rem;">
        <span style="font-size: 0.75rem;">{{ number_format($totalNppbKoli ?? 0, 0, ',', '.') }}</span>
    </td>
    <td style="padding: 0.5rem 1rem;">
        <span style="font-size: 0.75rem;">{{ number_format(isset($nppbPerBranch[$branch->branch_code]) ? $nppbPerBranch[$branch->branch_code]->total_pls : 0, 0, ',', '.') }}</span>
    </td>
</tr>
@endforeach
