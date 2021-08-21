<?php

namespace App\Http\Controllers;

use App\Addon;
use App\Coupon;
use App\Helpers\TranslationHelper;
use App\Item;
use App\Order;
use App\Orderitem;
use App\OrderItemAddon;
use App\Orderstatus;
use App\PushNotify;
use App\Restaurant;
use App\Sms;
use App\User;
use Hashids;
use Illuminate\Http\Request;
use Omnipay\Omnipay;
use OneSignal;

use Illuminate\Support\Facades\Log;

use ErrorCode;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;

use Razorpay\Api\Api;
use Ixudra\Curl\Facades\Curl;


// require_once("./PaytmChecksum.php");
// use paytm\checksum\PaytmChecksum;
// require_once('vendor/autoload.php');
// use paytm\checksum\PaytmChecksumLibrary;
// use vendor\paytm\paytmchecksum\PaytmChecksum;
use PaytmChecksumHelper;

class OrderController extends Controller
{    
    public function generateUniqueId($length = 12, $prefix = 'PUR', $paddingChar = '0'){
        $lastOrder = Order::orderBy('id', 'desc')->first();
        if ($lastOrder) {
            $lastOrderId = $lastOrder->id;
            $newId = $lastOrderId + 1;
            //$uniqueId = Hashids::encode($newId);
        } else {
            //first order
            $newId = 1;
        }

        $paddLength = $length - strlen($prefix) - strlen($newId);

        // Generate the sequence
        $nextSeq = $prefix;
        for($i = 0; $i <= $paddLength; $i++){
            $nextSeq .= $paddingChar;
        }
        $nextSeq .= $newId;

        return $nextSeq;
    }
    
    private function generatePaytmChecksum($orderId){
        $payTmMerchentId = env('PAYTM_MERCHANT_ID', 'DEFAULT_VALUE');
        $payTmMerchentKey = env('PAYTM_MERCHANT_KEY', 'DEFAULT_VALUE');

        $requstBody = [
            "mid" =>  $payTmMerchentId,
            "orderId" => $orderId,
        ];

        $body = json_encode($requstBody);
        $paytmChecksum = PaytmChecksumHelper::generateSignature($body, $payTmMerchentKey);
        
        /**
        * Verify checksum
        * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
        */
        // $isVerifySignature = PaytmChecksumHelper::verifySignature($body, $payTmMerchentKey, $paytmChecksum);
        // if($isVerifySignature) {
        //     echo "Checksum Matched";
        // } else {
        //     echo "Checksum Mismatched";
        // }
        
        return $paytmChecksum;
    }


