<?php

namespace App\Http\Controllers;

use App\AcceptDelivery;
use App\DeliveryCollection;
use App\DeliveryCollectionLog;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Helpers\TranslationHelper;
use App\LoginSession;
use App\Order;
use App\Orderitem;
use App\PushNotify;
use App\PushToken;
use App\RestaurantEarning;
use App\Sms;
use App\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JWTAuth;
use JWTAuthException;
use NotificationType;
use ErrorCode;

class DeliveryController extends Controller
{
    /**
     * @param $email
     * @param $password
     * @return mixed
     */
    private function getToken($email, $password)
    {
        $token = null;
        try {
            if (!$token = JWTAuth::attempt(['email' => $email, 'password' => $password])) {
                return response()->json([
                    'response' => 'error',
                    'message' => 'Password or email is invalid..',
                    'token' => $token,
                ]);
            }
        } catch (JWTAuthException $e) {
            return response()->json([
                'response' => 'error',
                'message' => 'Token creation failed',
            ]);
        }
        return $token;
    }

    /**
     * @param $phone
     * @param $password
     * @return mixed
     */
    private function getTokenFromPhoneAndPassword($phone, $password)
    {
        $token = null;
        //$credentials = $request->only('email', 'password');
        try {
            if (!$token = JWTAuth::attempt(['phone' => $phone, 'password' => $password])) {
                return response()->json([
                    'response' => 'error',
                    'message' => 'Password or phone is invalid..',
                    'token' => $token,
                ]);
            }
        } catch (JWTAuthException $e) {
            return response()->json([
                'response' => 'error',
                'message' => 'Token creation failed',
            ]);
        }
        return $token;
    }



    /**
     * @param $user_id
     * @param $push_token
     */
    private function savePushToken($user_id, $push_token)
    {
        $pushToken = PushToken::where('user_id', $user_id)->first();

        if ($pushToken) {
            //update the existing token
            $pushToken->token = $push_token;
            $pushToken->save();
        } else {
            //create new token for user
            $pushToken = new PushToken();
            $pushToken->token = $push_token;
            $pushToken->user_id = $user_id;
            $pushToken->save();
        }
        $success = $push_token;
        return response()->json($success);
    }



