<?php

namespace App\Http\Controllers;

use App\Item;
use App\PromoSlider;
use App\Restaurant;
use App\StoreWarning;
use App\User;
use Cache;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\DeliveryAreaPro\DeliveryArea;
use Modules\SuperCache\SuperCache;
use Nwidart\Modules\Facades\Module;

use ErrorCode;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use Ixudra\Curl\Facades\Curl;
use Tymon\JWTAuth\Providers\Auth\Illuminate;


class HomeController extends Controller
{

    public function loadHome(Request $request)//latitude, longitude, delivery_type
    {
        if($request->latitude == null) throw new ValidationException(ErrorCode::BAD_REQUEST, "Invalid latitude");
        if($request->longitude == null) throw new ValidationException(ErrorCode::BAD_REQUEST, "Invalid longitude");
        $lat = $request->latitude;
        $lng = $request->longitude;
        $deliveryType = isset($request->delivery_type) ? $request->delivery_type : 1;// default is DELIVERY
        $restaurants = [];
        $sliders = [];
        $coupons = [];

        // step1: Get the restaurants
        try{
            $restaurantResponse = json_decode(json_encode(app()->call('App\Http\Controllers\RestaurantController@getDeliveryRestaurants', [])), true);
            $restaurants = $restaurantResponse['original']['data'];

        }catch (\Throwable $th) {
            //Log::channel('orderlog')->info('ExceptionInValidation: ' .$th->getMessage());
            //throw new ValidationException(ErrorCode::BAD_REQUEST, "Something error happened");
        }

        // step2: Get the sliders
        try{
            if($restaurants != null && isset($request->slider) && $request->slider== 1){
                $sliderResponse = json_decode(json_encode(app()->call('App\Http\Controllers\PromoSliderController@promoSlider', [])), true);
                $sliders = $sliderResponse['original']['data'];
            }
        }catch (\Throwable $th){

        }

        // step2: Get the coupons
        try{
            if($restaurants != null && isset($request->sliders) && $request->sliders == 1){
                $coupons = [];
            }
        }catch (\Throwable $th){

        }

        $storeDisplayTypes = ['STORE_WITH_FULL_WIDTH_IMAGE_LAYOUT', 'STORE_WITH_SMALL_IMAGE_LAYOUT', 'STORE_GRID_LAYOUT'];

        return response()->json([
            'success' => true,
            'message' =>'Total ' .sizeof($restaurants) .' restaurants found',
            'store_display_type' => $storeDisplayTypes[0],
            'restaurants' => $restaurants,
            'promo_sliders' => $sliders,
        ]);

    }

};
