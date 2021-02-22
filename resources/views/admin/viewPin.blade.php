@extends('admin.layouts.master')
@section("title") View - Order Pin's
@endsection

@section('styles')
    {{-- <script src="https://code.jquery.com/jquery-3.5.1.js"></script> --}}
    <script>
        $(document).ready(function() {
            $('#table').DataTable();
        });
    </script>
@endsection


@section('content')
    <div class="page-header">
        <div class="page-header-content header-elements-md-inline">
            <div class="page-title d-flex">
                <h4><i class="icon-circle-right2 mr-2"></i>
                    <span class="font-weight-bold mr-2">TOTAL</span>
                    <span class="badge badge-primary badge-pill animated flipInX">{{ sizeof($orders) }}</span>
                </h4>
                <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
            </div>
        </div>
    </div>
    <div class="content">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="table" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                        <tr>
                            <th>SL</th>
                            <th>Pin</th>
                            <th>Order Id</th>
                            <th>Total</th>
                            <th>Time</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($orders as $index=>$order)
                            <tr>
                                <td>{{ $index+1 }}</td>
                                <td class="text-center">
                                <span style="font-size: 21px" class="badge badge-flat border-green-800 text-default text-capitalize">
                                {{$order->delivery_pin}}
                                </span>
                                </td>
                                <td>{{$order->unique_order_id}}</td>
                                <td>â‚¹ {{$order->total}}</td>
                                <td>{{ $order->created_at }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
