(function () {
  var data = window.SHIPMENT_INIT;
  if (!data || typeof L === 'undefined') return;

  var map = L.map('map', { scrollWheelZoom: false });

  // OpenStreetMap tiles — free, no API key or account required.
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var originIcon = L.divIcon({
    className: '',
    html: '<div style="width:14px;height:14px;border-radius:50%;background:#111827;border:3px solid #fff;box-shadow:0 0 0 2px #111827;"></div>',
    iconSize: [14, 14],
    iconAnchor: [7, 7]
  });
  var destIcon = L.divIcon({
    className: '',
    html: '<div style="width:14px;height:14px;border-radius:50%;background:#16a34a;border:3px solid #fff;box-shadow:0 0 0 2px #16a34a;"></div>',
    iconSize: [14, 14],
    iconAnchor: [7, 7]
  });
  var packageIcon = L.divIcon({
    className: '',
    html: '<div style="width:20px;height:20px;border-radius:50%;background:#d40511;border:3px solid #fff;box-shadow:0 0 10px rgba(212,5,17,0.6);"></div>',
    iconSize: [20, 20],
    iconAnchor: [10, 10]
  });

  var originMarker = L.marker([data.origin_lat, data.origin_lng], { icon: originIcon })
    .addTo(map).bindPopup('Origin: ' + data.origin_label);
  var destMarker = L.marker([data.destination_lat, data.destination_lng], { icon: destIcon })
    .addTo(map).bindPopup('Destination: ' + data.destination_label);
  var currentMarker = L.marker([data.current_lat, data.current_lng], { icon: packageIcon })
    .addTo(map).bindPopup('Current position — ' + data.status);

  var routeLine = L.polyline(
    [[data.origin_lat, data.origin_lng], [data.current_lat, data.current_lng], [data.destination_lat, data.destination_lng]],
    { color: '#d40511', weight: 3, dashArray: '6 6', opacity: 0.7 }
  ).addTo(map);

  function fitAll() {
    var bounds = L.latLngBounds([
      [data.origin_lat, data.origin_lng],
      [data.destination_lat, data.destination_lng],
      [data.current_lat, data.current_lng]
    ]);
    map.fitBounds(bounds, { padding: [40, 40] });
  }
  fitAll();

  // Poll our own JSON endpoint (api/track.php) for live updates — no third-party API involved.
  function poll() {
    fetch('/api/track.php?tn=' + encodeURIComponent(data.tracking_number))
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) return;
        var s = res.shipment;

        if (s.current_lat !== data.current_lat || s.current_lng !== data.current_lng) {
          data.current_lat = s.current_lat;
          data.current_lng = s.current_lng;
          currentMarker.setLatLng([s.current_lat, s.current_lng]);
          routeLine.setLatLngs([
            [data.origin_lat, data.origin_lng],
            [s.current_lat, s.current_lng],
            [data.destination_lat, data.destination_lng]
          ]);
        }

        var badge = document.getElementById('status-badge');
        if (badge && badge.textContent !== s.status) {
          badge.textContent = s.status;
        }

        var timelineEl = document.getElementById('timeline');
        if (timelineEl && res.events.length) {
          rebuildTimeline(timelineEl, res.events);
        }
      })
      .catch(function () { /* silent — will retry on next interval */ });
  }

  function rebuildTimeline(container, events) {
    var reversed = events.slice().reverse();
    var html = '<h3>Status Timeline</h3>';
    reversed.forEach(function (ev, i) {
      html += '<div class="timeline-item ' + (i === 0 ? '' : 'past') + '">'
        + '<div class="timeline-dot"></div>'
        + '<div class="timeline-body">'
        + '<div class="t-status">' + escapeHtml(ev.status) + '</div>'
        + '<div class="t-loc">' + escapeHtml(ev.location_label) + '</div>'
        + (ev.note ? '<div class="t-note">' + escapeHtml(ev.note) + '</div>' : '')
        + '<div class="t-time">' + escapeHtml(formatDate(ev.event_time)) + '</div>'
        + '</div></div>';
    });
    container.innerHTML = html;
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : str;
    return div.innerHTML;
  }

  function formatDate(iso) {
    var d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  setInterval(poll, 15000);
})();
