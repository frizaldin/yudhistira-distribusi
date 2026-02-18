document.addEventListener("DOMContentLoaded", function () {
    var layout = document.getElementById("layout");
    var toggleBtn = document.getElementById("sidebarToggle");
    var toggleIcon = document.getElementById("sidebarToggleIcon");
    var STORAGE_KEY = "sidebarCollapsed";

    function isMobile() {
        return window.innerWidth < 992;
    }

    function applySidebarState(collapsed) {
        if (!layout) return;
        if (collapsed) {
            layout.classList.add("sidebar-collapsed");
            if (toggleIcon) {
                toggleIcon.classList.remove("bi-caret-left-square");
                toggleIcon.classList.add("bi-caret-right-square");
            }
        } else {
            layout.classList.remove("sidebar-collapsed");
            if (toggleIcon) {
                toggleIcon.classList.remove("bi-caret-right-square");
                toggleIcon.classList.add("bi-caret-left-square");
            }
        }
    }

    function initSidebar() {
        var saved = localStorage.getItem(STORAGE_KEY);
        var collapsed;
        if (saved !== null) {
            collapsed = saved === "true";
        } else {
            collapsed = isMobile();
        }
        applySidebarState(collapsed);
        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? "true" : "false");
        } catch (e) {}
    }

    if (toggleBtn && layout) {
        initSidebar();
        toggleBtn.addEventListener("click", function () {
            var collapsed = layout.classList.contains("sidebar-collapsed");
            collapsed = !collapsed;
            applySidebarState(collapsed);
            try {
                localStorage.setItem(STORAGE_KEY, collapsed ? "true" : "false");
            } catch (e) {}
        });
    }

    // Simple handler for fake delete buttons
    document
        .querySelectorAll('[data-action="delete-row"]')
        .forEach(function (btn) {
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                if (confirm("Yakin ingin menghapus data ini?")) {
                    var tr = btn.closest("tr");
                    if (tr) tr.remove();
                }
            });
        });

    // Charts (if Chart.js available)
    if (typeof Chart !== "undefined") {
        function makeChart(id, config) {
            var el = document.getElementById(id);
            if (!el) return;
            return new Chart(el, config);
        }

        // dashboard pusat - national sales
        makeChart("nationalSalesChart", {
            type: "bar",
            data: {
                labels: [
                    "Jan",
                    "Feb",
                    "Mar",
                    "Apr",
                    "Mei",
                    "Jun",
                    "Jul",
                    "Agu",
                    "Sep",
                    "Okt",
                    "Nov",
                    "Des",
                ],
                datasets: [
                    {
                        label: "Penjualan Pusat",
                        data: [12, 14, 11, 16, 19, 21, 18, 22, 24, 23, 20, 25],
                        borderWidth: 2,
                        borderRadius: 8,
                        backgroundColor: "rgba(79, 70, 229, 0.18)",
                        borderColor: "rgba(79, 70, 229, 1)",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // dashboard cabang - branch sales
        makeChart("branchSalesChart", {
            type: "line",
            data: {
                labels: ["Sen", "Sel", "Rab", "Kam", "Jum", "Sab"],
                datasets: [
                    {
                        label: "Penjualan Cabang",
                        data: [12, 13, 9, 15, 14, 18],
                        tension: 0.35,
                        borderWidth: 2,
                        fill: true,
                        backgroundColor: "rgba(56, 189, 248, 0.18)",
                        borderColor: "rgba(56, 189, 248, 1)",
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // dashboard sales - pipeline
        makeChart("salesPipelineChart", {
            type: "bar",
            data: {
                labels: ["Lead", "Prospect", "Deal", "Closing"],
                datasets: [
                    {
                        data: [40, 22, 10, 7],
                        backgroundColor: [
                            "rgba(129, 140, 248, 0.7)",
                            "rgba(56, 189, 248, 0.7)",
                            "rgba(52, 211, 153, 0.7)",
                            "rgba(251, 191, 36, 0.8)",
                        ],
                        borderWidth: 0,
                        borderRadius: 10,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // transaksi penjualan - summary chart
        makeChart("transaksiChart", {
            type: "line",
            data: {
                labels: ["01", "05", "10", "15", "20", "25", "30"],
                datasets: [
                    {
                        label: "Total Penjualan",
                        data: [5, 7, 9, 8, 11, 13, 14],
                        tension: 0.3,
                        borderWidth: 2,
                        fill: true,
                        backgroundColor: "rgba(96, 165, 250, 0.18)",
                        borderColor: "rgba(37, 99, 235, 1)",
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // analytics: penjualan MoM/YoY
        makeChart("penjualanMoMChart", {
            type: "bar",
            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun"],
                datasets: [
                    {
                        label: "Tahun Ini",
                        data: [12, 14, 16, 18, 20, 22],
                        backgroundColor: "rgba(79, 70, 229, 0.8)",
                        borderRadius: 8,
                    },
                    {
                        label: "Tahun Lalu",
                        data: [10, 11, 13, 15, 16, 18],
                        backgroundColor: "rgba(148, 163, 184, 0.6)",
                        borderRadius: 8,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { tooltip: { enabled: true } },
                scales: {
                    x: { stacked: false, grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // analytics produk - top vs bottom
        makeChart("produkTopBottomChart", {
            type: "bar",
            data: {
                labels: [
                    "MTK 10",
                    "B. Indo 7",
                    "LKS IPA 9",
                    "B. Inggris 11",
                    "LKS IPS 8",
                ],
                datasets: [
                    {
                        label: "Penjualan (rbk)",
                        data: [120, 96, 88, 40, 28],
                        backgroundColor: [
                            "rgba(34, 197, 94, 0.8)",
                            "rgba(52, 211, 153, 0.8)",
                            "rgba(56, 189, 248, 0.8)",
                            "rgba(251, 191, 36, 0.8)",
                            "rgba(248, 113, 113, 0.9)",
                        ],
                        borderRadius: 8,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // analytics produk - heatmap simple (bar per area)
        makeChart("produkAreaChart", {
            type: "bar",
            data: {
                labels: ["Bandung", "Jakarta", "Bogor", "Depok", "Bekasi"],
                datasets: [
                    {
                        label: "Indeks Penjualan",
                        data: [90, 75, 68, 45, 38],
                        backgroundColor: "rgba(59, 130, 246, 0.8)",
                        borderRadius: 8,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // analytics customer - segmentasi
        makeChart("customerSegmentChart", {
            type: "doughnut",
            data: {
                labels: ["SD", "SMP", "SMA", "Toko Buku"],
                datasets: [
                    {
                        data: [40, 25, 20, 15],
                        backgroundColor: [
                            "rgba(96, 165, 250, 0.9)",
                            "rgba(59, 130, 246, 0.9)",
                            "rgba(37, 99, 235, 0.9)",
                            "rgba(129, 140, 248, 0.9)",
                        ],
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: "bottom" } },
            },
        });

        // analytics customer - CLV
        makeChart("clvChart", {
            type: "bar",
            data: {
                labels: ["Top 10", "Top 50", "Top 100", "Lainnya"],
                datasets: [
                    {
                        data: [45, 30, 15, 10],
                        backgroundColor: "rgba(22, 163, 74, 0.85)",
                        borderRadius: 10,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // analytics cabang - ranking
        makeChart("cabangRankingChart", {
            type:
                "horizontalBar" in Chart.controllers ? "horizontalBar" : "bar",
            data: {
                labels: [
                    "Bandung",
                    "Jakarta Barat",
                    "Depok",
                    "Cirebon",
                    "Tasikmalaya",
                ],
                datasets: [
                    {
                        data: [120, 110, 80, 70, 65],
                        backgroundColor: "rgba(79, 70, 229, 0.85)",
                        borderRadius: 8,
                    },
                ],
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: "rgba(148,163,184,0.35)" } },
                    y: { grid: { display: false } },
                },
            },
        });

        // analytics sales - aktivitas harian
        makeChart("salesActivityChart", {
            type: "line",
            data: {
                labels: ["Sen", "Sel", "Rab", "Kam", "Jum"],
                datasets: [
                    {
                        label: "Kunjungan",
                        data: [10, 14, 9, 13, 16],
                        tension: 0.35,
                        borderWidth: 2,
                        fill: true,
                        backgroundColor: "rgba(52, 211, 153, 0.2)",
                        borderColor: "rgba(5, 150, 105, 1)",
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // root cause - simple bar
        makeChart("rootCauseChart", {
            type: "bar",
            data: {
                labels: [
                    "Stok",
                    "Aktivitas Sales",
                    "Kompetitor",
                    "Musim Ajaran",
                ],
                datasets: [
                    {
                        data: [35, 28, 22, 15],
                        backgroundColor: [
                            "rgba(248, 113, 113, 0.9)",
                            "rgba(251, 191, 36, 0.9)",
                            "rgba(96, 165, 250, 0.9)",
                            "rgba(129, 140, 248, 0.9)",
                        ],
                        borderRadius: 8,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // forecasting chart
        makeChart("forecastingChart", {
            type: "line",
            data: {
                labels: ["Des", "Jan", "Feb", "Mar", "Apr", "Mei"],
                datasets: [
                    {
                        label: "Realisasi",
                        data: [100, 110, 115, 118, 0, 0],
                        tension: 0.3,
                        borderWidth: 2,
                        borderColor: "rgba(55, 65, 81, 1)",
                        backgroundColor: "rgba(148, 163, 184, 0.25)",
                        fill: false,
                        pointRadius: 3,
                    },
                    {
                        label: "Forecast",
                        data: [null, null, null, 118, 122, 128],
                        tension: 0.3,
                        borderWidth: 2,
                        borderColor: "rgba(59, 130, 246, 1)",
                        backgroundColor: "rgba(59, 130, 246, 0.2)",
                        borderDash: [6, 4],
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: "bottom" } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });

        // alert volume over time
        makeChart("alertVolumeChart", {
            type: "line",
            data: {
                labels: ["Sen", "Sel", "Rab", "Kam", "Jum"],
                datasets: [
                    {
                        data: [2, 5, 4, 6, 3],
                        tension: 0.3,
                        borderWidth: 2,
                        borderColor: "rgba(248, 113, 113, 1)",
                        backgroundColor: "rgba(248, 113, 113, 0.2)",
                        fill: true,
                        pointRadius: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: "rgba(148,163,184,0.35)" } },
                },
            },
        });
    }
});
// Toggle password visibility
const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("passwordInput");
const eyeIcon = document.getElementById("eyeIcon");

togglePassword.addEventListener("click", function () {
    const type =
        passwordInput.getAttribute("type") === "password" ? "text" : "password";
    passwordInput.setAttribute("type", type);

    if (type === "password") {
        eyeIcon.classList.remove("bi-eye");
        eyeIcon.classList.add("bi-eye-slash");
    } else {
        eyeIcon.classList.remove("bi-eye-slash");
        eyeIcon.classList.add("bi-eye");
    }
});
