<?php
namespace App\Http\Utils;
use DeliveryType;
use RestaurantTy;
use StoreChargeType;

class CommonUtils{
    public static function getDeliveryType($store){
        if($store->delivery_type == 1){
            return DeliveryType::DELIVERY;
        }else if($store->delivery_type == 2){
            return DeliveryType::TAKEAWAY;
        }else{
            return DeliveryType::DELIVERY_AND_TAKEAWAY;
        }
    }
    public static function getStoreType($store){
        return 'RESTAURANT';
    }
}

?>