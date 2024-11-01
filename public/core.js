var enqueuedGoogleMaps = false;
var enqueuedGoogleLibs = false;

function Sps($el, cb, libs) {
    var sps = (function($, settings) {
        return {
            map: null,

            api: {
                libraries: '',
                key: settings.map_key
            },

            init: function ($el, cb, libs) {
                var self = this;
                var loadedLibs = true;

                if(libs && libs.length) {
                    self.api.libraries = libs.join(',');
                    for(var key in libs) {
                        if(libs.hasOwnProperty(key) && window.google) {
                            loadedLibs = loadedLibs && window.google.hasOwnProperty(libs[key]);
                        }
                    }
                }

                $('body').one('sps-maps-loaded', function() {
                    self.initMap($el, cb);
                });

                enqueuedGoogleLibs = enqueuedGoogleLibs || loadedLibs;

                if (enqueuedGoogleMaps && enqueuedGoogleLibs) {
                    if(window.google) {
                        self.initMap($el, cb);
                    }
                    return;
                }

                // load API
                enqueuedGoogleMaps = true;
                $.getScript('https://www.google.com/jsapi', function () {
                    google.load('maps', '3', {
                        other_params: $.param(self.api), callback: function () {
                            $('body').trigger('sps-maps-loaded');
                        }
                    });
                });
            },

            initMap: function ($el, cb) {
                if($el instanceof jQuery) {
                    $el = $el.get(0);
                }

                var center = [parseFloat(settings.map_coordinates.lat), parseFloat(settings.map_coordinates.lng)];

                var args = {
                    zoom: 5,
                    center: new google.maps.LatLng(center[0], center[1]),
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    zoomControl: true,
                    zoomControlOptions: {
                        position: google.maps.ControlPosition.LEFT_BOTTOM
                    },
                    scaleControl: true,
                    streetViewControl: true,
                    streetViewControlOptions: {
                        position: google.maps.ControlPosition.LEFT_BOTTOM
                    }
                };

                // create map
                this.map = new google.maps.Map($el, args);

                //custom code to be injected
                if(typeof cb == 'function') {
                    cb();
                }
            },

            centerMap: function (position) {
                // if marker exists, center on the marker
                if (position) {
                    // set center of map
                    this.map.setZoom(13);
                    this.map.setCenter(position);
                }
            }
        };
    })(jQuery, window.spsSettings);

    sps.init($el, cb, libs);
    return sps;
}
