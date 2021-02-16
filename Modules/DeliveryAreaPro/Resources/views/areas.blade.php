@extends('admin.layouts.master')
@section("title") Areas - Delivery Area Pro
@endsection
@section('content')
<div class="page-header">
    <div class="page-header-content header-elements-md-inline">
        <div class="page-title d-flex">
            <h4>
                <span class="font-weight-bold mr-2">Modules</span>
                <i class="icon-circle-right2 mr-2"></i>
                <span class="font-weight-bold mr-2">Delivery Area Pro</span>
            </h4>
            <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
        </div>
        <div class="header-elements d-none py-0 mb-3 mb-md-0">
            <div class="breadcrumb">
                <button type="button" class="btn btn-secondary btn-labeled btn-labeled-left mr-2" id="addNewArea"
                    data-toggle="modal" data-target="#addNewAreaModal">
                <b><i class="icon-plus2"></i></b>
                Add New Area
                </button>
                <button type="button" class="btn btn-secondary btn-labeled btn-labeled-left" id="assignAreasToStore"
                    data-toggle="modal" data-target="#assignAreasToStoreModal">
                <b><i class="icon-map5"></i></b>
                Assign Areas to Store
                </button>
            </div>
        </div>
    </div>
</div>
@if(config('settings.googleApiKeyNoRestriction') == null)
<div class="content">
    <div class="card">
        <div class="card-body">
            <p class="text-danger"> <i class="icon-warning mr-1"></i> You have not set Google API key with no IP/HTTP restriction, please set it below to use Delivery Area Pro Module</p>
            <form action="{{ route('dap.saveSettings') }}" method="POST" enctype="multipart/form-data" class="my-3">
                <div class="form-group row">
                    <label class="col-lg-3 col-form-label"><strong>Google Map API Key: </strong> <br>
                    (with no IP/HTTP Restriction)</label>
                    <div class="col-lg-9">
                        <input type="text" class="form-control form-control-lg" name="googleApiKeyNoRestriction" placeholder="Google Map API Key which has no IP & HTTP Restrictions"
                            required>
                    </div>
                </div>
                @csrf
                <div class="text-right mt-5">
                    <button type="submit" class="btn btn-primary btn-labeled btn-labeled-left btn-lg">
                    <b><i class="icon-database-insert ml-1"></i></b>
                    Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@else
@include('deliveryareapro::includes.scripts')
<div class="content">
    <div class="card">
        <div class="card-body">
            @if($areas->count() > 0)
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th class="text-center" style="width: 10%;"><i class="
                                icon-circle-down2"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($areas as $area)
                        <tr>
                            <td>{{ $area->name }}</td>
                            <td>{{ $area->description }}</td>
                            <td><span class="badge badge-flat border-grey-800 text-default text-capitalize mr-1"> @if($area->is_active) ACTIVE @else INACTIVE @endif </span></td>
                            <td>{{ $area->created_at->diffForHumans() }}</td>
                            <td>{{ $area->updated_at->diffForHumans() }}</td>
                            <td>
                                <div class="btn-group btn-group-justified align-items-center">
                                    <a href="{{ route('dap.editArea', $area->id) }}"
                                        class="badge badge-primary badge-icon mr-1"> EDIT 
                                    <i class="icon-database-edit2 ml-1"></i></a>
                                    <a href="{{ route('dap.assignStoresToArea', $area->id) }}"
                                        class="badge badge-primary badge-icon"> Assign to Stores 
                                    <i class="icon-hyperlink ml-1"></i></a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-3">
                    {{ $areas->links() }}
                </div>
            </div>
            @else
            <p class="mb-0 text-muted"><b class="mr-1">No areas found</b> <a href="javascript:void(0)" data-toggle="modal" data-target="#addNewAreaModal"><b>Add Area</b></a></p>
            @endif
        </div>
    </div>
</div>
<div id="assignAreasToStoreModal" class="modal fade">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span class="font-weight-bold">Select a store</span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body pb-2">
                <div class="form-group row">
                    <select class="form-control select-search select" name="store_id" id="dynamic_select" required>
                        <option value="">Select a store </option>
                        @foreach ($stores as $store)
                        <option value="{{ route('dap.assignAreasToStore', $store->id) }}" class="text-capitalize">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="addNewAreaModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span class="font-weight-bold">Add New Area</span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="{{ route('dap.saveArea') }}" method="POST" enctype="multipart/form-data">
                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label">Name:</label>
                        <div class="col-lg-10">
                            <input type="text" name="name" class="form-control form-control-lg"
                                placeholder="Area name" required autocomplete="new-name">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 col-form-label">Description:</label>
                        <div class="col-lg-10">
                            <input type="text" name="description" class="form-control form-control-lg"
                                placeholder="Short description (optional)">
                        </div>
                    </div>
                    <div>
                        <div id="areaMap"></div>
                    </div>
                    <div class="form-group row">
                        {{-- <input type="text" name="areas"> --}}
                        <textarea name="geojson" class="form-control hidden" required="required"></textarea>
                    </div>
                    <div class="text-right hidden" id="saveGeoJson">
                        <button type="button" class="btn btn-success">
                        Process Data
                        <i class="icon-database-check ml-1"></i></button>
                    </div>
                    <div class="text-right hidden" id="saveAreaButton">
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
</div>
<script>
    "use strict";
    var coordinates = [];
    var GeoJSON = [];
    // For place API look into this-> https://developers.google.com/maps/documentation/javascript/examples/places-autocomplete
    function initMap() {
        const map = new google.maps.Map(document.getElementById("areaMap"), {
            center: {
                lat: 22.5726,
                lng: 88.3639
            },
            zoom: 5,
            minZoom: 1,
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
              console.log(obj);
                console.log("FINAL", obj)
                console.log(JSON.stringify(obj));
                $('textarea[name=geojson]').val(JSON.stringify(obj));
                setTimeout(function() {
                    $('#saveGeoJson').addClass('hidden');
                    $('#saveAreaButton').removeClass('hidden');
                }, 250);
            });
        })
    
    
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
            // if (areaName == undefined) areaName = " area";
            var confirmDialog = confirm("Do you want to delete this area?");
            if (confirmDialog == true) {
                map.data.remove(feature);
                $('#saveGeoJson').removeClass('hidden');
                $('#saveAreaButton').addClass('hidden');
            }
        });
    }

    $(function() {
        "use strict";

        $('.select').select2(); 

        // bind change event to select
        $('#dynamic_select').on('change', function () {
          console.log("here")
            var url = $(this).val(); // get selected value
            if (url) { // require a URL
                window.location = url; // redirect
            }
            return false;
        });
    });
</script>
@endif
@endsection