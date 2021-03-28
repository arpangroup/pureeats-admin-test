<?php

use Illuminate\Http\Request;

/* Paytm */
Route::get('/payment/test', function (){
    return "payment";
});

//https://localhost/PureEats/v2.4.1/public/api/payment/paytm/111
Route::get('/payment/paytm/{order_id}', [
    'uses' => 'PaymentController@payWithPaytm',
]);
Route::post('/payment/process-paytm', [
    'uses' => 'PaymentController@processPaytm',
]);
/* END Paytm */


Route::post('/payment/verify', [
    'uses' => 'PaymentController@verifyPayment',
]);