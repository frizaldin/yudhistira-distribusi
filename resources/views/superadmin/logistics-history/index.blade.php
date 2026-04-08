<x-layouts>
    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-3">
                <strong>Riwayat Mutasi, NKB &amp; Retur</strong><br />
                <small class="text-muted">Data langsung dari PostgreSQL (staging): mutasi (<code>m_mutasi_buku</code> /
                    <code>d_mutasi_buku</code>), NKB (<code>m_kirim_cabang</code> / <code>d_kirim_cabang</code>),
                    retur (<code>m_terima_buku</code> / <code>d_terima_buku</code>). Filter cabang &amp; tanggal
                    seperti sebelumnya.</small>
            </div>

            @if (isset($validationErrors) && $validationErrors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($validationErrors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="get" action="{{ route('riwayat_pengiriman.index') }}" class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Kode buku <span class="text-danger">*</span></label>
                    <select name="book_code" id="book_code" class="form-select select2-ajax" required
                        data-url="{{ route('api.products') }}" data-placeholder="Pilih buku">
                        @if (!empty($filters['book_code']))
                            <option value="{{ $filters['book_code'] }}" selected>{{ $filters['book_code'] }}</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cabang pengirim / asal</label>
                    <select name="branch_code" class="form-select select2-static">
                        <option value="">— Semua —</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->branch_code }}" @selected(($filters['branch_code'] ?? '') === $b->branch_code)>
                                {{ $b->branch_code }} — {{ $b->branch_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cabang tujuan / penerima</label>
                    <select name="recipient_code" class="form-select select2-static">
                        <option value="">— Semua —</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->branch_code }}" @selected(($filters['recipient_code'] ?? '') === $b->branch_code)>
                                {{ $b->branch_code }} — {{ $b->branch_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal mulai <span class="text-danger">*</span></label>
                    <input type="date" name="date_from" class="form-control" required
                        value="{{ $filters['date_from'] ?? '' }}" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal akhir <span class="text-danger">*</span></label>
                    <input type="date" name="date_to" class="form-control" required
                        value="{{ $filters['date_to'] ?? '' }}" />
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>Tampilkan
                    </button>
                    <a href="{{ route('riwayat_pengiriman.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

            @if ($searched && $product)
                <p class="text-muted small mb-3">
                    Buku: <strong>{{ $product->book_code }}</strong> — {{ $product->book_title }}<br />
                    Periode: {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }} –
                    {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}
                </p>

                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-mutasi" type="button">
                            Mutasi ({{ $mutasiRows->count() }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-nkb" type="button">
                            NKB ({{ $nkbRows->count() }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-retur" type="button">
                            Retur ({{ $returRows->count() }})
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-mutasi" role="tabpanel">
                        @if ($mutasiRows->isEmpty())
                            <p class="text-muted mb-0">Tidak ada mutasi buku pada filter ini.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Kode mutasi</th>
                                            <th>JO</th>
                                            <th>Publish</th>
                                            <th>Cabang (master)</th>
                                            <th>Penerima</th>
                                            <th>Tanggal</th>
                                            <th class="text-end">Koli</th>
                                            <th class="text-end">Eksemplar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($mutasiRows as $row)
                                            @php
                                                $dt = $row->send_date ?? $row->receive_date;
                                            @endphp
                                            <tr>
                                                <td><code>{{ $row->mutation_code }}</code></td>
                                                <td>{{ $row->jo_code ?: '—' }}</td>
                                                <td>{{ $row->publish_code ?: '—' }}</td>
                                                <td>{{ $branchNames[$row->master_branch_code] ?? $row->master_branch_code ?? '—' }}
                                                </td>
                                                <td>{{ $branchNames[$row->receiver] ?? $row->receiver ?? '—' }}</td>
                                                <td>{{ $dt ? \Carbon\Carbon::parse($dt)->format('d/m/Y') : '—' }}</td>
                                                <td class="text-end">{{ (int) $row->koli }}</td>
                                                <td class="text-end">{{ (int) $row->total_exemplar }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane fade" id="tab-nkb" role="tabpanel">
                        @if ($nkbRows->isEmpty())
                            <p class="text-muted mb-0">Tidak ada nota kirim cabang pada filter ini.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>No. nota kirim</th>
                                            <th>Pengirim</th>
                                            <th>Tujuan</th>
                                            <th>Tanggal kirim</th>
                                            <th>NPPB / SJ</th>
                                            <th class="text-end">Koli</th>
                                            <th class="text-end">Eksemplar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($nkbRows as $row)
                                            <tr>
                                                <td><code>{{ $row->nota_kirim_cab }}</code></td>
                                                <td>{{ $branchNames[$row->branch_sender] ?? $row->branch_sender ?? '—' }}
                                                </td>
                                                <td>{{ $branchNames[$row->recipient_branch_code] ?? $row->recipient_branch_code ?? '—' }}
                                                </td>
                                                <td>{{ $row->send_date ? \Carbon\Carbon::parse($row->send_date)->format('d/m/Y') : '—' }}
                                                </td>
                                                <td class="small text-muted">
                                                    @if ($row->nppb)
                                                        NPPB: <code>{{ $row->nppb }}</code>
                                                    @endif
                                                    @if ($row->sj)
                                                        {{ $row->nppb ? ' · ' : '' }}SJ: <code>{{ $row->sj }}</code>
                                                    @endif
                                                    @if (!$row->nppb && !$row->sj)
                                                        —
                                                    @endif
                                                </td>
                                                <td class="text-end">{{ (int) $row->koli }}</td>
                                                <td class="text-end">{{ (int) $row->total_exemplar }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane fade" id="tab-retur" role="tabpanel">
                        @if ($returRows->isEmpty())
                            <p class="text-muted mb-0">Tidak ada retur / terima buku pada filter ini.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Kode terima</th>
                                            <th>No. nota kirim</th>
                                            <th>Pengirim</th>
                                            <th>Tujuan terima</th>
                                            <th>Tanggal</th>
                                            <th class="text-end">Koli</th>
                                            <th class="text-end">Eksemplar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($returRows as $row)
                                            @php
                                                $dt = $row->retur_date ?? $row->send_date;
                                            @endphp
                                            <tr>
                                                <td><code>{{ $row->receive_code }}</code></td>
                                                <td>{{ $row->nota_kirim_cab ? $row->nota_kirim_cab : '—' }}</td>
                                                <td>{{ $branchNames[$row->branch_sender] ?? $row->branch_sender ?? '—' }}
                                                </td>
                                                <td>{{ $branchNames[$row->recipient_branch_code] ?? $row->recipient_branch_code ?? '—' }}
                                                </td>
                                                <td>{{ $dt ? \Carbon\Carbon::parse($dt)->format('d/m/Y') : '—' }}</td>
                                                <td class="text-end">{{ (int) $row->koli }}</td>
                                                <td class="text-end">{{ (int) $row->total_exemplar }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @elseif ($searched && !$product && !isset($validationErrors))
                <p class="text-muted mb-0">Produk tidak ditemukan.</p>
            @endif
        </div>
    </div>
</x-layouts>
