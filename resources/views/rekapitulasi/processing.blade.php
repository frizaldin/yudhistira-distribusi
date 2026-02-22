<x-layouts>
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 class="card-title">Rekapitulasi sedang diproses</h5>
            <p class="text-muted mb-2">
                Data rekap sedang dibangun di background. Halaman akan refresh otomatis dalam <span id="countdown">{{ $refresh_seconds }}</span> detik,
                atau klik tombol di bawah.
            </p>
            <p id="stop-msg" class="alert alert-warning small mb-3 d-none">
                Auto-refresh dihentikan. Queue worker di server kemungkinan belum jalan. Gunakan tombol <strong>Tampilkan tanpa queue</strong> di bawah.
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-center mb-3">
                <a href="{{ $recap_url ?? route('recap.index') }}" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh sekarang
                </a>
                @php
                    $base = $recap_url ?? route('recap.index');
                    $sync_url = $base . (str_contains($base, '?') ? '&' : '?') . 'sync=1';
                @endphp
                <a href="{{ $sync_url }}" class="btn btn-warning">
                    <i class="bi bi-lightning-charge me-1"></i> Tampilkan tanpa queue
                </a>
            </div>
            <p class="small text-muted mb-0">
                Jika loading terus (>1 menit), queue worker di server mungkin belum jalan. Gunakan <strong>Tampilkan tanpa queue</strong> untuk proses di request (mungkin 1–2 menit). Setelah itu atur cron: <code>* * * * * cd /path-project && php artisan schedule:run</code>
            </p>
        </div>
    </div>
    <script>
        (function () {
            var key = 'recap_processing_refreshes';
            var maxRefreshes = 4;
            var count = parseInt(sessionStorage.getItem(key) || '0', 10) + 1;
            sessionStorage.setItem(key, String(count));

            var refreshUrl = @json($recap_url ?? route('recap.index'));
            var seconds = {{ $refresh_seconds }};
            var countdownEl = document.getElementById('countdown');
            var stopMsg = document.getElementById('stop-msg');
            var meta = document.createElement('meta');
            meta.httpEquiv = 'refresh';
            meta.content = seconds + '; url=' + refreshUrl;

            if (count >= maxRefreshes) {
                sessionStorage.removeItem(key);
                stopMsg.classList.remove('d-none');
                if (countdownEl) countdownEl.closest('p').classList.add('d-none');
                return;
            }
            document.head.appendChild(meta);
            var left = seconds;
            var t = setInterval(function () {
                left--;
                if (countdownEl) countdownEl.textContent = left;
                if (left <= 0) clearInterval(t);
            }, 1000);
        })();
    </script>
</x-layouts>
