@extends('admin.layouts.master')
@section('content')
    <div class="page-header">
        <div class="page-header-content header-elements-md-inline">
            <div class="page-title d-flex">
                <h4>
                    <span class="font-weight-bold mr-2">Modules</span>
                    <i class="icon-circle-right2 mr-2"></i>
                    <span class="font-weight-bold mr-2">Rating System Pro Settings</span>
                </h4>
                <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
            </div>
            <div class="header-elements d-none py-0 mb-3 mb-md-0">

            </div>
        </div>
    </div>
    <div class="content">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                        <tr>
                            <td><strong>Header</strong></td>
                            <td>Code applied in Menu Header</td>
                            <td class="text-center">
                                @php $file1 = base_path('resources/views/admin/includes/header.blade.php'); @endphp
                                @if(!(strpos(file_get_contents($file1), '<!-- menuadminRatingSystemPro -->') === false))
                                    <span class="badge badge-success border-grey-800 text-white text-capitalize" style="min-width: 100px;"><i class="icon-database-check mr-1"></i> Configured </span>
                                @else
                                    <span class="badge badge-flat border-grey-800 text-default text-capitalize" style="min-width: 100px;"><i class="icon-database-remove mr-1"></i> Not Configured </span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Translation Edit</strong></td>
                            <td>Code applied in Translation Edit</td>
                            <td class="text-center">
                                @php $file2 = base_path('resources/views/admin/editTranslation.blade.php'); @endphp
                                @if(!(strpos(file_get_contents($file2), '<!-- moduleAdminTranslateRatingSystemPro --> ') === false))
                                    <span class="badge badge-success border-grey-800 text-white text-capitalize" style="min-width: 100px;"><i class="icon-database-check mr-1"></i> Configured </span>
                                @else
                                    <span class="badge badge-flat border-grey-800 text-default text-capitalize" style="min-width: 100px;"><i class="icon-database-remove mr-1"></i> Not Configured </span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Admin Order</strong></td>
                            <td>Code applied in Order</td>
                            <td class="text-center">
                                @php $file3 = base_path('app/Order.php'); @endphp
                                @if(!(strpos(file_get_contents($file3), '/*addisRatingCode*/') === false))
                                    <span class="badge badge-success border-grey-800 text-white text-capitalize" style="min-width: 100px;"><i class="icon-database-check mr-1"></i> Configured </span>
                                @else
                                    <span class="badge badge-flat border-grey-800 text-default text-capitalize" style="min-width: 100px;"><i class="icon-database-remove mr-1"></i> Not Configured </span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Restaurants</strong></td>
                            <td>Code applied in Restaurants Blade</td>
                            <td class="text-center">
                                @php $file4 = base_path('app/Restaurant.php'); @endphp
                                @if(!(strpos(file_get_contents($file4), '/*addRelationshipCode*/') === false))
                                    <span class="badge badge-success border-grey-800 text-white text-capitalize" style="min-width: 100px;"><i class="icon-database-check mr-1"></i> Configured </span>
                                @else
                                    <span class="badge badge-flat border-grey-800 text-default text-capitalize" style="min-width: 100px;"><i class="icon-database-remove mr-1"></i> Not Configured </span>
                                @endif
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="version">
                    v{{ $version }}
                </div>
            </div>
        </div>
    </div>
@endsection
