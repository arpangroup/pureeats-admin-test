<?php
namespace App\Http\Dto;
use StoreChargeType;
use CalculationType;


class CouponResponse{
    public string $name;
    public string $promocode;
    public string $description;
    public float $min_subtotal;
    public bool $is_exclusive;

    public static function getCoupon($coupon){
        $couponResponse = new CouponResponse();

        $couponResponse->name = $coupon->name;
        $couponResponse->promocode = $coupon->code;
        $couponResponse->description = $coupon->description;
        $couponResponse->min_subtotal = (float)$coupon->min_subtotal;
        $couponResponse->min_subtotal = (bool)$coupon->is_exclusive;

        return $couponResponse;
    }
}

?>