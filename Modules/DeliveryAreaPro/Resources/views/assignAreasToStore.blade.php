@extends('admin.layouts.master')
@section("title") Assign Area - Delivery Area Pro
@endsection
@section('content')
<link href="{{substr(url("/"), 0, strrpos(url("/"), '/'))}}/Modules/DeliveryAreaPro/Resources/assets/app.css" rel="stylesheet" type="text/css">
<div class="page-header">
    <div class="page-header-content header-elements-md-inline">
        <div class="page-title d-flex">
            <h4>
                <span class="font-weight-bold mr-2">Modules</span>
                <i class="icon-circle-right2 mr-2"></i>
                <span class="font-weight-bold mr-2"><a href="{{ route('dap.settings') }}">Delivery Area Pro</a></span>
                <i class="icon-circle-right2 mr-2"></i>
                <span class="font-weight-bold mr-2">{{ $restaurant->name }}</span>
            </h4>
            <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
        </div>
        <div class="header-elements d-none py-0 mb-3 mb-md-0">
            <div class="breadcrumb">
                <a class="btn btn-secondary btn-labeled btn-labeled-left mr-2" href="{{ route('admin.get.editRestaurant', $restaurant->id)."#operationAreaSettings" }}">
                <b><i class="icon-arrow-left15"></i></b>
                Go back to editing
                </a>
            </div>
        </div>
    </div>
</div>
<div class="content">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <legend class="font-weight-semibold text-uppercase font-size-sm">
                    <i class="icon-hyperlink mr-2"></i> Assign Areas
                </legend>
                <div class="form-group row form-group-feedback form-group-feedback-right">
                    @if(count($restaurant->delivery_areas) === 0)
                    <div class="col-lg-9">
                        <p class="text-warning"><b>{{ $restaurant->name }}</b> is not assigned to any area for operation.</p>
                    </div>
                    @else
                    <br>
                    <div class="col-lg-9">
                        <p><strong>{{ $restaurant->name }}</strong> is operating in <strong>{{ $restaurant->delivery_areas->count() }} </strong> areas.</p>
                        @foreach($restaurant->delivery_areas as $deliveryArea)
                        <span class="badge badge-flat border-grey-800 mr-1 mb-2" style="font-size: 0.9rem;">{{ $deliveryArea->name }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-12" id="manageAreasBlock" style="display: none;">
        <div class="form-group form-group-feedback form-group-feedback-right search-box">
            <input type="text" class="form-control form-control-lg search-input"
                placeholder="Filter with area name...">
            <div class="form-control-feedback form-control-feedback-lg">
                <i class="icon-search4"></i>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="col-lg-12">
                    <form action="{{ route('dap.updateStoreArea') }}" method="POST">
                        <input type="hidden" name="id" value="{{ $restaurant->id }}">
                        <div class="text-left">
                            <legend class="font-weight-semibold text-uppercase font-size-sm">
                                <i class="icon-map5 mr-2"></i> Available Areas
                            </legend>
                        </div>
                        <div class="clearfix"></div>
                        <div class="text-right mb-4">
                            <button type="button" class="btn btn-primary btn-labeled btn-labeled-left btn-sm" id="checkAll" data-popup="tooltip" title="Double Click to Check All" data-placement="left">
                            <b><i class="icon-check ml-1"></i></b>
                            Check All
                            </button>
                            <button type="button" class="btn btn-primary btn-labeled btn-labeled-left btn-sm" id="unCheckAll" data-popup="tooltip" title="Double Click to Un-check All" data-placement="top">
                            <b><i class="icon-cross3 ml-1"></i></b>
                            Un-check All
                            </button>
                        </div>
                        <div class="assigning-checkboxes mt-3">
                            @foreach($areas as $area)
                            <label>
                            <input type="checkbox" data-name="{{ $area->name }}" name="delivery_areas[]" value="{{ $area->id }}" @if(in_array($area->id, $restaurantAreaIds)) checked="checked" @endif />
                            <span data-popup="tooltip" title="{{ $area->description }}" data-placement="top">{{ $area->name }}</span>
                            </label>
                            @endforeach
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-primary btn-labeled btn-labeled-left btn-lg">
                            <b><i class="icon-database-insert ml-1"></i></b>
                            UPDATE
                            </button>
                        </div>
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(function() {
        "use strict";
        $('#manageAreasBlock').show(300);
        
        $('.assigning-checkboxes label').each(function(){
            $(this).attr('data-name', $(this).text().toLowerCase());
        });
    
        $('.search-input').on('keyup', function(){
        var searchTerm = $(this).val().toLowerCase();
            $('.assigning-checkboxes label').each(function(){
                if ($(this).filter('[data-name *= ' + searchTerm + ']').length > 0 || searchTerm.length < 1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        $('#checkAll').dblclick(function(event) {
            $("input:checkbox").prop("checked", true);
        });
        $('#unCheckAll').dblclick(function(event) {
            $("input:checkbox").prop("checked", false);
        });
    }); 
</script>
@endsection