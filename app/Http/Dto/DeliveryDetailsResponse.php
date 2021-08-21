<?php
namespace App\Http\Dto;
use StoreChargeType;
use CalculationType;
use RatingType;


class DeliveryDetailsResponse{
    public bool $is_operational;
    public bool $is_serviceable;
    public string $serviceable_message;//'Due to heavy rain not possible to deliver the order';
    public int $delivery_time;
    public string $delivery_time_text;
    public float $min_order_price;
    public string $delivery_distance;
    public string $delivery_distance_text;
    public string $duration;
    public string $duration_text;
    public bool $enGDM;

    public static function getDeliveryDetails($store, $distanceMatrixResponse = null){
        $response = new DeliveryDetailsResponse();

        $response->is_operational = false;
        $response->is_serviceable = false;
        $response->serviceable_message = '';
        $response->delivery_time = (int)$store->delivery_time;
        $response->delivery_time_text = $store->delivery_time .' min';//'92 min',
        $response->min_order_price = (float)$store->min_order_price;
        $response->delivery_distance = $distanceMatrixResponse == null ? '' : $distanceMatrixResponse['distance']['value'];
        $response->delivery_distance_text =$distanceMatrixResponse == null ? '' : $distanceMatrixResponse['distance']['text'];
        $response->duration = $distanceMatrixResponse == null ? '' : $distanceMatrixResponse['duration']['value'];
        $response->duration_text = $distanceMatrixResponse == null ? '' : $distanceMatrixResponse['duration']['text'];
        $response->enGDM = (bool)config('settings.enDistanceMatrixDeliveryTime');

        return $response;
    }
}

?>