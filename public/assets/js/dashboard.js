// Inisialisasi Peta Leaflet
const map = L.map("map").setView([-2.5489, 118.0149], 5);

// Tambahkan tile layer (OpenStreetMap)
L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
}).addTo(map);

// Data 80 Cabang di Indonesia (koordinat kota-kota besar)
const cabangData = [
    // Jawa Barat
    {
        name: "Bandung",
        lat: -6.9175,
        lng: 107.6191,
        sales: 1240,
        growth: 18,
        status: "top",
        alert: false,
    },
    {
        name: "Jakarta Barat",
        lat: -6.1694,
        lng: 106.7898,
        sales: 1030500000,
        growth: 11,
        status: "normal",
        alert: false,
    },
    {
        name: "Jakarta Pusat",
        lat: -6.1944,
        lng: 106.8229,
        sales: 980,
        growth: 9,
        status: "normal",
        alert: false,
    },
    {
        name: "Jakarta Selatan",
        lat: -6.2615,
        lng: 106.8106,
        sales: 920,
        growth: 8,
        status: "normal",
        alert: false,
    },
    {
        name: "Jakarta Timur",
        lat: -6.225,
        lng: 106.9004,
        sales: 890,
        growth: 7,
        status: "normal",
        alert: false,
    },
    {
        name: "Jakarta Utara",
        lat: -6.1384,
        lng: 106.899,
        sales: 850,
        growth: 6,
        status: "normal",
        alert: false,
    },
    {
        name: "Bogor",
        lat: -6.5944,
        lng: 106.7892,
        sales: 750,
        growth: 12,
        status: "normal",
        alert: false,
    },
    {
        name: "Depok",
        lat: -6.4028,
        lng: 106.7942,
        sales: 410300000,
        growth: -9,
        status: "alert",
        alert: true,
    },
    {
        name: "Bekasi",
        lat: -6.2383,
        lng: 106.9756,
        sales: 680,
        growth: 10,
        status: "normal",
        alert: false,
    },
    {
        name: "Cimahi",
        lat: -6.8841,
        lng: 107.5413,
        sales: 320,
        growth: 5,
        status: "normal",
        alert: false,
    },
    {
        name: "Cirebon",
        lat: -6.732,
        lng: 108.5523,
        sales: 450,
        growth: 8,
        status: "normal",
        alert: false,
    },
    {
        name: "Tasikmalaya",
        lat: -7.3274,
        lng: 108.2208,
        sales: 280,
        growth: 4,
        status: "normal",
        alert: false,
    },
    {
        name: "Sukabumi",
        lat: -6.917,
        lng: 106.927,
        sales: 250,
        growth: 3,
        status: "normal",
        alert: false,
    },
    {
        name: "Karawang",
        lat: -6.3227,
        lng: 107.3376,
        sales: 380,
        growth: 7,
        status: "normal",
        alert: false,
    },
    {
        name: "Subang",
        lat: -6.57,
        lng: 107.7633,
        sales: 180,
        growth: 2,
        status: "normal",
        alert: false,
    },

    // Jawa Tengah
    {
        name: "Semarang",
        lat: -6.9667,
        lng: 110.4167,
        sales: 720,
        growth: 13,
        status: "normal",
        alert: false,
    },
    {
        name: "Surakarta",
        lat: -7.5667,
        lng: 110.8167,
        sales: 580,
        growth: 11,
        status: "normal",
        alert: false,
    },
    {
        name: "Yogyakarta",
        lat: -7.7956,
        lng: 110.3694,
        sales: 650,
        growth: 12,
        status: "normal",
        alert: false,
    },
    {
        name: "Magelang",
        lat: -7.4706,
        lng: 110.2178,
        sales: 220,
        growth: 5,
        status: "normal",
        alert: false,
    },
    {
        name: "Pekalongan",
        lat: -6.8883,
        lng: 109.6753,
        sales: 290,
        growth: 6,
        status: "normal",
        alert: false,
    },
    {
        name: "Tegal",
        lat: -6.8667,
        lng: 109.1333,
        sales: 260,
        growth: 4,
        status: "normal",
        alert: false,
    },
    {
        name: "Salatiga",
        lat: -7.3306,
        lng: 110.5081,
        sales: 190,
        growth: 3,
        status: "normal",
        alert: false,
    },
    {
        name: "Purwokerto",
        lat: -7.4214,
        lng: 109.2344,
        sales: 340,
        growth: 8,
        status: "normal",
        alert: false,
    },
    {
        name: "Cilacap",
        lat: -7.7314,
        lng: 109.0108,
        sales: 240,
        growth: 4,
        status: "normal",
        alert: false,
    },
    {
        name: "Klaten",
        lat: -7.7058,
        lng: 110.6064,
        sales: 200,
        growth: 3,
        status: "normal",
        alert: false,
    },

    // Jawa Timur
    {
        name: "Surabaya",
        lat: -7.2504,
        lng: 112.7688,
        sales: 1150,
        growth: 16,
        status: "top",
        alert: false,
    },
    {
        name: "Malang",
        lat: -7.9666,
        lng: 112.6326,
        sales: 680,
        growth: 14,
        status: "normal",
        alert: false,
    },
    {
        name: "Sidoarjo",
        lat: -7.4478,
        lng: 112.7183,
        sales: 520,
        growth: 10,
        status: "normal",
        alert: false,
    },
    {
        name: "Gresik",
        lat: -7.155,
        lng: 112.6561,
        sales: 480,
        growth: 9,
        status: "normal",
        alert: false,
    },
    {
        name: "Mojokerto",
        lat: -7.4706,
        lng: 112.4401,
        sales: 320,
        growth: 7,
        status: "normal",
        alert: false,
    },
    {
        name: "Pasuruan",
        lat: -7.6458,
        lng: 112.9075,
        sales: 280,
        growth: 6,
        status: "normal",
        alert: false,
    },
    {
        name: "Probolinggo",
        lat: -7.7543,
        lng: 113.2159,
        sales: 240,
        growth: 5,
        status: "normal",
        alert: false,
    },
    {
        name: "Kediri",
        lat: -7.8167,
        lng: 112.0167,
        sales: 380,
        growth: 8,
        status: "normal",
        alert: false,
    },
    {
        name: "Blitar",
        lat: -8.1,
        lng: 112.1667,
        sales: 260,
        growth: 6,
        status: "normal",
        alert: false,
    },
    {
        name: "Jember",
        lat: -8.1724,
        lng: 113.7008,
        sales: 420,
        growth: 9,
        status: "normal",
        alert: false,
    },
    {
        name: "Banyuwangi",
        lat: -8.2191,
        lng: 114.3691,
        sales: 220,
        growth: 4,
        status: "normal",
        alert: false,
    },
    {
        name: "Madiun",
        lat: -7.6298,
        lng: 111.5239,
        sales: 300,
        growth: 7,
        status: "normal",
        alert: false,
    },

    // Sumatera
    {
        name: "Medan",
        lat: 3.5952,
        lng: 98.6722,
        sales: 820,
        growth: -12,
        status: "alert",
        alert: true,
    },
    {
        name: "Palembang",
        lat: -2.9761,
        lng: 104.7754,
        sales: 580,
        growth: 10,
        status: "normal",
        alert: false,
    },
    {
        name: "Bandar Lampung",
        lat: -5.4294,
        lng: 105.2625,
        sales: 420,
        growth: 8,
        status: "normal",
        alert: false,
    },
    {
        name: "Pekanbaru",
        lat: 0.5071,
        lng: 101.4478,
        sales: 480,
        growth: 9,
        status: "normal",
        alert: false,
    },
    {
        name: "Padang",
        lat: -0.9492,
        lng: 100.3543,
        sales: 380,
        growth: 7,
        status: "normal",
        alert: false,
    },
    {
        name: "Jambi",
        lat: -1.6099,
        lng: 103.6075,
        sales: 320,
        growth: 6,
        status: "normal",
        alert: false,
    },
    {
        name: "Bengkulu",
        lat: -3.7956,
        lng: 102.2592,
        sales: 180,
        growth: 3,
        status: "normal",
        alert: false,
    },
    {
        name: "Banda Aceh",
        lat: 5.5483,
        lng: 95.3238,
        sales: 240,
        growth: 5,
        status: "normal",
        alert: false,
    },
    {
        name: "Batam",
        lat: 1.0456,
        lng: 104.0305,
        sales: 520,
        growth: 11,
        status: "normal",
        alert: false,
    },
    {
        name: "Pematangsiantar",
        lat: 2.96,
        lng: 99.06,
        sales: 280,
        growth: 6,
        status: "normal",
        alert: false,
    },
    {
        name: "Tanjung Pinang",
        lat: 0.9167,
        lng: 104.45,
        sales: 200,
        growth: 4,
        status: "normal",
        alert: false,
    },

    // Kalimantan
    {
        name: "Banjarmasin",
        lat: -3.3144,
        lng: 114.5925,
        sales: 380,
        growth: 8,
        status: "normal",
        alert: false,
    },
    {
        name: "Pontianak",
        lat: -0.0263,
        lng: 109.3425,
        sales: 420,
        growth: 9,
        status: "normal",
        alert: false,
    },
    {
        name: "Samarinda",
        lat: -0.5021,
        lng: 117.1536,
        sales: 360,
        growth: 7,
        status: "normal",
        alert: false,
    },
    {
        name: "Balikpapan",
        lat: -1.2379,
        lng: 116.8529,
        sales: 480,
        growth: 10,
        status: "normal",
        alert: false,
    },
    {
        name: "Palangkaraya",
        lat: -2.2083,
        lng: 113.9167,
        sales: 220,
        growth: 5,
        status: "normal",
        alert: false,
    },
    {
        name: "Tarakan",
        lat: 3.3,
        lng: 117.6333,
        sales: 180,
        growth: 3,
        status: "normal",
        alert: false,
    },
    {
        name: "Bontang",
        lat: 0.1333,
        lng: 117.5,
        sales: 160,
        growth: 2,
        status: "normal",
        alert: false,
    },

    // Sulawesi
    {
        name: "Makassar",
        lat: -5.1477,
        lng: 119.4327,
        sales: 620,
        growth: 12,
        status: "normal",
        alert: false,
    },
    {
        name: "Manado",
        lat: 1.4748,
        lng: 124.8421,
        sales: 380,
        growth: 8,
        status: "normal",
        alert: false,
    },
    {
        name: "Palu",
        lat: -0.9,
        lng: 119.8667,
        sales: 240,
        growth: 5,
        status: "normal",
        alert: false,
    },
    {
        name: "Kendari",
        lat: -3.9985,
        lng: 122.513,
        sales: 280,
        growth: 6,
        status: "normal",
        alert: false,
    },
    {
        name: "Gorontalo",
        lat: 0.5333,
        lng: 123.0667,
        sales: 180,
        growth: 3,
        status: "normal",
        alert: false,
    },
    {
        name: "Parepare",
        lat: -4.0167,
        lng: 119.6167,
        sales: 200,
        growth: 4,
        status: "normal",
        alert: false,
    },
    {
        name: "Bitung",
        lat: 1.4453,
        lng: 125.1824,
        sales: 160,
        growth: 2,
        status: "normal",
        alert: false,
    },

    // Bali & Nusa Tenggara
    {
        name: "Denpasar",
        lat: -8.6705,
        lng: 115.2126,
        sales: 520,
        growth: 11,
        status: "normal",
        alert: false,
    },
    {
        name: "Mataram",
        lat: -8.5833,
        lng: 116.1167,
        sales: 240,
        growth: 6,
        status: "normal",
        alert: false,
    },
    {
        name: "Kupang",
        lat: -10.1833,
        lng: 123.5833,
        sales: 280,
        growth: 7,
        status: "normal",
        alert: false,
    },
    {
        name: "Singaraja",
        lat: -8.1167,
        lng: 115.0833,
        sales: 180,
        growth: 3,
        status: "normal",
        alert: false,
    },

    // Papua
    {
        name: "Jayapura",
        lat: -2.5333,
        lng: 140.7167,
        sales: 220,
        growth: 5,
        status: "normal",
        alert: false,
    },
    {
        name: "Sorong",
        lat: -0.8667,
        lng: 131.25,
        sales: 180,
        growth: 3,
        status: "normal",
        alert: false,
    },
    {
        name: "Merauke",
        lat: -8.4667,
        lng: 140.3333,
        sales: 120,
        growth: 1,
        status: "normal",
        alert: false,
    },
    {
        name: "Biak",
        lat: -1.1833,
        lng: 136.0833,
        sales: 140,
        growth: 2,
        status: "normal",
        alert: false,
    },

    // Tambahan untuk mencapai 80
    {
        name: "Cabang X",
        lat: -6.2,
        lng: 106.8167,
        sales: 120,
        growth: -21,
        status: "alert",
        alert: true,
    },
    {
        name: "Cianjur",
        lat: -6.8167,
        lng: 107.1333,
        sales: 160,
        growth: 2,
        status: "normal",
        alert: false,
    },
    {
        name: "Garut",
        lat: -7.2167,
        lng: 107.9,
        sales: 2,
        growth: 4,
        status: "normal",
        alert: false,
    },
    {
        name: "Sumedang",
        lat: -6.85,
        lng: 107.9167,
        sales: 180,
        growth: 3,
        status: "normal",
        alert: false,
    },
    {
        name: "Indramayu",
        lat: -6.3333,
        lng: 108.3167,
        sales: 220,
        growth: 5,
        status: "normal",
        alert: false,
    },
];

