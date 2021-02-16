<?php

namespace App\Console\Commands;


use App\Order;
use App\User;
use App\Restaurant;
use Carbon\Carbon;
use Illuminate\Console\Command;

use App\Helpers\TranslationHelper;
use App\PushNotify;

class AautoCancelOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:AautoCancelOrder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This will auto cancel the order in a certain interval';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo "#################Starting AutoOrderCancel ######################## \n";
        //$orders = Order::all();        
        // Return order which created before 10 minutes
        $orders = Order::where('orderstatus_id', '=', 1)->where('created_at', '<', Carbon::now()->subMinutes(10))->get();
        echo('COUNT_ORDERS: ' .$orders->count() ) ;
        $keys = ['orderRefundWalletComment', 'orderPartialRefundWalletComment'];
        
        foreach ($orders as $order) {
            echo "\nhhhhhh";
            echo "\n#### ORDER_ID: " .$order->id;
            $user = User::where('id', $order->user_id)->first();
            

            //if payment method is not COD, and order status is 1 (Order placed) then refund to wallet
            $refund = false;
            

            //if COD, then check if wallet is present
            if ($order->payment_mode == 'COD') {
                if ($order->wallet_amount != null) {
                    //refund wallet amount
                    $user->deposit($order->wallet_amount * 100, ['description' => $translationData->orderPartialRefundWalletComment . $order->unique_order_id]);
                    $refund = true;
                }
            } else {
                //if online payment, refund the total to wallet
                $user->deposit(($order->total) * 100, ['description' => $translationData->orderRefundWalletComment . $order->unique_order_id]);
                $refund = true;
            }

            //cancel order
            $order->orderstatus_id = 6; //6 means canceled..
            $order->save();
            echo "\n#### ORDER_STatus Changed: " .$order->id;


            //throw notification to user
            if (config('settings.enablePushNotificationOrders') == 'true') {
                $notify = new PushNotify();
                $notify->sendPushNotification('6', $order->user_id);
            }


        }
        echo "\n#################Finishing AutoOrderCancel ######################## \n";
    }
}
