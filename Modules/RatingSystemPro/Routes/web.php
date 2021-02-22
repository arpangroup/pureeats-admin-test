<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('ratingsystempro')->group(function() {
    Route::get('/', 'RatingSystemProController@index');
    Route::get('/settings', 'RatingSystemProController@settings');
    Route::get('/reports', 'RatingSystemProController@reports')->name('ratingsystempro.reports');
});
