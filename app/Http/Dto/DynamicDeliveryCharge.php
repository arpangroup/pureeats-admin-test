<?php
namespace App\Http\Dto;


use phpDocumentor\Reflection\Types\Collection;

class DynamicDeliveryCharge{
    public float $base_delivery_charge;
    public int $base_delivery_distance;
    public float $extra_delivery_charge;
    public int $extra_delivery_distance;
}



?>