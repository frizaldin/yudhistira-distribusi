<x-layouts>
    <!-- Cutoff Data Info -->
    @if (isset($usingCutoffData) && $usingCutoffData && isset($activeCutoff) && $activeCutoff)
        <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Menggunakan Cutoff Data:</strong>
            Data ditampilkan berdasarkan cutoff data aktif
            ({{ \Carbon\Carbon::parse($activeCutoff->start_date)->format('d/m/Y') }} -
            {{ \Carbon\Carbon::parse($activeCutoff->end_date)->format('d/m/Y') }}).
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">Detail Cabang: {{ $branch->branch_name ?? $branchCode }}</h4>
                        <small class="text-muted">
                            Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} -
                            {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                        </small>
                    </div>
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Detail Produk per Cabang</strong>
                        @if (isset($products) && $products->total() > 0)
                            <small class="text-muted">
                                Total: {{ number_format($products->total(), 0, ',', '.') }} produk
                            </small>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="font-weight: 600; padding: 0.75rem 1rem;">Kode Produk</th>
                                        <th style="font-weight: 600; padding: 0.75rem 1rem;">Nama Produk</th>
                                        <th class="text-end" style="font-weight: 600; padding: 0.75rem 1rem;">SP</th>
                                        <th class="text-end" style="font-weight: 600; padding: 0.75rem 1rem;">Faktur
                                        </th>
                                        <th class="text-end" style="font-weight: 600; padding: 0.75rem 1rem;">Sisa SP
                                        </th>
                                        <th class="text-end" style="font-weight: 600; padding: 0.75rem 1rem;">Stock
                                            Cabang</th>
                                        <th class="text-end" style="font-weight: 600; padding: 0.75rem 1rem;">Eksemplar
                                        </th>
                                        <th class="text-end" style="font-weight: 600; padding: 0.75rem 1rem;">Koli</th>
                                        <th class="text-end" style="font-weight: 600; padding: 0.75rem 1rem;">Eceran
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($products->items() ?? [] as $product)
                                        <tr>
                                            <td style="padding: 0.75rem 1rem;">{{ $product['book_code'] }}</td>
                                            <td style="padding: 0.75rem 1rem;">{{ $product['book_name'] }}</td>
                                            <td class="text-end" style="padding: 0.75rem 1rem;">
                                                {{ number_format($product['sp'] ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end" style="padding: 0.75rem 1rem;">
                                                {{ number_format($product['faktur'] ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end" style="padding: 0.75rem 1rem;">
                                                {{ number_format($product['sisa_sp'] ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end" style="padding: 0.75rem 1rem;">
                                                {{ number_format($product['stock_cabang'] ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end" style="padding: 0.75rem 1rem;">
                                                {{ number_format($product['eksemplar'] ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end" style="padding: 0.75rem 1rem;">
                                                {{ number_format($product['koli'] ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end" style="padding: 0.75rem 1rem;">
                                                {{ number_format($product['plastik'] ?? 0, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">
                                                <small>Belum ada data produk</small>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if (isset($products) && $products->hasPages())
                            <div class="card-footer bg-white border-top py-2">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div class="mb-2 mb-md-0">
                                        <small class="text-muted">
                                            Menampilkan {{ $products->firstItem() }} - {{ $products->lastItem() }}
                                            dari {{ $products->total() }} produk
                                        </small>
                                    </div>
                                    <div>
                                        {{ $products->links('pagination::bootstrap-5') }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts>
