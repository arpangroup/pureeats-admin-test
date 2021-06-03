<?php

namespace App;

use App\Alert;
use App\Orderstatus;
use App\PushToken;
use App\Translation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Ixudra\Curl\Facades\Curl;
use NotificationType;

class PushNotify
{
    /**
     * @param $orderstatus_id
     * @param $user_id
     */
    public function sendPushNotification($orderstatus_id, $user_id, $unique_order_id = null)
    {

        //check if admin has set a default translation?
        $translation = Translation::where('is_default', 1)->first();
        if ($translation) {
            //if yes, then take the default translation and use instread of translations from config
            $translation = json_decode($translation->data);

            $runningOrderPreparingTitle = $translation->runningOrderPreparingTitle;
            $runningOrderPreparingSub = $translation->runningOrderPreparingSub;
            $runningOrderDeliveryAssignedTitle = $translation->runningOrderDeliveryAssignedTitle;
            $runningOrderDeliveryAssignedSub = $translation->runningOrderDeliveryAssignedSub;
            $runningOrderOnwayTitle = $translation->runningOrderOnwayTitle;
            $runningOrderOnwaySub = $translation->runningOrderOnwaySub;
            $runningOrderDelivered = !empty($translation->runningOrderDelivered) ? $translation->runningOrderDelivered : config('settings.runningOrderDelivered');
            $runningOrderDeliveredSub = !empty($translation->runningOrderDeliveredSub) ? $translation->runningOrderDelivered : config('settings.runningOrderDeliveredSub');
            $runningOrderCanceledTitle = $translation->runningOrderCanceledTitle;
            $runningOrderCanceledSub = $translation->runningOrderCanceledSub;
            $runningOrderReadyForPickup = $translation->runningOrderReadyForPickup;
            $runningOrderReadyForPickupSub = $translation->runningOrderReadyForPickupSub;
            $deliveryGuyNewOrderNotificationMsg = $translation->deliveryGuyNewOrderNotificationMsg;
            $deliveryGuyNewOrderNotificationMsgSub = $translation->deliveryGuyNewOrderNotificationMsgSub;

        } else {
            //else use from config
            $runningOrderPreparingTitle = config('settings.runningOrderPreparingTitle');
            $runningOrderPreparingSub = config('settings.runningOrderPreparingSub');
            $runningOrderDeliveryAssignedTitle = config('settings.runningOrderDeliveryAssignedTitle');
            $runningOrderDeliveryAssignedSub = config('settings.runningOrderDeliveryAssignedSub');
            $runningOrderOnwayTitle = config('settings.runningOrderOnwayTitle');
            $runningOrderOnwaySub = config('settings.runningOrderOnwaySub');
            $runningOrderDelivered = config('settings.runningOrderDelivered');
            $runningOrderDeliveredSub = config('settings.runningOrderDeliveredSub');
            $runningOrderCanceledTitle = config('settings.runningOrderCanceledTitle');
            $runningOrderCanceledSub = config('settings.runningOrderCanceledSub');
            $runningOrderReadyForPickup = config('settings.runningOrderReadyForPickup');
            $runningOrderReadyForPickupSub = config('settings.runningOrderReadyForPickupSub');
            $deliveryGuyNewOrderNotificationMsg = config('settings.deliveryGuyNewOrderNotificationMsg');
            $deliveryGuyNewOrderNotificationMsgSub = config('settings.deliveryGuyNewOrderNotificationMsgSub');
        }

        if ($orderstatus_id == '2') {
            $msgTitle = $runningOrderPreparingTitle;
            $msgMessage = $runningOrderPreparingSub;
            $click_action = config('settings.storeUrl') . '/running-order/' . $unique_order_id;
        }
        if ($orderstatus_id == '3') {
            $msgTitle = $runningOrderDeliveryAssignedTitle;
            $msgMessage = $runningOrderDeliveryAssignedSub;
            $click_action = config('settings.storeUrl') . '/running-order/' . $unique_order_id;
        }
        if ($orderstatus_id == '4') {
            $msgTitle = $runningOrderOnwayTitle;
            $msgMessage = $runningOrderOnwaySub;
            $click_action = config('settings.storeUrl') . '/running-order/' . $unique_order_id;
        }
        if ($orderstatus_id == '5') {
            $msgTitle = $runningOrderDelivered;
            $msgMessage = $runningOrderDeliveredSub;
            $click_action = config('settings.storeUrl') . '/my-orders/';
        }
        if ($orderstatus_id == '6') {
            $msgTitle = $runningOrderCanceledTitle;
            $msgMessage = $runningOrderCanceledSub;
            $click_action = config('settings.storeUrl') . '/my-orders/';
        }
        if ($orderstatus_id == '7') {
            $msgTitle = $runningOrderReadyForPickup;
            $msgMessage = $runningOrderReadyForPickupSub;
            $click_action = config('settings.storeUrl') . '/running-order/' . $unique_order_id;
        }
        if ($orderstatus_id == 'TO_RESTAURANT') {
            //$msgTitle = $restaurantNewOrderNotificationMsg;
            //$msgMessage = $restaurantNewOrderNotificationMsgSub;
            //$click_action = config('settings.storeUrl') . '/public/restaurant-owner/dashboard';
        }
        if ($orderstatus_id == 'TO_DELIVERY') {
            $msgTitle = $deliveryGuyNewOrderNotificationMsg;
            $msgMessage = $deliveryGuyNewOrderNotificationMsgSub;
            $click_action = config('settings.storeUrl') . '/delivery/orders/' . $unique_order_id;
        }
        if ($orderstatus_id == 'TRANSFERRED_ORDER') {
            $msgTitle = $deliveryGuyNewOrderNotificationMsg;
            $msgMessage = $deliveryGuyNewOrderNotificationMsgSub;
            $click_action = config('settings.storeUrl') . '/delivery/orders/' . $unique_order_id;
        }
        $msg = array(
            'title' => $msgTitle,
            'message' => $msgMessage,
            'badge' => '/assets/img/favicons/favicon-96x96.png',
            'icon' => '/assets/img/favicons/favicon-512x512.png',
            'click_action' => $click_action,
            'unique_order_id' => $unique_order_id,
            'order_status_id' =>$orderstatus_id,
        );

        $alert = new Alert();
        $alert->data = json_encode($msg);
        $alert->user_id = $user_id;
        $alert->is_read = 0;
        $alert->save();

        $this->sendNotification($user_id, $msg);
    }