    private function validateOrderRequest(Request $request, $user){
        // validate User
        if($user){
            $paymentMethods = array('COD', 'WALLET', 'RAZORPAY', 'PAYTM', 'GOOGLE_PAY', 'PHONEPAY', 'UPI');

            // User:
            if($user->is_active != 1)throw new ValidationException(ErrorCode::ACCOUNT_BLOCKED, "User is blocked temporarily");

            // Order:
            if(!is_numeric($request->restaurant_id))throw new ValidationException(ErrorCode::BAD_REQUEST, "Incorrect datatype for restaurant_id");

            // Location:
            if($request->location == null)throw new ValidationException(ErrorCode::BAD_REQUEST, "locationshould not be null");
            if($request->full_address == null)throw new ValidationException(ErrorCode::BAD_REQUEST, "full_address should not be null");
            if($request->delivery_type == null)throw new ValidationException(ErrorCode::BAD_REQUEST, "delivery_type should not be null");
            if($request->delivery_type == 1 || $request->delivery_type == 3){
                if($request->delivery_distance == null || !is_numeric($request->delivery_distance))throw new ValidationException(ErrorCode::BAD_REQUEST, "Invalid delivery_distance");
            }

            // Payment:
            if($request->payment_mode == null)throw new ValidationException(ErrorCode::BAD_REQUEST, "payment_mode should not be null");
            if(!in_array($request->payment_mode, $paymentMethods))throw new ValidationException(ErrorCode::BAD_REQUEST, "Invalid payment_mode");
            //if($request->pending_payment == null)throw new ValidationException(ErrorCode::BAD_REQUEST, "pending_payment should not be null");
            //if(!is_bool($request->pending_payment))throw new ValidationException(ErrorCode::BAD_REQUEST, "pending_payment should be true or false");
            //if(($request->payment_mode == 'COD' ||$request->payment_mode == 'WALLET') && $request->pending_payment == true)throw new ValidationException(ErrorCode::BAD_REQUEST, 'For '.$request->payment_method .' pending_payment should be always false');
            //if(($request->payment_mode == 'RAZORPAY' ||$request->payment_mode == 'PAYTM') && $request->payment_mode == false)throw new ValidationException(ErrorCode::BAD_REQUEST, "For ".$request->payment_method .' pending_payment should be always true');
            if($request->partial_wallet){
                if(!is_bool($request->partial_wallet))throw new ValidationException(ErrorCode::BAD_REQUEST, "partial_wallet should be true or false");
                if($request->partial_wallet == true){
                    if($request->partial_wallet_amount == null || !is_numeric($request->partial_wallet_amount))throw new ValidationException(ErrorCode::BAD_REQUEST, "Invalid data type partial_wallet_amount");
                    if($user->balanceFloat < $request->partial_wallet_amount)throw new ValidationException(ErrorCode::BAD_REQUEST, "Invalid wallet amount, available wallet balance is ".$user->balanceFloat);
                }
            }
            // Check if user has enough wallet balance for full wallet payment
            if($request->payment_mode == 'WALLET' && $user->balanceFloat < $request->total)throw new ValidationException(ErrorCode::BAD_REQUEST, "Invalid wallet amount, available wallet balance is ".$user->balanceFloat);
        }else{
            throw new ValidationException(ErrorCode::INVALID_AUTH_TOKEN, "Authentication Fail");
        }
        
    }


   
    /**
     * @param Request $request
     */
    public function placeOrder(Request $request, TranslationHelper $translationHelper)
    {
        $user = auth()->user();
        Log::channel('orderlog')->info('#############################################################');
        Log::channel('orderlog')->info('Inside placeOrder()');
        Log::channel('orderlog')->info('#############################################################');
        
        $this->validateOrderRequest($request, $user);
        
        try{
            Log::channel('orderlog')->info('REQUEST_BODY: ' .json_encode($request->all()));
            $keys = ['orderPaymentWalletComment', 'orderPartialPaymentWalletComment'];
            $translationData = $translationHelper->getDefaultLanguageValuesForKeys($keys);

            $newOrder = new Order();
            $newId = $this->generateUniqueId();
            $newOrder->unique_order_id = $newId;
            $newOrder->user_id = $user->id;
            //$newOrder->device_id = $request->device_id;
            //$newOrder->order_from = 'WEB';
            //if($request->device_id) $newOrder->order_from = 'ANDROID';
            $newOrder->location = json_encode($request->location);
            $newOrder->address = $request->full_address;
            //$newOrder->transaction_id = $request->payment_token; //NEW           
            $newOrder->order_comment = $request->order_comment;
            $newOrder->payment_mode = $request->payment_mode;
            $newOrder->delivery_type = 2;
            if ($request->delivery_type == 1) $newOrder->delivery_type = 1;
            $user->delivery_pin = strtoupper(str_random(5));

            $restaurant = Restaurant::where('id', $request->restaurant_id)->first(); 
            $newOrder->restaurant_id = $restaurant->id; 
           
            if($request->payment_mode == 'COD' || $request->payment_mode == 'WALLET'){
                $newOrder->orderstatus_id = '1';
                if ($restaurant->auto_acceptable) {
                    Log::channel('orderlog')->info('restaurant is auto accepble, so set orderstatus_id = 2');
                    $newOrder->orderstatus_id = '2';
                    //$this->smsToDelivery($restaurant->id);
                    if (config('settings.enablePushNotificationOrders') == 'true') {
                        Log::channel('orderlog')->info('send push notification to user');
                        //to user
                         $notify = new PushNotify();
                         $notify->sendPushNotification('2', $newOrder->user_id, $newOrder->unique_order_id);
                    }
                    //$this->sendPushNotificationStoreOwner($order->restaurant_id);
                }
            }else if($request->payment_mode == 'GOOGLE_PAY' || $request->payment_mode == 'PHONEPAY' || $request->payment_mode == 'PAYTM' || $request->payment_mode == 'UPI'){
                $newOrder->orderstatus_id = '8';
            }else {
                $newOrder->orderstatus_id = '8';
            }




            
            // OrderTotal:
            Log::channel('orderlog')->info('###### Calculate OrderTotal........');
            $orderTotal = 0;
            foreach ($request['order_items'] as $oI) {
                $originalItem = Item::where('id', $oI['id'])->first();
                $orderTotal += ($originalItem->price * $oI['quantity']);

                if (isset($oI['selectedaddons'])) {
                    foreach ($oI['selectedaddons'] as $selectedaddon) {
                        $addon = Addon::where('id', $selectedaddon['addon_id'])->first();
                        if ($addon) {
                            $orderTotal += $addon->price * $oI['quantity'];
                        }
                    }
                }
            }
            $newOrder->sub_total = $orderTotal;
            Log::channel('orderlog')->info('###### SubTotal: '.$orderTotal);



            // RestaurantCharge based on orderAmount
            Log::channel('orderlog')->info('###### Calculate StoreCharge........');
            $restaurantCharge = 0;//2%=>10==>10
            if($restaurant->restaurant_charges){
                //$restaurant_charge = $this->calculateRestaurantCharge($restaurant, $orderTotal);
                $restaurantCharge = (float) (((float) $restaurant->restaurant_charges / 100) * $orderTotal);
                //$newOrder->restaurant_charge_in_percentage = (float)$restaurant->restaurant_charges; //NEW 
                $newOrder->restaurant_charge = $restaurantCharge;
                Log::channel('orderlog')->info('###### StoreChargePercentage: ' .$restaurant->restaurant_charges);
                Log::channel('orderlog')->info('###### StoreChargeApplied: ' .$restaurantCharge);
            }
           

            
            //GST: For charging GST net value should be considered which is arrived after reduction of cash discount.
            // Here we are calculating tax orderAmount to maximize the profit
            //https://www.quora.com/Is-GST-applied-before-or-after-a-cash-discount
            Log::channel('orderlog')->info('###### Calculate Tax........');
            $taxAmount = 0;
            if (config('settings.taxApplicable') == 'true') {
                Log::channel('orderlog')->info('Tax Applicable: TRUE');
                $newOrder->tax = config('settings.taxPercentage');
                $taxAmount = (float) (((float) config('settings.taxPercentage') / 100) * $orderTotal);
                Log::channel('orderlog')->info('TaxPercentage: '. config('settings.taxPercentage'));
            } 
            $newOrder->tax_amount = $taxAmount;
            Log::channel('orderlog')->info('TaxApplied: '.$taxAmount);

            
            //Coupon:
            Log::channel('orderlog')->info('###### Calculate CouponAmount........');
            if ($request->coupon) {
                Log::channel('orderlog')->info('Coupon Applied: ' .$request['coupon']);
                $coupon = Coupon::where('code', strtoupper($request['coupon']))->first();
                if ($coupon) {
                    Log::channel('orderlog')->info($request['coupon'] .' is a valid coupon');
                    $newOrder->coupon_name = $request['coupon'];
                    if ($coupon->discount_type == 'PERCENTAGE') {
                        Log::channel('orderlog')->info('DISCOUNT_TYPE: PERCENTAGE' );
                        $percentage_discount = (($coupon->discount / 100) * $orderTotal);
                        Log::channel('orderlog')->info('PERCENTAGE_DISCOUNT: ' .$percentage_discount );
                        if ($coupon->max_discount) {
                            if ($percentage_discount >= $coupon->max_discount) {
                                Log::channel('orderlog')->info('Percentage discount is greater than MaxDiscount');
                                $percentage_discount = $coupon->max_discount;
                            }
                        }
                        $newOrder->coupon_amount = $percentage_discount;
                        $orderTotal = $orderTotal - $percentage_discount;
                        Log::channel('orderlog')->info('COUPON_AMOUNT_APPLIED: ' .$newOrder->coupon_amount);
                    }
                    if ($coupon->discount_type == 'AMOUNT') {
                        Log::channel('orderlog')->info('FLAT_DISCOUNT: ' .$coupon->discount);
                        $newOrder->coupon_amount = $coupon->discount;
                        $orderTotal = $orderTotal - $coupon->discount;
                    }
                    $coupon->count = $coupon->count + 1;
                    $coupon->save();
                }
            }


            $orderTotal = $orderTotal + $restaurantCharge + $taxAmount;


            Log::channel('orderlog')->info('###### Calculating DeliveryCharge.....');
            if ($request->delivery_type == 1) {
                if ($restaurant->delivery_charge_type == 'DYNAMIC') {
                    Log::channel('orderlog')->info("DYNAMIC deliveryCharge enabled");
                    //get distance between user and restaurant,                    
                    if (config('settings.enGDMA') == 'true') {
                        Log::channel('orderlog')->info('Google distance matrix enabled, distance: '.(float) $request->delivery_distance);
                        Log::channel('orderlog')->info("****Note:  create custom function to calculate the distance from backend and store on DB, to avoid extra pricing");
                        /* Make custom function to calculate the distance and store on DB, to avoid extra pricing*/
                        $distance = (float) $request->delivery_distance;
                    } else {
                        Log::channel('orderlog')->info('Google distance matrix not enabled');
                        //$distance = $this->getDistance($request['user']['data']['default_address']['latitude'], $request['user']['data']['default_address']['longitude'], $restaurant->latitude, $restaurant->longitude);
                        $distance = $this->getDistance($request['location']['latitude'], $request['location']['longitude'], $restaurant->latitude, $restaurant->longitude);
                        Log::channel('orderlog')->info('Server Calculated GeoGraphicalDistance: ' .$distance);
                        Log::channel('orderlog')->info('CLIENT Calculated GeoGraphicalDistance: ' .$request->distance);
                    }

                    if ($distance > $restaurant->base_delivery_distance) {
                        Log::channel('orderlog')->info('Distance is more than baseDistance ');
                        $extraDistance = $distance - $restaurant->base_delivery_distance;
                        $extraCharge = ($extraDistance / $restaurant->extra_delivery_distance) * $restaurant->extra_delivery_charge;
                        $dynamicDeliveryCharge = $restaurant->base_delivery_charge + $extraCharge;

                        if (config('settings.enDelChrRnd') == 'true') {
                            Log::channel('orderlog')->info('DeliveryCharge RoundUp enabled ');
                            $dynamicDeliveryCharge = ceil($dynamicDeliveryCharge);

                            Log::channel('orderlog')->info('Server DeliveryCharge: ' .$dynamicDeliveryCharge);
                        }

                        $newOrder->delivery_charge = $dynamicDeliveryCharge;
                        $orderTotal = $orderTotal + $dynamicDeliveryCharge;
                    } else {
                        Log::channel('orderlog')->info('Applying base delivery charge');
                        $newOrder->delivery_charge = $restaurant->base_delivery_charge;
                        $orderTotal = $orderTotal + $restaurant->base_delivery_charge;
                        Log::channel('orderlog')->info('DeliveryCharge: ' .$newOrder->delivery_charge);
                    }

                } else {
                    Log::channel('orderlog')->info("FIXED deliveryCharge enabled");
                    $newOrder->delivery_charge = $restaurant->delivery_charges;
                    $orderTotal = $orderTotal + $restaurant->delivery_charges;
                    Log::channel('orderlog')->info('DeliveryCharge: ' .$newOrder->delivery_charge);
                }

            } else {
                Log::channel('orderlog')->info("SelfPickup Order, so deliveryCharge: 0");
                $newOrder->delivery_charge = 0;
            }

            //DriverTipAmount:
            if (isset($request['tip_amount']) && !empty($request['tip_amount'])) {
                Log::channel('orderlog')->info('tipAmount: ' .$request['tip_amount'] .' Current Total: ' .$orderTotal);
                $orderTotal = $orderTotal + $request['tip_amount'];
                $newOrder->tip_amount = $request['tip_amount'];
                Log::channel('orderlog')->info('After apply tipAmount Total: '.$orderTotal);
            }

            //this is the final order total
            $newOrder->total = $orderTotal;
            Log::channel('orderlog')->info('TOTAL: ' .$newOrder->total);
            //return response()->json(['subtotal' => $orderTotal,'order' => $newOrder,]);

            if($request->payment_mode == 'WALLET' && $user->balanceFloat < $orderTotal){
                Log::channel('orderlog')->info('payment_mode = WALLET, and users wallet balance is less then total, so returning with error message');
                return response()->json(['success' => false, 'message' => "Invalid wallet amount, available wallet balance is ".$user->balanceFloat, ]);
            }

            if ($request->partial_wallet == true) {
                Log::channel('orderlog')->info('partial_wallet = true, so deduct all user amount');
                if($user->balanceFloat < $request->partial_wallet_amount){
                    Log::channel('orderlog')->info('User has no sufficient wallet balance to proceed....so return the error message');
                    return response()->json(['success' => false, 'message' => "Invalid wallet amount, available wallet balance is ".$user->balanceFloat, ]);
                }

                //deduct all user amount and add
                $userWalletBalance = $user->balanceFloat;
                $newOrder->payable = $orderTotal - $userWalletBalance;            
                $newOrder->partial_wallet = 1;    
                $newOrder->partial_wallet_amount = $request->partial_wallet_amount;
                Log::channel('orderlog')->info('Total: '.$orderTotal  .' payable: '.$newOrder->payable);
            }
            if ($request->partial_wallet == false) {
                Log::channel('orderlog')->info('partial_wallet = false');
                $newOrder->payable = $orderTotal;
                Log::channel('orderlog')->info('Total: '.$orderTotal  .' payable: '.$newOrder->payable);
            }



            // Save the order
            Log::channel('orderlog')->info('Saving order........');
            if($request->screenshot != null) {
                $imageData = base64_decode($request->screenshot);
                $source = imagecreatefromstring($imageData);
                $rotate = imagerotate($source, 0, 0); // if want to rotate the image
                $filename =  $newOrder->unique_order_id .'_'. time().'_'.  str_random(10). '.jpg';
                $file = public_path('/images/bill/' . $filename);
                $imageSave = imagejpeg($rotate, $file, 100);
                imagedestroy($source);
                $newOrder->screenshot = $filename;
            }
            $newOrder->save();

            Log::channel('orderlog')->info('Saving order_items........');
            foreach ($request['order_items'] as $orderItem) {
                $item = new Orderitem();
                $item->order_id = $newOrder->id;
                $item->item_id = $orderItem['id'];
                $item->name = $orderItem['name'];
                $item->quantity = $orderItem['quantity'];
                $item->price = $orderItem['price'];
                $item->save();
                if (isset($orderItem['selectedaddons'])) {
                    foreach ($orderItem['selectedaddons'] as $selectedaddon) {
                        $addon = new OrderItemAddon();
                        $addon->orderitem_id = $item->id;
                        $addon->addon_category_name = $selectedaddon['addon_category_name'];
                        $addon->addon_name = $selectedaddon['addon_name'];
                        $addon->addon_price = $selectedaddon['price'];
                        $addon->save();
                    }
                }
            }

            $response = [
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => $newOrder,
            ];

            /* ################################# START_PAYMENT ##################################################*/
            Log::channel('orderlog')->info('Start payment processing...........');
            if($request->payment_mode == 'PAYTM'){
                Log::channel('orderlog')->info('Generating checksum for paytm...........');
                $response['data']['paytm_checksum'] = $this->generatePaytmChecksum($newOrder->unique_order_id);
                Log::channel('orderlog')->info('PAYTM_CHECKSUM: ' .$response['data']['paytm_checksum']);
            }else if($request->payment_mode == 'GOOGLE_PAY' || $request->payment_mode == 'PHONEPAY' || $request->payment_mode == 'UPI'){
                Log::channel('orderlog')->info('Payment method:' .$request->payment_mode);
            }else if($request->payment_mode == 'RAZORPAY'){
                $razorPayResponse = $this->generateRazorPayOrderId($newOrder->payable);
                //return  $razorPayResponse;
                if($razorPayResponse['razorpay_success'] == true && isset($razorPayResponse['response']->id) ){                   
                   $newOrder->rzp_order_id = $razorPayResponse['response']->id;
                   $newOrder->save();
                }else{
                    $response['success'] = false;
                    $response['message'] = "Razorpay error";
                    $response['data'] = $razorPayResponse['response']->error;
                }
            }else if($request->payment_mode == 'WALLET'){
                Log::channel('orderlog')->info('Current Users wallerBalance is: ' .$user->balanceFloat . ', Paying Full Wallet Payment of'.$orderTotal);
                $user->withdraw($orderTotal * 100, ['description' => $translationData->orderPaymentWalletComment . $newOrder->unique_order_id]);
                Log::channel('orderlog')->info('Current WalletBalance after full wallet payment : ' .$user->balanceFloat);
            }else if($request->payment_mode == 'COD' && $request->partial_wallet == true){
                Log::channel('orderlog')->info('COD Order with Partial Wallet...so deduct the partial amount of '.$request->partial_wallet_amount);
                $userWalletBalance = $user->balanceFloat;
                $user->withdraw($userWalletBalance * 100, ['description' => $translationData->orderPartialPaymentWalletComment . $newOrder->unique_order_id]);
                Log::channel('orderlog')->info('Current WalletBalance after full wallet payment : ' .$user->balanceFloat);
            }else{
                Log::channel('orderlog')->info('Payment method not match');
            }


            return response()->json($response); 
        } catch (\Throwable $th) {
            Log::channel('orderlog')->info('ExceptionInValidation: ' .$th->getMessage());
            throw new ValidationException(ErrorCode::BAD_REQUEST, "Something error happened");  
        }
    }



