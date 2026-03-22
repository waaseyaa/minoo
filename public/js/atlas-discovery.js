function atlasDiscovery() {
  return {
    // Data
    allCommunities: [],
    filteredCommunities: [],
    proximityGroups: [],
    hasLocation: false,
    userLat: null,
    userLng: null,
    locationName: '',

    // Filters
    searchQuery: '',
    typeFilter: 'all',
    provinceFilter: [],
    nationFilter: [],
    populationFilter: 'all',

    // Computed lists for dropdowns
    availableProvinces: [],
    availableNations: [],

    // Intermediate filter state
    attributeFiltered: [],

    // Map
    map: null,
    markers: null,
    markerMap: {},

    // Businesses
    allBusinesses: [],

    // Entity type filters
    filters: {
      communities: true,
      businesses: true
    },

    // Header
    headerTitle: 'All Communities',
    headerMeta: '',

    init() {
      this.allCommunities = window.__atlas_communities || [];
      this.allBusinesses = window.__atlas_businesses || [];
      const loc = window.__atlas_location;

      // Extract available filter values
      const provinces = new Set();
      const nations = new Set();
      this.allCommunities.forEach(function(c) {
        if (c.province) provinces.add(c.province);
        if (c.nation) nations.add(c.nation);
      });
      this.availableProvinces = Array.from(provinces).sort();
      this.availableNations = Array.from(nations).sort();

      // Set location if available from server
      if (loc && loc.lat && loc.lng) {
        this.setLocation(loc.lat, loc.lng, loc.name);
      } else {
        this.tryBrowserGeolocation();
      }

      this.applyFilters();
      this.initMap();
    },

    tryBrowserGeolocation() {
      if (!navigator.geolocation) return;
      var self = this;
      navigator.geolocation.getCurrentPosition(
        function(pos) {
          self.setLocation(pos.coords.latitude, pos.coords.longitude, null);
          // Persist to server session
          fetch('/api/location/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ latitude: pos.coords.latitude, longitude: pos.coords.longitude })
          }).catch(function() {});
        },
        function() {
          // Denied or error — stay in alphabetical mode
          self.applyFilters();
        },
        { timeout: 5000 }
      );
    },

    setLocation(lat, lng, name) {
      this.userLat = lat;
      this.userLng = lng;
      this.hasLocation = true;
      this.locationName = name || '';

      // Calculate distances for all communities
      var self = this;
      this.allCommunities.forEach(function(c) {
        c.distance = self.haversine(lat, lng, c.lat, c.lng);
      });

      this.applyFilters();
      this.updateHeader();
    },

    haversine(lat1, lon1, lat2, lon2) {
      var R = 6371;
      var dLat = (lat2 - lat1) * Math.PI / 180;
      var dLon = (lon2 - lon1) * Math.PI / 180;
      var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    },

    applyFilters() {
      var self = this;
      var results = this.allCommunities.filter(function(c) {
        // Search
        if (self.searchQuery && c.name.toLowerCase().indexOf(self.searchQuery.toLowerCase()) === -1) return false;

        // Type
        if (self.typeFilter === 'fn' && c.is_municipality) return false;
        if (self.typeFilter === 'mun' && !c.is_municipality) return false;

        // Province
        if (self.provinceFilter.length > 0 && self.provinceFilter.indexOf(c.province) === -1) return false;

        // Nation
        if (self.nationFilter.length > 0 && (!c.nation || self.nationFilter.indexOf(c.nation) === -1)) return false;

        // Population
        if (self.populationFilter !== 'all') {
          var pop = c.population || 0;
          if (self.populationFilter === '0-500' && pop >= 500) return false;
          if (self.populationFilter === '500-2000' && (pop < 500 || pop >= 2000)) return false;
          if (self.populationFilter === '2000-5000' && (pop < 2000 || pop >= 5000)) return false;
          if (self.populationFilter === '5000+' && pop < 5000) return false;
        }

        return true;
      });

      // Update available nations based on results WITHOUT the nation filter applied
      // This prevents the circular dependency where selecting a type empties the nation dropdown
      var self2 = this;
      var forNationDropdown = this.allCommunities.filter(function(c) {
        if (self2.searchQuery && c.name.toLowerCase().indexOf(self2.searchQuery.toLowerCase()) === -1) return false;
        if (self2.typeFilter === 'fn' && c.is_municipality) return false;
        if (self2.typeFilter === 'mun' && !c.is_municipality) return false;
        if (self2.provinceFilter.length > 0 && self2.provinceFilter.indexOf(c.province) === -1) return false;
        if (self2.populationFilter !== 'all') {
          var pop = c.population || 0;
          if (self2.populationFilter === '0-500' && pop >= 500) return false;
          if (self2.populationFilter === '500-2000' && (pop < 500 || pop >= 2000)) return false;
          if (self2.populationFilter === '2000-5000' && (pop < 2000 || pop >= 5000)) return false;
          if (self2.populationFilter === '5000+' && pop < 5000) return false;
        }
        return true;
      });
      var filteredNations = new Set();
      forNationDropdown.forEach(function(c) {
        if (c.nation) filteredNations.add(c.nation);
      });
      this.availableNations = Array.from(filteredNations).sort();

      // Sort
      if (this.hasLocation) {
        results.sort(function(a, b) { return (a.distance || 0) - (b.distance || 0); });
      } else {
        results.sort(function(a, b) { return a.name.localeCompare(b.name); });
      }

      // Store attribute-filtered set, then apply viewport filter on top
      this.attributeFiltered = results;
      this.filteredCommunities = results;
      this.updateMapMarkers();
      this.applyViewportFilter();
    },

    buildProximityGroups(communities) {
      var groups = [
        { label: 'Within 50 km', max: 50, communities: [] },
        { label: '50 – 100 km', max: 100, communities: [] },
        { label: '100 – 200 km', max: 200, communities: [] },
        { label: '200+ km', max: Infinity, communities: [] }
      ];
      communities.forEach(function(c) {
        var d = c.distance || Infinity;
        for (var i = 0; i < groups.length; i++) {
          if (d < groups[i].max || (i === groups.length - 1)) {
            groups[i].communities.push(c);
            break;
          }
        }
      });
      this.proximityGroups = groups;
    },

    updateHeader() {
      if (this.hasLocation && this.locationName) {
        this.headerTitle = 'Communities near ' + this.locationName;
        var within200 = this.allCommunities.filter(function(c) { return c.distance && c.distance <= 200; }).length;
        this.headerMeta = within200 + ' communities within 200 km';
      } else if (this.hasLocation) {
        var within200Count = this.allCommunities.filter(function(c) { return c.distance && c.distance <= 200; }).length;
        this.headerTitle = 'Communities Near You';
        this.headerMeta = within200Count + ' communities within 200 km';
      } else {
        this.headerTitle = 'All Communities';
        this.headerMeta = this.allCommunities.length + ' communities';
      }
    },

    // --- Map ---
    initMap() {
      // Guard against double-initialization (Alpine may call init() more than once)
      if (this.map) return;

      var defaultCenter = this.hasLocation ? [this.userLat, this.userLng] : [50.0, -85.0];
      var defaultZoom = this.hasLocation ? 8 : 5;

      this.map = L.map('atlas-map').setView(defaultCenter, defaultZoom);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
      }).addTo(this.map);

      // User location marker
      if (this.hasLocation) {
        L.circleMarker([this.userLat, this.userLng], {
          radius: 8, fillColor: '#3388ff', fillOpacity: 0.8, color: '#fff', weight: 2
        }).addTo(this.map).bindPopup('Your location');
      }

      // Per-type cluster layers
      this.communityLayer = L.markerClusterGroup();
      this.businessLayer = L.markerClusterGroup({ maxClusterRadius: 40 });
      this.map.addLayer(this.communityLayer);
      this.map.addLayer(this.businessLayer);

      // Keep backward compat for markerMap highlight
      this.markers = this.communityLayer;

      this.updateMapMarkers();

      // Sync map viewport with list
      var self = this;
      this.map.on('moveend', function() {
        self.applyViewportFilter();
      });
    },

    updateMapMarkers() {
      this.updateCommunityMarkers();
      this.updateBusinessMarkers();
    },

    updateCommunityMarkers() {
      if (!this.communityLayer) return;
      this.communityLayer.clearLayers();
      this.markerMap = {};
      var self = this;
      this.filteredCommunities.forEach(function(c) {
        if (!c.lat || !c.lng) return;
        var marker = L.marker([c.lat, c.lng])
          .bindPopup('<strong>' + c.name + '</strong><br>' +
            (c.is_municipality ? 'Municipality' : 'First Nation') +
            (c.nation ? ' · ' + c.nation : ''));
        marker.on('click', function() {
          window.location.href = '/communities/' + c.slug;
        });
        self.communityLayer.addLayer(marker);
        self.markerMap[c.id] = marker;
      });
    },

    updateBusinessMarkers() {
      if (!this.businessLayer) return;
      this.businessLayer.clearLayers();
      var self = this;
      this.allBusinesses.forEach(function(b) {
        if (!b.lat || !b.lng) return;
        var marker = L.circleMarker([b.lat, b.lng], {
          radius: 6,
          fillColor: '#b0643c',
          color: '#b0643c',
          fillOpacity: 0.8,
          weight: 1
        }).bindPopup('<strong>' + b.name + '</strong>' +
          (b.community_name ? '<br>' + b.community_name : ''));
        marker.on('click', function() {
          window.location.href = '/businesses/' + b.slug;
        });
        self.businessLayer.addLayer(marker);
      });
    },

    applyViewportFilter() {
      if (!this.map) return;
      var bounds = this.map.getBounds();
      // Apply viewport constraint on top of attribute-filtered set
      this.filteredCommunities = (this.attributeFiltered || this.allCommunities).filter(function(c) {
        if (!c.lat || !c.lng) return false;
        return bounds.contains([c.lat, c.lng]);
      });
      if (this.hasLocation) {
        this.buildProximityGroups(this.filteredCommunities);
      }
    },

    toggleFilter(type) {
      this.filters[type] = !this.filters[type];
      var layers = { communities: this.communityLayer, businesses: this.businessLayer };
      var layer = layers[type];
      if (!layer) return;
      if (this.filters[type]) {
        this.map.addLayer(layer);
      } else {
        this.map.removeLayer(layer);
      }
    },

    highlightMarker(id) {
      var marker = this.markerMap[id];
      if (marker && marker._icon) {
        marker._icon.style.filter = 'hue-rotate(120deg) brightness(1.2)';
      }
    },

    unhighlightMarker(id) {
      var marker = this.markerMap[id];
      if (marker && marker._icon) {
        marker._icon.style.filter = '';
      }
    }
  };
}
