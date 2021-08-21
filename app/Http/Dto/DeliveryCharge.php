<?php
namespace App\Http\Dto;
use DeliveryChargeType;


use phpDocumentor\Reflection\Types\Collection;
use phpDocumentor\Reflection\Types\Object_;
use stdClass;

class DeliveryCharge{
    public string $delivery_charge_type;
    public float $fixed_delivery_charge;
    public DynamicDeliveryCharge $dynamic_delivery_charge;


    public static function getDeliveryCharge($store){
        $deliveryCharge = new DeliveryCharge();
        $deliveryCharge->delivery_charge_type = DeliveryChargeType::FIXED;
        $deliveryCharge->fixed_delivery_charge = (float)$store->delivery_charges;
        $deliveryCharge->dynamic_delivery_charge = new DynamicDeliveryCharge();

        if(isset($store->delivery_charge_type) && $store->delivery_charge_type == DeliveryChargeType::DYNAMIC){
            $dynamicDeliveryCharge = new DynamicDeliveryCharge();
            $dynamicDeliveryCharge->base_delivery_charge = (float)$store->base_delivery_charge;
            $dynamicDeliveryCharge->base_delivery_distance = (int)$store->base_delivery_distance;
            $dynamicDeliveryCharge->extra_delivery_charge = (float)$store->extra_delivery_charge;
            $dynamicDeliveryCharge->extra_delivery_distance = (int)$store->extra_delivery_distance;


            $deliveryCharge->delivery_charge_type = DeliveryChargeType::DYNAMIC;
            $deliveryCharge->dynamic_delivery_charge = $dynamicDeliveryCharge;
        }
        return $deliveryCharge;
    }

}



?>