/**
 * Wires a "Find on map" button to our own api/geocode.php endpoint, which
 * proxies OpenStreetMap's free Nominatim geocoder. Paste any address —
 * street, home, or a general place name — into the label field, click the
 * button, and the paired latitude/longitude fields auto-fill.
 */
function attachGeocodeLookup(labelId, latId, lngId, buttonId, statusId) {
  var labelInput = document.getElementById(labelId);
  var latInput = document.getElementById(latId);
  var lngInput = document.getElementById(lngId);
  var btn = document.getElementById(buttonId);
  var statusEl = document.getElementById(statusId);
  if (!labelInput || !latInput || !lngInput || !btn || !statusEl) return;

  btn.addEventListener('click', function () {
    var q = labelInput.value.trim();
    if (!q) {
      statusEl.textContent = 'Type an address in the field above first.';
      statusEl.className = 'geocode-status geocode-error';
      return;
    }

    btn.disabled = true;
    var originalLabel = btn.textContent;
    btn.textContent = 'Looking up…';
    statusEl.textContent = '';
    statusEl.className = 'geocode-status';

    fetch('/api/geocode.php?q=' + encodeURIComponent(q))
      .then(function (r) { return r.json(); })
      .then(function (res) {
        btn.disabled = false;
        btn.textContent = originalLabel;
        if (!res.ok) {
          statusEl.textContent = res.error || 'Not found — enter coordinates manually.';
          statusEl.className = 'geocode-status geocode-error';
          return;
        }
        latInput.value = res.lat.toFixed(7);
        lngInput.value = res.lng.toFixed(7);
        statusEl.textContent = '✓ Found: ' + res.display_name;
        statusEl.className = 'geocode-status geocode-success';
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = originalLabel;
        statusEl.textContent = 'Lookup failed — enter coordinates manually.';
        statusEl.className = 'geocode-status geocode-error';
      });
  });
}
