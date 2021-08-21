<?php
namespace App\Http\Dto;
use StoreChargeType;
use CalculationType;
use RatingType;


class ItemCategoryInfo{
    public string $name;
    public int $id;
    public string $localized_name;
    public string $desc;
    public string $localized_desc;
    public array $products =[];
}

?>