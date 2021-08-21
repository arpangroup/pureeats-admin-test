<?php

namespace App\Http\Controllers;

use App\Http\Services\RestaurantService;
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



class RestaurantControllerV1 extends Controller
{

    private $storeService;
    public function __construct(RestaurantService $restaurantService){
        $this->storeService = $restaurantService;
    }



    /**
     * @param $name
     * @param $data
     */
    private function processSuperCache($name, $data = null)
    {
        if (Module::find('SuperCache') && Module::find('SuperCache')->isEnabled()) {
            $superCache = new SuperCache();
            $superCache->cacheResponse($name, $data);
        }
    }


    /**
     * @param $latitudeFrom
     * @param $longitudeFrom
     * @param $restaurant
     * @return boolean
     */
    private function checkOperation($latitudeFrom, $longitudeFrom, $restaurant)
    {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($restaurant->latitude);
        $lonTo = deg2rad($restaurant->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        $distance = $angle * 6371; //distance in km

        //if any delivery area assigned
        if (count($restaurant->delivery_areas) > 0) {
            //check if delivery pro module exists,
            if (Module::find('DeliveryAreaPro') && Module::find('DeliveryAreaPro')->isEnabled()) {
                $dap = new DeliveryArea();
                return $dap->checkArea($latitudeFrom, $longitudeFrom, $restaurant->delivery_areas);
            } else {
                //else use geenral distance
                if ($distance <= $restaurant->delivery_radius) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            //if no delivery areas, then use general distance
            if ($distance <= $restaurant->delivery_radius) {
                return true;
            } else {
                return false;
            }
        }
    }


    private function getAllStores($isActive = true){
        $isEnable = false;
        $restaurants = [];
        Cache::forget('stores-active');
        Cache::forget('stores-inactive');


        if($isActive){
            if (Cache::has('stores-active') && $isEnable) {
                $activeRestaurants = Cache::get('stores-active');
            }else{
                $activeRestaurants = Restaurant::where('is_accepted', '1')
                    ->where('is_active', 1)
                    ->whereIn('delivery_type', [1, 2, 3])
                    ->with(['coupons' => function($query){
                        //$query->where('is_exclusive', 1);
                        $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                        //$query->take(1);
                        //$query->get();
                    }])
                    ->ordered()
                    ->get();
                $this->processSuperCache('stores-active', $activeRestaurants);
            }
            $restaurants = $activeRestaurants;
        }else{
            if (Cache::has('stores-inactive') && $isEnable) {
                $inactiveRestaurants = Cache::get('stores-inactive');
            } else {
                $inactiveRestaurants = Restaurant::where('is_accepted', '1')
                    ->where('is_active', 0)
                    ->whereIn('delivery_type', [1, 2, 3])
                    ->with(['coupons' => function($query){
                        $query->where('is_exclusive', 1);
                        $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                        $query->take(1);
                    }])
                    ->ordered()
                    ->get();
                $this->processSuperCache('stores-inactive', $inactiveRestaurants);
            }
            $restaurants = $inactiveRestaurants;
        }

        return $restaurants;
    }

    private function getNearestStores($lat, $lng){
        $activeRestaurants = $this->getAllStores(true);
        $inactiveRestaurants = $this->getAllStores(false);

        /**
         * ACTIVE Restaurants
         */
        $nearMeActive = new Collection();
        if (config('settings.enDistanceMatrixDeliveryTime') == 'true') {
            foreach ($activeRestaurants as $restaurant) {
                $distanceMatrixResponse = $this->getGoogleDistance($lat, $lng, $restaurant);
                $distance = ($distanceMatrixResponse['distance']['value']) /1000;
                if ($distance <= $restaurant->delivery_radius){
                    $deliveryTimeInSecond = $distanceMatrixResponse['duration']['value'];
                    $deliveryTimeInMin = ($deliveryTimeInSecond /60) + ((int)$restaurant->delivery_time);

                    //$restaurant['deliveryTimeVal']      =  $deliveryTimeInMin;
                    //$restaurant['deliveryTimeText']     =  $deliveryTimeInMin .' mins';
                    //$restaurant['deliveryDistanceVal']  =  $distanceMatrixResponse['distance']['value'];
                    //$restaurant['deliveryDistanceText'] =  $distanceMatrixResponse['distance']['text'];
                    //$restaurant['delivery_time_text'] = ceil($deliveryTimeInMin);
                    $restaurant['delivery_time'] = ceil($deliveryTimeInMin);
                    $restaurant['delivery_distance'] = (double)number_format($distanceMatrixResponse['distance']['value'], 2) ;
                    $restaurant['delivery_distance_text'] = number_format($distanceMatrixResponse['distance']['text'], 2) .' ';
                    $nearMeActive->push($restaurant);
                }
            }

        }else{
            foreach ($activeRestaurants as $restaurant) {
                $check = $this->checkOperation($lat, $lng, $restaurant);
                if ($check) {
                    // Calculate the delivery time based on geographical distance
                    $distanceInKm = $this->getDistance($lat, $lng, $restaurant->latitude, $restaurant->longitude);
                    $actualDeliveryTime = $distanceInKm * ((int)config('settings.approxDeliveryTimePerKm')) ;
                    $restaurantDeliveryTime = $actualDeliveryTime + ((int)$restaurant->delivery_time);

                    //$restaurant['deliveryTimeVal']      =  $restaurantDeliveryTime;
                    //$restaurant['deliveryTimeText']     =  $restaurantDeliveryTime .' mins';
                    //$restaurant['deliveryDistanceVal']  =  $distance;
                    //$restaurant['deliveryDistanceText'] =  $distance .' Km';
                    $restaurant['delivery_time'] = ceil($restaurantDeliveryTime);
                    $restaurant['delivery_distance'] = number_format($distanceInKm * 1000, 2) ;//convert to meter
                    $restaurant['delivery_distance_text'] = number_format($distanceInKm *1000 ,2) .' meter';
                    $nearMeActive->push($restaurant);
                }
            }
        }

        /**
         * INACTIVE Restaurants
         */
        $nearMeInActive = new Collection();
        foreach ($inactiveRestaurants as $inactiveRestaurant) {
            $distanceInKm = $this->getDistance($lat, $lng, $inactiveRestaurant->latitude, $inactiveRestaurant->longitude);
            $deliveryTime = $distanceInKm * 3 ;// 3 min per km
            $inactiveRestaurant['delivery_time'] =(int) ($deliveryTime + ((int)    $inactiveRestaurant->delivery_time));
            $inactiveRestaurant['delivery_distance'] = number_format($distanceInKm * 1000, 2) ;//convert to meter
            $inactiveRestaurant['delivery_distance_text'] = number_format($distanceInKm *1000, 2) .' meter';
            $check = $this->checkOperation($lat, $lng, $inactiveRestaurant);
            if ($check) {
                $nearMeInActive->push($inactiveRestaurant);
            }
        }

        /**
         * Suffle the ACTIVE Restaurants
         */
        $nearMeActive = $nearMeActive->toArray();
        if (config('settings.randomizeStores') == 'true') {
            shuffle($nearMeActive);
            usort($nearMeActive, function ($left, $right) {
                return $right['is_featured'] - $left['is_featured'];
            });
        }


        $nearMeInActive = $nearMeInActive->toArray();

        $merged = array_merge($nearMeActive, $nearMeInActive);
        return $merged;
    }


    //http://192.168.0.100:8000/api/restaurants?lat=21.9726545&lng=87.3725539
    public function getRestaurants(Request $request){
        $nearestStores = $this->getNearestStores($request->lat, $request->lng);
        return $this->storeService->getAllStores();

        $restaurants = new Collection();
        foreach ($nearestStores as $store) {
            $restaurant = $this->mapTo($store);
            $restaurants->push($restaurant);
        }


        return response()->json([
            'success' => true,
            'message' =>'Total ' .sizeof($nearestStores) .' restaurants found',
            'data' => $restaurants
        ]);
    }

    //api/restaurants?id=1&slug=&lat=21.9726545&lng=87.3725539
    public function getRestaurantInfo(Request $request){
        $id = isset($request['id']) ? $request['id'] : null;
        $slug = isset($request['slug']) ? $request['slug'] : null;
        $lat = isset($request['lat']) ? $request['lat'] : null;
        $lng = isset($request['lng']) ? $request['lng'] : null;
        //return ['id' => $id, 'slug' => $slug, 'lat'=>$lat, 'lng'=> $lng ];
        return $this->storeService->sayHello();


        if($id == null && $slug == null){ // check id/slug passed or not

            throw new ValidationException(ErrorCode::BAD_REQUEST, "query parameter id should not be null or empty");
        }

        if($lat != null && !is_numeric($lat) ){// check valid latitude or not
            throw new ValidationException(ErrorCode::BAD_REQUEST, "invalid query parameter lat, it should be a valid numeric value");
        }

        if($lng != null && !is_numeric($lng)  ){// check valid longitude or not
            throw new ValidationException(ErrorCode::BAD_REQUEST, "invalid query parameter lng, it should be a valid numeric value");
        }

        $restaurantInfo = $this->getRestaurantDetails($id, $slug, $lat, $lng);
        //return response()->json(['key'=>'val']);
        return response()->json($restaurantInfo);
    }

    public function getRestaurantDetails($id, $slug, $lat, $lng){

        if($id == null && $slug == null) throw new ValidationException(ErrorCode::BAD_REQUEST, "query parameter id should not be null or empty");
        $restaurantInfo = [];


        $slugStr = ($id != null) ? 'id='.$id : 'slug='.$slug;
        if($lat != null && $lng != null){
            $slugStr .= '&lat=' .$lat .'&lng='.$lng;
        }
        //return $slugStr;

        /*
        // LatLng is not null so return the neared=s
        if($lat != null && $lng != null){
            $nearestStores = $this->getNearestStores($lat, $lng);
            if($id != null){// id passed: &id=1

                foreach ($nearestStores as $store){
                    if($store['id'] == $id){
                        $restaurantInfo = $store;
                        break;
                    }
                }
            }else{// slug passed: &slug=pureeats-restaurant
                foreach ($nearestStores as $store){
                    if($store['slug'] == $slug){
                        $restaurantInfo = $store;
                        break;
                    }
                }
            }
        }
        */

        if (Cache::has('store-info-' . $slugStr)) {
            $restaurantInfo = Cache::get('store-info-' . $slugStr);
        } else {
            if($id != null){
                $restaurantInfo = Restaurant::where('id', $id)
                    ->with(['coupons' => function($query){
                        $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                        //$query->take(1);
                        //$query->get();
                    }])
                    ->first();
            }else{
                $restaurantInfo = Restaurant::where('slug', $slug)
                    ->with(['coupons' => function($query){
                        //$query->where('is_exclusive', 1);
                        $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                        //$query->take(1);
                        //$query->get();
                    }])
                    ->first();
            }
            //////////////
            /// //////////////////
            if (config('settings.enDistanceMatrixDeliveryTime') == 'true') {
                foreach ($activeRestaurants as $restaurant) {
                    $distanceMatrixResponse = $this->getGoogleDistance($lat, $lng, $restaurant);
                    $distance = ($distanceMatrixResponse['distance']['value']) /1000;
                    if ($distance <= $restaurant->delivery_radius){
                        $deliveryTimeInSecond = $distanceMatrixResponse['duration']['value'];
                        $deliveryTimeInMin = ($deliveryTimeInSecond /60) + ((int)$restaurant->delivery_time);

                        //$restaurant['deliveryTimeVal']      =  $deliveryTimeInMin;
                        //$restaurant['deliveryTimeText']     =  $deliveryTimeInMin .' mins';
                        //$restaurant['deliveryDistanceVal']  =  $distanceMatrixResponse['distance']['value'];
                        //$restaurant['deliveryDistanceText'] =  $distanceMatrixResponse['distance']['text'];
                        //$restaurant['delivery_time_text'] = ceil($deliveryTimeInMin);
                        $restaurant['delivery_time'] = ceil($deliveryTimeInMin);
                        $restaurant['delivery_distance'] = (double)number_format($distanceMatrixResponse['distance']['value'], 2) ;
                        $restaurant['delivery_distance_text'] = number_format($distanceMatrixResponse['distance']['text'], 2) .' ';
                        $nearMeActive->push($restaurant);
                    }
                }

            }else{
                foreach ($activeRestaurants as $restaurant) {
                    $check = $this->checkOperation($lat, $lng, $restaurant);
                    if ($check) {
                        // Calculate the delivery time based on geographical distance
                        $distanceInKm = $this->getDistance($lat, $lng, $restaurant->latitude, $restaurant->longitude);
                        $actualDeliveryTime = $distanceInKm * ((int)config('settings.approxDeliveryTimePerKm')) ;
                        $restaurantDeliveryTime = $actualDeliveryTime + ((int)$restaurant->delivery_time);

                        //$restaurant['deliveryTimeVal']      =  $restaurantDeliveryTime;
                        //$restaurant['deliveryTimeText']     =  $restaurantDeliveryTime .' mins';
                        //$restaurant['deliveryDistanceVal']  =  $distance;
                        //$restaurant['deliveryDistanceText'] =  $distance .' Km';
                        $restaurant['delivery_time'] = ceil($restaurantDeliveryTime);
                        $restaurant['delivery_distance'] = number_format($distanceInKm * 1000, 2) ;//convert to meter
                        $restaurant['delivery_distance_text'] = number_format($distanceInKm *1000 ,2) .' meter';
                        $nearMeActive->push($restaurant);
                    }
                }
            }
            /// ////////////////////////////
            /// ////////////////////////////////////////////

            //$this->processSuperCache('store-info-' . $slugStr, $restaurantInfo);
        }


        return $restaurantInfo;
    }

    public function mapTo($store){
        $store = json_encode($store, JSON_FORCE_OBJECT);
        $store = json_decode($store);
        $response = [
            'store_id' => (double)$store->id, //long
            'name' => $store->name,
            'localized_name' => '',
            'description' => $store->description,
            'localized_description' => '',
            'slug' => $store->slug,
            'contact_number' => (double)$store->contact_number, //long
            'image' => url('/'). $store->image,
            'thumb' => '',
            'store_type' => 'RESTAURANT',
            'delivery_type' => $this->mapToDeliveryType($store),//[DELIVERY, TAKEAWAY, DELIVERY_AND_TAKEAWAY]
            'price_range' => $store->price_range == null ? '' : $store->price_range,//string
            'average_cost_for_two' => '', //string
            'is_pureveg' => (boolean)$store->is_pureveg,
            'open_table_support' => false,
            'is_table_reservation_supported' => false,
            'is_perm_closed' => (bool)($store->is_active == 0),// this store will be show as disabled, no need to show the opening/closing time
            'is_temp_closed' => (bool)($store->is_accepted == 0),//will show opening/closing time
            'is_opening_soon' => false,//true<==If open today otherwise it is false
            'disclaimer_text' => '',
            'delivery_charge' => $this->mapToDeliveryCharge($store),
            'store_charge' => $this->mapToStoreCharge($store),
            'rating' => $this->mapToRating($store),
            'location' => $this->mapToLocation($store),
            'order_details' => $this->mapToOrderDetails($store),//<================ is_serviceable<===
            'timing' => $this->mapToTiming($store),
            'takeaway_details' => null,
            'items' => [],
            'coupons' => $this->mapCoupons($store),
        ];

        return $response;

    }



    private function mapToRating($store){
        $rating = [
            'rating_type' =>'STORE',
            'rating' => (float)$store->rating,
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
        $scheduleData = $this->getScheduleData($store);
        $timing = [
            'timing_desc' => $scheduleData['status'],//"6:30am – 10pm (Today)";//5 PM to 1 AM (Mon-Sun)
            'is_open' => $scheduleData['is_open'],
            'customised_timings' =>json_decode($store->schedule_data, true)
        ];
        return $timing;
    }
    private function mapToDeliveryCharge($store){
        $dynamicDeliveryCharge = null;
        if($store->delivery_charge_type == 'DYNAMIC'){
            $dynamicDeliveryCharge = [
                'base_delivery_charge' => (float)$store->base_delivery_charge,
                'base_delivery_distance' => (int)$store->base_delivery_distance,
                'extra_delivery_charge' => (float)$store->extra_delivery_charge,
                'extra_delivery_distance' => (int)$store->extra_delivery_distance,
            ];
        }

        $deliveryCharge = [
            'delivery_charge_type' => isset($store->delivery_charge_type) ? $store->delivery_charge_type : 'FIXED',//[DYNAMIC, FIXED]
            'fixed_delivery_charge' => (float)$store->delivery_charges,
            'dynamic_delivery_charge' => $dynamicDeliveryCharge,
        ];
        return $deliveryCharge;
    }
    private function mapToStoreCharge($store){
        $storeCharge = [
            'store_charge_type' => isset($store->store_charge_type) ? $store->store_charge_type : 'FIXED',//[ DYNAMIC, FIXED, PERCENTAGE]
            'calculation_type' => 'PERCENTAGE',
            'fixed_store_charge' => (float)$store->restaurant_charges,
            'dynamic_store_charge' => null
        ];
        return $storeCharge;
    }
    private function mapToOrderDetails($store){
        $orderDetails = [
            'is_serviceable' => true,//<===========================
            'delivery_time' => (int)$store->delivery_time,
            'delivery_time_text' => $store->delivery_time .' min',//'92 min',
            'min_order_price' => (float)$store->min_order_price,
            'delivery_distance' => $store->delivery_distance,
            'delivery_distance_text' => $store->delivery_distance_text,
        ];
        return $orderDetails;
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
    private function mapCoupons($store){
        $coupons = new Collection();
        foreach ($store->coupons as $coupon){
            $couponObj = [
                'name' => $coupon->name,//16% OFF
                //'promocode' => $coupon->code,//WELCOMENEW
                'description' => $coupon->description,//
                'min_subtotal' => (int)$coupon->min_subtotal,
                'is_exclusive' => (bool)$coupon->is_exclusive
            ];
            $coupons->push($couponObj);
        }
        return $coupons;

    }




    private function getGoogleDistance($latitudeFrom, $longitudeFrom, $restaurant)
    {       
        $lat1 = $latitudeFrom;
        $long1 = $longitudeFrom;
        $lat2 = $restaurant->latitude;
        $long2 = $restaurant->longitude;
        $API_KEY = 'AIzaSyCt_14My2CYghVw6eZFSYFlFPBOK29lkww';
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=".$lat1.",".$long1."&destinations=".$lat2.",".$long2."&alternatives=true&mode=driving&language=de-DE&sensor=false&key=".$API_KEY;
        $curl = Curl::to($url)->get();

        $curl = json_decode($curl, true);
        //$response_a = json_decode($response, true);

        // $distance = $curl['rows'][0]['elements'][0]['distance'];
        // $time = $curl['rows'][0]['elements'][0]['duration'];
        // $distance = $curl->rows[0]->elements;

        return array(
            'distance' => $curl['rows'][0]['elements'][0]['distance'],
            'duration' => $curl['rows'][0]['elements'][0]['duration']
        );

    }
    private static function compareByTimeStamp($time1, $time2) { 
        if (strtotime($time1['open']) < strtotime($time2['open'])) 
            return -1; 
        else if (strtotime($time1['open']) > strtotime($time2['open']))  
            return 1; 
        else
            return 0; 
    }
    private function getScheduleData($restaurant){
        //return $restaurant->schedule_data;
        $scheduleData = json_decode($restaurant->schedule_data, true);
        
        // get current day and time
        $todayDateTime = date("d-m-Y H:i:s");
        $currTime = date('H:i', strtotime($todayDateTime));
        $nameOfToDay = strtolower(date('l', strtotime($todayDateTime)));

        $status = array( 'is_open' => 0, 'status' => 'closed');// 0=>closed


        if ($scheduleData && array_key_exists($nameOfToDay, $scheduleData)){            
            // get todays slot
            $todaysSlots = $scheduleData[$nameOfToDay];        
            usort($todaysSlots, array($this, 'compareByTimeStamp')); // sort the array
            if($todaysSlots){
                // openNow:
                foreach($todaysSlots as $slot){
                    //$openTime = $slot['open'] .':00';
                    //$closeTime = $slot['close'] .':00';
                    $openTime = $slot['open'];
                    $closeTime = $slot['close'];
                    if((strtotime($openTime) < strtotime($currTime)) && (strtotime($currTime) < strtotime($closeTime)) ) {
                        $status = array( 'is_open' => 1, 'status' => 'open now');//1=>open


                        $diffInSec = strtotime($slot['close']) - strtotime($currTime);
                        $diffInMin = abs($diffInSec / 60);
                        if($diffInMin > 0 && $diffInMin <30){
                            $status = array( 'is_open' => 2, 'status' => 'close in ' .$diffInMin . ' minutes');//2=>will close soon
                        }
                        return $status;
                    }
                }


                // WillOpen:
                $lastSlot = end($todaysSlots);
                foreach($todaysSlots as $slot){
                    if(strtotime($currTime) < strtotime($openTime)){
                        $status = array( 'is_open' => 0, 'status' => 'will open at ' .$slot['open']);

                        $diffInSec = strtotime($slot['close']) - strtotime($currTime);
                        $diffInMin = abs($diffInSec / 60);
                        if($diffInMin > 0 && $diffInMin <59){
                            $status = array( 'is_open' => 0, 'status' => 'will open in ' .$diffInMin . ' minutes');//
                        }
                    }else if(strtotime($currTime) > strtotime($lastSlot['close'])){
                        $status = array( 'is_open' => 0, 'status' => 'will open tomorrow ');
                    }
                }            
            
            }
        }else{
            return $status;
        }
        return $status;

    }
    private function getDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo)
    {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * 6371;
    }


}
