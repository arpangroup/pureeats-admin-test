<?php
namespace App\Http\Dto;
use StoreChargeType;
use CalculationType;


class StoreCharge{
    public string $store_charge_type;
    public string $calculation_type;
    public float $fixed_store_charge;
    public DynamicStoreCharge $dynamic_store_charge;

    public static function getStoreCharge($store){
        $storeCharge = new StoreCharge();
        $storeCharge->store_charge_type = StoreChargeType::FIXED;
        $storeCharge->calculation_type = CalculationType::PERCENTAGE;
        $storeCharge->fixed_store_charge = (float)$store->restaurant_charges;
        $storeCharge->dynamic_store_charge = new DynamicStoreCharge();

        return $storeCharge;
    }
}

?>