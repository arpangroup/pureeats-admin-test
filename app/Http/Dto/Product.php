<?php
namespace App\Http\Dto;
use StoreChargeType;
use CalculationType;
use RatingType;


class Product{
    public string $name;
    public int $id;
    public float $price;
    public int $old_price;
    public string $image;
    public string $thumb;
    public string $desc;
    public bool $is_recommended;
    public bool $is_veg;
    public bool $is_active;
    public array $tag_slugs =[];


    public static function getProduct($product){
        $response = new Product();

        $response->id = (int)$product->id;
        $response->name = $product->name;
        $response->price = (float)$product->price;
        $response->old_price = (int)$product->old_price;
        $response->image = (string)$product->image;
        $response->thumb = '';
        $response->desc = (string)$product->desc;
        $response->is_recommended = (bool)$product->is_recommended;
        $response->is_veg = (bool)$product->is_veg;
        $response->is_active = (bool)$product->is_active;

        if(isset($product->is_new) && $product->is_new == true){
            array_push($response->tag_slugs, 'new');
        }

        if(isset($product->is_popular) && $product->is_popular == true){
            array_push($response->tag_slugs, 'popular');
        }

        if(isset($product->is_recommended) && $product->is_recommended == true){
            array_push($response->tag_slugs, 'recommended');
        }

        if(isset($product->is_veg) && $product->is_veg == true){
            array_push($response->tag_slugs, 'veg');
        }

        if(isset($product->bestseller) && $product->bestseller == true){
            array_push($response->tag_slugs, 'bestseller');
        }

        if(isset($product->is_delivery_enabled) && $product->is_delivery_enabled == true){
            array_push($response->tag_slugs, 'delivery-enabled');
        }

        return $response;
    }
}

?>