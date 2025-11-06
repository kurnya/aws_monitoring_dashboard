<div class="max-w-6xl mx-auto px-6 mt-10 mb-20">
  <h2 class="text-2xl font-bold mb-6 text-gray-800">Historical Data (24 Jam)</h2>

  <div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-col md:flex-row items-start md:items-center gap-3">
    <div class="flex items-center gap-2">
      <label for="tanggal" class="font-medium text-gray-700">Tanggal:</label>
      <input id="tanggal" type="date" class="p-2 border border-gray-300 rounded-lg focus:ring focus:ring-blue-200">
    </div>

    <div class="flex items-center gap-2">
      <label for="interval" class="font-medium text-gray-700">Interval:</label>
      <select id="interval" class="p-2 border border-gray-300 rounded-lg focus:ring focus:ring-blue-200">
        <option value="60" selected>Per 1 Jam</option>
        <option value="10">Per 10 Menit</option>
      </select>
    </div>

    <button id="filterBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
      Tampilkan Data
    </button>
  </div>

  <div id="chartContainer" class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>
  <p id="error-message" class="text-red-500 text-sm mt-4 hidden"></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(async () => {
  const container = document.getElementById('chartContainer');
  const errorMessage = document.getElementById('error-message');
  const tanggalInput = document.getElementById('tanggal');
  const intervalSelect = document.getElementById('interval');
  const filterBtn = document.getElementById('filterBtn');
  let allData = [];

  const today = new Date();
  const todayStr = today.toISOString().split('T')[0];
  tanggalInput.value = todayStr;

  async function fetchData(tanggal) {
    const res = await fetch(`${window.location.origin}/api/historical?tanggal=${tanggal}`);
    if (!res.ok) throw new Error('Gagal mengambil data dari server');
    const rawData = await res.json();

    if (!Array.isArray(rawData) || rawData.length === 0)
      throw new Error('Tidak ada data yang tersedia.');

    allData = rawData.map(d => {
      let waktu;
      const timeField = d.created_at || d.waktu;
      if (timeField) {
        const [datePart, timePart] = timeField.split(" ");
        const [year, month, day] = datePart.split("-").map(Number);
        const [hour, minute, second] = timePart.split(":").map(Number);
        waktu = new Date(year, month - 1, day, hour, minute, second);
      } else {
        waktu = new Date();
      }
      return { ...d, waktu };
    });
  }

  try {
    await fetchData(todayStr);
    renderCharts(new Date(todayStr), 60);
  } catch (err) {
    errorMessage.textContent = err.message;
    errorMessage.classList.remove('hidden');
  }

  filterBtn.addEventListener('click', async () => {
    const selectedDateStr = tanggalInput.value;
    const selectedInterval = parseInt(intervalSelect.value);
    if (!selectedDateStr) return alert("Pilih tanggal terlebih dahulu");

    try {
      await fetchData(selectedDateStr);
      renderCharts(new Date(selectedDateStr), selectedInterval);
    } catch (err) {
      errorMessage.textContent = err.message;
      errorMessage.classList.remove('hidden');
    }
  });

  function renderCharts(selectedDate, intervalMinutes) {
    container.innerHTML = '';
    errorMessage.classList.add('hidden');

    const filtered = allData.filter(d => {
      const localDate = d.waktu.toLocaleDateString('id-ID', { timeZone: 'Asia/Jakarta' });
      const selectedLocal = selectedDate.toLocaleDateString('id-ID', { timeZone: 'Asia/Jakarta' });
      return localDate === selectedLocal;
    });

    if (filtered.length === 0) {
      errorMessage.textContent = 'Tidak ada data untuk tanggal ini.';
      errorMessage.classList.remove('hidden');
      return;
    }

    const fieldLabels = {
      windspeed: { label: "Kecepatan Angin (m/s)", icon: "💨" },
      winddir: { label: "Arah Angin (°)", icon: "🧭" },
      temp: { label: "Suhu Udara (°C)", icon: "🌡️" },
      rh: { label: "Kelembapan Relatif (%)", icon: "💧" },
      pressure: { label: "Tekanan Udara (hPa)", icon: "⚙️" },
      rain: { label: "Curah Hujan (mm)", icon: "☔" },
      solrad: { label: "Radiasi Matahari (W/m²)", icon: "☀️" },
      netrad: { label: "Radiasi Bersih (W/m²)", icon: "🔆" },
      watertemp: { label: "Suhu Air (°C)", icon: "🌊" },
      waterlevel: { label: "Tinggi Muka Air (m)", icon: "📏" }
    };

    const aggregated = aggregateData(filtered, intervalMinutes);

    Object.entries(fieldLabels).forEach(([key, { label, icon }]) => {
      const card = document.createElement('div');
      card.className = 'bg-white rounded-xl shadow p-4 relative hover:shadow-lg transition-all';

      const title = document.createElement('h3');
      title.className = 'text-lg font-semibold text-gray-800 mb-3';
      title.textContent = `${label} (${intervalMinutes === 60 ? "Per Jam" : "Per 10 Menit"})`;

      const btn = document.createElement('button');
      btn.innerHTML = '⤢';
      btn.className = 'absolute top-3 right-3 text-gray-500 hover:text-blue-600';
      btn.title = 'Perbesar Grafik';

      const canvas = document.createElement('canvas');
      canvas.height = 120;

      card.appendChild(title);
      card.appendChild(btn);
      card.appendChild(canvas);
      container.appendChild(card);

      const pointIcon = new Image(28, 28);
      const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28">
          <text x="4" y="22" font-size="22">${icon}</text>
        </svg>`;
      pointIcon.src = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);

      const ctx = canvas.getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: aggregated.map(a => a.time),
          datasets: [{
            label: label,
            data: aggregated.map(a => a[key]),
            borderWidth: 2,
            borderColor: '#2563eb',
            tension: 0.3,
            pointStyle: pointIcon,
            pointRadius: 8,
            pointHoverRadius: 10
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: ctx => `${icon} ${ctx.dataset.label}: ${ctx.formattedValue}`
              }
            }
          },
          scales: {
            x: { title: { display: true, text: 'Waktu (WIB)' } },
            y: { title: { display: true, text: label }, beginAtZero: true }
          }
        }
      });

      btn.addEventListener('click', () => {
        if (!document.fullscreenElement) card.requestFullscreen();
        else document.exitFullscreen();
      });
    });
  }

  function aggregateData(data, intervalMinutes) {
    data.sort((a, b) => a.waktu - b.waktu);
    const grouped = [];
    const step = intervalMinutes * 60 * 1000;
    let bucketStart = new Date(data[0].waktu);
    bucketStart.setMinutes(Math.floor(bucketStart.getMinutes() / intervalMinutes) * intervalMinutes, 0, 0);

    while (bucketStart <= data[data.length - 1].waktu) {
      const bucketEnd = new Date(bucketStart.getTime() + step);
      const bucketData = data.filter(d => d.waktu >= bucketStart && d.waktu < bucketEnd);

      if (bucketData.length > 0) {
        const averaged = { time: bucketStart.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) };
        Object.keys(bucketData[0]).forEach(k => {
          if (typeof bucketData[0][k] === 'number') {
            averaged[k] = bucketData.reduce((sum, d) => sum + (d[k] || 0), 0) / bucketData.length;
          }
        });
        grouped.push(averaged);
      }
      bucketStart = bucketEnd;
    }
    return grouped;
  }
})();
</script>
