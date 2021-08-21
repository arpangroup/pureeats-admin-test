<?php
namespace App\Http\Mapper;

use App\Http\Dto\AddressResponse;
use App\Http\Dto\CouponResponse;
use App\Http\Dto\DeliveryCharge;
use App\Http\Dto\DeliveryDetailsResponse;
use App\Http\Dto\RestaurantResponse;
use App\Http\Dto\StoreCharge;
use App\Http\Dto\StoreRatingResponse;
use App\Http\Utils\CommonUtils;
use Ixudra\Curl\Facades\Curl;

class RestaurantMapper{

    public static function mapTo($store){
        $store = json_encode($store, JSON_FORCE_OBJECT);
        $store = json_decode($store);

        $response = new RestaurantResponse();

        $response->store_id = (double)$store->id;
        $response->name = $store->name;
        $response->localized_name = '';
        $response->description = isset($store->description) ? $store->description : '';
        $response->localized_description = '';
        $response->slug = isset($store->slug) ? $store->slug : '';
        $response->contact_number = (double)isset($store->contact_number) ? $store->contact_number : '';
        $response->image = isset($store->image) ? url('/'). $store->image : '';
        $response->thumb = '';
        $response->store_type = CommonUtils::getStoreType($store);
        $response->delivery_type =  CommonUtils::getDeliveryType($store);
        $response->price_range = isset($store->price_range) ? $store->price_range : '' ;
        $response->average_cost_for_two = '';
        $response->is_pureveg = (boolean)$store->is_pureveg;
        $response->open_table_support = false;
        $response->is_table_reservation_supported = false;
        $response->is_perm_closed = (bool)($store->is_accepted == 0);
        $response->is_temp_closed = (bool)($store->is_accepted == 0);
        $response->is_opening_soon = false;
        $response->disclaimer_text = '';
        $response->delivery_charge = DeliveryCharge::getDeliveryCharge($store);
        $response->store_charge = StoreCharge::getStoreCharge($store);
        $response->rating = StoreRatingResponse::getStoreRating($store);
        $response->location = AddressResponse::getAddress($store);
        $response->delivery_details = DeliveryDetailsResponse::getDeliveryDetails($store, null);
        $response->timing = (object)self::mapToTiming($store);
        //$response->takeaway_details = (object)null;
        $response->addCoupons($store->coupons);
        $response->addItems($store->items);

        return $response;
    }


    public static function mapToDistanceMatrixAndReturnStore($store, $distanceMatrixResponse=null){
        $orderDetails = self::mapToOrderDetails($store, $distanceMatrixResponse);
        $store['order_details'] = $orderDetails;
        return $store;
    }

    private static function mapToTiming($store){
        $scheduleData = self::getScheduleData($store);
        $timing = [
            'timing_desc' => $scheduleData['status'],//"6:30am – 10pm (Today)";//5 PM to 1 AM (Mon-Sun)
            'is_open' => $scheduleData['is_open'],
            'customised_timings' =>json_decode($store->schedule_data, true)
        ];
        return $timing;
    }

    private static function mapCoupons($store){
        $coupons = array();
        foreach ($store->coupons as $coupon){
            array_push($coupons, CouponResponse::getCoupon($coupon));
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


    private static function getScheduleData($restaurant){
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
            //usort($todaysSlots, array($this, 'compareByTimeStamp')); // sort the array
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

}

?>