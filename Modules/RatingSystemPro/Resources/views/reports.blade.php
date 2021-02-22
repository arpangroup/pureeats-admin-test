@extends('admin.layouts.master')
@section('content')
    <style>
        .star-rating {
            line-height:32px;
            font-size:1.25em;
        }

        .star-rating .icon-star-full2{color: #ffc107;}
    </style>
    <div class="page-header">
        <div class="page-header-content header-elements-md-inline">
            <div class="page-title d-flex">
                <h4>
                    <span class="font-weight-bold mr-2">{{ @trans('ratingsystempro::default.modules') }}</span>
                    <i class="icon-circle-right2 mr-2"></i>
                    <span class="font-weight-bold mr-2">{{ @trans('ratingsystempro::default.rating_system') }}</span>
                </h4>
                <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
            </div>
            <div class="header-elements">
                <form action="{{ route('ratingsystempro.reports') }}" method="GET">
                    <div class="form-group row mb-0">
                        <div class="col-lg-5">
                            <select class="form-control selectRest" name="restaurant_id" style="width: 300px;">
                                <option></option>
                                @foreach ($restaurants as $restaurant_select)
                                    <option value="{{ $restaurant_select->id }}" @if( app('request')->input('restaurant_id') == $restaurant_select->id) selected @endif class="text-capitalize">{{ $restaurant_select->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-5">
                            <select class="form-control selectDelivery" name="delivery_guy_id">
                                <option></option>
                                @foreach ($delivery_guys as $delivery_guy)
                                    <option value="{{ $delivery_guy->id }}" @if( app('request')->input('delivery_guy_id') == $delivery_guy->id) selected @endif class="text-capitalize">{{ $delivery_guy->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-search4"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="content">

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        @if(!empty($ratingStores) && count($ratingStores))
                            <div class="row">
                                <div class="col-lg-12">
                                    <h1 class="mb-0">{{ $ratingStores[0]->restaurant->name }}</h1>
                                </div>
                                <div class="col-lg-6">
                                    <p class="mb-0">Average Restaurant rating</p>
                                    <h1 class="display-4 font-weight-bolder mb-0">{{ number_format($ratingStores->average('rating'), 1) }}/<small class="text-muted">5</small></h1>
                                    <div class="star-rating">
                                        <span class="icon icon-star-empty3" data-rating="1"></span>
                                        <span class="icon icon-star-empty3" data-rating="2"></span>
                                        <span class="icon icon-star-empty3" data-rating="3"></span>
                                        <span class="icon icon-star-empty3" data-rating="4"></span>
                                        <span class="icon icon-star-empty3" data-rating="5"></span>
                                        <input type="hidden" name="whatever1" class="rating-value" value="{{ $ratingStores->average('rating') }}">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <br>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            5 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingStores->where('rating', 5)->count() }}</div>
                                    </div>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            4 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingStores->where('rating', 4)->count() }}</div>
                                    </div>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            3 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingStores->where('rating', 3)->count() }}</div>
                                    </div>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            2 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar" role="progressbar" style="width: 45%" aria-valuenow="45%%" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingStores->where('rating', 2)->count() }}</div>
                                    </div>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            1 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: 15%" aria-valuenow="1" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingStores->where('rating', 1)->count() }}</div>
                                    </div>
                                    <br>
                                </div>
                            </div>
                        @endif

                        <hr>

                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">N. Order</th>
                                    <th scope="col">Restaurant</th>
                                    <th scope="col">Delivery Guy</th>
                                    <th scope="col">Rating</th>
                                    <th scope="col">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ratingDataStores as $ratingDataStore)
                                    <tr>
                                        <th scope="row"><a href="{{ url('/admin/order/' . $ratingDataStore->order->unique_order_id) }}">{{ $ratingDataStore->order->unique_order_id }}</a></th>
                                        <td>{{ $ratingDataStore->restaurant->name }}</td>
                                        <td>{{ $ratingDataStore->order->accept_delivery->user->name ?? '' }}</td>
                                        <td><i class="icon-star-full2 mr-1"></i> {{ $ratingDataStore->rating }}</td>
                                        <td>{{ $ratingDataStore->created_at->format('Y-m-d') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if(count($ratingDataStores))
                            {{ $ratingDataStores->links() }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        @if(!empty($ratingDeliverys) && count($ratingDeliverys))
                            <div class="row">
                                <div class="col-lg-12">
                                    <h1 class="mb-0">{{ $ratingDeliverys[0]->delivery_guy->name }}</h1>
                                </div>
                                <div class="col-lg-6">
                                    <p class="mb-0">Average Delivery Guy rating</p>
                                    <h1 class="display-4 font-weight-bolder mb-0">{{ number_format($ratingDeliverys->average('rating'), 1) }}/<small class="text-muted">5</small></h1>
                                    <div class="star-rating">
                                        <span class="icon icon-star-empty3" data-rating="1"></span>
                                        <span class="icon icon-star-empty3" data-rating="2"></span>
                                        <span class="icon icon-star-empty3" data-rating="3"></span>
                                        <span class="icon icon-star-empty3" data-rating="4"></span>
                                        <span class="icon icon-star-empty3" data-rating="5"></span>
                                        <input type="hidden" name="whatever1" class="rating-value" value="{{ $ratingDeliverys->average('rating') }}">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <br>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            5 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingDeliverys->where('rating', 5)->count() }}</div>
                                    </div>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            4 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingDeliverys->where('rating', 4)->count() }}</div>
                                    </div>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            3 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingDeliverys->where('rating', 3)->count() }}</div>
                                    </div>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            2 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar" role="progressbar" style="width: 45%" aria-valuenow="45%%" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingDeliverys->where('rating', 2)->count() }}</div>
                                    </div>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-right">
                                            1 <i class="icon-star-full2"></i>
                                        </div>
                                        <div class="col-7">
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: 15%" aria-valuenow="1" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-1 p-0">{{ $ratingDeliverys->where('rating', 1)->count() }}</div>
                                    </div>
                                    <br>
                                </div>
                            </div>
                        @endif

                        <hr>

                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th scope="col">N. Order</th>
                                <th scope="col">Restaurant</th>
                                <th scope="col">Delivery Guy</th>
                                <th scope="col">Rating</th>
                                <th scope="col">Date</th>
                            </tr>
                            </thead>
                            <tbody>
                                @foreach($ratingDataDeliverys as $ratingDataDelivery)
                                    <tr>
                                        <th scope="row"><a href="{{ url('/admin/order/' . $ratingDataDelivery->order->unique_order_id) }}">{{ $ratingDataDelivery->order->unique_order_id }}</a></th>
                                        <td>{{ $ratingDataDelivery->order->restaurant->name ?? '' }}</td>
                                        <td>{{ $ratingDataDelivery->delivery_guy->name ?? '' }}</td>
                                        <td><i class="icon-star-full2 mr-1"></i> {{ $ratingDataDelivery->rating }}</td>
                                        <td>{{ $ratingDataDelivery->created_at->format('Y-m-d') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if(count($ratingDataDeliverys))
                            {{ $ratingDataDeliverys->appends( \Illuminate\Support\Arr::except(Request::query(), 'delivery_guy'))->links() }}
                        @endif
                    </div>
                </div>
            </div>
        </div>


    </div>
    <script>
        $('.selectRest').select2({
            placeholder: 'Select Store',
            allowClear: true,
            width: "200px"
        });

        $('.selectDelivery').select2({
            placeholder: 'Select Delivery Guy',
            allowClear: true,
            width: "200px"
        });

        var $star_rating = $('.star-rating .icon');

        var SetRatingStar = function() {
            return $star_rating.each(function() {
                if (parseInt($star_rating.siblings('input.rating-value').val()) >= parseInt($(this).data('rating'))) {
                    return $(this).removeClass('icon-star-empty3').addClass('icon-star-full2');
                } else {
                    return $(this).removeClass('icon-star-full2').addClass('icon-star-empty3');
                }
            });
        };

        // $star_rating.on('click', function() {
        //     $star_rating.siblings('input.rating-value').val($(this).data('rating'));
        //     return SetRatingStar();
        // });

        SetRatingStar();
        $(document).ready(function() {

        });
    </script>
@endsection
