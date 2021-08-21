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

    public function mapTo($store){
        $response = [
            'store_id' => $store->id,
            'name' => $store->name,
            'localized_name' => '',
            'description' => $store->description,
            'localized_description' => '',
            'slug' => $store->slug,
            'contact_number' => $store->contact_number,
            'image' => $store->image,
            'thumb' => '',
            'store_type' => 'RESTAURANT',
            'delivery_type' => $this->mapToDeliveryType($store),//[DELIVERY, TAKEAWAY, DELIVERY_AND_TAKEAWAY]

            'price_range' => $store->price_range == null ? '' : $store->price_range,//string
            'average_cost_for_two' => '', //string

            'is_pureveg' => (boolean)$store->is_pureveg,
            'open_table_support' => false,
            'is_table_reservation_supported' => false,

            'is_perm_closed' => $store->id,//<=======================================
            'is_temp_closed' => $store->id,//<=======================================
            'is_opening_soon' => $store->id,//<=======================================
            'disclaimer_text' => '',

            'timing' => $this->mapToTiming($store),//<=======================================
            'delivery_charge' => $this->mapToDeliveryCharge($store),//<=======================================
            'store_charge' => $this->mapToStoreCharge($store),//<=======================================

            'rating' => $this->mapToRating($store),
            'location' => $this->mapToLocation($store),
            'order_details' => $this->mapToOrderDetails($store),//<=======================================
            'takeaway_details' => [],
            'items' => [],
            'coupons' => [],
        ];

        return $response;

    }

    private function mapToRating($store){
        $rating = [
            'rating_type' =>'STORE',
            'rating' => (float)$$store->rating,
            'votes' => (int) 120,
            'rating_subtitle' => 'Very Good'
        ];
        return $rating;
    }
    private function mapToLocation($store){
        $location = [
            'latitude' => (float)$store->latitude,
            'longitude' =>(float)$store->longitude,
            'address' => $store->address,
            'house' => $store->pincode,
            'landmark' => $store->landmark,
            'pincode' => '',
            'locality' => '',
            'city' => '',
            'zipcode' => ''
        ];
        return $location;
    }
    private function mapToTiming($store){
        $timing = [
            'timing_desc' => '',//"6:30am – 10pm (Today)";//5 PM to 1 AM (Mon-Sun)
            'customised_timings' =>[
            ]
        ];
        return $timing;
    }
    private function mapToDeliveryCharge($store){
        $deliveryCharge = [
            'delivery_charge_type' => '',//[DYNAMIC, FIXED]
            'fixed_delivery_charge' => (float) 0,
            'base_delivery_charge' => (float) 0,
            'base_delivery_distance' => (float) 0,
            'extra_delivery_charge' => (float) 0,
            'extra_delivery_distance' => (float) 0
        ];
        return $deliveryCharge;
    }
    private function mapToOrderDetails($store){
        $orderDetails = [
            'is_serviceable' => true,
            'delivery_time' => (int)$store->delivery_time,
            'delivery_time_text' => $store->delivery_time .' min',//'92 min',
            'min_order_price' => (float)$store->min_order_price
        ];
        return $orderDetails;
    }
    private function mapToStoreCharge($store){
        $storeCharge = [
            'store_charge_type' => '',//[ DYNAMIC, FIXED, PERCENTAGE]
            'calculation_type' => 'PERCENTAGE',
            'fixed_store_charge' => (float) 0,
            'base_store_charge' => (float) 0,
            'extra_store_charge' => (float) 0
        ];
        return $storeCharge;
    }
    private function mapToDeliveryType($store){
        if($store->delivery_type == 1){
            return 'DELIVERY';
        }else if($store->delivery_type == 2){
            return 'TAKEAWAY';
        }else{
            return 'DELIVERY_AND_TAKEAWAY';
        }
    }

};
