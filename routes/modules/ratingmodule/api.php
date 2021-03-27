<?php

Route::get('/get-restaurant-ratings/{restaurantId}', [
    'uses' => 'RatingController@getRestaurantRatings',
]);
Route::get('/get-driver-ratings/{driverId}', [
    'uses' => 'RatingController@getDriverRatings',
]);

Route::group(['middleware' => ['jwt.auth']], function () {

    Route::post('/get-ratable-order', [
        'uses' => 'RatingController@getRatableOrder',
    ]);

    Route::post('/save-new-rating', [
        'uses' => 'RatingController@saveNewRating',
    ]);

    Route::post('/single-ratable-order', [
        'uses' => 'RatingController@singleRatableOrder',
    ]);

});
