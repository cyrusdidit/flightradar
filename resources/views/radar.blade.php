<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FlightRadar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .topbar { height: 64px; display:flex; align-items:center; padding:0 16px; font-family: system-ui; }
        .badge { margin-left: 12px; font-size: 12px; opacity: .75; }

        #side-panel {
            position: fixed;
            top: 64px;
            left: 0;
            width: 320px;
            height: calc(100vh - 64px);
            background: #fff;
            border-right: 1px solid #ddd;
            box-shadow: 4px 0 12px rgba(0,0,0,.1);
            transform: translateX(-100%);
            transition: transform .25s ease;
            font-family: system-ui;
            z-index: 1000;
        }

        #side-panel.open {
            transform: translateX(0);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .panel-body {
            padding: 12px;
            font-size: 14px;
        }

        .panel-body div {
            margin-bottom: 8px;
        }

        #panel-close {
            border: none;
            background: none;
            font-size: 18px;
            cursor: pointer;
        }

    </style>
</head>
<body>
    <div class="topbar">
        <strong>Radar Lite</strong>
        <span class="badge" id="status">Loading…</span>
    </div>
    <div id="map"></div>



        <div id="side-panel">
        <div class="panel-header">
            <strong id="panel-callsign">Flight</strong>
            <button id="panel-close">✕</button>
        </div>
        <div class="panel-body">
            <div><b>ICAO:</b> <span id="panel-icao"></span></div>
            <div><b>Altitude:</b> <span id="panel-altitude"></span></div>
            <div><b>Speed:</b> <span id="panel-speed"></span></div>
            <div><b>Heading:</b> <span id="panel-heading"></span></div>
            <div><b>On ground:</b> <span id="panel-ground"></span></div>
            <div><b>Last contact:</b> <span id="panel-last"></span></div>
        </div>
    </div>


    <script type="module">
      let selectedIcao = null;
        const map = L.map('map').setView([56.95, 24.1], 6); // Lvish default
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 18,
          attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        // Plane icon & rotatw
        function planeIcon(heading, selected = false) {
        const size = selected ? 26 : 18;
        //HIGHLIGHTS SAID PLANE
        return L.divIcon({
            className: '',
            html: `
              <div style="
                font-size: ${size}px;
                transform: rotate(${heading || 0}deg);
                transform-origin: center;
                filter: ${selected ? 'drop-shadow(0 0 6px #3b82f6)' : 'none'};
              ">
                ✈️
              </div>
            `,
            iconSize: [size, size],
            iconAnchor: [size / 2, size / 2],
        });
    }


      const panel = document.getElementById('side-panel');

      function openPanel(p) {
          document.getElementById('panel-callsign').textContent = p.callsign || 'Unknown';
          document.getElementById('panel-icao').textContent = p.icao24;
          document.getElementById('panel-altitude').textContent =
              `${p.altitude_m ?? '—'} m (${p.altitude_ft ?? '—'} ft)`;
          document.getElementById('panel-speed').textContent =
              `${p.velocity_ms ?? '—'} m/s (${p.velocity_kts ?? '—'} kt)`;
          document.getElementById('panel-heading').textContent =
              `${p.heading_deg ?? '—'}°`;
          document.getElementById('panel-ground').textContent =
              p.on_ground ? 'Yes' : 'No';
          document.getElementById('panel-last').textContent =
              p.last_contact
                  ? new Date(p.last_contact * 1000).toLocaleTimeString()
                  : '—';

          panel.classList.add('open');
      }

          document.getElementById('panel-close').onclick = () => {
              panel.classList.remove('open');
              selectedIcao = null;
          };

          map.on('click', () => {
              panel.classList.remove('open');
              selectedIcao = null;
          });


        const markers = new Map(); 

        function msToKts(ms) { return ms == null ? null : (ms * 1.943844).toFixed(0); } // meters/sec to knots
        function mToFt(m) { return m == null ? null : (m * 3.28084).toFixed(0); }





       async function fetchPlanes() {
    const status = document.getElementById('status');

    try {
        const res = await fetch('/api/planes');
        const data = await res.json();

        status.textContent =
            `Updated: ${new Date(data.time * 1000).toLocaleTimeString()} • Planes: ${data.planes.length}`;

        const seen = new Set();

        for (const p of data.planes) {
            const key = p.icao24;
            seen.add(key);

            const lat = p.latitude;
            const lon = p.longitude;

            if (lat == null || lon == null) continue;

            if (!markers.has(key)) {
                const marker = L.marker(
                    [lat, lon],
                    { icon: planeIcon(p.heading_deg, p.icao24 === selectedIcao) }
                ).addTo(map);

                marker.bindTooltip(((p.callsign || '').trim() || key));

              // opens panel & pans to said plane 

                marker.on('click', () => {
                    selectedIcao = p.icao24;
                    openPanel(p);

                    map.flyTo(
                        [p.latitude, p.longitude],
                        Math.max(map.getZoom(), 11),
                        { duration: 0.6 }
                    );
                });

                markers.set(key, marker);
            } else {
                const marker = markers.get(key);
                marker.setLatLng([lat, lon]);
                marker.setIcon(
                    planeIcon(p.heading_deg, p.icao24 === selectedIcao)
                );
            }
        }

        // Byebye planes that disappear
        for (const [key, marker] of markers.entries()) {
            if (!seen.has(key)) {
                map.removeLayer(marker);
                markers.delete(key);
            }
        }

    } catch (e) {
        status.textContent = 'Error loading planes';
        console.error(e);
    }
}


        //Interval!!!!!!!!!!! initial + poll every 5 seconds
        fetchPlanes();
        setInterval(fetchPlanes, 5000);
    </script>
</body>
</html>
