(function ($, Sps) {
    var sps = null;

    var map = {
        $el: null,
        geocoder: null,
        marker: null,

        init: function () {
            this.$el = $('#sps-map').find('.sps-gmap');
            sps = new Sps(this.$el.find('.sps-map-container'), this.initMap.bind(this), ['places']);
        },

        initMap: function () {
            var self = this;

            self.geocoder = new google.maps.Geocoder();

            // add search
            var autocomplete = new google.maps.places.Autocomplete(self.$el.find('.sps-map-search').get(0));
            autocomplete.map = sps.map;
            autocomplete.bindTo('bounds', sps.map);

            // add dummy marker
            self.marker = new google.maps.Marker({
                draggable: true,
                raiseOnDrag: true,
                map: sps.map
            });

            //setup map based on saved data (if any)
            self.searchForPlace();

            //setup events
            google.maps.event.addListener(self.marker, 'dragend', function () {
                self.updateLocationData();
            });

            google.maps.event.addListener(sps.map, 'click', function (e) {
                self.marker.setPosition(e.latLng);
                self.updateLocationData();
            });

            google.maps.event.addListener(autocomplete, 'place_changed', function () {
                self.searchForPlace();
            });
        },

        searchForPlace: function () {
            var self = this;
            var address = self.$el.find('.sps-map-search').val();

            if (address.length == 0) {
                var latlng = new google.maps.LatLng(
                    this.$el.find('.sps-meta-field-lat').val(),
                    this.$el.find('.sps-meta-field-lng').val()
                );
                self.marker.setPosition(latlng);
                self.reverseGeocode(latlng);
                return;
            }

            self.geocoder.geocode({'address': address}, function (results, status) {
                // validate
                if (status != google.maps.GeocoderStatus.OK) {
                    console.log('Geocoder failed due to: ' + status);
                    return;
                }

                if (!results[0]) {
                    console.log('No results found');
                    return;
                }

                // get place
                var place = results[0];
                place.formatted_address = address;
                self.marker.setPosition(place.geometry.location);
                self.updateLocationData(place);
            });
        },

        updateLocationData: function (place) {
            if (typeof place === 'undefined') {
                //first search for place
                var position = this.marker.getPosition(),
                    latlng = new google.maps.LatLng(position.lat(), position.lng());

                this.reverseGeocode(latlng);
            } else {
                this.updateInputFields(place);
            }
        },

        updateInputFields: function (location) {
            this.$el.find('.sps-meta-field-address').val(location.formatted_address);
            this.$el.find('.sps-meta-field-lat').val(location.geometry.location.lat());
            this.$el.find('.sps-meta-field-lng').val(location.geometry.location.lng());
            sps.centerMap(this.marker.getPosition());
        },

        reverseGeocode: function(latlng) {
            var self = this;
            self.geocoder.geocode({'latLng': latlng}, function (results, status) {
                // validate
                if (status != google.maps.GeocoderStatus.OK) {
                    console.log('Geocoder failed due to: ' + status);
                    return;
                }

                if (!results[0]) {
                    console.log('No results found');
                    return;
                }

                // get location
                var location = results[0];
                self.$el.find('.sps-map-search').val(location.formatted_address);
                self.updateInputFields(location);
            });
        }
    };

    map.init();

    $(".sps-map-categories").select2({
        placeholder: "Select a state"
    });

})(jQuery, window.Sps);