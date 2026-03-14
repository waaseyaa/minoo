(function() {
  var data = window.__atlas_detail;
  if (!data || !data.lat || !data.lng) return;

  var mapEl = document.getElementById('atlas-detail-map');
  if (!mapEl) return;

  var map = L.map('atlas-detail-map').setView([data.lat, data.lng], 10);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18
  }).addTo(map);

  // Primary community marker
  L.marker([data.lat, data.lng])
    .addTo(map)
    .bindPopup('<strong>' + data.name + '</strong>')
    .openPopup();

  // Nearby community markers + polylines
  if (data.nearby && data.nearby.length > 0) {
    data.nearby.forEach(function(n) {
      if (!n.lat || !n.lng) return;

      // Secondary marker (smaller, grey)
      L.circleMarker([n.lat, n.lng], {
        radius: 6,
        fillColor: '#6b8f71',
        fillOpacity: 0.7,
        color: '#fff',
        weight: 1
      }).addTo(map).bindPopup('<a href="/communities/' + n.slug + '">' + n.name + '</a>');

      // Decorative polyline
      L.polyline([[data.lat, data.lng], [n.lat, n.lng]], {
        color: '#c8d6c4',
        weight: 1.5,
        opacity: 0.6,
        dashArray: '4 6'
      }).addTo(map);
    });

    // Fit bounds to include all markers
    var allPoints = [[data.lat, data.lng]];
    data.nearby.forEach(function(n) {
      if (n.lat && n.lng) allPoints.push([n.lat, n.lng]);
    });
    map.fitBounds(allPoints, { padding: [30, 30] });
  }
})();
