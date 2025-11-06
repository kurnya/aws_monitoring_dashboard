<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AWS Monitoring - BMKG</title>
  @vite('resources/js/app.js')
  <style>
    .compass-needle {
      transition: transform 0.6s ease-in-out;
      transform-origin: 50% 50%;
    }
  </style>
</head>

<body class="bg-gradient-to-b from-white to-gray-50 min-h-screen text-gray-800">
  @include('components.appbar')
  @include('components.navbar')

  <main id="main-content" class="max-w-7xl mx-auto px-6 py-10 mb-10">
    <div id="cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <div class="col-span-full text-center text-gray-500">Memuat data...</div>
    </div>
  </main>
  @include('components.footer')


  <script>
    let fetchInterval = null; 
    let latestDataCache = null;
    let rainHistory = []; 
    let rainStatus = "Tidak Hujan"; 

    const icons = {
      temp: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14a4 4 0 108 0V5a2 2 0 10-4 0v9a4 4 0 01-4 4z" /></svg>`,
      windspeed: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h18M3 6h13M3 18h9" /></svg>`,
      winddir: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19V6m0 0l-5 5m5-5l5 5" /></svg>`,
      rh: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22a5 5 0 005-5c0-2.5-5-9-5-9s-5 6.5-5 9a5 5 0 005 5z" /></svg>`,
      pressure: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12l3-3" /></svg>`,
      rain: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 13a4 4 0 00-8 0v1H5a2 2 0 100 4h14a2 2 0 100-4h-3v-1z" /></svg>`,
      solrad: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.364-7.364l-1.414 1.414M6.05 17.95l-1.414 1.414M17.95 17.95l-1.414-1.414M6.05 6.05L4.636 7.464" /></svg>`,
      netrad: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-yellow-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2m10-10h-2M4 12H2" /></svg>`,
      watertemp: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22a8 8 0 01-8-8c0-4 8-12 8-12s8 8 8 12a8 8 0 01-8 8z" /></svg>`,
      waterlevel: `<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 20s2-2 4-2 4 2 4 2 2-2 4-2 4 2 4 2V4H4v16z" /></svg>`
    };

    const fieldLabels = {
      waktu: "Waktu Pengamatan",
      windspeed: "Kecepatan Angin (m/s)",
      winddir: "Arah Angin (°)",
      temp: "Suhu Udara (°C)",
      rh: "Kelembapan Relatif (%)",
      pressure: "Tekanan Udara (hPa)",
      rain: "Curah Hujan (mm)",
      solrad: "Radiasi Matahari (W/m²)",
      netrad: "Radiasi Bersih (W/m²)",
      watertemp: "Suhu Air (°C)",
      waterlevel: "Tinggi Muka Air (m)"
    };

    const excludedFields = ['idaws', 'waktu', 'ta_min', 'ta_max', 'pancilevel', 'pancitemp','rain_status'];

    function arahDerajatToText(deg) {
      const arah = ["U", "UT", "T", "ST", "S", "SB", "B", "UB"];
      const index = Math.round(((deg % 360) / 45)) % 8;
      return arah[index];
    }

    function renderCards(data) {
      const container = document.getElementById('cards');
      if (!data) {
        container.innerHTML = '<div class="col-span-full text-center text-red-500">Gagal memuat data.</div>';
        return;
      }

      const hist = data.hist || {};
      const filteredEntries = Object.entries(data).filter(([key]) =>
        !excludedFields.includes(key) && key !== 'hist'
      );

      container.className = "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6";

      container.innerHTML = filteredEntries.map(([key, value]) => {
        const val = Number(value);
        const max = hist[`max_${key}`];
        const min = hist[`min_${key}`];
        const avg = hist[`avg_${key}`];

        let status = "Normal";
        let color = "bg-gray-100 text-gray-600";
        if (key === "temp") {
          if (val < 23) [status, color] = ["Dingin", "bg-blue-100 text-blue-700"];
          else if (val <= 31) [status, color] = ["Normal", "bg-green-100 text-green-700"];
          else [status, color] = ["Panas", "bg-orange-100 text-orange-700"];
        } else if (key === "rh") {
          if (val < 60) [status, color] = ["Kering", "bg-yellow-100 text-yellow-700"];
          else if (val <= 85) [status, color] = ["Normal", "bg-green-100 text-green-700"];
          else [status, color] = ["Lembap", "bg-blue-100 text-blue-700"];
        } else if (key === "windspeed") {
          if (val < 0.5) [status, color] = ["Tenang", "bg-gray-100 text-gray-600"];
          else if (val <= 3) [status, color] = ["Berangin", "bg-green-100 text-green-700"];
          else if (val <= 7) [status, color] = ["Sejuk", "bg-yellow-100 text-yellow-700"];
          else [status, color] = ["Kencang", "bg-red-100 text-red-700"];
        } else if (key === "rain") {
          const rainStatusNow = data.rain_status || "Tidak Hujan";
          if (rainStatusNow === "Hujan Lebat") [status, color] = ["Lebat", "bg-red-100 text-red-700"];
          else if (rainStatusNow === "Hujan Sedang") [status, color] = ["Sedang", "bg-blue-200 text-blue-800"];
          else if (rainStatusNow === "Gerimis") [status, color] = ["Gerimis", "bg-blue-100 text-blue-700"];
          else [status, color] = ["Tidak Hujan", "bg-gray-100 text-gray-600"];
        }
          else if (key === "pressure") {
          if (val < 1008) [status, color] = ["Rendah", "bg-yellow-100 text-yellow-700"];
          else if (val <= 1016) [status, color] = ["Normal", "bg-green-100 text-green-700"];
          else [status, color] = ["Tinggi", "bg-blue-100 text-blue-700"];
        } else if (key === "solrad" || key === "netrad") {
          if (val < 200) [status, color] = ["Rendah", "bg-blue-100 text-blue-700"];
          else if (val <= 600) [status, color] = ["Sedang", "bg-green-100 text-green-700"];
          else [status, color] = ["Tinggi", "bg-orange-100 text-orange-700"];
        } else if (key === "watertemp") {
          if (val < 24) [status, color] = ["Dingin", "bg-blue-100 text-blue-700"];
          else if (val <= 30) [status, color] = ["Normal", "bg-green-100 text-green-700"];
          else [status, color] = ["Hangat", "bg-orange-100 text-orange-700"];
        } else if (key === "waterlevel") {
          if (val < 2) [status, color] = ["Rendah", "bg-gray-100 text-gray-600"];
          else if (val <= 2.4) [status, color] = ["Normal", "bg-green-100 text-green-700"];
          else [status, color] = ["Tinggi", "bg-red-100 text-red-700"];
        }

        if (key === "winddir") {
          const arahText = arahDerajatToText(val);
          return `
            <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-md hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  ${icons[key] || ''}
                  <h2 class="font-semibold text-gray-700 text-sm">${fieldLabels[key] ?? key}</h2>
                </div>
                <span class="px-2.5 py-0.5 text-xs font-medium rounded-md bg-indigo-100 text-indigo-700 shadow-sm">
                  ${val.toFixed(0)}° • ${arahText}
                </span>
              </div>
              <div class="relative w-40 h-40 mx-auto mt-2">
                <svg viewBox="0 0 100 100" class="w-full h-full">
                  <circle cx="50" cy="50" r="45" stroke="#CBD5E1" stroke-width="0.8" fill="none"/>
                  <circle cx="50" cy="50" r="30" stroke="#E2E8F0" stroke-width="0.6" fill="none"/>
                  <circle cx="50" cy="50" r="15" stroke="#E2E8F0" stroke-width="0.6" fill="none"/>
                  <line x1="50" y1="5" x2="50" y2="95" stroke="#CBD5E1" stroke-width="0.6"/>
                  <line x1="5" y1="50" x2="95" y2="50" stroke="#CBD5E1" stroke-width="0.6"/>
                  <line x1="15" y1="15" x2="85" y2="85" stroke="#E2E8F0" stroke-width="0.5"/>
                  <line x1="15" y1="85" x2="85" y2="15" stroke="#E2E8F0" stroke-width="0.5"/>
                  <text x="50" y="8" text-anchor="middle" font-size="7" fill="#334155" font-weight="bold">U</text>
                  <text x="50" y="97" text-anchor="middle" font-size="7" fill="#334155" font-weight="bold">S</text>
                  <text x="92" y="53" text-anchor="middle" font-size="7" fill="#334155" font-weight="bold">T</text>
                  <text x="8" y="53" text-anchor="middle" font-size="7" fill="#334155" font-weight="bold">B</text>
                  <text x="75" y="20" text-anchor="middle" font-size="6" fill="#64748B">UT</text>
                  <text x="75" y="84" text-anchor="middle" font-size="6" fill="#64748B">ST</text>
                  <text x="25" y="84" text-anchor="middle" font-size="6" fill="#64748B">SB</text>
                  <text x="25" y="20" text-anchor="middle" font-size="6" fill="#64748B">UB</text>
                  <g class="compass-needle" style="transform: rotate(${val}deg); transform-origin: 50% 50%;">
                    <polygon points="50,15 46,50 54,50" fill="#4F46E5"></polygon>
                    <circle cx="50" cy="50" r="2.5" fill="#1E3A8A"/>
                  </g>
                </svg>
              </div>
            </div>`;
        }

        return `
          <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-md hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">${icons[key] || ''}<h2 class="font-semibold text-gray-700 text-sm">${fieldLabels[key] ?? key}</h2></div>
              <span class="px-2 py-0.5 text-xs font-medium rounded-md ${color}">${status}</span>
            </div>
            <div class="text-center mt-3">
              <p class="text-4xl font-extrabold text-gray-900">${val.toFixed(1)}</p>
              <p class="text-sm text-gray-500 -mt-1">${(fieldLabels[key] || '').match(/\((.*?)\)/)?.[1] || ''}</p>
            </div>
            <div class="flex justify-center items-center gap-6 mt-4 text-xs">
              <div class="text-blue-600 font-semibold">${Number(min ?? 0).toFixed(1)}</div>
              <div class="text-green-600 font-semibold">${Number(avg ?? 0).toFixed(1)}</div>
              <div class="text-red-600 font-semibold">${Number(max ?? 0).toFixed(1)}</div>
            </div>
            <div class="flex justify-center gap-6 text-[10px] text-gray-500 mt-1">
              <span>Min 24h</span><span>Avg 24h</span><span>Max 24h</span>
            </div>
          </div>`;
      }).join('');
    }

    async function fetchLatest() {
      try {
        const res = await fetch('/api/latest');
        if (!res.ok) throw new Error('Gagal fetch data');
        const json = await res.json();

        const currentRain = Number(json.rain ?? 0);
        const now = Date.now();

        rainHistory.push({ time: now, rain: currentRain });

        rainHistory = rainHistory.filter(d => now - d.time <= 5 * 60 * 1000);

        const twoMinAgo = rainHistory.find(d => now - d.time >= 2 * 60 * 1000);
        const fiveMinAgo = rainHistory.find(d => now - d.time >= 5 * 60 * 1000);

        if (twoMinAgo) {
          const delta2min = currentRain - twoMinAgo.rain;

          if (delta2min >= 5) rainStatus = "Hujan Lebat";
          else if (delta2min >= 2) rainStatus = "Hujan Sedang";
          else if (delta2min >= 1) rainStatus = "Gerimis";
          else if (fiveMinAgo && currentRain === fiveMinAgo.rain) rainStatus = "Tidak Hujan";
        }

        json.rain_status = rainStatus;
        latestDataCache = json;

        renderCards(json);
      } catch (err) {
        console.error(err);
        renderCards(latestDataCache);
      }
    }


    async function loadPage(page) {
      const main = document.getElementById('main-content');
      const navButtons = document.querySelectorAll('nav button');

      if (fetchInterval) {
        clearInterval(fetchInterval);
        fetchInterval = null;
      }

      navButtons.forEach(btn => {
        btn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
        btn.classList.add('text-gray-500');
      });

      const activeButton = document.getElementById(`nav-${page}`);
      if (activeButton) activeButton.classList.add('bg-white', 'text-gray-900', 'shadow-sm');

      if (page === 'current') {
        main.innerHTML = `
          <div id="cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="col-span-full text-center text-gray-500">Memuat data...</div>
          </div>`;
        fetchLatest();
        fetchInterval = setInterval(fetchLatest, 5000);
        return;
      }

      main.innerHTML = `<div class="text-center text-gray-500 py-20 animate-pulse">Memuat halaman ${page}...</div>`;

      try {
        let url = '/';
        if (page === 'historical') url = '/historical';
        else {
          main.innerHTML = `<div class="text-center text-gray-500 py-20">Halaman ${page} masih dikembangkan.</div>`;
          return;
        }

        const response = await fetch(url);
        const html = await response.text();

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const content = doc.querySelector('#main-content');
        main.innerHTML = content ? content.innerHTML : html;

        const scripts = doc.querySelectorAll('script');
        scripts.forEach(oldScript => {
          const newScript = document.createElement('script');
          if (oldScript.src) newScript.src = oldScript.src;
          else newScript.textContent = oldScript.textContent;
          document.body.appendChild(newScript);
        });

      } catch (err) {
        console.error(err);
        main.innerHTML = `<div class="text-red-500 text-center py-20">Gagal memuat halaman ${page}</div>`;
      }
    }

    fetchLatest();
    fetchInterval = setInterval(fetchLatest, 5000);
  </script>
</body>
</html>
