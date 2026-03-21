document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('business-detail-map');
    if (!el) return;

    var lat = parseFloat(el.dataset.lat);
    var lng = parseFloat(el.dataset.lng);
    var name = el.dataset.name || '';
    var address = el.dataset.address || '';
    var precision = el.dataset.precision || 'community';
    var zoom = precision === 'address' ? 15 : 12;

    var map = L.map('business-detail-map').setView([lat, lng], zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    var popupContent = '<strong>' + name + '</strong>';
    if (address) {
        popupContent += '<br>' + address;
    }

    L.marker([lat, lng])
        .addTo(map)
        .bindPopup(popupContent)
        .openPopup();
});
