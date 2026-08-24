(function () {
  var data = window.SHIPMENT_INIT;
  if (!data) return;

  /* ------------------------------------------------------------------
     The live map is the most important thing on this page, so this file
     is written so that no single failure can take it down silently.
     Three layers of protection, in order of how likely each is:

       1. Bad coordinate data (an admin typo, a legacy row) is repaired
          or discarded here rather than being handed to Leaflet, which
          would otherwise throw and kill the whole map.
       2. Tile images failing (OpenStreetMap outage, blocked host, rate
          limit) is handled by the tile layer itself — the map still
          loads, pans and zooms, and the route/markers still draw over a
          plain background.
       3. Anything genuinely unexpected is caught and turned into a
          readable message instead of a blank grey box, with the
          server-rendered status timeline below still intact.
     ------------------------------------------------------------------ */

  function showMapMessage(titleText, bodyText) {
    var mapEl = document.getElementById('map');
    if (mapEl) {
      mapEl.classList.add('map-unavailable');
      mapEl.innerHTML = '';
      var msg = document.createElement('div');
      msg.className = 'map-unavailable-inner';
      var title = document.createElement('strong');
      title.textContent = titleText;
      var body = document.createElement('span');
      body.textContent = bodyText;
      msg.appendChild(title);
      msg.appendChild(body);
      mapEl.appendChild(msg);
    }
    // Hide the "live position, auto-refreshes" tag — nothing is refreshing.
    var liveTag = document.querySelector('.map-live-tag');
    if (liveTag) liveTag.style.display = 'none';
    var legend = document.querySelector('.map-legend');
    if (legend) legend.style.display = 'none';
  }

  // Leaflet is served from this site (assets/vendor/leaflet/), not a CDN,
  // so this should never happen — but if the file is ever missing after a
  // partial upload, say so rather than leaving an unexplained empty box.
  if (typeof L === 'undefined') {
    showMapMessage(
      'The map could not be loaded.',
      'The map library did not load. All tracking details and the full status history below are still up to date.'
    );
    return;
  }

  /**
   * Coordinates arrive from the database, where an admin typed them in.
   * A typo ("34" pasted into longitude as "-1182437"), a missing value,
   * or anything non-numeric would otherwise reach Leaflet and throw an
   * "Invalid LatLng" error that kills the entire map. Latitude is also
   * clamped to ±85 rather than ±90: the Web Mercator projection every
   * tile map uses goes to infinity at the poles, and a value past that
   * band can wedge the view. Returns null when a point is unusable, and
   * every caller below is written to cope with null.
   */
  function safePoint(lat, lng) {
    var la = parseFloat(lat);
    var ln = parseFloat(lng);
    if (!isFinite(la) || !isFinite(ln)) return null;
    if (la > 85) la = 85; else if (la < -85) la = -85;
    if (ln > 180) ln = 180; else if (ln < -180) ln = -180;
    return [la, ln];
  }

  data.events = data.events || [];

  var originPt = safePoint(data.origin_lat, data.origin_lng);
  var destPt = safePoint(data.destination_lat, data.destination_lng);
  var currentPt = safePoint(data.current_lat, data.current_lng);

  // Fall back along the chain rather than giving up: a shipment can
  // always be drawn somewhere as long as one usable coordinate exists.
  if (!currentPt) currentPt = originPt || destPt;
  if (!originPt) originPt = currentPt;
  if (!destPt) destPt = currentPt;

  if (!originPt || !destPt || !currentPt) {
    showMapMessage(
      'Map location not available for this shipment.',
      'No valid coordinates have been recorded yet. The status history below is still up to date.'
    );
    return;
  }

  var map;
  try {
    map = L.map('map', { scrollWheelZoom: false });
  } catch (e) {
    showMapMessage(
      'The map could not be displayed.',
      'Something went wrong loading the map on your device. All tracking details and the full status history below are still up to date.'
    );
    return;
  }

  // OpenStreetMap tiles — free, no API key or account required.
  // errorTileUrl is a transparent 1px PNG: if a tile can't be fetched
  // (OSM outage, blocked host, rate limiting, patchy mobile signal) the
  // map still works — it just shows a clean background under the route
  // and markers instead of Leaflet's broken-image placeholders.
  var tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap contributors',
    errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
    crossOrigin: true
  });

  // If tiles are failing consistently, tell the visitor why the map looks
  // plain — the position and route are still accurate, which is the part
  // that actually matters for tracking a parcel.
  var tileErrors = 0;
  tiles.on('tileerror', function () {
    tileErrors++;
    if (tileErrors === 8) {
      var note = document.querySelector('.map-tile-note');
      if (!note) {
        note = document.createElement('div');
        note.className = 'map-tile-note';
        note.textContent = 'Map imagery is unavailable right now, so the background is blank — '
          + 'the route and live position shown are still accurate.';
        var mapEl = document.getElementById('map');
        if (mapEl && mapEl.parentNode) mapEl.parentNode.insertBefore(note, mapEl.nextSibling);
      }
    }
  });
  tiles.addTo(map);

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

  // Leaflet renders a string passed to bindPopup/bindTooltip/setTooltipContent
  // as raw HTML, not text — every value below can be admin-entered free text
  // (origin/destination/location labels), so each one is escaped before
  // Leaflet ever sees it. Skipping this on any of them would be a stored-XSS
  // hole reachable by any admin account against every visitor to this
  // shipment's public tracking page.
  var originMarker = L.marker(originPt, { icon: originIcon })
    .addTo(map).bindPopup('Origin: ' + escapeHtml(data.origin_label));
  var destMarker = L.marker(destPt, { icon: destIcon })
    .addTo(map).bindPopup('Destination: ' + escapeHtml(data.destination_label));
  var currentMarker = L.marker(currentPt, { icon: packageIcon })
    .addTo(map)
    .bindPopup('Current position — ' + escapeHtml(data.status))
    .bindTooltip(escapeHtml(data.current_location_label || data.status), {
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
    var traveledPoints = [originPt];

    past.forEach(function (ev) {
      // A checkpoint with unusable coordinates is skipped rather than
      // allowed to break the route line for the whole shipment.
      var pt = safePoint(ev.lat, ev.lng);
      if (!pt) return;
      var onOrigin = sameSpot(pt[0], pt[1], originPt[0], originPt[1]);
      var onDest = sameSpot(pt[0], pt[1], destPt[0], destPt[1]);
      if (!onOrigin && !onDest) {
        var marker = L.marker(pt, { icon: footprintIcon })
          .addTo(map)
          .bindPopup(
            '<strong>' + escapeHtml(ev.status) + '</strong><br>'
            + escapeHtml(ev.location_label) + '<br>'
            + '<span style="color:#6b7280;font-size:12px;">' + escapeHtml(formatDate(ev.event_time)) + '</span>'
          );
        historyMarkers.push(marker);
      }
      traveledPoints.push(pt);
    });

    traveledPoints.push(currentPt);
    traveledLine.setLatLngs(traveledPoints);
    remainingLine.setLatLngs([currentPt, destPt]);
  }

  renderHistory(data.events);

  function fitAll() {
    var points = [originPt, destPt, currentPt];
    data.events.forEach(function (ev) {
      var pt = safePoint(ev.lat, ev.lng);
      if (pt) points.push(pt);
    });
    try {
      map.fitBounds(L.latLngBounds(points), { padding: [40, 40] });
    } catch (e) {
      // Degenerate bounds (every point identical, for a shipment that
      // hasn't moved yet) — just centre on the current position.
      map.setView(currentPt, 6);
    }
  }
  fitAll();

  // Poll our own JSON endpoint (api/track.php) for live updates — no third-party API involved.
  // Everything in here is wrapped so a bad response, a dropped connection,
  // or one malformed coordinate can never break the already-working map;
  // worst case an update is skipped and the next poll tries again.
  function poll() {
    fetch('/api/track.php?tn=' + encodeURIComponent(data.tracking_number))
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok || !res.shipment) return;
        var s = res.shipment;

        var newPt = safePoint(s.current_lat, s.current_lng);
        if (newPt && (newPt[0] !== currentPt[0] || newPt[1] !== currentPt[1])) {
          currentPt = newPt;
          data.current_lat = newPt[0];
          data.current_lng = newPt[1];
          currentMarker.setLatLng(newPt);
        }

        if (s.current_location_label && s.current_location_label !== data.current_location_label) {
          data.current_location_label = s.current_location_label;
          currentMarker.setTooltipContent(escapeHtml(s.current_location_label));
        }

        if (res.events && res.events.length) {
          data.events = res.events;
        }
        renderHistory(data.events);

        var badge = document.getElementById('status-badge');
        if (badge && s.status && badge.textContent !== s.status) {
          badge.textContent = s.status;
        }

        var timelineEl = document.getElementById('timeline');
        if (timelineEl && res.events && res.events.length) {
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
