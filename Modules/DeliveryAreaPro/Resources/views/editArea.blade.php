@extends('admin.layouts.master')
@section("title") Edit Area - Delivery Area Pro
@endsection
@section('content')
@include('deliveryareapro::includes.scripts')
<div class="page-header">
    <div class="page-header-content header-elements-md-inline">
        <div class="page-title d-flex">
            <h4>
                <span class="font-weight-bold mr-2">Modules</span>
                <i class="icon-circle-right2 mr-2"></i>
                <span class="font-weight-bold mr-2"><a href="{{ route('dap.settings') }}">Delivery Area Pro</a></span>
                <i class="icon-circle-right2 mr-2"></i>
                <span class="font-weight-bold mr-2">{{ $area->name }}</span>
            </h4>
            <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
        </div>
    </div>
</div>
<div class="content">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('dap.updateArea') }}" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="area_id" value="{{ $area->id }}">
                <div class="form-group row">
                    <label class="col-lg-2 col-form-label">Name:</label>
                    <div class="col-lg-10">
                        <input type="text" name="name" class="form-control form-control-lg"
                            placeholder="Area name" required autocomplete="new-name" value="{{ $area->name }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-lg-2 col-form-label">Description:</label>
                    <div class="col-lg-10">
                        <input type="text" name="description" class="form-control form-control-lg"
                            placeholder="Short description (optional)" value="{{ $area->description }}">
                    </div>
                </div>
                <div>
                    <div id="areaMap"></div>
                </div>
                <div class="form-group row">
                    <textarea name="geojson" class="form-control hidden" required="required">{{ $area->geojson }}</textarea>
                </div>
                <div class="text-right hidden" id="saveGeoJson">
                    <button type="button" class="btn btn-success">
                    Process Data
                    <i class="icon-database-check ml-1"></i></button>
                </div>
                <div class="text-right" id="saveAreaButton">
                    <button type="submit" class="btn btn-primary">
                    Save Area
                    <i class="icon-database-insert ml-1"></i></button>
                </div>
                @csrf
            </form>
            <p class="text-muted"><b><span class="text-info"> <i class="icon-exclamation mr-1"></i></span> Right click on an area to delete it.</b></p>
        </div>
    </div>
</div>
<script>
    "use strict";
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
                position: google.maps.ControlPosition.TOP_RIGHT,
                drawingModes: [
                    google.maps.drawing.OverlayType.POLYGON,
                ]
            },
            polygonOptions: {
                fillOpacity: 0.40,
                draggable: false,
                geodesic: true,
                clickable: false,
                fillColor: 'green',
                strokeColor: 'green',
                strokeWeight: 1.5
            }
        });
        drawingManager.setMap(map);
    
        // on polygon draw complete, hide the submit button -> force to click on Export data button
        google.maps.event.addListener(drawingManager, 'polygoncomplete', function(polygon) {
            $('#saveGeoJson').removeClass('hidden');
            $('#saveAreaButton').addClass('hidden');
        });
    
        //take the polugon and add the data layer, and delete the polygon
        google.maps.event.addListener(drawingManager, 'overlaycomplete', function(event) {
            switch (event.type) {
                case google.maps.drawing.OverlayType.POLYGON:
                    if (event.overlay != null)
                        event.overlay.setMap(null);
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
                $('textarea[name=geojson]').val(JSON.stringify(obj));
                setTimeout(function() {
                    $('#saveGeoJson').addClass('hidden');
                    $('#saveAreaButton').removeClass('hidden');
                }, 250);
            });
        })
    
        var savedData = {!! $area->geojson !!};
    
        var bounds = new google.maps.LatLngBounds();
            google.maps.event.addListener(map.data, 'addfeature', function (e) {
                if (e.feature.getGeometry().getType() === 'Polygon') {
                    var polys = e.feature.getGeometry().getArray();
                    for (var i = 0; i < polys.length; i++) {
                        for (var j = 0; j < polys[i].getLength(); j++) {
                            bounds.extend(polys[i].getAt(j));
                        }
                    }
                    map.fitBounds(bounds);
                } else if (e.feature.getGeometry().getType() === 'MultiPolygon') {
                    var multi = e.feature.getGeometry().getArray();
                    for (var k = 0; k < multi.length; k++) {
                        var polys = multi[k].getArray();
                        for (var i = 0; i < polys.length; i++) {
                            for (var j = 0; j < polys[i].getLength(); j++) {
                                bounds.extend(polys[i].getAt(j));
                            }
                        }
                    }
                    map.fitBounds(bounds);
                }
            });
          map.data.addGeoJson(savedData);
    
        //data style
        map.data.setStyle({
            fillColor: 'green',
            strokeColor: 'green',
            strokeWeight: 1.5
        });
    
        // Right click on polygon to delete it.
        map.data.addListener("rightclick", event => {
            var feature = event.feature;
            var areaName = event.feature.getProperty("name");
            // if (areaName == undefined) areaName = "New Area";
            var confirmDialog = confirm("Do you want to delete this area?");
            if (confirmDialog == true) {
                map.data.remove(feature);
                $('#saveGeoJson').removeClass('hidden');
                $('#saveAreaButton').addClass('hidden');
            }
        });
    }
</script>
@endsection