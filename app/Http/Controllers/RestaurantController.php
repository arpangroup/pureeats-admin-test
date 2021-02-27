<?php

namespace App\Http\Controllers;

use App\Item;
use App\Restaurant;
use App\User;
use Cache;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Modules\DeliveryAreaPro\DeliveryArea;
use Modules\SuperCache\SuperCache;
use Nwidart\Modules\Facades\Module;

use ErrorCode;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use Ixudra\Curl\Facades\Curl;

class RestaurantController extends Controller
{

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


    /**
     * Created By Arpan [11-Jan-2021]
     */
    private static function compareByTimeStamp($time1, $time2) { 
        if (strtotime($time1['open']) < strtotime($time2['open'])) 
            return -1; 
        else if (strtotime($time1['open']) > strtotime($time2['open']))  
            return 1; 
        else
            return 0; 
    } 


    
    /**
     * Created By Arpan [11-Jan-2021]
     */
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
     * @param Request $request
     * @return mixed
     */
    public function getDeliveryRestaurants(Request $request)
    {
        // Cache::forget('stores-delivery-active');
        // Cache::forget('stores-delivery-inactive');
        // die();

        // get all active restauants doing delivery
        if (Cache::has('stores-delivery-active')) {
            $restaurants = Cache::get('stores-delivery-active');
        } else {         
            $restaurants = Restaurant::where('is_accepted', '1')
                ->where('is_active', 1)
                //->whereIn('delivery_type', [1, 3])
                ->with(['coupons' => function($query){
                    $query->where('is_exclusive', 1);
                    $query->select('name', 'code')->get();
                    $query->take(1);
                }])
                ->ordered()
                ->get();
            $this->processSuperCache('stores-delivery-active', $restaurants);
        }

        //Create a new Laravel collection from the array data
        $nearMe = new Collection();


        if (config('settings.enDistanceMatrixDeliveryTime') == 'true') {
            foreach ($restaurants as $restaurant) {
                $distanceMatrixResponse = $this->getGoogleDistance($request->latitude, $request->longitude, $restaurant); 
                $distance = ($distanceMatrixResponse['distance']['value']) /1000;
                if ($distance <= $restaurant->delivery_radius){
                    $deliveryTimeInSecond = $distanceMatrixResponse['duration']['value'];    
                    $deliveryTimeInMin = ($deliveryTimeInSecond /60) + ((int)$restaurant->delivery_time);


                    $restaurant['schedule'] = $this->getScheduleData($restaurant);
                    //$restaurant['deliveryTimeVal']      =  $deliveryTimeInMin;   
                    //$restaurant['deliveryTimeText']     =  $deliveryTimeInMin .' mins';   
                    //$restaurant['deliveryDistanceVal']  =  $distanceMatrixResponse['distance']['value']; 
                    //$restaurant['deliveryDistanceText'] =  $distanceMatrixResponse['distance']['text'];
                    $restaurant['delivery_time'] = ceil($deliveryTimeInMin);  
                    $nearMe->push($restaurant); 
                }
            } 
                       
        }else{
            foreach ($restaurants as $restaurant) {
                //$distance = $this->getDistance($request->latitude, $request->longitude, $restaurant->latitude, $restaurant->longitude);               
                // if ($distance <= $restaurant->delivery_radius) {
                //     $nearMe->push($restaurant);
                // }            
                
                $check = $this->checkOperation($request->latitude, $request->longitude, $restaurant);
                if ($check) {
                    // Calculate the delivery time based on geographical distance
                    $distance = $this->getDistance($request->latitude, $request->longitude, $restaurant->latitude, $restaurant->longitude);
                    $actualDeliveryTime = $distance * ((int)config('settings.approxDeliveryTimePerKm')) ;
                    $restaurantDeliveryTime = $actualDeliveryTime + ((int)$restaurant->delivery_time);

                    $restaurant['schedule'] = $this->getScheduleData($restaurant);
                    //$restaurant['deliveryTimeVal']      =  $restaurantDeliveryTime;
                    //$restaurant['deliveryTimeText']     =  $restaurantDeliveryTime .' mins';  
                    //$restaurant['deliveryDistanceVal']  =  $distance;
                    //$restaurant['deliveryDistanceText'] =  $distance .' Km';  
                    $restaurant['delivery_time'] = ceil($restaurantDeliveryTime);
                    $nearMe->push($restaurant); 
                }
            }
        }
        


        // $nearMe = $nearMe->shuffle()->sortByDesc('is_featured');
        $nearMe = $nearMe->map(function ($restaurant) {
            return $restaurant->only(['id', 'name', 'description', 'image', 'rating', 'delivery_time', 'price_range', 'slug', 'is_featured', 'is_active', 'coupons', 'schedule', 'latitude', 'longitude']);
        });

        //return response()->json($nearMe);

        // $onlyInactive = $nearMe->where('is_active', 0)->get();
        // dd($onlyInactive);
        $nearMe = $nearMe->toArray();

        if (config('settings.randomizeStores') == 'true') {
            shuffle($nearMe);
            usort($nearMe, function ($left, $right) {
                return $right['is_featured'] - $left['is_featured'];
            });
        }

        if (Cache::has('stores-delivery-inactive')) {
            $inactiveRestaurants = Cache::get('stores-delivery-inactive');
        } else {
            $inactiveRestaurants = Restaurant::where('is_accepted', '1')
                ->where('is_active', 0)
                //->whereIn('delivery_type', [1, 3])
                ->with(['coupons' => function($query){
                    $query->where('is_exclusive', 1);
                    $query->select('name', 'code')->get();
                    $query->take(1);
                }])
                ->ordered()
                ->get();
            $this->processSuperCache('stores-delivery-inactive', $inactiveRestaurants);
        }

        $nearMeInActive = new Collection();
        foreach ($inactiveRestaurants as $inactiveRestaurant) {
            $distance = $this->getDistance($request->latitude, $request->longitude, $inactiveRestaurant->latitude, $inactiveRestaurant->longitude);
            $deliveryTime = $distance * 3 ;// 3 min per km
            $restaurant['delivery_time'] =(int) ($deliveryTime + ((int)    $restaurant->delivery_time));
            // if ($distance <= $inactiveRestaurant->delivery_radius) {
            //     $nearMeInActive->push($inactiveRestaurant);
            // }
            $inactiveRestaurant['schedule'] = $this->getScheduleData($inactiveRestaurant);
            $check = $this->checkOperation($request->latitude, $request->longitude, $inactiveRestaurant);
            if ($check) {
                $nearMeInActive->push($inactiveRestaurant);
            }
        }
        $nearMeInActive = $nearMeInActive->map(function ($restaurant) {
            return $restaurant->only(['id', 'name', 'description', 'image', 'rating', 'delivery_time', 'price_range', 'slug', 'is_featured', 'is_active', 'coupons', 'schedule','latitude', 'longitude']);
            // return $restaurant->only(['id', 'name', 'description', 'image', 'rating', 'delivery_time', 'price_range', 'slug', 'is_featured', 'is_active', 'coupons', 'schedule', 'latitude', 'longitude', 'deliveryTimeVal', 'deliveryTimeText', 'deliveryDistanceVal', 'deliveryDistanceText']);
        });
        $nearMeInActive = $nearMeInActive->toArray();

        $merged = array_merge($nearMe, $nearMeInActive);

        return response()->json([
            'success' => true,
            'message' =>'Total ' .sizeof($merged) .' restaurants found',
            'data' => $merged
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getSelfPickupRestaurants(Request $request)
    {
        // sleep(500);
        // get all active restauants doing selfpickups
        if (Cache::has('stores-selfpickup-active')) {
            $restaurants = Cache::get('stores-selfpickup-active');
        } else {
            $restaurants = Restaurant::where('is_accepted', '1')
                ->where('is_active', 1)
                ->whereIn('delivery_type', [2, 3])
                ->ordered()
                ->get();
            $this->processSuperCache('stores-selfpickup-active', $restaurants);
        }

        //Create a new Laravel collection from the array data
        $nearMe = new Collection();

        foreach ($restaurants as $restaurant) {
            $distance = $this->getDistance($request->latitude, $request->longitude, $restaurant->latitude, $restaurant->longitude);
            // if ($distance <= $restaurant->delivery_radius) {
            //     $nearMe->push($restaurant);
            // }
            $restaurant->distance = $distance;
            $check = $this->checkOperation($request->latitude, $request->longitude, $restaurant);
            if ($check) {
                $nearMe->push($restaurant);
            }
        }

        $nearMe = $nearMe->map(function ($restaurant) {
            return $restaurant->only(['id', 'name', 'description', 'image', 'rating', 'delivery_time', 'price_range', 'slug', 'is_featured', 'is_active', 'distance']);
        });

        $nearMe = $nearMe->toArray();
        if (config('settings.randomizeStores') == 'true') {
            shuffle($nearMe);
            usort($nearMe, function ($left, $right) {
                return $right['is_featured'] - $left['is_featured'];
            });
        }

        if (config('settings.sortSelfpickupStoresByDistance') == 'true') {
            $nearMe = collect($nearMe)->sortBy('distance')->toArray();
        }

        if (Cache::has('stores-selfpickup-inactive')) {
            $inactiveRestaurants = Cache::get('stores-selfpickup-inactive');
        } else {
            $inactiveRestaurants = Restaurant::where('is_accepted', '1')
                ->where('is_active', 0)
                ->whereIn('delivery_type', [2, 3])
                ->ordered()
                ->get();
            $this->processSuperCache('stores-selfpickup-inactive', $inactiveRestaurants);
        }

        $nearMeInActive = new Collection();
        foreach ($inactiveRestaurants as $inactiveRestaurant) {
            $distance = $this->getDistance($request->latitude, $request->longitude, $inactiveRestaurant->latitude, $inactiveRestaurant->longitude);
            // if ($distance <= $inactiveRestaurant->delivery_radius) {
            //     $nearMeInActive->push($inactiveRestaurant);
            // }
            $inactiveRestaurant->distance = $distance;
            $check = $this->checkOperation($request->latitude, $request->longitude, $inactiveRestaurant);
            if ($check) {
                $nearMeInActive->push($inactiveRestaurant);
            }
        }
        $nearMeInActive = $nearMeInActive->map(function ($restaurant) {
            return $restaurant->only(['id', 'name', 'description', 'image', 'rating', 'delivery_time', 'price_range', 'slug', 'is_featured', 'is_active', 'distance']);
        });
        $nearMeInActive = $nearMeInActive->toArray();

        if (config('settings.sortSelfpickupStoresByDistance') == 'true') {
            $nearMeInActive = collect($nearMeInActive)->sortBy('distance')->toArray();
        }

        $merged = array_merge($nearMe, $nearMeInActive);

        return response()->json($merged);
    }

    /**
     * @param $slug
     */
    public function getRestaurantInfo($slug)
    {
        //  Cache::forget('store-info-' . $slug);

        if (Cache::has('store-info-1' . $slug)) {
            $restaurantInfo = Cache::get('store-info-' . $slug);
        } else {
            $restaurantInfo = Restaurant::where('slug', $slug)
                ->with('coupons')
                ->first();

            $restaurantInfo['schedule'] = $this->getScheduleData($restaurantInfo);
                
            $restaurantInfo->makeHidden(['delivery_areas']);
            $this->processSuperCache('store-info-' . $slug, $restaurantInfo);
        }

        return response()->json($restaurantInfo);
    }
    
    /**
     * @param $id
     */
    public function getRestaurantInfoById($id)
    {
        $restaurant = Restaurant::where('id', $id)->first();
        $restaurant->makeHidden(['delivery_areas']);

        return response()->json($restaurant);
    }

    /**
     * @param Request $request
     */
    public function getRestaurantInfoAndOperationalStatusOld(Request $request)
    {
        $restaurant = Restaurant::where('id', $request->restaurant_id)->with('payment_gateways_active')->first();
        //if($request->payment_method == 'COD') throw new ValidationException(ErrorCode::UNSUPPORTED_PAYMENT_METHOD, "Cash on delivery is not valid for orders above ₹350. Please pay online to proceed");
        
        if ($restaurant) {
            $restaurant->makeHidden(['delivery_areas', 'location_id', 'schedule_data']);

            /* Step1: Check Address is operational or not  */
            $isAddressOperational = $this->checkOperation($request->latitude, $request->longitude, $restaurant);
            if(!$isAddressOperational){
                throw new ValidationException(ErrorCode::ADDRESS_NOT_OPERATIONAL, "Does not deliver to the current location");
            }
            
            /* Step2: Check deliveryType is acceptable or not */
            if(($restaurant->delivery_type != 3) && ($request->delivery_type != $restaurant->delivery_type)){
                $deliveryTypeStr = ($request->delivery_type == 1) ? 'Delivery' : 'Self pickup';
                throw new ValidationException(ErrorCode::UNSUPPORTED_DELIVERY_TYPE, $deliveryTypeStr .' not possible for this restaurant');
            }

            
            /* Step3: Check Payment method is active or not */
            if($request->payment_method){
                $isPaymentGatewayAvailable = false;
                foreach ($restaurant['payment_gateways_active'] as $paymentGateway){
                    if(strcasecmp($request->payment_method, $paymentGateway['name'])){
                        $isPaymentGatewayAvailable = true;
                        break;
                    }
                }
                if(!$isPaymentGatewayAvailable){
                    throw new ValidationException(ErrorCode::PAYMENT_METHOD_NOT_AVAILABLE, $request->payment_method .' not available for this restaurant');
                }
            }

            // If wallet amount present, check User have valid wallet amount or not
            // if($request->user_id){
            //     $user = User::where('id', $request->user_id)->first();
            
            //     $user = [
            //         'id' => $user->id,
            //         //'auth_token' =>  $user->auth_token,
            //         'name' => $user->name,
            //         //'email' => $user->email,
            //         'phone' => $user->phone,
            //         //'default_address_id' => $user->default_address_id,
            //         //'default_address' => $user->default_address,
            //         //'delivery_pin' => $user->delivery_pin,
            //         'wallet_balance' => $user->balanceFloat,
            //     ];
            // }


            /* Step4: Check restaurant is open or not */
            //Skip....Implement later

            /* Step5: Check restaurant is accepting order or not */
            // skip........Implement later

            return response()->json([
                'success' => true,
                'message' => 'Operational',
                'data' => null,
            ]);    
        } else {
            throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, 'Restaurant ID not passed or not found.');
        }

    }



    /**
     * @param Request $request
     */
    public function getRestaurantInfoAndOperationalStatus(Request $request)
    {
        $restaurant = Restaurant::where('id', $request->restaurant_id)->with('payment_gateways_active')->first();
        //if($request->payment_method == 'COD') throw new ValidationException(ErrorCode::UNSUPPORTED_PAYMENT_METHOD, "Cash on delivery is not valid for orders above ₹350. Please pay online to proceed");

        if ($restaurant) {
            $restaurant->makeHidden(['delivery_areas', 'location_id', 'schedule_data']);
            return response()->json([
                'success' => true,
                'message' => 'operational',
                'data' => $restaurant,
                'code' => '',
            ]);
        } else {
            throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, 'Restaurant ID not passed or not found.');
        }

    }


    /**
     * @param $slug
     */
    public function getRestaurantItems($slug)
    {
        // Cache::forget('store-info-' . $slug);
        Cache::forever('items-cache', 'true');
        if (Cache::has('store-info-' . $slug)) {
            $restaurant = Cache::get('store-info-' . $slug);
        } else {
            $restaurant = Restaurant::where('slug', $slug)->first();

            //$distance = $this->getDistance($request->latitude, $request->longitude, $restaurant->latitude, $restaurant->longitude);
            //$deliveryTime = $distance * 3 ;// 3 min per km
            //$restaurant['delivery_time'] =(int) ($deliveryTime + ((int)    $restaurant->delivery_time));

            $restaurant['schedule'] = $this->getScheduleData($restaurant);
            $this->processSuperCache('store-info-' . $slug, $restaurant);
        }

        // Cache::forget('items-recommended-' . $restaurant->id);
        // Cache::forget('items-all-' . $restaurant->id);

        if (Cache::has('items-recommended-' . $restaurant->id) && Cache::has('items-all-' . $restaurant->id)) {
            $recommended = Cache::get('items-recommended-' . $restaurant->id);
            $array = Cache::get('items-all-' . $restaurant->id);
        } else {
            if (config('settings.showInActiveItemsToo') == 'true') {
                $recommended = Item::where('restaurant_id', $restaurant->id)->where('is_recommended', '1')
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->get();

                // $items = Item::with('add')
                $items = Item::where('restaurant_id', $restaurant->id)
                    ->join('item_categories', function ($join) {
                        $join->on('items.item_category_id', '=', 'item_categories.id');
                        $join->where('is_enabled', '1');
                    })
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->get(array('items.*', 'item_categories.name as category_name'));
            } else {
                $recommended = Item::where('restaurant_id', $restaurant->id)->where('is_recommended', '1')
                    ->where('is_active', '1')
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->get();

                // $items = Item::with('add')
                $items = Item::where('restaurant_id', $restaurant->id)
                    ->join('item_categories', function ($join) {
                        $join->on('items.item_category_id', '=', 'item_categories.id');
                        $join->where('is_enabled', '1');
                    })
                    ->where('is_active', '1')
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->get(array('items.*', 'item_categories.name as category_name'));
            }

            $items = json_decode($items, true);

            $array = [];
            foreach ($items as $item) {
                $array[$item['category_name']][] = $item;
            }

            $this->processSuperCache('items-recommended-' . $restaurant->id, $recommended);
            $this->processSuperCache('items-all-' . $restaurant->id, $array);
        }

        return response()->json(array(
            'restaurant' => $restaurant,
            'recommended' => $recommended,
            'items' => $array,
        ));

    }

    /**
     * @param Request $request
     */
    public function searchRestaurants(Request $request)
    {
        //get lat and lng and query from user...
        // get all active restauants doing delivery & selfpickup
        $restaurants = Restaurant::where('name', 'LIKE', "%$request->q%")
            ->where('is_accepted', '1')
            ->take(20)->get();

        //Create a new Laravel collection from the array data
        $nearMeRestaurants = new Collection();

        foreach ($restaurants as $restaurant) {
            // $distance = $this->getDistance($request->latitude, $request->longitude, $restaurant->latitude, $restaurant->longitude);
            // if ($distance <= $restaurant->delivery_radius) {
            //     $nearMeRestaurants->push($restaurant);
            // }
            $check = $this->checkOperation($request->latitude, $request->longitude, $restaurant);
            if ($check) {
                $nearMeRestaurants->push($restaurant);
            }
        }

        $items = Item::
            where('is_active', '1')
            ->where('name', 'LIKE', "%$request->q%")
            ->with('restaurant')
            ->get();

        $nearMeItems = new Collection();
        foreach ($items as $item) {

            if ($item->restaurant->is_active) {
                $itemRestro = $item->restaurant;
                // $distance = $this->getDistance($request->latitude, $request->longitude, $itemRestro->latitude, $itemRestro->longitude);
                // if ($distance <= $itemRestro->delivery_radius) {
                //     $nearMeItems->push($item);
                // }
                $check = $this->checkOperation($request->latitude, $request->longitude, $itemRestro);
                if ($check) {
                    $nearMeItems->push($item);
                }
            }

        }

        $response = [
            'restaurants' => $nearMeRestaurants,
            'items' => $nearMeItems->take(20),
        ];

        return response()->json($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function checkRestaurantOperationService(Request $request)
    {
        $check = false;

        $restaurant = Restaurant::where('id', $request->restaurant_id)->first();
        if ($restaurant) {
            // $distance = $this->getDistance($request->latitude, $request->longitude, $restaurant->latitude, $restaurant->longitude);
            // if ($distance <= $restaurant->delivery_radius) {
            //     $status = true;
            // }
            $check = $this->checkOperation($request->latitude, $request->longitude, $restaurant);
            return $check;
        }
        return response()->json($check);
    }

    /**
     * @param Request $request
     */
    public function getSingleItem(Request $request)
    {
        if (Cache::has('item-single-' . $request->id)) {
            $item = Cache::get('item-single-' . $request->id);
        } else {

            if (config('settings.showInActiveItemsToo') == 'true') {
                $item = Item::where('id', $request->id)
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->first();
            } else {
                $item = Item::where('id', $request->id)
                    ->where('is_active', '1')
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->first();
            }

            $this->processSuperCache('item-single-' . $request->id, $item);
        }

        if ($item) {
            return response()->json($item);
        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getFilteredRestaurants(Request $request)
    {
        $activeFilteredRestaurants = Restaurant::where('is_accepted', '1')
            ->where('is_active', 1)
            ->whereHas('restaurant_categories', function ($query) use ($request) {
                $query->whereIn('restaurant_category_id', $request->category_ids);
            })->get();

        $nearMe = new Collection();

        foreach ($activeFilteredRestaurants as $restaurant) {
            $check = $this->checkOperation($request->latitude, $request->longitude, $restaurant);
            if ($check) {
                $nearMe->push($restaurant);
            }
        }
        $nearMe = $nearMe->map(function ($restaurant) {
            return $restaurant->only(['id', 'name', 'description', 'image', 'rating', 'delivery_time', 'price_range', 'slug', 'is_featured', 'is_active']);
        });
        $nearMe = $nearMe->toArray();

        $inActiveFilteredRestaurants = Restaurant::where('is_accepted', '1')
            ->where('is_active', 0)
            ->whereHas('restaurant_categories', function ($query) use ($request) {
                $query->whereIn('restaurant_category_id', $request->category_ids);
            })->get();

        $nearMeInActive = new Collection();

        foreach ($inActiveFilteredRestaurants as $restaurant) {
            $check = $this->checkOperation($request->latitude, $request->longitude, $restaurant);
            if ($check) {
                $nearMeInActive->push($restaurant);
            }
        }

        $nearMeInActive = $nearMeInActive->map(function ($restaurant) {
            return $restaurant->only(['id', 'name', 'description', 'image', 'rating', 'delivery_time', 'price_range', 'slug', 'is_featured', 'is_active']);
        });
        $nearMeInActive = $nearMeInActive->toArray();

        $merged = array_merge($nearMe, $nearMeInActive);

        return response()->json($merged);
    }

    /**
     * @param Request $request
     */
    public function checkCartItemsAvailability(Request $request)
    {
        $items = $request->items;
        $activeItemIds = [];
        foreach ($items as $item) {
            $oneItem = Item::where('id', $item['id'])->first();
            if ($oneItem) {
                if (!$oneItem->is_active) {
                    array_push($activeItemIds, $oneItem->id);
                }
            }
        }
        return response()->json($activeItemIds);
    }




    
    /**
     * @param Request $request
     */
    public function getRestaurantDetails(Request $request)
    { 
        
        // Cache::forget('store-info-' . $slug);
        Cache::forever('items-cache', 'true'); 
        
        $restaurant = Restaurant::where('id', $request->restaurant_id)
            ->with(['coupons' => function($query){
                $query->where('is_exclusive', 1);
                //$query->select('name', 'code')->get();
                //$query->take(1);
            }])
            ->first();
        $restaurant['schedule'] = $this->getScheduleData($restaurant);
       
        if($request->latitude && $request->longitude){                    
            if (config('settings.enDistanceMatrixDeliveryTime') == 'true' && ($restaurant->delivery_type == 1 || $restaurant->delivery_type == 3)) {
                $distanceMatrixResponse = $this->getGoogleDistance($request->latitude, $request->longitude, $restaurant); 
                $distance = ($distanceMatrixResponse['distance']['value']) /1000;
                
                $deliveryTimeInSecond = $distanceMatrixResponse['duration']['value'];    
                $deliveryTimeInMin = ($deliveryTimeInSecond /60) + ((int)$restaurant->delivery_time);
                $restaurant['delivery_time'] = ceil($deliveryTimeInMin);  
                // Check is_operational or not:
                if ($distance <= $restaurant->delivery_radius){
                    $restaurant['is_operational'] = true;  
                }else{
                    $restaurant['is_operational'] = false;  
                }
            }else{
                // Calculate the delivery time based on geographical distance
                $distance = $this->getDistance($request->latitude, $request->longitude, $restaurant->latitude, $restaurant->longitude);
                $actualDeliveryTime = $distance * ((int)config('settings.approxDeliveryTimePerKm')) ;
                $restaurantDeliveryTime = $actualDeliveryTime + ((int)$restaurant->delivery_time);
                $restaurant['delivery_time'] = ceil($restaurantDeliveryTime);
                // Check is_operational or not:
                if ($distance <= $restaurant->delivery_radius){
                    $restaurant['is_operational'] = true;  
                }else{
                    $restaurant['is_operational'] = false;  
                }
            }
        }
    
        // Cache::forget('items-recommended-' . $restaurant->id);
        // Cache::forget('items-all-' . $restaurant->id);

        if (Cache::has('items-recommended-' . $restaurant->id) && Cache::has('items-all-' . $restaurant->id)) {
            $recommended = Cache::get('items-recommended-' . $restaurant->id);
            $array = Cache::get('items-all-' . $restaurant->id);
        } else {
            if (config('settings.showInActiveItemsToo') == 'true') {
                $recommended = Item::where('restaurant_id', $restaurant->id)->where('is_recommended', '1')
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->get();

                // $items = Item::with('add')
                $items = Item::where('restaurant_id', $restaurant->id)
                    ->join('item_categories', function ($join) {
                        $join->on('items.item_category_id', '=', 'item_categories.id');
                        $join->where('is_enabled', '1');
                    })
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->get(array('items.*', 'item_categories.name as category_name'));
            } else {
                $recommended = Item::where('restaurant_id', $restaurant->id)->where('is_recommended', '1')
                    ->where('is_active', '1')
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->get();

                // $items = Item::with('add')
                $items = Item::where('restaurant_id', $restaurant->id)
                    ->join('item_categories', function ($join) {
                        $join->on('items.item_category_id', '=', 'item_categories.id');
                        $join->where('is_enabled', '1');
                    })
                    ->where('is_active', '1')
                    ->with('addon_categories')
                    ->with(array('addon_categories.addons' => function ($query) {
                        $query->where('is_active', 1);
                    }))
                    ->get(array('items.*', 'item_categories.name as category_name'));
            }

            $items = json_decode($items, true);

            $array = [];
            foreach ($items as $item) {
                $array[$item['category_name']][] = $item;
            }

            $this->processSuperCache('items-recommended-' . $restaurant->id, $recommended);
            $this->processSuperCache('items-all-' . $restaurant->id, $array);
        }

        return response()->json(array(
            'restaurant' => $restaurant,
            'recommended' => $recommended,
            'items' => $array,
        ));
        

    }
    
};
