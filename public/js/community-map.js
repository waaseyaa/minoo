/**
 * Community living-map (#798). Reads the seven Mamaweswen nations from the
 * inlined #community-markers JSON and plots them on a Leaflet map. Public
 * factual data only; no individual people-listings.
 */
(function () {
  'use strict';

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  var el = document.getElementById('community-map');
  var dataEl = document.getElementById('community-markers');
  if (!el || !dataEl || typeof L === 'undefined') {
    return;
  }

  var markers;
  try {
    markers = JSON.parse(dataEl.textContent || '[]');
  } catch (e) {
    return;
  }
  if (!Array.isArray(markers) || markers.length === 0) {
    return;
  }

  var map = L.map(el, { scrollWheelZoom: false, minZoom: 5, maxZoom: 12 });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 12,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var bounds = [];
  markers.forEach(function (m) {
    if (typeof m.lat !== 'number' || typeof m.lng !== 'number') {
      return;
    }
    var marker = L.marker([m.lat, m.lng]).addTo(map);
    var href = '/communities/' + encodeURIComponent(m.slug);
    marker.bindTooltip(m.name + (m.anchor ? ' (anchor)' : ''));
    marker.bindPopup(
      '<strong>' + escapeHtml(m.name) + '</strong><br>' +
      escapeHtml(m.reserve || '') +
      '<br><a href="' + href + '">View community &rarr;</a>'
    );
    bounds.push([m.lat, m.lng]);
  });

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [40, 40] });
  }
})();
