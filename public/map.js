(function($, Sps, spsMapVars, spsSettings){

    function SpsMap(container) {
        var sps = null;
        var markerMaxSize = 40;

        var map = {
            mainMarker: null,
            infoBox: null,
            directionsService: null,
            directionsDisplay: null,
            markers: {},

            init: function() {
                //"this" here will be map DOM element
                var self = this;

                //load infobox lib
                $.getScript(spsMapVars.infobox, function () {
                    map.initMap.call(self);
                });
            },

            initMap: function () {
                //"this" here will be map DOM element
                var $self = $(this);

                var myOptions = {
                    content: document.createElement("div"),
                    disableAutoPan: true,
                    zIndex: null,
                    closeBoxURL: "",
                    infoBoxClearance: new google.maps.Size(1, 1),
                    isHidden: false,
                    pane: "floatPane",
                    enableEventPropagation: false
                };

                map.infoBox = new InfoBox(myOptions);
                map.directionsDisplay = new google.maps.DirectionsRenderer();
                map.directionsService = new google.maps.DirectionsService();

                var center = $self.find('.sps-map-container').data('center');
                var img = new Image();

                img.onload = function() {
                    var ratio = this.height/this.width;
                    var height = ratio * markerMaxSize;
                    var iconSize = new google.maps.Size(markerMaxSize, height);
                    map.mainMarker = map.createMarker(this.src, iconSize, center);
                    sps.centerMap(map.mainMarker.getPosition());
                };
                img.src = spsSettings.main_marker || spsMapVars.images + 'main-point.png';

                $self.find('.sps-poi-marker').each(function () {
                    var self = this;
                    var location = $(this).data('location');
                    var img = new Image();

                    img.onload = function() {
                        var ratio = this.height/this.width;
                        var height = ratio * markerMaxSize;
                        var iconSize = new google.maps.Size(markerMaxSize, height);
                        map.markers[self.id] = map.createMarker.call(self, this.src, iconSize, location, true);
                    };
                    img.src = spsSettings.poi_marker || spsMapVars.images + 'poi.png';

                    $(this).on('click', function (e) {
                        e.preventDefault();
                        var marker = map.markers[this.id];
                        if (marker) {
                            new google.maps.event.trigger(marker, 'click');
                        }
                    });
                });

                //setup extra controls
                var $credits = $self.find('.sps-author-info');
                if($credits.length) {
                    sps.map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push($credits.get(0));
                }

                var $distanceType = $self.find('.sps-travel-mode');
                if($distanceType.length) {
                    $distanceType.on('click', '.sps-map-button', function(e) {
                        e.preventDefault();
                        $(this).siblings().removeClass('active');
                        $(this).addClass('active');

                        var mode = $(this).data('travel-mode');
                        $self.find('.sps-travel-mode-value').val(mode).trigger('change');
                    });

                    $self.find('.sps-travel-mode-value').on('change', function() {
                        $self.find('.sps-poi-marker.active').trigger('click');
                    });

                    sps.map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push($distanceType.get(0));
                }
            },

            closeBox: function ($container) {
                this.infoBox.close();
                this.directionsDisplay.setMap(null);
                sps.centerMap(this.mainMarker.getPosition());
                $container.find('.sps-poi-marker, .sps-travel-mode').removeClass('active');
            },

            createMarker: function(icon, iconSize, location, withEvents) {
                //this here is sps-poi-marker button

                var marker = new google.maps.Marker({
                    position: {
                        lat: parseFloat(location.lat),
                        lng: parseFloat(location.lng)
                    },
                    icon: {
                        url: icon,
                        scaledSize: iconSize,
                        origin: new google.maps.Point(0, 0),
                        anchor: new google.maps.Point(23, 45)
                    },
                    map: sps.map,
                    id: this.id
                });

                if(!withEvents) {
                    return marker;
                }

                google.maps.event.addListener(marker, 'click', function () {
                    var currentMarker = this;

                    map.drawRoute(currentMarker, function(){
                        var $clickedItem = $('#' + currentMarker.id);
                        var image = $clickedItem.data('image');

                        setTimeout(function () {
                            sps.map.setCenter(currentMarker.getPosition());
                            sps.map.panBy(0, image ? -160 : -60);
                        }, 1);
                    });
                });

                return marker;
            },

            drawRoute: function(currentMarker, cb) {
                var $clickedItem = $('#' + currentMarker.id);
                var href = $clickedItem.attr('href');
                var image = $clickedItem.data('image');
                var description = $clickedItem.data('description');
                var mode = $clickedItem.closest('.sps-gmap').find('.sps-travel-mode-value').val();

                $clickedItem.siblings().removeClass('active');

                map.directionsService.route({
                    origin: map.mainMarker.getPosition(),
                    destination: currentMarker.getPosition(),
                    travelMode: google.maps.TravelMode[mode]
                }, function (result, status) {
                    if (status == google.maps.DirectionsStatus.OK) {
                        $clickedItem.addClass('active');

                        var distance = result.routes[0].legs[0].distance.value / 1000;
                        var time = Math.round(result.routes[0].legs[0].duration.value / 60);

                        var $minimizer = $('<span class="sps-info-minimizer"></span>');
                        $minimizer.click(function(){
                            $(this).closest('.sps-info').toggleClass('mini');
                        });

                        var $closer = $('<span class="sps-info-closer"></span>');
                        $closer.click(map.closeBox.bind(map, $clickedItem.closest('.sps-gmap')));

                        var infoboxHtml = '<div class="sps-info">';

                        if (href != '#') {
                            infoboxHtml += '<a href="' + href + '" target="_blank">';
                        }

                        if (image) {
                            infoboxHtml += '<div class="sps-info-image" style="background-image: url(' + $clickedItem.data('image') + ')">' +
                                '<div class="sps-info-gradient"></div>';
                        }

                        infoboxHtml += '<div class="sps-info-title">' + $clickedItem.html() + '</div>';

                        if (image) {
                            infoboxHtml += '</div>';
                        }

                        if (href != '#') {
                            infoboxHtml += '</a>';
                        }

                        if (description) {
                            infoboxHtml += '<div class="sps-info-description">' + description + '</div>';

                            if (href != '#') {
                                infoboxHtml += ' <a href="' + href + '" target="_blank" class="sps-info-readmore">Read more</a>';
                            }
                        }

                        infoboxHtml += '</div>';

                        infoboxHtml = $(infoboxHtml).prepend($closer);

                        if (image) {
                            infoboxHtml.addClass('has-image');
                        }

                        if (image || description) {
                            infoboxHtml.prepend($minimizer);
                        }

                        infoboxHtml.find('.sps-info-title').append('<div class="sps-info-details">About ' + distance.toFixed(1) + ' km (' + time + ' minutes)</div>');

                        map.infoBox.setContent(infoboxHtml.get(0));
                        map.infoBox.open(sps.map, currentMarker);

                        map.directionsDisplay.setDirections(result);
                        map.directionsDisplay.setMap(sps.map);
                        map.directionsDisplay.setOptions({suppressMarkers: true});

                        $clickedItem.closest('.sps-gmap').find('.sps-travel-mode').addClass('active');

                        if(typeof cb === 'function') {
                            cb();
                        }
                    }
                });
            }
        };

        sps = new Sps($(container).find('.sps-map-container'), map.init.bind(container));

        return map;
    }

    $('.sps-gmap').each(function(){
        //there may be many maps here
        new SpsMap(this);
    });
})(jQuery, window.Sps, window.spsMapVars, window.spsSettings);