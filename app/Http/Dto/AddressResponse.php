<?php
namespace App\Http\Dto;
use StoreChargeType;
use CalculationType;
use RatingType;


class AddressResponse{
    public int $address_id;
    public float $latitude;
    public float $longitude;
    public string $address;
    public string $house;
    public string $landmark;
    public string $pincode;
    public string $locality;
    public string $city;
    public string $zipcode;


    public static function getAddress($store){
        $address = new AddressResponse();

        $address->latitude = (float)$store->latitude;
        $address->longitude = (float)$store->longitude;
        $address->address = $store->address;
        $address->house = '';
        $address->landmark = '';
        $address->pincode = '';
        $address->locality = '';
        $address->city = '';
        $address->zipcode = '';

        return $address;
    }
}

?>