    public function sendNotification($user_id, $msg){
        $secretKey = 'key=' . config('settings.firebaseSecret');
        $token = PushToken::where('user_id', $user_id)->first();
        //Log::debug("SECRET_KEY: " .$secretKey);

        if ($token) {
            $fullData = array(
                'to' => $token->token,
                'data' => $msg,
            );

            $response = Curl::to('https://fcm.googleapis.com/fcm/send')
                ->withHeader('Content-Type: application/json')
                ->withHeader("Authorization: $secretKey")
                ->withData(json_encode($fullData))
                ->post();

            Log::debug("RESPONSE: " .$response);
            Log::debug('#################Notification Send Success for userId '.$user_id);
        }else{
            Log::error('#################token not available for userId: ' .$user_id);
        }

    }




    /**
     * @param $user_id
     * @param $amount
     * @param $message
     * @param $type
     */
    public function sendWalletAlert($user_id, $amount, $message, $type)
    {

        $amountWithCurrency = config('settings.currencySymbolAlign') == 'left' ? config('settings.currencyFormat') . $amount : $amount . config('settings.currencyFormat');

        $msg = array(
            'title' => config('settings.walletName'),
            'message' => $amountWithCurrency . ' ' . $message,
            'is_wallet_alert' => true,
            'transaction_type' => $type,
        );

        $alert = new Alert();
        $alert->data = json_encode($msg);
        $alert->user_id = $user_id;
        $alert->is_read = 0;
        $alert->save();

    }


