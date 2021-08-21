<?php
namespace App\Http\Dto;
use StoreChargeType;
use CalculationType;
use RatingType;


class ServiceableResponse{
    public int $is_serviceable;
    public string $message;


    public static function getServiceable($store){
        $response = new ServiceableResponse();

        $response->is_serviceable = true;
        $response->message = 'Due to heavy rain not possible to deliver the order';

        return $response;
    }
}

?>