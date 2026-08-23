(function () {
  var data = window.SHIPMENT_INIT;
  if (!data || typeof L === 'undefined') return;
  data.events = data.events || [];

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
    html: '<div style="width:14px;height:14px;border-radius:50%;background:#374151;border:3px solid #fff;box-shadow:0 0 0 2px #374151;"></div>',
    iconSize: [14, 14],
    iconAnchor: [7, 7]
  });
  // Current live position — blue with an idling pulse ring, like Google Maps'
  // live location dot (see .current-marker-pulse in style.css).
  var packageIcon = L.divIcon({
    className: '',
    html: '<div class="current-marker-pulse"></div><div style="position:relative;width:20px;height:20px;border-radius:50%;background:#4285f4;border:3px solid #fff;box-shadow:0 0 10px rgba(66,133,244,0.7);"></div>',
    iconSize: [20, 20],
    iconAnchor: [10, 10]
  });
  // Footprint — a past location the shipment has already left. Every checkpoint
  // is blue (current) the moment it's reported, then fades to this footprint
  // color as soon as a newer checkpoint comes in.
  var footprintIcon = L.divIcon({
    className: '',
    html: '<div style="width:14px;height:14px;border-radius:50%;background:#fde68a;border:2.5px solid #d97706;box-shadow:0 1px 3px rgba(17,24,39,0.3);"></div>',
    iconSize: [14, 14],
    iconAnchor: [7, 7]
  });

  var originMarker = L.marker([data.origin_lat, data.origin_lng], { icon: originIcon })
    .addTo(map).bindPopup('Origin: ' + data.origin_label);
  var destMarker = L.marker([data.destination_lat, data.destination_lng], { icon: destIcon })
    .addTo(map).bindPopup('Destination: ' + data.destination_label);
  var currentMarker = L.marker([data.current_lat, data.current_lng], { icon: packageIcon })
    .addTo(map)
    .bindPopup('Current position — ' + data.status)
    .bindTooltip(data.current_location_label || data.status, {
      permanent: true,
      direction: 'top',
      offset: [0, -14],
      className: 'current-location-tooltip'
    });

  // Solid line = the path already traveled (origin through every past checkpoint to now).
  // Dashed line = the remaining leg from the current position to the destination.
  var traveledLine = L.polyline([], { color: '#111827', weight: 3, opacity: 0.55 }).addTo(map);
  var remainingLine = L.polyline([], { color: '#d40511', weight: 3, dashArray: '6 6', opacity: 0.7 }).addTo(map);
  var historyMarkers = [];

  function sameSpot(lat1, lng1, lat2, lng2) {
    return Math.abs(lat1 - lat2) < 0.0005 && Math.abs(lng1 - lng2) < 0.0005;
  }

  function renderHistory(events) {
    historyMarkers.forEach(function (m) { map.removeLayer(m); });
    historyMarkers = [];

    // Every event except the most recent one is a footprint — the latest event
    // is where the shipment is right now, already shown by the blue marker.
    var past = events.slice(0, Math.max(0, events.length - 1));
    var traveledPoints = [[data.origin_lat, data.origin_lng]];

    past.forEach(function (ev) {
      var onOrigin = sameSpot(ev.lat, ev.lng, data.origin_lat, data.origin_lng);
      var onDest = sameSpot(ev.lat, ev.lng, data.destination_lat, data.destination_lng);
      if (!onOrigin && !onDest) {
        var marker = L.marker([ev.lat, ev.lng], { icon: footprintIcon })
          .addTo(map)
          .bindPopup(
            '<strong>' + escapeHtml(ev.status) + '</strong><br>'
            + escapeHtml(ev.location_label) + '<br>'
            + '<span style="color:#6b7280;font-size:12px;">' + escapeHtml(formatDate(ev.event_time)) + '</span>'
          );
        historyMarkers.push(marker);
      }
      traveledPoints.push([ev.lat, ev.lng]);
    });

    traveledPoints.push([data.current_lat, data.current_lng]);
    traveledLine.setLatLngs(traveledPoints);
    remainingLine.setLatLngs([[data.current_lat, data.current_lng], [data.destination_lat, data.destination_lng]]);
  }

  renderHistory(data.events);

  function fitAll() {
    var points = [
      [data.origin_lat, data.origin_lng],
      [data.destination_lat, data.destination_lng],
      [data.current_lat, data.current_lng]
    ];
    data.events.forEach(function (ev) { points.push([ev.lat, ev.lng]); });
    map.fitBounds(L.latLngBounds(points), { padding: [40, 40] });
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
        }

        if (s.current_location_label && s.current_location_label !== data.current_location_label) {
          data.current_location_label = s.current_location_label;
          currentMarker.setTooltipContent(s.current_location_label);
        }

        if (res.events) {
          data.events = res.events;
        }
        renderHistory(data.events);

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