    /**
     * @param $orderId
     * Send the Order arrived notification to all the delivery guy, when sore accepted the order
     */
    public function sendPushNotificationToDeliveryGuy($notificationType, $orderstatus_id, $order_id, $unique_order_id, $restaurant_id, $deliveryguy_id = null){
        Log::debug('Inside sendPushNotificationToDeliveryGuy........');
        Log::debug('NOTIFICATION_TYPE: ' .$notificationType . ' :: ORDER_STATUS: ' .$orderstatus_id . ' :: ORDER_ID: ' .$order_id .' :: UNIQUE_ORDER_ID: ' .$unique_order_id . ' :: RESTAURANT_ID: ' .$restaurant_id . ' :: DELIVERY_GUY_ID: '.$deliveryguy_id);
        $msg = array(
            'title' => '',
            'message' =>'',
            'order_id' => $order_id,
            'unique_order_id' => $unique_order_id,
            'order_status_id' =>$orderstatus_id,
            'notification_type' => $notificationType,
            'user_id' => -1,
            'channel' => 'WEB',
        );

        if($notificationType){
            switch ($notificationType) {
                case NotificationType::ORDER_ARRIVED: // Trigger when Store accepted the order
                    Log::debug('TYPE: ORDER_ARRIVED' );
                    $msg['title'] = 'New Order Arrived';
                    $msg['message'] = 'Please accept the order ' . $unique_order_id;

                    // notify to all deliveryGuy attached with the store
                    $restaurant = Restaurant::where('id', $restaurant_id)->first();
                    Log::debug('RESTAURANT: ' .json_encode($restaurant));
                    if ($restaurant == null) return;
                    $pivotUsers = $restaurant->users()->wherePivot('restaurant_id', $restaurant_id)->get();
                    Log::debug('PIVOT_USERS: ' .json_encode($pivotUsers));
                    foreach ($pivotUsers as $pU) {
                        if ($pU->hasRole('Delivery Guy')) {//send Notification to Delivery Guy
                            $msg['user_id'] = $pU->id;
                            Log::debug('SEND_NOTIFICATION_TO: ' .$pU->id);
                            $this->sendNotification($pU->id, $msg);
                        }
                    }
                    break;
                case NotificationType::DELIVERY_ASSIGNED:// Trigger when DeliveryGuy Accept the order
                    Log::debug('TYPE: DELIVERY_ASSIGNED' );
                    // As a particular deliveryGuy accept the order,
                    // so, all other deliveryGuy will notify that some one else is assigned for the order
                    $msg['title'] = 'Delivery Assigned';
                    $msg['message'] = 'Delivery assigned for the order ' .$unique_order_id;

                    $restaurant = Restaurant::where('id', $restaurant_id)->first();
                    Log::debug('RESTAURANT: ' .json_encode($restaurant));
                    if ($restaurant == null) return;
                    $pivotUsers = $restaurant->users()->wherePivot('restaurant_id', $restaurant_id)->get();
                    foreach ($pivotUsers as $pU) {
                        if ($pU->hasRole('Delivery Guy') && $deliveryguy_id != $pU->id) {//send Notification to other Delivery Guy
                            $msg['user_id'] = $pU->id;
                            Log::debug('Sending notification to '.$pU->id );
                            $this->sendNotification($pU->id, $msg);
                        }
                    }
                    break;
                case NotificationType::DELIVERY_RE_ASSIGNED: // Triggered when a new DeliveryGuy is reassigned
                    Log::debug('TYPE: DELIVERY_RE_ASSIGNED' );
                    $msg['title'] = 'Delivery ReAssigned';
                    $msg['message'] = 'Delivery re-assigned for the order ' .$unique_order_id;

                    $acceptDelivery = AcceptDelivery::where('order_id', $order_id)->first();
                    if($acceptDelivery == null) return;
                    $msg['user_id'] = $acceptDelivery->user_id;
                    Log::debug('Sending notification to '.$acceptDelivery->user_id );
                    $this->sendNotification($acceptDelivery->user_id, $msg);//only to reAssigned DeliveryGuy

                    $alert = new Alert();
                    $alert->data = json_encode($msg);
                    $alert->user_id = $acceptDelivery->user_id;
                    $alert->is_read = 0;
                    $alert->save();
                    break;
                case NotificationType::ORDER_CANCELLED:
                    Log::debug('TYPE: ORDER_CANCELLED' );
                    $msg['title'] =  'Order Cancelled';
                    $msg['message'] = $unique_order_id .' Cancelled by the user';

                    $acceptDelivery = AcceptDelivery::where('order_id', $order_id)->first();
                    Log::debug('ACCEPT_DELIVERY: ' .json_encode($acceptDelivery));
                    if($acceptDelivery == null){
                        Log::debug('$acceptDelivery is null' );
                        // there might be chance that the order is cancelled before delivery assigned
                        // in this case we need to send the cancel notification to all the deliveryGuy attached with the store
                        $restaurant = Restaurant::where('id', $restaurant_id)->first();
                        if ($restaurant == null) return;
                        $pivotUsers = $restaurant->users()->wherePivot('restaurant_id', $restaurant_id)->get();
                        foreach ($pivotUsers as $pU) {
                            if ($pU->hasRole('Delivery Guy')) {//send Notification to Delivery Guy
                                $msg['user_id'] = $pU->id;
                                Log::debug('Sending notification to '.$pU->id );
                                $this->sendNotification($pU->id, $msg);

                                $alert = new Alert();
                                $alert->data = json_encode($msg);
                                $alert->user_id = $acceptDelivery->user_id;
                                $alert->is_read = 0;
                                $alert->save();
                            }
                        }
                    }else{
                        Log::debug('$acceptDelivery is not null' );
                        $msg['user_id'] = $acceptDelivery->user_id;
                        Log::debug('Sending notification to '.$acceptDelivery->user_id );
                        $this->sendNotification($acceptDelivery->user_id, $msg);

                        $alert = new Alert();
                        $alert->data = json_encode($msg);
                        $alert->user_id = $acceptDelivery->user_id;
                        $alert->is_read = 0;
                        $alert->save();
                    }
                    break;
                case NotificationType::ORDER_TRANSFERRED:
                    Log::debug('TYPE: ORDER_TRANSFERRED' );
                    $msg['title'] =  'Order Transferred';
                    $msg['message'] = $unique_order_id .' Transferred to other Delivery Guy';
                    if($deliveryguy_id == null) return;

                    $msg['user_id'] = $deliveryguy_id;
                    Log::debug('Sending notification to '.$deliveryguy_id );
                    $this->sendNotification($deliveryguy_id, $msg);

                    $alert = new Alert();
                    $alert->data = json_encode($msg);
                    $alert->user_id =$deliveryguy_id;
                    $alert->is_read = 0;
                    $alert->save();
                    break;
            }

        }
    }

}
