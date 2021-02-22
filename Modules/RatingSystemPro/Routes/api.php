<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::middleware('jwt.auth')->prefix('ratingsystempro')->group(function() {
    Route::post('/store', 'RatingSystemProController@store');
    Route::post('/deliveryguy', 'RatingSystemProController@deliveryguy');
    Route::post('/list/{id}', 'RatingSystemProController@list');
});