// Marker groups
const markers = L.markerClusterGroup({
    chunkedLoading: true,
    spiderfyOnMaxZoom: true,
    showCoverageOnHover: false,
    zoomToBoundsOnClick: true,
});

// Function untuk menentukan warna marker berdasarkan status
function getMarkerColor(status, alert) {
    if (alert) return "red";
    if (status === "top") return "green";
    return "blue";
}

// Function untuk membuat custom icon
function createCustomIcon(cabang) {
    const color = getMarkerColor(cabang.status, cabang.alert);
    return L.divIcon({
        className: "custom-marker",
        html: `<div style="background-color: ${color}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
        iconSize: [20, 20],
        iconAnchor: [10, 10],
    });
}

// Tambahkan marker untuk setiap cabang
cabangData.forEach((cabang) => {
    const marker = L.marker([cabang.lat, cabang.lng], {
        icon: createCustomIcon(cabang),
    });

    const growthIcon = cabang.growth >= 0 ? "↑" : "↓";
    const growthColor = cabang.growth >= 0 ? "green" : "red";
    const statusBadge =
        cabang.status === "top"
            ? '<span class="badge bg-success">Top Performer</span>'
            : cabang.alert
            ? '<span class="badge bg-danger">Bermasalah</span>'
            : '<span class="badge bg-primary">Normal</span>';

    marker.bindPopup(`
          <div style="min-width: 200px;">
            <h6 style="margin: 0 0 8px 0; font-weight: 600;">${cabang.name}</h6>
            ${statusBadge}
            <hr style="margin: 8px 0;">
            <div style="font-size: 12px;">
              <p style="margin: 4px 0;"><strong>Penjualan:</strong> ${cabang.sales.toLocaleString(
                  "id-ID"
              )}</p>
              <p style="margin: 4px 0;"><strong>Growth:</strong> <span style="color: ${growthColor};">${growthIcon} ${Math.abs(
        cabang.growth
    )}%</span></p>
            </div>
            <a href="analytics-cabang.html" style="font-size: 11px; text-decoration: none;">Lihat detail →</a>
          </div>
        `);

    markers.addLayer(marker);
});

map.addLayer(markers);

// Filter functionality
document.querySelectorAll("[data-filter]").forEach((btn) => {
    btn.addEventListener("click", function () {
        document
            .querySelectorAll("[data-filter]")
            .forEach((b) => b.classList.remove("active"));
        this.classList.add("active");

        const filter = this.getAttribute("data-filter");
        markers.clearLayers();

        let filteredData = cabangData;
        if (filter === "top") {
            filteredData = cabangData
                .filter((c) => c.status === "top")
                .slice(0, 10);
        } else if (filter === "alert") {
            filteredData = cabangData.filter((c) => c.alert);
        }

        filteredData.forEach((cabang) => {
            const marker = L.marker([cabang.lat, cabang.lng], {
                icon: createCustomIcon(cabang),
            });

            const growthIcon = cabang.growth >= 0 ? "↑" : "↓";
            const growthColor = cabang.growth >= 0 ? "green" : "red";
            const statusBadge =
                cabang.status === "top"
                    ? '<span class="badge bg-success">Top Performer</span>'
                    : cabang.alert
                    ? '<span class="badge bg-danger">Bermasalah</span>'
                    : '<span class="badge bg-primary">Normal</span>';

            marker.bindPopup(`
              <div style="min-width: 200px;">
                <h6 style="margin: 0 0 8px 0; font-weight: 600;">${
                    cabang.name
                }</h6>
                ${statusBadge}
                <hr style="margin: 8px 0;">
                <div style="font-size: 12px;">
                  <p style="margin: 4px 0;"><strong>Penjualan:</strong> ${cabang.sales.toLocaleString(
                      "id-ID"
                  )}</p>
                  <p style="margin: 4px 0;"><strong>Growth:</strong> <span style="color: ${growthColor};">${growthIcon} ${Math.abs(
                cabang.growth
            )}%</span></p>
                </div>
                <a href="analytics-cabang.html" style="font-size: 11px; text-decoration: none;">Lihat detail →</a>
              </div>
            `);

            markers.addLayer(marker);
        });
    });
});

// Zoom to all markers
document.getElementById("zoomToAll").addEventListener("click", function () {
    map.fitBounds(markers.getBounds(), { padding: [50, 50] });
});

// Legend
const legend = L.control({ position: "bottomright" });
legend.onAdd = function (map) {
    const div = L.DomUtil.create("div", "legend");
    div.style.backgroundColor = "white";
    div.style.padding = "10px";
    div.style.borderRadius = "5px";
    div.style.boxShadow = "0 2px 4px rgba(0,0,0,0.2)";
    div.innerHTML = `
          <strong style="display: block; margin-bottom: 8px;">Legenda:</strong>
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <div style="width: 16px; height: 16px; background-color: green; border-radius: 50%; border: 2px solid white; margin-right: 8px;"></div>
            <span style="font-size: 12px;">Top Performer</span>
          </div>
          <div style="display: flex; align-items: center; margin-bottom: 4px;">
            <div style="width: 16px; height: 16px; background-color: blue; border-radius: 50%; border: 2px solid white; margin-right: 8px;"></div>
            <span style="font-size: 12px;">Normal</span>
          </div>
          <div style="display: flex; align-items: center;">
            <div style="width: 16px; height: 16px; background-color: red; border-radius: 50%; border: 2px solid white; margin-right: 8px;"></div>
            <span style="font-size: 12px;">Bermasalah</span>
          </div>
        `;
    return div;
};
legend.addTo(map);
