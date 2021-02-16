var coordinates = [];
var GeoJSON = [];
// For place API look into this-> https://developers.google.com/maps/documentation/javascript/examples/places-autocomplete
function initMap() {
    const map = new google.maps.Map(document.getElementById("areaMap"), {
        center: {
            lat: -34.397,
            lng: 150.644
        },
        zoom: 6,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
    });
    const drawingManager = new google.maps.drawing.DrawingManager({
        drawingMode: google.maps.drawing.OverlayType.POLYGON,
        drawingControl: true,
        drawingControlOptions: {
            position: google.maps.ControlPosition.TOP_CENTER,
            drawingModes: [
                google.maps.drawing.OverlayType.POLYGON,
            ]
        },
        polygonOptions: {
            strokeWeight: 2,
            fillOpacity: 0.40,
            draggable: true,
            geodesic: true,
            clickable: true,
        }
    });
    drawingManager.setMap(map);
    // var dataLayer = new google.maps.Data();
    google.maps.event.addListener(drawingManager, 'polygoncomplete', function(polygon) {
        var path = polygon.getPath()
        for (var i = 0; i < path.length; i++) {
            coordinates.push({
                lat: path.getAt(i).lat(),
                lng: path.getAt(i).lng()
            });
        }
        console.log(coordinates);
        $('input[name=areas]').val(JSON.stringify(coordinates));
    });
    google.maps.event.addListener(drawingManager, 'overlaycomplete', function(event) {
        switch (event.type) {
            case google.maps.drawing.OverlayType.POLYGON:
                map.data.add(new google.maps.Data.Feature({
                    geometry: new google.maps.Data.Polygon([event.overlay.getPath().getArray()])
                }));
                break;
        }
    });
    // get the final GeoJSON data - later store this in DB after stringify
    google.maps.event.addDomListener(document.getElementById('saveGeoJson'), 'click', function() {
        map.data.toGeoJson(function(obj) {
            console.log("FINAL", obj)
            console.log(JSON.stringify(obj));
        });
    })
    var data = {
        "type": "FeatureCollection",
        "features": [{
            "type": "Feature",
            "geometry": {
                "type": "Polygon",
                "coordinates": [
                    [
                        [149.62215505973995, -34.43619223181807],
                        [149.35299002067745, -34.815888093596854],
                        [149.92977224723995, -34.82490727547911],
                        [149.94075857536495, -34.526753537760506],
                        [149.62215505973995, -34.43619223181807]
                    ]
                ]
            },
            "properties": {
                "name": "Saurabh1"
            }
        }, {
            "type": "Feature",
            "geometry": {
                "type": "Polygon",
                "coordinates": [
                    [
                        [149.47933279411495, -33.767559650784946],
                        [149.32552420036495, -34.1230022957905],
                        [149.79244314567745, -34.09571328693954],
                        [149.47933279411495, -33.767559650784946]
                    ]
                ]
            },
            "properties": {
                "name": "Saurabh2"
            }
        }, {
            "type": "Feature",
            "geometry": {
                "type": "Polygon",
                "coordinates": [
                    [
                        [149.83638845817745, -33.708175069104435],
                        [149.80892263786495, -33.93635209637803],
                        [150.42415701286495, -33.76299306564575],
                        [150.35274588005245, -33.621308316250335],
                        [150.10006033317745, -33.657894481892036],
                        [149.99569021598995, -33.47022620344963],
                        [149.78694998161495, -33.566399931316305],
                        [149.89681326286495, -33.657894481892036],
                        [149.83638845817745, -33.708175069104435]
                    ]
                ]
            },
            "properties": {
                "name": "Saurabh3"
            }
        }]
    }
    map.data.addGeoJson(data);
    map.data.setStyle({
        fillColor: 'green',
        strokeWeight: 3
    });
    // Right click on polygon to delete it.
    map.data.addListener("rightclick", event => {
        var feature = event.feature;
        var areaName = event.feature.getProperty("name");
        if (areaName == undefined) areaName = "New Area";
        var confirmDialog = confirm("Do you want to delete " + areaName + "?");
        if (confirmDialog == true) {
            map.data.remove(feature);
        }
    });
    // Hover on polygon to view the name
    map.data.addListener("mousemove", event => {
        var areaName = event.feature.getProperty("name");
        if (areaName == undefined) areaName = "New Area";
        console.log(areaName);
        $('#infoBlock').html(areaName);
    });
    console.log("JSON", data);
}