    public function generateRazorPayOrderId($totalAmount)
    {
        $api_key = config('settings.razorpayKeyId');
        $api_secret = config('settings.razorpayKeySecret');

        $api = new Api($api_key, $api_secret);

        try {
            $response = Curl::to('https://api.razorpay.com/v1/orders')
                ->withOption('USERPWD', "$api_key:$api_secret")
                ->withData(array('amount' => $totalAmount * 100, 'currency' => 'INR', 'payment_capture' => 1))
                ->post();

            $response = json_decode($response);
            $response = [
                'razorpay_success' => true,
                'response' => $response,
            ];
            //return response()->json($response);
            return $response;
        } catch (\Throwable $th) {
            $response = [
                'razorpay_success' => false,
                'message' => $th->getMessage(),
            ];
            //return response()->json($reInside placeOrder()  sponse);
            return $response;
        }
    }




   
    /**
     * @param Request $request
     */
    public function placeOrderNEW(Request $request, TranslationHelper $translationHelper)
    {
        $user = auth()->user();

        Log::channel('orderlog')->info('#############################################################');
        Log::channel('orderlog')->info('Inside placeOrder()');
        Log::channel('orderlog')->info('#############################################################');


        if ($user) {
            Log::channel('orderlog')->info('REQUEST: ' .json_encode($request->all()));
            $keys = ['orderPaymentWalletComment', 'orderPartialPaymentWalletComment'];
            $translationData = $translationHelper->getDefaultLanguageValuesForKeys($keys);

            $newOrder = new Order();
            $newId = $this->generateUniqueId();
            $newOrder->unique_order_id = $newId;
            $newOrder->user_id = $user->id;
            //$newOrder->device_id = $request->device_id;
            //$newOrder->order_from = 'WEB';
            //if($request->device_id) $newOrder->order_from = 'ANDROID';
            $newOrder->location = json_encode($request->location);
            $newOrder->address = $request->full_address;
            //$newOrder->transaction_id = $request->payment_token; //NEW           
            $newOrder->order_comment = $request->order_comment;
            $newOrder->payment_mode = $request->payment_method;
            $newOrder->delivery_type = 2;
            if ($request->delivery_type == 1) $newOrder->delivery_type = 1;
            $user->delivery_pin = strtoupper(str_random(5));

            $restaurant = Restaurant::where('id', $request->restaurant_id)->first(); 
            $newOrder->restaurant_id = $restaurant->id; 
           
            if($request->pending_payment == true || $request->payment_method == 'PAYTM' || $request['method'] == 'MERCADOPAGO') {
                Log::channel('orderlog')->info('###### Inside Pending Payment');
                $newOrder->orderstatus_id = '8'; // PENDING_PAYMENT
            }elseif ($restaurant->auto_acceptable) {
                Log::channel('orderlog')->info('###### Inside AutoAcceptable');
                $newOrder->orderstatus_id = '2';
                $this->smsToDelivery($request->restaurant_id);
                if (config('settings.enablePushNotificationOrders') == 'true') {
                    //to user
                    $notify = new PushNotify();
                    $notify->sendPushNotification('2', $newOrder->user_id, $newOrder->unique_order_id);
                }
            } else {
                $newOrder->orderstatus_id = '1';
            }


            
            // OrderTotal:
            Log::channel('orderlog')->info('###### Calculate OrderTotal........');
            $orderTotal = 0;
            foreach ($request['order_items'] as $oI) {
                $originalItem = Item::where('id', $oI['id'])->first();
                $orderTotal += ($originalItem->price * $oI['qty']);

                if (isset($oI['selectedaddons'])) {
                    foreach ($oI['selectedaddons'] as $selectedaddon) {
                        $addon = Addon::where('id', $selectedaddon['addon_id'])->first();
                        if ($addon) {
                            $orderTotal += $addon->price * $oI['quantity'];
                        }
                    }
                }
            }
            $newOrder->sub_total = $orderTotal;
            Log::channel('orderlog')->info('###### SubTotal: '.$orderTotal);



            // RestaurantCharge based on orderAmount
            Log::channel('orderlog')->info('###### Calculate RestaurantCharge........');
            $restaurantCharge = 0;//2%=>10==>10
            if($restaurant->restaurant_charges){
                //$restaurant_charge = $this->calculateRestaurantCharge($restaurant, $orderTotal);
                $restaurantCharge = (float) (((float) $restaurant->restaurant_charges / 100) * $orderTotal);
                //$newOrder->restaurant_charge_in_percentage = (float)$restaurant->restaurant_charges; //NEW 
                $newOrder->restaurant_charge = $restaurantCharge;
                Log::channel('orderlog')->info('###### RestaurantCharge: ' .$restaurant->restaurant_charges);
                Log::channel('orderlog')->info('###### RestaurantChargeApplied: ' .$restaurantCharge);
            }
           

            
            //GST: For charging GST net value should be considered which is arrived after reduction of cash discount.
            // Here we are calculating tax orderAmount to maximize the profit
            //https://www.quora.com/Is-GST-applied-before-or-after-a-cash-discount
            Log::channel('orderlog')->info('###### Calculate Tax........');
            $taxAmount = 0;
            if (config('settings.taxApplicable') == 'true') {
                Log::channel('orderlog')->info('Tax Applicable: TRUE');
                $newOrder->tax = config('settings.taxPercentage');
                $taxAmount = (float) (((float) config('settings.taxPercentage') / 100) * $orderTotal);
                Log::channel('orderlog')->info('TaxPercentage: '. config('settings.taxPercentage'));
            } 
            $newOrder->tax_amount = $taxAmount;
            Log::channel('orderlog')->info('TaxApplied: '.$taxAmount);

            
            //Coupon:
            Log::channel('orderlog')->info('###### Calculate CouponAmount........');
            if ($request->coupon) {
                Log::channel('orderlog')->info('Coupon Applied: ' .$request->coupon);
                $coupon = Coupon::where('code', strtoupper($request['coupon']['code']))->first();
                if ($coupon) {
                    Log::channel('orderlog')->info($request->coupon .' is a valid coupon');
                    $newOrder->coupon_name = $request['coupon']['code'];
                    if ($coupon->discount_type == 'PERCENTAGE') {
                        Log::channel('orderlog')->info('DISCOUNT_TYPE: PERCENTAGE' );
                        $percentage_discount = (($coupon->discount / 100) * $orderTotal);
                        Log::channel('orderlog')->info('PERCENTAGE_DISCOUNT: ' .$percentage_discount );
                        if ($coupon->max_discount) {
                            if ($percentage_discount >= $coupon->max_discount) {
                                Log::channel('orderlog')->info('Percentage discount is greater than MaxDiscount');
                                $percentage_discount = $coupon->max_discount;
                            }
                        }
                        $newOrder->coupon_amount = $percentage_discount;
                        $orderTotal = $orderTotal - $percentage_discount;
                        Log::channel('orderlog')->info('COUPON_AMOUNT_APPLIED: ' .$newOrder->coupon_amount);
                    }
                    if ($coupon->discount_type == 'AMOUNT') {
                        Log::channel('orderlog')->info('FLAT_DISCOUNT: ' .$coupon->discount);
                        $newOrder->coupon_amount = $coupon->discount;
                        $orderTotal = $orderTotal - $coupon->discount;
                    }
                    $coupon->count = $coupon->count + 1;
                    $coupon->save();
                }
            }


            $orderTotal = $orderTotal + $restaurantCharge + $taxAmount;


            Log::channel('orderlog')->info('###### Calculating DeliveryCharge.....');
            if ($request->delivery_type == 1) {
                if ($restaurant->delivery_charge_type == 'DYNAMIC') {
                    //get distance between user and restaurant,                    
                    if (config('settings.enGDMA') == 'true') {
                        Log::channel('orderlog')->info('Google distance matrix enabled, distance: '.(float) $request->distance);
                        $distance = (float) $request->distance;
                    } else {
                        Log::channel('orderlog')->info('Google distance matrix not enabled');
                        //$distance = $this->getDistance($request['user']['data']['default_address']['latitude'], $request['user']['data']['default_address']['longitude'], $restaurant->latitude, $restaurant->longitude);
                        $distance = $this->getDistance($request['location']['latitude'], $request['location']['longitude'], $restaurant->latitude, $restaurant->longitude);
                        Log::channel('orderlog')->info('Server Calculated GeoGraphicalDistance: ' .$distance);
                        Log::channel('orderlog')->info('CLIENT Calculated GeoGraphicalDistance: ' .$request->distance);
                    }

                    if ($distance > $restaurant->base_delivery_distance) {
                        $extraDistance = $distance - $restaurant->base_delivery_distance;
                        $extraCharge = ($extraDistance / $restaurant->extra_delivery_distance) * $restaurant->extra_delivery_charge;
                        $dynamicDeliveryCharge = $restaurant->base_delivery_charge + $extraCharge;

                        if (config('settings.enDelChrRnd') == 'true') {
                            Log::channel('orderlog')->info('DeliveryCharge RoundUp enabled ');
                            $dynamicDeliveryCharge = ceil($dynamicDeliveryCharge);

                            Log::channel('orderlog')->info('Server DeliveryCharge: ' .$dynamicDeliveryCharge);
                        }

                        $newOrder->delivery_charge = $dynamicDeliveryCharge;
                        $orderTotal = $orderTotal + $dynamicDeliveryCharge;
                    } else {
                        $newOrder->delivery_charge = $restaurant->base_delivery_charge;
                        $orderTotal = $orderTotal + $restaurant->base_delivery_charge;
                    }

                } else {                    
                    $newOrder->delivery_charge = $restaurant->delivery_charges;
                    $orderTotal = $orderTotal + $restaurant->delivery_charges;
                }

            } else {
                $newOrder->delivery_charge = 0;
            }

            //DriverTipAmount:
            if (isset($request['tipAmount']) && !empty($request['tipAmount'])) {
                $orderTotal = $orderTotal + $request['tipAmount'];
                $newOrder->tip_amount = $request['tipAmount'];
            }

            //this is the final order total
            $newOrder->total = $orderTotal;
            Log::channel('orderlog')->info('TOTAL: ' .$newOrder->total);
            //return response()->json(['subtotal' => $orderTotal,'order' => $newOrder,]);

            
            if ($request['method'] == 'COD') {
                if ($request->partial_wallet == true) {
                    //deduct all user amount and add
                    $newOrder->payable = $orderTotal - $user->balanceFloat;
                }
                if ($request->partial_wallet == false) {
                    $newOrder->payable = $orderTotal;
                }
            }
      
            Log::channel('orderlog')->info('Saving user........');
            $user->save();


            /*##########################################################*/
            /*################## PaymentSection[START]                  */
            /*##########################################################*/           
            
            //process paypal payment
            Log::channel('orderlog')->info('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
            Log::channel('orderlog')->info('############# ProcessingPayment............');            
            Log::channel('orderlog')->info('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
            if ($request['method'] == 'PAYPAL' || $request['method'] == 'PAYSTACK' || $request['method'] == 'RAZORPAY' || $request['method'] == 'STRIPE' || $request['method'] == 'PAYMONGO' || $request['method'] == 'MERCADOPAGO' || $request['method'] == 'PAYTM') {
                Log::channel('orderlog')->info('Inside OnlinePaymentGateway');
                //successfuly received payment
                $newOrder->save();
                if ($request->partial_wallet == true) {

                    $userWalletBalance = $user->balanceFloat;
                    $newOrder->wallet_amount = $userWalletBalance;
                    $newOrder->save();
                    //deduct all user amount and add
                    $user->withdraw($userWalletBalance * 100, ['description' => $translationData->orderPartialPaymentWalletComment . $newOrder->unique_order_id]);
                }
                foreach ($request['order_items'] as $orderItem) {
                    $item = new Orderitem();
                    $item->order_id = $newOrder->id;
                    $item->item_id = $orderItem['itemId'];
                    $item->name = $orderItem['itemName'];
                    $item->quantity = $orderItem['qty'];
                    $item->price = $orderItem['price'];
                    $item->save();
                    if (isset($orderItem['selectedaddons'])) {
                        foreach ($orderItem['selectedaddons'] as $selectedaddon) {
                            $addon = new OrderItemAddon();
                            $addon->orderitem_id = $item->id;
                            $addon->addon_category_name = $selectedaddon['addon_category_name'];
                            $addon->addon_name = $selectedaddon['addon_name'];
                            $addon->addon_price = $selectedaddon['price'];
                            $addon->save();
                        }
                    }
                }

                $response = [
                    'success' => true,
                    'message' => 'Order placed successfully',
                    'data' => $newOrder,
                ];

                Log::channel('orderlog')->info('..........................................');
                Log::channel('orderlog')->info(json_encode($newOrder));
                Log::channel('orderlog')->info('..........................................');


                // Send SMS to restaurant owner only if not configured for auto acceptance, and order staus ID is 1 and sms notify is On by Admin
                if (!$restaurant->auto_acceptable && $newOrder->orderstatus_id == '1' && config('settings.smsRestaurantNotify') == 'true') {
                    Log::channel('orderlog')->info('Trigger SMS to Restaurant : ' .$request->restaurant_id);
                    $this->smsToRestaurant($request->restaurant_id, $orderTotal);
                    Log::channel('orderlog')->info('SMS send success to restaurant');
                }
                // END SMS

                if ($restaurant->auto_acceptable && config('settings.enablePushNotification') && config('settings.enablePushNotificationOrders') == 'true') {
                    //get all pivot users of restaurant (delivery guy/ res owners)
                    $pivotUsers = $restaurant->users()
                        ->wherePivot('restaurant_id', $restaurant->id)
                        ->get();
                    //filter only res owner and send notification.
                    foreach ($pivotUsers as $pU) {
                        if ($pU->hasRole('Delivery Guy')) {
                            //send Notification to Res Owner
                            $notify = new PushNotify();
                            Log::channel('orderlog')->info('Trigger Push notification to Driver ID: '.$pU->id .', ' .$pU->name);
                            $notify->sendPushNotification('TO_DELIVERY', $pU->id, $newOrder->unique_order_id);
                            Log::channel('orderlog')->info('PUSH Notification send success to driver');
                        }
                    }

                }

                /* OneSignal Push Notification to Store Owner */
                if ($newOrder->orderstatus_id == '1' && config('settings.oneSignalAppId') != null && config('settings.oneSignalRestApiKey') != null) {
                    $this->sendPushNotificationStoreOwner($request->restaurant_id);
                }
                /* END OneSignal Push Notification to Store Owner */

                return response()->json($response);
            }
            //if new payment gateway is added, write elseif here
            else {
                Log::channel('orderlog')->info('Inside COD Payment');
                $newOrder->save();
                if ($request['method'] == 'COD') {
                    if ($request->partial_wallet == true) {
                        $userWalletBalance = $user->balanceFloat;
                        $newOrder->wallet_amount = $userWalletBalance;
                        $newOrder->save();
                        //deduct all user amount and add
                        $user->withdraw($userWalletBalance * 100, ['description' => $translationData->orderPartialPaymentWalletComment . $newOrder->unique_order_id]);
                    }
                }

                //if method is WALLET, then deduct amount with appropriate description
                if ($request['method'] == 'WALLET') {
                    $userWalletBalance = $user->balanceFloat;
                    $newOrder->wallet_amount = $orderTotal;
                    $newOrder->save();
                    $user->withdraw($orderTotal * 100, ['description' => $translationData->orderPaymentWalletComment . $newOrder->unique_order_id]);
                }

                foreach ($request['order_items'] as $orderItem) {
                    $item = new Orderitem();
                    $item->order_id = $newOrder->id;
                    $item->item_id = $orderItem['itemId'];
                    $item->name = $orderItem['itemName'];
                    $item->quantity = $orderItem['qty'];
                    $item->price = $orderItem['price'];
                    $item->save();
                    if (isset($orderItem['selectedaddons'])) {
                        foreach ($orderItem['selectedaddons'] as $selectedaddon) {
                            $addon = new OrderItemAddon();
                            $addon->orderitem_id = $item->id;
                            $addon->addon_category_name = $selectedaddon['addon_category_name'];
                            $addon->addon_name = $selectedaddon['addon_name'];
                            $addon->addon_price = $selectedaddon['price'];
                            $addon->save();
                        }
                    }
                }

                $response = [
                    'success' => true,
                    'message' => 'Order placed successfully',
                    'data' => $newOrder,
                ];

                Log::channel('orderlog')->info('..........................................');
                Log::channel('orderlog')->info(json_encode($newOrder));
                Log::channel('orderlog')->info('..........................................');

                // Send SMS
                if (!$restaurant->auto_acceptable && $newOrder->orderstatus_id == '1' && config('settings.smsRestaurantNotify') == 'true') {
                    Log::channel('orderlog')->info('Send SMS to Restaurant');
                    $restaurant_id = $request['order'][0]['restaurant_id'];
                    $this->smsToRestaurant($restaurant_id, $orderTotal);
                    Log::channel('orderlog')->info('Successfully Send SMS to Restaurant');

                }
                // END SMS

                if ($restaurant->auto_acceptable && config('settings.enablePushNotification') && config('settings.enablePushNotificationOrders') == 'true') {
                    //get all pivot users of restaurant (delivery guy/ res owners)
                    $pivotUsers = $restaurant->users()
                        ->wherePivot('restaurant_id', $restaurant->id)
                        ->get();
                    //filter only res owner and send notification.
                    foreach ($pivotUsers as $pU) {
                        if ($pU->hasRole('Delivery Guy')) {
                            //send Notification to Res Owner
                            $notify = new PushNotify();
                            Log::channel('orderlog')->info('Send SMS to Restaurant');
                            Log::channel('orderlog')->info('Trigger Push notification to Driver ID: '.$pU->id .', ' .$pU->name);
                            $notify->sendPushNotification('TO_DELIVERY', $pU->id, $newOrder->unique_order_id);
                            Log::channel('orderlog')->info('PUSH Notification send success to driver');
                        }
                    }

                }

                /* OneSignal Push Notification to Store Owner */
                if ($newOrder->orderstatus_id == '1' && config('settings.oneSignalAppId') != null && config('settings.oneSignalRestApiKey') != null) {
                    $this->sendPushNotificationStoreOwner($restaurant_id);
                }
                /* END OneSignal Push Notification to Store Owner */

                return response()->json($response);
            }

        }
    }



    /**
     * @param Request $request
     */
    public function placeOrderOld(Request $request, TranslationHelper $translationHelper)
    {
        $user = auth()->user();

        if ($user) {
            $keys = ['orderPaymentWalletComment', 'orderPartialPaymentWalletComment'];
            $translationData = $translationHelper->getDefaultLanguageValuesForKeys($keys);

            $newOrder = new Order();

            $checkingIfEmpty = Order::count();

            $lastOrder = Order::orderBy('id', 'desc')->first();

            if ($lastOrder) {
                $lastOrderId = $lastOrder->id;
                $newId = $lastOrderId + 1;
                $uniqueId = Hashids::encode($newId);
            } else {
                //first order
                $newId = 1;
            }

            $uniqueId = Hashids::encode($newId);
            $unique_order_id = 'OD' . '-' . date('m-d') . '-' . strtoupper(str_random(4)) . '-' . strtoupper($uniqueId);
            $newOrder->unique_order_id = $unique_order_id;

            $restaurant_id = $request['order'][0]['restaurant_id'];
            $restaurant = Restaurant::where('id', $restaurant_id)->first();

            $newOrder->user_id = $user->id;

            if ($request['pending_payment'] || $request['method'] == 'MERCADOPAGO' || $request['method'] == 'PAYTM') {
                $newOrder->orderstatus_id = '8';
            } elseif ($restaurant->auto_acceptable) {
                $newOrder->orderstatus_id = '2';
                $this->smsToDelivery($restaurant_id);
                if (config('settings.enablePushNotificationOrders') == 'true') {
                    //to user
                    $notify = new PushNotify();
                    $notify->sendPushNotification('2', $newOrder->user_id, $newOrder->unique_order_id);
                }
            } else {
                $newOrder->orderstatus_id = '1';
            }

            $newOrder->location = json_encode($request['location']);

            $full_address = $request['user']['data']['default_address']['house'] . ', ' . $request['user']['data']['default_address']['address'];
            $newOrder->address = $full_address;

            //get restaurant charges
            $newOrder->restaurant_charge = $restaurant->restaurant_charges;

            $newOrder->transaction_id = $request->payment_token;

            $orderTotal = 0;
            foreach ($request['order'] as $oI) {
                $originalItem = Item::where('id', $oI['id'])->first();
                $orderTotal += ($originalItem->price * $oI['quantity']);

                if (isset($oI['selectedaddons'])) {
                    foreach ($oI['selectedaddons'] as $selectedaddon) {
                        $addon = Addon::where('id', $selectedaddon['addon_id'])->first();
                        if ($addon) {
                            $orderTotal += $addon->price * $oI['quantity'];
                        }
                    }
                }
            }
            $newOrder->sub_total = $orderTotal;

            if ($request->coupon) {
                $coupon = Coupon::where('code', strtoupper($request['coupon']['code']))->first();
                if ($coupon) {
                    $newOrder->coupon_name = $request['coupon']['code'];
                    if ($coupon->discount_type == 'PERCENTAGE') {
                        $percentage_discount = (($coupon->discount / 100) * $orderTotal);
                        if ($coupon->max_discount) {
                            if ($percentage_discount >= $coupon->max_discount) {
                                $percentage_discount = $coupon->max_discount;
                            }
                        }
                        $newOrder->coupon_amount = $percentage_discount;
                        $orderTotal = $orderTotal - $percentage_discount;
                    }
                    if ($coupon->discount_type == 'AMOUNT') {
                        $newOrder->coupon_amount = $coupon->discount;
                        $orderTotal = $orderTotal - $coupon->discount;
                    }
                    $coupon->count = $coupon->count + 1;
                    $coupon->save();
                }
            }

            if ($request->delivery_type == 1) {
                if ($restaurant->delivery_charge_type == 'DYNAMIC') {
                    //get distance between user and restaurant,
                    if (config('settings.enGDMA') == 'true') {
                        $distance = (float) $request->dis;
                    } else {
                        $distance = $this->getDistance($request['user']['data']['default_address']['latitude'], $request['user']['data']['default_address']['longitude'], $restaurant->latitude, $restaurant->longitude);
                    }

                    if ($distance > $restaurant->base_delivery_distance) {
                        $extraDistance = $distance - $restaurant->base_delivery_distance;
                        $extraCharge = ($extraDistance / $restaurant->extra_delivery_distance) * $restaurant->extra_delivery_charge;
                        $dynamicDeliveryCharge = $restaurant->base_delivery_charge + $extraCharge;

                        if (config('settings.enDelChrRnd') == 'true') {
                            $dynamicDeliveryCharge = ceil($dynamicDeliveryCharge);
                        }

                        $newOrder->delivery_charge = $dynamicDeliveryCharge;
                        $orderTotal = $orderTotal + $dynamicDeliveryCharge;
                    } else {
                        $newOrder->delivery_charge = $restaurant->base_delivery_charge;
                        $orderTotal = $orderTotal + $restaurant->base_delivery_charge;
                    }

                } else {
                    $newOrder->delivery_charge = $restaurant->delivery_charges;
                    $orderTotal = $orderTotal + $restaurant->delivery_charges;
                }

            } else {
                $newOrder->delivery_charge = 0;
            }

            $orderTotal = $orderTotal + $restaurant->restaurant_charges;

            if (config('settings.taxApplicable') == 'true') {
                $newOrder->tax = config('settings.taxPercentage');

                $taxAmount = (float) (((float) config('settings.taxPercentage') / 100) * $orderTotal);
            } else {
                $taxAmount = 0;
            }

            $newOrder->tax_amount = $taxAmount;

            $orderTotal = $orderTotal + $taxAmount;

            if (isset($request['tipAmount']) && !empty($request['tipAmount'])) {
                $orderTotal = $orderTotal + $request['tipAmount'];
            }

            //this is the final order total

            if ($request['method'] == 'COD') {
                if ($request->partial_wallet == true) {
                    //deduct all user amount and add
                    $newOrder->payable = $orderTotal - $user->balanceFloat;
                }
                if ($request->partial_wallet == false) {
                    $newOrder->payable = $orderTotal;
                }
            }

            $newOrder->total = $orderTotal;

            $newOrder->order_comment = $request['order_comment'];

            $newOrder->payment_mode = $request['method'];

            $newOrder->restaurant_id = $request['order'][0]['restaurant_id'];

            $newOrder->tip_amount = $request['tipAmount'];

            if ($request->delivery_type == 1) {
                //delivery
                $newOrder->delivery_type = 1;
            } else {
                //selfpickup
                $newOrder->delivery_type = 2;
            }

            $user->delivery_pin = strtoupper(str_random(5));
            $user->save();
            //process paypal payment
            if ($request['method'] == 'PAYPAL' || $request['method'] == 'PAYSTACK' || $request['method'] == 'RAZORPAY' || $request['method'] == 'STRIPE' || $request['method'] == 'PAYMONGO' || $request['method'] == 'MERCADOPAGO' || $request['method'] == 'PAYTM') {
                //successfuly received payment
                $newOrder->save();
                if ($request->partial_wallet == true) {

                    $userWalletBalance = $user->balanceFloat;
                    $newOrder->wallet_amount = $userWalletBalance;
                    $newOrder->save();
                    //deduct all user amount and add
                    $user->withdraw($userWalletBalance * 100, ['description' => $translationData->orderPartialPaymentWalletComment . $newOrder->unique_order_id]);
                }
                foreach ($request['order'] as $orderItem) {
                    $item = new Orderitem();
                    $item->order_id = $newOrder->id;
                    $item->item_id = $orderItem['id'];
                    $item->name = $orderItem['name'];
                    $item->quantity = $orderItem['quantity'];
                    $item->price = $orderItem['price'];
                    $item->save();
                    if (isset($orderItem['selectedaddons'])) {
                        foreach ($orderItem['selectedaddons'] as $selectedaddon) {
                            $addon = new OrderItemAddon();
                            $addon->orderitem_id = $item->id;
                            $addon->addon_category_name = $selectedaddon['addon_category_name'];
                            $addon->addon_name = $selectedaddon['addon_name'];
                            $addon->addon_price = $selectedaddon['price'];
                            $addon->save();
                        }
                    }
                }

                $response = [
                    'success' => true,
                    'data' => $newOrder,
                ];

                // Send SMS to restaurant owner only if not configured for auto acceptance, and order staus ID is 1 and sms notify is On by Admin
                if (!$restaurant->auto_acceptable && $newOrder->orderstatus_id == '1' && config('settings.smsRestaurantNotify') == 'true') {

                    $restaurant_id = $request['order'][0]['restaurant_id'];
                    $this->smsToRestaurant($restaurant_id, $orderTotal);
                }
                // END SMS

                if ($restaurant->auto_acceptable && config('settings.enablePushNotification') && config('settings.enablePushNotificationOrders') == 'true') {

                    //get all pivot users of restaurant (delivery guy/ res owners)
                    $pivotUsers = $restaurant->users()
                        ->wherePivot('restaurant_id', $restaurant->id)
                        ->get();
                    //filter only res owner and send notification.
                    foreach ($pivotUsers as $pU) {
                        if ($pU->hasRole('Delivery Guy')) {
                            //send Notification to Res Owner
                            $notify = new PushNotify();
                            $notify->sendPushNotification('TO_DELIVERY', $pU->id, $newOrder->unique_order_id);
                        }
                    }

                }

                /* OneSignal Push Notification to Store Owner */
                if ($newOrder->orderstatus_id == '1' && config('settings.oneSignalAppId') != null && config('settings.oneSignalRestApiKey') != null) {
                    $this->sendPushNotificationStoreOwner($restaurant_id);
                }
                /* END OneSignal Push Notification to Store Owner */

                return response()->json($response);
            }
            //if new payment gateway is added, write elseif here
            else {
                $newOrder->save();
                if ($request['method'] == 'COD') {
                    if ($request->partial_wallet == true) {
                        $userWalletBalance = $user->balanceFloat;
                        $newOrder->wallet_amount = $userWalletBalance;
                        $newOrder->save();
                        //deduct all user amount and add
                        $user->withdraw($userWalletBalance * 100, ['description' => $translationData->orderPartialPaymentWalletComment . $newOrder->unique_order_id]);
                    }
                }

                //if method is WALLET, then deduct amount with appropriate description
                if ($request['method'] == 'WALLET') {
                    $userWalletBalance = $user->balanceFloat;
                    $newOrder->wallet_amount = $orderTotal;
                    $newOrder->save();
                    $user->withdraw($orderTotal * 100, ['description' => $translationData->orderPaymentWalletComment . $newOrder->unique_order_id]);
                }

                foreach ($request['order'] as $orderItem) {
                    $item = new Orderitem();
                    $item->order_id = $newOrder->id;
                    $item->item_id = $orderItem['id'];
                    $item->name = $orderItem['name'];
                    $item->quantity = $orderItem['quantity'];
                    $item->price = $orderItem['price'];
                    $item->save();
                    if (isset($orderItem['selectedaddons'])) {
                        foreach ($orderItem['selectedaddons'] as $selectedaddon) {
                            $addon = new OrderItemAddon();
                            $addon->orderitem_id = $item->id;
                            $addon->addon_category_name = $selectedaddon['addon_category_name'];
                            $addon->addon_name = $selectedaddon['addon_name'];
                            $addon->addon_price = $selectedaddon['price'];
                            $addon->save();
                        }
                    }
                }

                $response = [
                    'success' => true,
                    'data' => $newOrder,
                ];

                // Send SMS
                if (!$restaurant->auto_acceptable && $newOrder->orderstatus_id == '1' && config('settings.smsRestaurantNotify') == 'true') {

                    $restaurant_id = $request['order'][0]['restaurant_id'];
                    $this->smsToRestaurant($restaurant_id, $orderTotal);

                }
                // END SMS

                if ($restaurant->auto_acceptable && config('settings.enablePushNotification') && config('settings.enablePushNotificationOrders') == 'true') {
                    //get all pivot users of restaurant (delivery guy/ res owners)
                    $pivotUsers = $restaurant->users()
                        ->wherePivot('restaurant_id', $restaurant->id)
                        ->get();
                    //filter only res owner and send notification.
                    foreach ($pivotUsers as $pU) {
                        if ($pU->hasRole('Delivery Guy')) {
                            //send Notification to Res Owner
                            $notify = new PushNotify();
                            $notify->sendPushNotification('TO_DELIVERY', $pU->id, $newOrder->unique_order_id);
                        }
                    }

                }

                /* OneSignal Push Notification to Store Owner */
                if ($newOrder->orderstatus_id == '1' && config('settings.oneSignalAppId') != null && config('settings.oneSignalRestApiKey') != null) {
                    $this->sendPushNotificationStoreOwner($restaurant_id);
                }
                /* END OneSignal Push Notification to Store Owner */

                return response()->json($response);
            }

        }
    }

    /**
     * @param Request $request
     */
    public function getOrders(Request $request)
    {
        $user = auth()->user();
        Log::info('Inside GetOrders: '. $user->id);
        Log::info('User failed to login.', ['id' => $user->id]);
        Log::channel('orderlog')->info('Something happened!');
        //Log::channel('orderlog')->info('Something happened!' .json_encode($request->all()));
       
        if ($user) {
            $orders = Order::where('user_id', $user->id)->with('orderitems', 'orderitems.order_item_addons', 'restaurant')->orderBy('id', 'DESC')->get();
            return response()->json($orders);
        }
        return response()->json(['success' => false], 401);
    }



   
     public function getOrderDetails(Request $request)
     {
         $user = auth()->user();
         if ($user) {
             $order = Order::where('id', $request->order_id)
                 ->with('orderitems', 'orderitems.order_item_addons', 'restaurant')->orderBy('id', 'DESC')
                 ->first();
             if($order == null)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "Invalid order_id" .$request->order_id);


             //$items = Orderitem::where('order_id', $request->order_id)->get();
             return response()->json([
                 'success' => true,
                 'data' => $order,
             ]);
         }else{
             throw new ValidationException(ErrorCode::INVALID_AUTH_TOKEN, "Authentication Fail");
         }


     }








    /**
     * @param Request $request
     */
    public function getOrderItems(Request $request)
    {
        $user = auth()->user();
        if ($user) {

            $items = Orderitem::where('order_id', $request->order_id)->get();
            return response()->json($items);
        }
        return response()->json(['success' => false], 401);

    }

    /**
     * @param Request $request
     */
    public function cancelOrder(Request $request, TranslationHelper $translationHelper)
    {
        Log::channel('orderlog')->info('########## Inside cancelOrder()');
        Log::channel('orderlog')->info('USER: ' .json_encode($request->all()));

        $keys = ['orderRefundWalletComment', 'orderPartialRefundWalletComment'];

        $translationData = $translationHelper->getDefaultLanguageValuesForKeys($keys);



        $user = auth()->user();
        if($user == null)throw new ValidationException(ErrorCode::INVALID_AUTH_TOKEN, "Authentication Fail");
        $order = Order::where('id', $request->order_id)->first();
        if($order == null)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "Invalid order_id" .$request->order_id);      


        //check if user is cancelling their own order before accept by restaurant...
        //if ($order->user_id == $user->id && $order->orderstatus_id == 1)
        if ($order->user_id == $user->id) {
            Log::channel('orderlog')->info('########## $user '.$user->id . ' is cancelling his own order');

            //if payment method is not COD, and order status is 1 (Order placed) then refund to wallet
            $refund = false;

            //if COD, then check if wallet is present
            if ($order->payment_mode == 'COD') {
                Log::channel('orderlog')->info('PAYMENT_MODE: COD');
                if ($order->wallet_amount != null) {
                    //refund wallet amount
                    Log::channel('orderlog')->info('Refund wallet amount: ' .$order->wallet_amount * 100);
                    $user->deposit($order->wallet_amount * 100, ['description' => $translationData->orderPartialRefundWalletComment . $order->unique_order_id]);
                    $refund = true;
                }
            } else {
                //if online payment, refund the total to wallet
                Log::channel('orderlog')->info('PAYMENT_MODE: ONLINE');
                Log::channel('orderlog')->info('Refund to wallet amount: ' .($order->total) * 100);
                $user->deposit(($order->total) * 100, ['description' => $translationData->orderRefundWalletComment . $order->unique_order_id]);
                $refund = true;
            }

            //cancel order
            $order->orderstatus_id = 6; //6 means canceled..
            $order->save();

            //throw notification to user
            if (config('settings.enablePushNotificationOrders') == 'true') {
                $notify = new PushNotify();
                Log::channel('orderlog')->info('Sending cancell notification to customer');
                $notify->sendPushNotification('6', $order->user_id);
                Log::channel('orderlog')->info('Cancell notification send successfully to customer');
            }

            //throw notification to Restaurant owner, if already accepted
            $statusToBeCheck = array(2, 3, 4, 7, 10);
            if (in_array($order->user_id, $statusToBeCheck)  && config('settings.enablePushNotification') && config('settings.enablePushNotificationOrders') == 'true') {
                $restaurant = Restaurant::where('id', $order->restaurant_id)->first();
                Log::channel('orderlog')->info('Sending Notification to Restaurant: '.$restaurant->id);

                //get all pivot users of restaurant (delivery guy/ res owners)
                $pivotUsers = $restaurant->users()
                    ->wherePivot('restaurant_id', $restaurant->id)
                    ->get();
                //filter only res owner and send notification.
                foreach ($pivotUsers as $pU) {
                    if ($pU->hasRole('Delivery Guy')) {
                        //send Notification to Res Owner
                        $notify = new PushNotify();
                        Log::channel('orderlog')->info('Trigger Push notification to Driver ID: '.$pU->id .'. ' .$pU->name);
                        $notify->sendPushNotification('TO_DELIVERY', $pU->id, $order->unique_order_id);
                        Log::channel('orderlog')->info('PUSH Notification send success to driver');
                    }
                }
            }

            $response = [
                'success' => true,
                'message' => 'Order cancelled successfully, amount will be refund within 48 hour',
                'refund' => $refund,
            ];

            return response()->json($response);

        } else {
            Log::channel('orderlog')->info('Order cant be cancelled');
            if($order->orderstatus_id != 1)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, 'Order is already accepted by the restaurant'); 
            throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, 'Order cant be cancelled'); 
        }

    }

    /**
     * @param $latitudeFrom
     * @param $longitudeFrom
     * @param $latitudeTo
     * @param $longitudeTo
     * @return mixed
     */
    private function getDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo)
    {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * 6371;
    }

    /**
     * @param $restaurant_id
     * @param $orderTotal
     */
    private function smsToRestaurant($restaurant_id, $orderTotal)
    {
        //get restaurant
        $restaurant = Restaurant::where('id', $restaurant_id)->first();
        if ($restaurant) {
            if ($restaurant->is_notifiable) {
                //get all pivot users of restaurant (Store Ownerowners)
                $pivotUsers = $restaurant->users()
                    ->wherePivot('restaurant_id', $restaurant_id)
                    ->get();
                //filter only res owner and send notification.
                foreach ($pivotUsers as $pU) {
                    if ($pU->hasRole('Store Owner')) {
                        // Include Order orderTotal or not ?
                        switch (config('settings.smsRestOrderValue')) {
                            case 'true':
                                $message = config('settings.defaultSmsRestaurantMsg') . round($orderTotal);
                                break;
                            case 'false':
                                $message = config('settings.defaultSmsRestaurantMsg');
                                break;
                        }
                        // As its not an OTP based message Nulling OTP
                        $otp = null;
                        $smsnotify = new Sms();
                        $smsnotify->processSmsAction('OD_NOTIFY', $pU->phone, $otp, $message);
                    }
                }
            }
        }
    }

    /**
     * @param $restaurant_id
     */
    private function smsToDelivery($restaurant_id)
    {
        //get restaurant
        $restaurant = Restaurant::where('id', $restaurant_id)->first();
        if ($restaurant) {
            //get all pivot users of restaurant (Store Ownerowners)
            $pivotUsers = $restaurant->users()
                ->wherePivot('restaurant_id', $restaurant_id)
                ->get();
            //filter only res owner and send notification.
            foreach ($pivotUsers as $pU) {
                if ($pU->hasRole('Delivery Guy')) {
                    if ($pU->delivery_guy_detail->is_notifiable) {
                        $message = config('settings.defaultSmsDeliveryMsg');
                        // As its not an OTP based message Nulling OTP
                        $otp = null;
                        $smsnotify = new Sms();
                        $smsnotify->processSmsAction('OD_NOTIFY', $pU->phone, $otp, $message);
                    }
                }
            }
        }
    }

    private function sendPushNotificationStoreOwner($restaurant_id)
    {
        $restaurant = Restaurant::where('id', $restaurant_id)->first();
        if ($restaurant) {
            //get all pivot users of restaurant (Store Ownerowners)
            $pivotUsers = $restaurant->users()
                ->wherePivot('restaurant_id', $restaurant_id)
                ->get();
            //filter only res owner and send notification.
            foreach ($pivotUsers as $pU) {
                if ($pU->hasRole('Store Owner')) {
                    // \Log::info('Send Push notification to store owner');
                    $message = config('settings.restaurantNewOrderNotificationMsg');
                    OneSignal::sendNotificationToExternalUser(
                        $message,
                        $pU->id,
                        $url = null,
                        $data = null,
                        $buttons = null,
                        $schedule = null
                    );
                }
            }
        }
    }
}