    /**
     * @param Request $request
     */
    public function login(Request $request)
    {
        Log::info('#############################################################');
        Log::info('Inside loginUsingOtp() :: Role: DELIVERY_GUY');
        Log::info('#############################################################');
        if($request->phone && $request->otp){
            //  First check phone is valid  or not
            $user = \App\User::where('phone', $request->phone)->get()->first();
            Log::info('IsRoleCustomer: ' .$user->hasRole('Customer'));
            if($user && $user->hasRole('Delivery Guy')){
                if($user->is_active == 1){
                    if($user->delivery_guy_detail == null)throw new AuthenticationException(ErrorCode::ACCOUNT_BLOCKED, "User'is not approved, user details not updated");

                    Log::info('IsActive: ' .$user->is_active);
                    $sms = new Sms();
                    Log::info('Calling Verify OTP.....: ');
                    $verifyResponse = $sms->verifyOtp($request->phone, $request->otp);
                    if($verifyResponse['valid_otp'] == true){
                        Log::info('OTP Verification: true');
                        $password = \Hash::make($request->otp);
                        $user->password = $password;
                        $user->save();

                        $token = self::getTokenFromPhoneAndPassword($request->phone, $request->otp);
                        $user->auth_token = $token;
                        $user->save();

                        Log::info('Saving push token......');
                        if($request->push_token){
                            $this->savePushToken($user->id, $request->push_token);
                        }

                        try{
                            if($request->meta != null){
                                $loginSession =  LoginSession::where('user_id', $user->id)->get()->first();
                                if(!$loginSession){
                                    $loginSession = new LoginSession();
                                    $loginSession->user_id = $user->id;
                                }
                                $loginSession->login_at = Carbon::now();
                                $loginSession->mac_address = isset($request->meta['MAC']) ? $request->meta['MAC'] : null;
                                $loginSession->ip_address = isset($request->meta['wifiIP']) ? $request->meta['wifiIP'] : null;
                                $loginSession->manufacturer = isset($request->meta['manufacturer']) ? $request->meta['manufacturer'] : null;
                                $loginSession->model = isset($request->meta['model']) ? $request->meta['model'] : null;
                                $loginSession->sdk = isset($request->meta['sdk']) ? $request->meta['sdk'] : null;
                                $loginSession->brand = isset($request->meta['brand']) ? $request->meta['brand'] : null;
                                $loginSession->save();

                            }
                        }catch (\Throwable $th) {
                            Log::error('ERROR inside login() during meta record insertion');
                            Log::error('ERROR: ' .$th->getMessage());
                        }

                        $onGoingDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                            $query->whereIn('orderstatus_id', ['3', '4']);
                        })->where('user_id', $user->id)->where('is_complete', 0)->count();

                        $completedDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                            $query->whereIn('orderstatus_id', ['5']);
                        })->where('user_id', $user->id)->where('is_complete', 1)->count();



                        $response = [
                            'success' => true,
                            'data' => [
                                'id' => $user->id,
                                'auth_token' => $user->auth_token,
                                'name' => $user->name,
                                'email' => $user->email,
                                'wallet_balance' => $user->balanceFloat,
                                'onGoingCount' => $onGoingDeliveriesCount,
                                'completedCount' => $completedDeliveriesCount,
                                'push_token'=>$request->push_token,

                                'nick_name' => $user->delivery_guy_detail->name,
                                'age' => $user->delivery_guy_detail->age,
                                'photo' => $user->delivery_guy_detail->photo,
                                'phone' => $user->phone,
                                'vehicle_number'=>$user->delivery_guy_detail->vehicle_number,
                                'description' => $user->delivery_guy_detail->description,


                            ],
                        ];
                        return response()->json($response, 201);

                    }else{
                        return response()->json(['success' => false,"message" => "Invalid OTP", ]);
                    }
                }else{
                    throw new AuthenticationException(ErrorCode::ACCOUNT_BLOCKED, "User blocked");
                }
            }else{
                if(!$user)throw new AuthenticationException(ErrorCode::PHONE_NOT_EXIST, "driver not found for " .$request->phone);
                if(!$user->hasRole('Delivery Guy'))throw new AuthenticationException(ErrorCode::BAD_REQUEST, "Invalid Role ");
                throw new AuthenticationException(ErrorCode::BAD_RESPONSE, "Something error happened");
            }
        }else{
            if(!$request->phone)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "phone should not be null");
            if(!$request->otp)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "otp should not be null");
            return response()->json([
                'success' => false,
                "message" => "Invalid request body"
            ]);
        }

    }




    /**
     * @param Request $request
     */
    public function loginOld(Request $request)
    {
        $user = \App\User::where('email', $request->email)->get()->first();
        if ($user && \Hash::check($request->password, $user->password)) {

            if ($user->hasRole('Delivery Guy')) {
                $token = self::getToken($request->email, $request->password);
                $user->auth_token = $token;
                $user->save();

                $onGoingDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                    $query->whereIn('orderstatus_id', ['3', '4']);
                })->where('user_id', $user->id)->where('is_complete', 0)->count();

                $completedDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                    $query->whereIn('orderstatus_id', ['5']);
                })->where('user_id', $user->id)->where('is_complete', 1)->count();

                $response = [
                    'success' => true,
                    'data' => [
                        'id' => $user->id,
                        'auth_token' => $user->auth_token,
                        'name' => $user->name,
                        'email' => $user->email,
                        'wallet_balance' => $user->balanceFloat,
                        'onGoingCount' => $onGoingDeliveriesCount,
                        'completedCount' => $completedDeliveriesCount,
                    ],
                ];
            } else {
                $response = ['success' => false, 'data' => 'Record doesnt exists'];
            }
        } else {
            $response = ['success' => false, 'data' => 'Record doesnt exists...'];
        }
        return response()->json($response, 201);
    }

    /**
     * @param Request $request
     */
    public function updateDeliveryUserInfo(Request $request)
    {
        $deliveryUser = auth()->user();

        if ($deliveryUser && $deliveryUser->hasRole('Delivery Guy')) {

            $onGoingDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                $query->whereIn('orderstatus_id', ['3', '4']);
            })->where('user_id', $deliveryUser->id)->where('is_complete', 0)->count();

            $completedDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                $query->whereIn('orderstatus_id', ['5']);
            })->where('user_id', $deliveryUser->id)->where('is_complete', 1)->count();

            $orders = AcceptDelivery::whereHas('order', function ($query) {
                $query->whereIn('orderstatus_id', ['3', '4', '5']);
            })->where('user_id', $deliveryUser->id)
                ->with(array('order' => function ($q) {
                    $q->select('id', 'orderstatus_id', 'unique_order_id', 'address', 'payment_mode', 'payable');
                }))->orderBy('created_at', 'DESC')->get();

            $earnings = $deliveryUser->transactions()->orderBy('id', 'DESC')->get();
            $totalEarnings = 0;
            foreach ($deliveryUser->transactions->reverse() as $transaction) {
                if ($transaction->type === 'deposit') {
                    $totalEarnings += $transaction->amount / 100;
                }
            }

            $deliveryCollection = DeliveryCollection::where('user_id', $deliveryUser->id)->first();
            if (!$deliveryCollection) {
                $deliveryCollectionAmount = 0;
            } else {
                $deliveryCollectionAmount = $deliveryCollection->amount;
            }

            $dateRange = Carbon::today()->subDays(7);
            $earningData = DB::table('transactions')
                ->where('payable_id', $deliveryUser->id)
                ->where('created_at', '>=', $dateRange)
                ->where('type', 'deposit')
                ->select(DB::raw('sum(amount) as total'), DB::raw('date(created_at) as dates'))
                ->groupBy('dates')
                ->orderBy('dates', 'desc')
                ->get();

            for ($i = 0; $i <= 6; $i++) {
                if (!isset($earningData[$i])) {
                    $amount[] = 0;
                } else {
                    $amount[] = $earningData[$i]->total / 100;
                }
            }

            for ($i = 0; $i <= 6; $i++) {
                $days[] = Carbon::now()->subDays($i)->format('D');
            }

            foreach ($amount as $amt) {
                $amtArr[] = [
                    'y' => $amt,
                ];
            }
            $amtArr = array_reverse($amtArr);
            foreach ($days as $key => $day) {
                $dayArr[] = [
                    'x' => $day,
                ];
            }
            $dayArr = array_reverse($dayArr);
            $chartData = [];
            for ($i = 0; $i <= 6; $i++) {
                array_push($chartData, ($amtArr[$i] + $dayArr[$i]));
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $deliveryUser->id,
                    'auth_token' => $deliveryUser->auth_token,
                    'name' => $deliveryUser->name,
                    'email' => $deliveryUser->email,
                    'wallet_balance' => $deliveryUser->balanceFloat,
                    'onGoingCount' => $onGoingDeliveriesCount,
                    'completedCount' => $completedDeliveriesCount,
                    'orders' => $orders,
                    'earnings' => $earnings,
                    'totalEarnings' => $totalEarnings,
                    'deliveryCollection' => $deliveryCollectionAmount,
                ],
                'chart' => [
                    'chartData' => $chartData,
                ],
            ];
            return response()->json($response, 201);

        }

        $response = ['success' => false, 'data' => 'Record doesnt exists'];
    }

    /**
     * @param Request $request
     */
    public function getDeliveryOrders(Request $request)
    {
        $deliveryUser = Auth::user();
        $userRestaurants = $deliveryUser->restaurants;

        $deliveryGuyCommissionRate = $deliveryUser->delivery_guy_detail->commission_rate;

        $orders = Order::where('orderstatus_id', '2')
            ->where('delivery_type', '1')
            ->with('restaurant')
            ->orderBy('id', 'DESC')
            ->get();

        $deliveryGuyNewOrders = collect();
        foreach ($orders as $order) {

            $commission = 0;
            if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                $commission = $deliveryGuyCommissionRate / 100 * $order->total;
            }
            if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                $commission = $deliveryGuyCommissionRate / 100 * $order->delivery_charge;
            }
            $order->commission = number_format((float) $commission, 2, '.', '');

            foreach ($userRestaurants as $ur) {
                //checking if delivery guy is assigned to that restaurant
                if ($order->restaurant->id == $ur->id) {
                    $deliveryGuyNewOrders->push($order);
                }
            }
        }

        $alreadyAcceptedDeliveries = collect();
        $acceptDeliveries = AcceptDelivery::where('user_id', Auth::user()->id)->where('is_complete', 0)->get();
        foreach ($acceptDeliveries as $ad) {
            //$order = Order::where('id', $ad->order_id)->whereIn('orderstatus_id', ['3'])->with('restaurant')->first(); /ORIGINAL
            $order = Order::where('id', $ad->order_id)->whereIn('orderstatus_id', ['3', '10', '73', '710'])->with('restaurant')->first();

            if ($order) {
                $commission = 0;
                if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->total;
                }
                if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->delivery_charge;
                }
                $order->commission = number_format((float) $commission, 2, '.', '');

                $alreadyAcceptedDeliveries->push($order);
            }
        }

        $pickedupOrders = collect();
        $acceptDeliveries = AcceptDelivery::where('user_id', Auth::user()->id)->where('is_complete', 0)->get();
        foreach ($acceptDeliveries as $ad) {
            $order = Order::where('id', $ad->order_id)->whereIn('orderstatus_id', ['4', '11'])->with('restaurant')->first();

            if ($order) {
                $commission = 0;
                if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->total;
                }
                if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->delivery_charge;
                }
                $order->commission = number_format((float) $commission, 2, '.', '');

                $pickedupOrders->push($order);
            }
        }

        $cancelledOrders = collect();
        $transferredOrders = collect();
        if(isset($request->processing_orders)){
            $orderIds = $request->processing_orders;
            $processingOrders = Order::whereIn('id', $orderIds)->get();

            foreach ($processingOrders as $pd){
                if($pd->orderstatus_id == '6'){ // Order is CANCELLED by the user
                    $cancelledOrders->push($pd);
                }else if($pd->accept_delivery != null){ // Order is accepted by some other delivery guy
                    $transferredOrders->push($pd);
                }
            }
        }


        $response = [
            'new_orders' => $deliveryGuyNewOrders,
            'accepted_orders' => $alreadyAcceptedDeliveries,
            'pickedup_orders' => $pickedupOrders,
            'cancelled_orders' => $cancelledOrders,
            'transferred_orders' => $transferredOrders,
        ];

        return response()->json($response);
    }

    /**
     * @param Request $request
     */
    public function getSingleDeliveryOrder(Request $request)
    {
        //find the order
        $singleOrder = Order::where('unique_order_id', $request->unique_order_id)->first();

        //get order id and delivery boy id
        $singleOrderId = $singleOrder->id;
        $deliveryUser = Auth::user();

        $checkOrder = AcceptDelivery::where('order_id', $singleOrderId)
            ->where('user_id', $deliveryUser->id)
            ->first();

        $deliveryGuyCommissionRate = $deliveryUser->delivery_guy_detail->commission_rate;

        $commission = 0;
        if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
            $commission = $deliveryGuyCommissionRate / 100 * $singleOrder->total;
        }
        if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
            $commission = $deliveryGuyCommissionRate / 100 * $singleOrder->delivery_charge;
        }

        //check if the loggedin delivery boy has accepted the order
        if ($checkOrder) {
            //this order was already accepted by this delivery boy
            //so send the order to him
            $singleOrder = Order::where('unique_order_id', $request->unique_order_id)
                ->with('restaurant')
                ->with('orderitems.order_item_addons')
                ->with(array('user' => function ($query) {
                    $query->select('id', 'name', 'phone');
                }))
                ->first();

            $singleOrder->commission = number_format((float) $commission, 2, '.', '');

            // sleep(3);
            return response()->json($singleOrder);
        }

        //else other can view the order
        $singleOrder = Order::where('unique_order_id', $request->unique_order_id)
            ->where('orderstatus_id', 2)
            ->with('restaurant')
            ->with('orderitems.order_item_addons')
            ->with(array('user' => function ($query) {
                $query->select('id', 'name', 'phone');
            }))
            ->first();
        $singleOrder->commission = number_format((float) $commission, 2, '.', '');

        // sleep(3);
        return response()->json($singleOrder);
    }

    /**
     * @param Request $request
     */
    public function setDeliveryGuyGpsLocation(Request $request)
    {

        $deliveryUser = auth()->user();

        if ($deliveryUser->hasRole('Delivery Guy')) {

            //update the lat, lng and heading of delivery guy
            $deliveryUser->delivery_guy_detail->delivery_lat = $request->delivery_lat;
            $deliveryUser->delivery_guy_detail->delivery_long = $request->delivery_long;
            $deliveryUser->delivery_guy_detail->heading = $request->heading;
            $deliveryUser->delivery_guy_detail->save();

            $success = true;
            return response()->json($success);
        }

    }

    /**
     * @param Request $request
     */
    public function getDeliveryGuyGpsLocation(Request $request)
    {
        $order = Order::where('id', $request->order_id)->first();

        if ($order) {
            $deliveryUserId = $order->accept_delivery->user->id;
            $deliveryUser = User::where('id', $deliveryUserId)->first();
            $deliveryUserDetails = $deliveryUser->delivery_guy_detail;
        }

        if ($deliveryUserDetails) {
            return response()->json($deliveryUserDetails);
        }
    }

    /**
     * @param Request $request
     */
    public function acceptToDeliver(Request $request)
    {
        $deliveryUser = auth()->user();

        if ($deliveryUser && $deliveryUser->hasRole('Delivery Guy')) {

            $max_accept_delivery_limit = $deliveryUser->delivery_guy_detail->max_accept_delivery_limit;

            $order = Order::where('id', $request->order_id)->first();

            if ($order) {
                //if($order->orderstatus_id != '2' || $order->orderstatus_id != '7') throw new ValidationException(\ErrorCode::OPERATION_ALREADY_COMPLETED, "Order is already accepted");
                //if($order->accept_delivery != null && $order->accept_delivery->user_id == $deliveryUser->id) throw new ValidationException(\ErrorCode::OPERATION_ALREADY_COMPLETED, "Order is already accepted");

                $deliveryGuyCommissionRate = $deliveryUser->delivery_guy_detail->commission_rate;
                $commission = 0;
                if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->total;
                }
                if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->delivery_charge;
                }

                $checkOrder = AcceptDelivery::where('order_id', $order->id)->first();

                if (!$checkOrder) {
                    //check the max_accept_delivery_limit
                    $nonCompleteOrders = AcceptDelivery::where('user_id', $deliveryUser->id)->where('is_complete', 0)->with('order')->get();
                    // dd($nonCompleteOrders->count());

                    $countNonCompleteOrders = 0;
                    if ($nonCompleteOrders) {
                        foreach ($nonCompleteOrders as $nonCompleteOrder) {
                            if ($nonCompleteOrder->order && $nonCompleteOrder->order->orderstatus_id != 6) {
                                $countNonCompleteOrders++;
                            }
                        }
                    }

                    if ($countNonCompleteOrders < $max_accept_delivery_limit) {

                        try {
                            $order->orderstatus_id = '3'; //Accepted by delivery boy (Deliery Boy Assigned)
                            $order->rider_accept_at = Carbon::now()->toDateTimeString();
                            $order->save();

                            $acceptDelivery = new AcceptDelivery();
                            $acceptDelivery->order_id = $order->id;
                            $acceptDelivery->user_id = $deliveryUser->id;
                            $acceptDelivery->customer_id = $order->user->id;
                            $acceptDelivery->save();

                            $singleOrder = Order::where('id', $request->order_id)
                                ->with('restaurant')
                                ->with('orderitems.order_item_addons')
                                ->with(array('user' => function ($query) {
                                    $query->select('id', 'name', 'phone');
                                }))
                                ->first();
                            try{
                                // Send push notification to DeliveryGuy
                                $notify = new PushNotify();
                                $notify->sendPushNotificationToDeliveryGuy(NotificationType::DELIVERY_ASSIGNED, $order->orderstatus_id, $order->id, $order->unique_order_id, $order->restaurant_id);

                            }catch (\Throwable $e){
                                return redirect()->back()->with(array('message' => 'Something Went Wrong during notification send'));
                            }

                            // sleep(3);
                            if (config('settings.enablePushNotificationOrders') == 'true') {
                                $notify = new PushNotify();
                                $notify->sendPushNotification('3', $order->user_id, $order->unique_order_id);
                            }

                        } catch (Illuminate\Database\QueryException $e) {
                            $errorCode = $e->errorInfo[1];
                            if ($errorCode == 1062) {
                                $singleOrder->already_accepted = true;
                            }
                        }
                        $singleOrder->commission = number_format((float) $commission, 2, '.', '');
                        return response()->json($singleOrder);
                    } else {
                        $singleOrder = Order::where('id', $request->order_id)
                            ->with('restaurant')
                            ->with('orderitems.order_item_addons')
                            ->with(array('user' => function ($query) {
                                $query->select('id', 'name', 'phone');
                            }))
                            ->first();
                        $singleOrder->max_order = true;
                        $singleOrder->commission = number_format((float) $commission, 2, '.', '');
                        return response()->json($singleOrder);
                    }
                } else {
                    $order->rider_reassigned_at = Carbon::now()->toDateTimeString();
                    $order->save();

                    $singleOrder = Order::where('id', $request->order_id)
                        ->with('restaurant')
                        ->with('orderitems.order_item_addons')
                        ->with(array('user' => function ($query) {
                            $query->select('id', 'name', 'phone');
                        }))
                        ->first();
                    $singleOrder->already_accepted = true;
                    $singleOrder->commission = number_format((float) $commission, 2, '.', '');
                    return response()->json($singleOrder);
                }
            }
        }

    }


    /**
     * @param Request $request
     */
    public function reachedPickUpLocation(Request $request)
    {

        $deliveryUser = auth()->user();

        if ($deliveryUser && $deliveryUser->hasRole('Delivery Guy')) {

            $order = Order::where('id', $request->order_id)->first();

            if ($order) {
                if($order->orderstatus_id == '10' || $order->orderstatus_id == '710') throw new ValidationException(\ErrorCode::OPERATION_ALREADY_COMPLETED, "Already reached pickup location");

                $deliveryGuyCommissionRate = $deliveryUser->delivery_guy_detail->commission_rate;
                $commission = 0;
                if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->total;
                }
                if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->delivery_charge;
                }

                if($order->orderstatus_id == 3){
                    $order->orderstatus_id = '10';
                }else if($order->orderstatus_id == '73'){
                    $order->orderstatus_id = 710;  // As DeliveryGuy not accepted(status_id != 3) the order, but restaurant is marking the order as READY
                }else{
                    $order->orderstatus_id = 10;
                }
                $order->rider_reached_pickup_location_at = Carbon::now()->toDateTimeString();// Produces something like "2019-03-11 12:25:00"
                $order->save();


                // Notify to the restaurant that DeliveryGuy has reached to pickup the order
                if (config('settings.enablePushNotificationOrders') == 'true') {
                    $notify = new PushNotify();
                    //$notify->sendPushNotification('4', $order->user_id, $order->unique_order_id);
                }

                $singleOrder = Order::where('id', $request->order_id)
                    ->with('restaurant')
                    ->with('orderitems.order_item_addons')
                    ->with(array('user' => function ($query) {
                        $query->select('id', 'name', 'phone');
                    }))
                    ->first();



                $singleOrder->commission = number_format((float) $commission, 2, '.', '');

                return response()->json($singleOrder);
            }else{
                throw new ValidationException(\ErrorCode::BAD_REQUEST, "Invalid order_id");
            }
        }
    }


    /**
     * @param Request $request
     */
    public function pickedupOrder(Request $request)
    {

        $deliveryUser = auth()->user();

        if ($deliveryUser->hasRole('Delivery Guy')) {

            $order = Order::where('id', $request->order_id)->first();

            if ($order) {
                if($order->orderstatus_id == '4') throw new ValidationException(\ErProrCode::OPERATION_ALREADY_COMPLETED, "Already Pickedup");

                $deliveryGuyCommissionRate = $deliveryUser->delivery_guy_detail->commission_rate;
                $commission = 0;
                if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->total;
                }
                if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->delivery_charge;
                }

                $order->orderstatus_id = '4'; //Accepted by delivery boy (Deliery Boy Assigned)
                if($request->bill_photos != null) {
                    $files = collect();
                    foreach($request->bill_photos as $billPhoto){
                        $billPhotoBase64String = $billPhoto;

                        $imageData = base64_decode($billPhotoBase64String);
                        $source = imagecreatefromstring($imageData);
                        $rotate = imagerotate($source, 0, 0); // if want to rotate the image
                        $filename = $order->unique_order_id .'_'. time().'_'.  str_random(10). '.jpg';
                        $file = public_path('/images/bill/' . $filename);
                        $imageSave = imagejpeg($rotate, $file, 100);
                        imagedestroy($source);
                        $files->push($filename);
                    }
                    $order->bill_photos = json_encode($files);
                }
                $order->rider_picked_at = Carbon::now()->toDateTimeString();
                $order->save();

                $singleOrder = Order::where('id', $request->order_id)
                    ->with('restaurant')
                    ->with('orderitems.order_item_addons')
                    ->with(array('user' => function ($query) {
                        $query->select('id', 'name', 'phone');
                    }))
                    ->first();

                if (config('settings.enablePushNotificationOrders') == 'true') {
                    $notify = new PushNotify();
                    $notify->sendPushNotification('4', $order->user_id, $order->unique_order_id);
                }

                $singleOrder->commission = number_format((float) $commission, 2, '.', '');

                return response()->json($singleOrder);
            }
        }
    }


    /**
     * @param Request $request
     */
    public function reachedDropLocation(Request $request)
    {

        $deliveryUser = auth()->user();

        if ($deliveryUser && $deliveryUser->hasRole('Delivery Guy')) {

            $order = Order::where('id', $request->order_id)->first();

            if ($order) {
                if($order->orderstatus_id != '4') throw new ValidationException(\ErrorCode::OPERATION_ALREADY_COMPLETED, "Already reached drop location");

                $deliveryGuyCommissionRate = $deliveryUser->delivery_guy_detail->commission_rate;
                $commission = 0;
                if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->total;
                }
                if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->delivery_charge;
                }

                $order->orderstatus_id = 11;
                $order->rider_reached_drop_location_at = Carbon::now()->toDateTimeString();// Produces something like "2019-03-11 12:25:00"
                $order->save();

                $singleOrder = Order::where('id', $request->order_id)
                    ->with('restaurant')
                    ->with('orderitems.order_item_addons')
                    ->with(array('user' => function ($query) {
                        $query->select('id', 'name', 'phone');
                    }))
                    ->first();



                $singleOrder->commission = number_format((float) $commission, 2, '.', '');

                return response()->json($singleOrder);
            }else{
                throw new ValidationException(\ErrorCode::BAD_REQUEST, "Invalid order_id");
            }
        }
    }

    /**
     * @param Request $request
     */
    public function sendMessageToCustomer(Request $request){
        $deliveryUser = auth()->user();
        if ($deliveryUser && $deliveryUser->hasRole('Delivery Guy')){
            $order = Order::where('id', $request->order_id)->first();

            if ($order && $order->orderstatus_id == 11) {
                if($order->is_order_reached_message_send == 0){
                    // send the order reached message to the customer
                    // This is not a mandatory message, if deliveryGuy seems that customer is unreachable, then he can send message
                    $sms = new Sms();
                    Log::info('Sending Order reached to drop location SMS ');
                    // TRIGGER_THE _SMS..........
                    $order->is_order_reached_message_send = '1';
                    $order->save();

                    $response = ['success' => true, "message" => "SMS send successfully"];
                    return response()->json($response);
                }else{
                    throw new ValidationException(\ErrorCode::BAD_REQUEST, "SMS already send, you can send only single SMS per order");
                }
            }else{
                throw new ValidationException(\ErrorCode::BAD_REQUEST, "Invalid request, order not reached the drop location");
            }
        }else{
            throw new AuthenticationException(\ErrorCode::BAD_REQUEST, "Invalid Delivery User");
        }
    }




    /**
     * @param Request $request
     */
    public function deliverOrder(Request $request, TranslationHelper $translationHelper)
    {
        $keys = ['deliveryCommissionMessage', 'deliveryTipTransactionMessage'];

        $translationData = $translationHelper->getDefaultLanguageValuesForKeys($keys);

        $deliveryUser = auth()->user();

        if ($deliveryUser->hasRole('Delivery Guy')) {

            $order = Order::where('id', $request->order_id)->first();
            $user = $order->user;

            if ($order) {
                if($order->orderstatus_id != '11') throw new ValidationException(\ErrorCode::OPERATION_ALREADY_COMPLETED, "Already Delivered");

                $deliveryGuyCommissionRate = $deliveryUser->delivery_guy_detail->commission_rate;
                $commission = 0;
                if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->total;
                }
                if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                    $commission = $deliveryGuyCommissionRate / 100 * $order->delivery_charge;
                }

                if (config('settings.enableDeliveryPin') == 'true') {
                    if ($order->delivery_pin == strtoupper($request->delivery_pin)) {
                        $order->orderstatus_id = '5'; //Accepted by delivery boy (Deliery Boy Assigned)
                        $order->rider_deliver_at = Carbon::now()->toDateTimeString();// Produces something like "2019-03-11 12:25:00"
                        $order->save();

                        $completeDelivery = AcceptDelivery::where('order_id', $order->id)->first();
                        $completeDelivery->is_complete = true;
                        $completeDelivery->save();

                        $singleOrder = Order::where('id', $request->order_id)
                            ->with('restaurant')
                            ->with('orderitems.order_item_addons')
                            ->with(array('user' => function ($query) {
                                $query->select('id', 'name', 'phone');
                            }))
                            ->first();

                        if (config('settings.enablePushNotificationOrders') == 'true') {
                            $notify = new PushNotify();
                            $notify->sendPushNotification('5', $order->user_id, $order->unique_order_id);
                        }

                        //Update restautant earnings...
                        $restaurant_earning = RestaurantEarning::where('restaurant_id', $order->restaurant->id)
                            ->where('is_requested', 0)
                            ->first();
                        if ($restaurant_earning) {
                            // $restaurant_earning->amount += $order->total - $order->delivery_charge;
                            $restaurant_earning->amount += $order->total - ($order->delivery_charge + $order->tip_amount);
                            $restaurant_earning->save();
                        } else {
                            $restaurant_earning = new RestaurantEarning();
                            $restaurant_earning->restaurant_id = $order->restaurant->id;
                            // $restaurant_earning->amount = $order->total - $order->delivery_charge;
                            $restaurant_earning->amount = $order->total - ($order->delivery_charge + $order->tip_amount);
                            $restaurant_earning->save();
                        }

                        //Update delivery guy collection
                        if ($order->payment_mode == 'COD') {
                            $delivery_collection = DeliveryCollection::where('user_id', $completeDelivery->user_id)->first();
                            if ($delivery_collection) {
                                $delivery_collection->amount += $order->payable;
                                $delivery_collection->save();
                            } else {
                                $delivery_collection = new DeliveryCollection();
                                $delivery_collection->user_id = $completeDelivery->user_id;
                                $delivery_collection->amount = $order->payable;
                                $delivery_collection->save();
                            }
                        }

                        //Update delivery guy's earnings...
                        if (config('settings.enableDeliveryGuyEarning') == 'true') {
                            //if enabled, then check based on which value the commision will be calculated
                            $deliveryUser = AcceptDelivery::where('order_id', $order->id)->first();
                            if ($deliveryUser->user) {
                                if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                                    //get order total and delivery guy's commission rate and transfer to wallet
                                    // $commission = $deliveryUser->user->delivery_guy_detail->commission_rate / 100 * $order->total;
                                    $commission = $deliveryUser->user->delivery_guy_detail->commission_rate / 100 * ($order->total - $order->tip_amount);
                                    $deliveryUser->user->deposit($commission * 100, ['description' => $translationData->deliveryCommissionMessage . $order->unique_order_id]);
                                }
                                if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                                    //get order delivery charge and delivery guy's commission rate and transfer to wallet
                                    $commission = $deliveryUser->user->delivery_guy_detail->commission_rate / 100 * $order->delivery_charge;
                                    $deliveryUser->user->deposit($commission * 100, ['description' => $translationData->deliveryCommissionMessage . $order->unique_order_id]);
                                }
                            }
                        }
                        $singleOrder->commission = number_format((float) $commission, 2, '.', '');
                        return response()->json($singleOrder);
                    } else {
                        $singleOrder = Order::where('id', $request->order_id)
                            ->whereIn('orderstatus_id', ['2', '3', '4'])
                            ->with('restaurant')
                            ->with('orderitems.order_item_addons')
                            ->with(array('user' => function ($query) {
                                $query->select('id', 'name', 'phone');
                            }))
                            ->first();

                        $singleOrder->delivery_pin_error = true;
                        $singleOrder->commission = number_format((float) $commission, 2, '.', '');
                        // sleep(3);
                        return response()->json($singleOrder);
                    }
                } else {
                    $order->orderstatus_id = '5'; //Accepted by delivery boy (Deliery Boy Assigned)
                    $order->save();

                    $completeDelivery = AcceptDelivery::where('order_id', $order->id)->first();
                    $completeDelivery->is_complete = true;
                    $completeDelivery->save();

                    $singleOrder = Order::where('id', $request->order_id)
                        ->with('restaurant')
                        ->with('orderitems.order_item_addons')
                        ->with(array('user' => function ($query) {
                            $query->select('id', 'name', 'phone');
                        }))
                        ->first();

                    if (config('settings.enablePushNotificationOrders') == 'true') {
                        $notify = new PushNotify();
                        $notify->sendPushNotification('5', $order->user_id, $order->unique_order_id);
                    }

                    $restaurant_earning = RestaurantEarning::where('restaurant_id', $order->restaurant->id)
                        ->where('is_requested', 0)
                        ->first();
                    if ($restaurant_earning) {
                        // $restaurant_earning->amount += $order->total - $order->delivery_charge;
                        $restaurant_earning->amount += $order->total - ($order->delivery_charge + $order->tip_amount);
                        $restaurant_earning->save();
                    } else {
                        $restaurant_earning = new RestaurantEarning();
                        $restaurant_earning->restaurant_id = $order->restaurant->id;
                        // $restaurant_earning->amount = $order->total - $order->delivery_charge;
                        $restaurant_earning->amount = $order->total - ($order->delivery_charge + $order->tip_amount);
                        $restaurant_earning->save();
                    }

                    //Update delivery guy collection
                    if ($order->payment_mode == 'COD') {
                        $delivery_collection = DeliveryCollection::where('user_id', $completeDelivery->user_id)->first();
                        if ($delivery_collection) {
                            $delivery_collection->amount += $order->payable;
                            $delivery_collection->save();
                        } else {
                            $delivery_collection = new DeliveryCollection();
                            $delivery_collection->user_id = $completeDelivery->user_id;
                            $delivery_collection->amount = $order->payable;
                            $delivery_collection->save();
                        }
                    }

                    //Update delivery guy's earnings...
                    if (config('settings.enableDeliveryGuyEarning') == 'true') {
                        //if enabled, then check based on which value the commision will be calculated
                        $deliveryUser = AcceptDelivery::where('order_id', $order->id)->first();
                        if ($deliveryUser->user) {
                            if (config('settings.deliveryGuyCommissionFrom') == 'FULLORDER') {
                                //get order total and delivery guy's commission rate and transfer to wallet
                                // $commission = $deliveryUser->user->delivery_guy_detail->commission_rate / 100 * $order->total;
                                $commission = $deliveryUser->user->delivery_guy_detail->commission_rate / 100 * ($order->total - $order->tip_amount);
                                $deliveryUser->user->deposit($commission * 100, ['description' => $translationData->deliveryCommissionMessage . $order->unique_order_id]);
                            }
                            if (config('settings.deliveryGuyCommissionFrom') == 'DELIVERYCHARGE') {
                                //get order delivery charge and delivery guy's commission rate and transfer to wallet
                                $commission = $deliveryUser->user->delivery_guy_detail->commission_rate / 100 * $order->delivery_charge;
                                $deliveryUser->user->deposit($commission * 100, ['description' => $translationData->deliveryCommissionMessage . $order->unique_order_id]);
                            }
                        }
                    }
                    // update tip amount charges
                    if ($deliveryUser->user) {
                        if ($deliveryUser->user->delivery_guy_detail->tip_commission_rate && !is_null($deliveryUser->user->delivery_guy_detail->tip_commission_rate)) {
                            $commission = $deliveryUser->user->delivery_guy_detail->tip_commission_rate / 100 * $order->tip_amount;
                            $deliveryUser->user->deposit($commission * 100, ['description' => $translationData->deliveryTipTransactionMessage . ' : ' . $order->unique_order_id]);
                        }
                    }
                    return response()->json($singleOrder);
                }
            }
        }
    }






    /*############################# 02-May-2021[START] #####################################*/

    public function dashboard(Request $request){
        $user = User::where('id', $request->user_id)->first();
        if ($user) {
            //$earnings = $user->transactions()->orderBy('id', 'DESC')->get();

            $todaysEarning = $user->transactions()->whereDate('created_at', Carbon::today()->toDateString())->orderBy('id', 'DESC')->get();
            $yesterdaysEarning = $user->transactions()->whereDate('created_at', Carbon::yesterday()->toDateString())->orderBy('id', 'DESC')->get();
            $thisWeekEarning = $user->transactions()->whereBetween('created_at', [Carbon::now()->startOfWeek()->toDateString(), Carbon::now()->endOfWeek()->toDateString()])->orderBy('id', 'DESC')->get();
            $thisMonthEarning = $user->transactions()->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month)->orderBy('id', 'DESC')->get();


            $todaysCompletedDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                $query->whereIn('orderstatus_id', ['5'])->whereDate('created_at', Carbon::today()->toDateString());
            })->where('user_id', $user->id)->where('is_complete', 1)->count();


            $yesterdaysCompletedDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                $query->whereIn('orderstatus_id', ['5'])->whereDate('created_at', Carbon::yesterday()->toDateString());
            })->where('user_id', $user->id)->where('is_complete', 1)->count();

            $thisWeekCompletedDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                //$query->whereIn('orderstatus_id', ['5'])->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                $query->whereIn('orderstatus_id', ['5'])
                    ->where('created_at', '>', Carbon::now()->startOfWeek())
                    ->where('created_at', '<', Carbon::now()->endOfWeek());

                $query->whereIn('orderstatus_id', ['5'])
                    ->whereBetween('created_at', [
                        Carbon::parse('last monday')->startOfDay(),
                        Carbon::parse('next friday')->endOfDay(),
                    ]);
            })->where('user_id', $user->id)->where('is_complete', 1)->count();


            $thisMonthCompletedDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                $query->whereIn('orderstatus_id', ['5'])->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now());
            })->where('user_id', $user->id)->where('is_complete', 1)->count();


            $cashOnHold = 0;
            $lastPayment = 0;
            $deliveryCollection = DeliveryCollection::where('user_id', $user->id)->first();
            if($deliveryCollection) {
                $cashOnHold = $deliveryCollection->amount;

                $lastLog = DeliveryCollectionLog::where('delivery_collection_id', $deliveryCollection->id)->orderBy('id', 'desc')->first();
                //return response()->json($lastLog, 201);
                if($lastLog) $lastPayment = $lastLog->amount;
            }


            $dateRange = Carbon::today()->subDays(7);
            $earningData = DB::table('transactions')
                ->where('payable_id', $user->id)
                ->where('created_at', '>=', $dateRange)
                ->where('type', 'deposit')
                ->select(DB::raw('sum(amount) as total'), DB::raw('date(created_at) as dates'))
                ->groupBy('dates')
                ->orderBy('dates', 'desc')
                ->get();

            for ($i = 0; $i <= 6; $i++) {
                if (!isset($earningData[$i])) {
                    $amount[] = 0;
                } else {
                    $amount[] = $earningData[$i]->total / 100;
                }
            }

            for ($i = 0; $i <= 6; $i++) {
                $days[] = Carbon::now()->subDays($i)->format('D');
            }

            foreach ($amount as $amt) {
                $amtArr[] = [
                    'y' => $amt,
                ];
            }
            $amtArr = array_reverse($amtArr);
            foreach ($days as $key => $day) {
                $dayArr[] = [
                    'x' => $day,
                ];
            }
            $dayArr = array_reverse($dayArr);
            $chartData = [];
            for ($i = 0; $i <= 6; $i++) {
                array_push($chartData, ($amtArr[$i] + $dayArr[$i]));
            }

            $response = [
                'success' => true,
                'data' => [
                    //Todays
                    //'todays_earnings' => $todaysEarning,
                    'todays_order_count' => $todaysCompletedDeliveriesCount,
                    'todays_earning_amount' => $this->getTotalDepositAmount($todaysEarning),
                    //Yesterdays
                    //'yesterday_earnings' => $yesterdaysEarning,
                    'yesterday_order_count' => $yesterdaysCompletedDeliveriesCount,
                    'yesterday_earning_amount' => $this->getTotalDepositAmount($yesterdaysEarning),
                    //Week
                    //'this_week_earnings' => $yesterdaysEarning,
                    'this_week_order_count' => $thisWeekCompletedDeliveriesCount,
                    'this_week_earning_amount' => $this->getTotalDepositAmount($thisWeekEarning),
                    //Week
                    //'this_month_earnings' => $thisWeekEarning,
                    'this_month_order_count' => $thisMonthCompletedDeliveriesCount,
                    'this_month_earning_amount' => $this->getTotalDepositAmount($thisMonthEarning),
                    //'cash_in_hold' => ($delideliveryCollection != null) ? $deliveryCollection->amount : 0,
                    'cash_in_hold' => 0,
                    'last_payment' => $lastPayment,
                    'chartData' => $chartData,


                ],
            ];
            return response()->json($response, 201);

        }


        $response = ['success' => false, 'data' => 'Record doesnt exists'];
    }

    private function getTotalDepositAmount($transactions){
        $totalEarnings = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->type === 'deposit') {
                $totalEarnings += $transaction->amount / 100;
            }
        }
        return $totalEarnings;
    }
    private function getTotalWithdrawAmount($transactions){
        $totalEarnings = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->type === 'withdraw') {
                $totalEarnings += $transaction->amount / 100;
            }
        }
        return $totalEarnings;
    }


    /**
     * @param $user
     * @param $lat
     * @param $lng
     */
    public function updateHeartBeat($user, $lat, $lng, $count, $meta = null){
        $location = array('lat' => $lat,'lng' => $lng, 'bearing' => '');
        $current_date_time = Carbon::now()->toDateTimeString();// Produces something like "2019-03-11 12:25:00"
        $current_timestamp = Carbon::now()->timestamp; // Produces something like 1552296328

        $THRESHOLD_TIME = 2;
        if (config('settings.logoutThresholdTime') != null) {
            $THRESHOLD_TIME = config('settings.logoutThresholdTime');
        }

        $loginSession = null;
        $existingLoginSession = LoginSession::where('user_id', $user->id)
            //->whereNull('last_checkout_at')
            ->orderBy('id', 'desc')->first();


        if($existingLoginSession){
            // Check if difference between last_seen time and current time
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $current_date_time);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $existingLoginSession->last_checkout_at);
            //$diffInMinutes = abs($from - $to) / 60; //Output: 20
            $diffInMinutes = round(abs(strtotime($from) - strtotime($to)) /60, 2); //Output: 20
            //return response()->json(["diff_in_minutes"=>$diffInMinutes]);


            if($diffInMinutes < $THRESHOLD_TIME){
                $loginSession = $existingLoginSession;
            }else{
                $loginSession = new LoginSession();
                $loginSession->user_id = $user->id;
            }
        }else{// First time
            $loginSession = new LoginSession();
            $loginSession->user_id = $user->id;
        }


        //$loginSession->login_at = $current_date_time;
        $loginSession->last_checkout_at = $current_date_time;
        //$loginSession->location = json_encode($location);// it save as string
        $loginSession->location = $location;//this save the actual json data
        $loginSession->count = $count;
        $loginSession->save();

        $deliveryUser = $user;
        if ($deliveryUser->hasRole('Delivery Guy')) {
            //update the lat, lng and heading of delivery guy
            $deliveryUser->delivery_guy_detail->delivery_lat = $lat;
            $deliveryUser->delivery_guy_detail->delivery_long = $lng;
            $deliveryUser->delivery_guy_detail->heading = $meta;
            $deliveryUser->delivery_guy_detail->save();
        }


    }

    /**
     * @param Request $request
     */
    public function scheduleHeartBeat(Request $request)
    {
        $deliveryUser = auth()->user();
        //$deliveryUser = auth()->user();

        if ($deliveryUser && $deliveryUser->hasRole('Delivery Guy')) {//$request->lat && $request->lng
            $this->updateHeartBeat($deliveryUser, $request->lat, $request->lng, $request->count , null);
            //return $this->getDeliveryOrders($request);

            $onGoingDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                $query->whereIn('orderstatus_id', ['3', '4', '73', '10', '710', '11']);
            })->where('user_id', $deliveryUser->id)->where('is_complete', 0)->count();

            $completedDeliveriesCount = AcceptDelivery::whereHas('order', function ($query) {
                $query->whereIn('orderstatus_id', ['5']);
            })->where('user_id', $deliveryUser->id)->where('is_complete', 1)->count();


            // for 5th heartbeat check if there exist any new order
            // we are checking it with a fix number, to reduce the database hit
            $deliveryGuyNewOrders = collect();
            if($request->count == '5'){
                $orders = Order::where('orderstatus_id', '2')
                    ->where('delivery_type', '1')
                    ->with('restaurant')
                    ->orderBy('id', 'DESC')
                    ->get();
                $userRestaurants = $deliveryUser->restaurants;

                foreach ($orders as $order) {
                    foreach ($userRestaurants as $ur) {
                        //checking if delivery guy is assigned to that restaurant
                        if ($order->restaurant->id == $ur->id) {
                            $deliveryGuyNewOrders->push($order);
                        }
                    }
                }
            }

            $response = [
                'success' => true,
                'new_orders' => $deliveryGuyNewOrders,
                'accepted_orders' => [],
                'pickedup_orders' => [],
                'cancelled_orders' => [],
                'transferred_orders' => [],
                'on_going_deliveries_count' => $onGoingDeliveriesCount,
                'completed_deliveries_count' => $completedDeliveriesCount,
            ];
            return response()->json($response);

        }else{
            if(!$user)throw new AuthenticationException(ErrorCode::PHONE_NOT_EXIST, "Customer not found for " .$request->phone);
            if(!$request->lat)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "lat should not be null");
            if(!$request->lng)throw new ValidationException(ErrorCode::INVALID_REQUEST_BODY, "lng should not be null");
            throw new AuthenticationException(ErrorCode::BAD_RESPONSE, "Invalid request body");
        }
    }


    /**
     * @param $user_id
     */
    public function getLoginHistory(Request $request)
    {
        $user = auth()->user();
        if(!$user)throw new AuthenticationException(ErrorCode::PHONE_NOT_EXIST, "Customer not found for " .$request->phone);

        $sessions = LoginSession::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->get();

        $THRESHOLD_TIME = 2;
        if (config('settings.logoutThresholdTime') != null) {
            $THRESHOLD_TIME = config('settings.logoutThresholdTime');
        }


        $loginSessions = collect();
        if($sessions != null){
            foreach ($sessions as $session) {
                $from = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now()->toDateTimeString());
                $to = Carbon::createFromFormat('Y-m-d H:i:s', $session->last_checkout_at);
                $diffInMinutes = round(abs(strtotime($from) - strtotime($to)) /60, 2); //Output: 20

                $sessionObj = array(
                    'login_at' => date("d-M-y h:i a", strtotime($session->created_at)),
                    'logout_at' => date("d-M-y h:i a", strtotime($session->updated_at)),
                    'last_seen' => date("d-M-y h:i a", strtotime($session->last_checkout_at)),
                    'is_online' => false,
                );
                if($diffInMinutes < $THRESHOLD_TIME){
                    $sessionObj['is_online'] = true;
                }else{
                    $sessionObj['is_online'] = false;
                }
                $loginSessions->push($sessionObj);
            }
        }
        return response()->json($loginSessions);
    }

    /*############################# 02-May-2021[END] #####################################*/


}
