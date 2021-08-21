<?php
namespace App\Http\Dto;
use StoreChargeType;
use CalculationType;
use RatingType;


class StoreRatingResponse{
    public string $rating_type;
    public float $rating;
    public int $votes;
    public string $rating_subtitle;

    public static function getStoreRating($store){
        $ratingResponse = new StoreRatingResponse();

        $ratingResponse->rating_type = RatingType::STORE;
        $ratingResponse->rating = (float)$store->rating;
        $ratingResponse->votes = (int) 120;
        $ratingResponse->rating_subtitle = 'Very Good';

        return $ratingResponse;
    }
}

?>