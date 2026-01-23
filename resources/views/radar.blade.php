<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Radar Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .topbar { height: 64px; display:flex; align-items:center; padding:0 16px; font-family: system-ui; }
        .badge { margin-left: 12px; font-size: 12px; opacity: .75; }
    </style>
</head>
<body>
    <div class="topbar">
        <strong>Radar Lite</strong>
        <span class="badge" id="status">Loading…</span>
    </div>
    <div id="map"></div>

    <script type="module">
        const map = L.map('map').setView([56.95, 24.1], 6); // Latvia-ish default
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 18,
          attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        // Plane icon (simple triangle/plane look). You can swap later for PNG/SVG.
        function planeIcon(heading) {
          // Use a div icon rotated by heading degrees
          return L.divIcon({
            className: '',
            html: `<div style="
              width: 18px; height: 18px;
              transform: rotate(${heading || 0}deg);
              transform-origin: center;
            ">✈️</div>`,
            iconSize: [18, 18],
            iconAnchor: [9, 9],
          });
        }

        const markers = new Map(); // icao24 -> marker

        function msToKts(ms) { return ms == null ? null : (ms * 1.943844).toFixed(0); } // meters/sec to knots
        function mToFt(m) { return m == null ? null : (m * 3.28084).toFixed(0); }

        async function fetchPlanes() {
          const status = document.getElementById('status');
          try {
            const res = await fetch('/api/planes');
            const data = await res.json();

            status.textContent = `Updated: ${new Date(data.time * 1000).toLocaleTimeString()} • Planes: ${data.planes.length}`;

            const seen = new Set();

            for (const p of data.planes) {
              // key for marker tracking
              const key = p.icao24;
              seen.add(key);

              const lat = p.latitude;
              const lon = p.longitude;

              if (lat == null || lon == null) continue;

              const popupHtml = `
                <div style="font-family: system-ui; font-size: 13px;">
                  <div><strong>${(p.callsign || '').trim() || 'N/A'}</strong></div>
                  <div>Alt: ${p.altitude_m ?? '—'} m (${p.altitude_ft ?? '—'} ft)</div>
                  <div>Speed: ${p.velocity_ms ?? '—'} m/s (${p.velocity_kts ?? '—'} kt)</div>
                  <div>Heading: ${p.heading_deg ?? '—'}°</div>
                  <div>On ground: ${p.on_ground ? 'Yes' : 'No'}</div>
                  <div style="opacity:.7">Last contact: ${p.last_contact ? new Date(p.last_contact * 1000).toLocaleTimeString() : '—'}</div>
                </div>
              `;

              if (!markers.has(key)) {
                const marker = L.marker([lat, lon], { icon: planeIcon(p.heading_deg) }).addTo(map);
                marker.bindTooltip(((p.callsign || '').trim() || key), { permanent: false });
                marker.bindPopup(popupHtml);
                markers.set(key, marker);
              } else {
                const marker = markers.get(key);
                marker.setLatLng([lat, lon]);
                marker.setIcon(planeIcon(p.heading_deg));
                marker.setTooltipContent(((p.callsign || '').trim() || key));
                marker.setPopupContent(popupHtml);
              }
            }

            // Remove markers no longer present
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

        // initial + poll every 5 seconds
        fetchPlanes();
        setInterval(fetchPlanes, 5000);
    </script>
</body>
</html>
