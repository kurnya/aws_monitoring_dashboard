<header class="bg-white shadow-md">
  <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
    <div class="flex items-center gap-4">
      <img src="{{ asset('images/bmkg.png') }}" alt="BMKG" class="h-15 w-12">
      <div>
        <h1 class="text-xl font-bold text-gray-900">BADAN METEOROLOGI KLIMATOLOGI DAN GEOFISIKA</h1>
        <p class="text-sm text-gray-600 -mt-1">Automatic Weather Station - System Online</p>
        <a href="http://202.90.199.132/aws-new/monitoring/3000000011" class="text-blue-600 text-sm hover:underline">AWS Maritim Pontianak</a>
      </div>
    </div>
    <div class="text-right">
      <div id="main-time" class="text-lg font-semibold text-gray-900">-</div>
      <div id="local-time" class="text-sm text-gray-600">-</div>
    </div>
  </div>

  <script>
    let zonaWaktu = "WIB";

    function getOffsetByZone(zone) {
      switch (zone) {
        case "WITA": return 8;
        case "WIT": return 9;
        case "UTC": return 0;
        default: return 7;
      }
    }

    async function updateAppbarTime() {
      try {
        const res = await fetch('/api/latest');
        const data = await res.json();

        if (data.waktu) {
          const utcDate = new Date(data.waktu.replace(" ", "T") + "Z");
          const offsetHours = getOffsetByZone(zonaWaktu);
          const utcMillis = utcDate.getTime() + offsetHours * 60 * 60 * 1000;
          const localDate = new Date(utcMillis);

          const hari = localDate.toLocaleDateString("id-ID", { weekday: "long", timeZone: "UTC" });
          const tanggal = localDate.toLocaleDateString("id-ID", { day: "2-digit", month: "long", year: "numeric", timeZone: "UTC" });
          const jamLocal = localDate.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit", timeZone: "UTC" }).replace(":", ".");
          const jamUTC = utcDate.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit", timeZone: "UTC" }).replace(":", ".");

          document.getElementById("main-time").innerText = `${hari}, ${tanggal} ${jamLocal} ${zonaWaktu}`;
          document.getElementById("local-time").innerText = `(UTC: ${jamUTC})`;
        }
      } catch {
        console.error("Gagal update waktu appbar");
      }
    }
    updateAppbarTime();
    setInterval(updateAppbarTime, 1000);
  </script>
</header>
