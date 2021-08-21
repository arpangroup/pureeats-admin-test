<?php

namespace App\Http\Controllers;

use App\AcceptDelivery;
use App\Order;
use App\Restaurant;
use App\Setting;
use App\User;
use DB;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Http\Request;
use Modules\RatingSystemPro\Entities\RatingDeliveryGuy;
use Modules\RatingSystemPro\Entities\RatingStore;

class RatingController extends Controller
{

    /**
     * @param Request $request
     */
    public function getRatableOrder(Request $request)
    {
        //check if order exists
        $order = Order::where('id', $request->order_id)->with('restaurant', 'orderitems')->first();

        if ($order) {
            //check if order belongs to the auth user
            if ($order->user->id == $request->user_id) {
                //check if order already rated,
                $rating = DB::table('ratings')->where('order_id', $order->id)->get();

                if ($rating->isEmpty()) {
                    //empty rating, that means not rated earlier
                    $response = [
                        'success' => true,
                        'order' => $order,
                    ];
                    return response()->json($response);
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Already rated',
                    ];
                    return response()->json($response);
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Order doesnt belongs to user',
                ];
                return response()->json($response);
            }
        }

        $response = [
            'success' => false,
            'message' => 'No order found',
        ];
        return response()->json($response);
    }

    /**
     * @param Request $request
     */
    public function saveNewRating(Request $request)
    {
        return "hello";
        //return response()->json( ['success' => "hello world",]);
        $user = User::where('id', $request->user_id)->first();


        if ($user) {
            //find the restaurant
            $order = Order::where('id', $request->order_id)->first();
            if ($order) {

                //rating the restaurant
                $restaurant = Restaurant::where('id', $order->restaurant_id)->first();
                //$rating = new \willvincent\Rateable\Rating;
                $rating = new \Modules\RatingSystemPro\Entities\RatingStore;
                $rating->restaurant_id =$restaurant->id;
                $rating->order_id = $order->id;
                $rating->user_id = $user->id;
                $rating->rating = $request->restaurant_rating;
                $rating->tags = $request->restaurant_tags;
                $rating->comment = $request->restaurant_comment;
                $restaurant->ratings()->save($rating);



                //rating the delivery guy
                $deliveryGuy = AcceptDelivery::where('order_id', $order->id)->first();
                $deliveryGuy = User::where('id', $deliveryGuy->user_id)->first();
                //$rating = new \willvincent\Rateable\Rating;
                $rating = new \Modules\RatingSystemPro\Entities\RatingDeliveryGuy;
                $rating->delivery_guy_id = $deliveryGuy->id;
                $rating->order_id = $order->id;
                $rating->user_id = $user->id;
                $rating->rating = $request->delivery_rating;
                $rating->tags = $request->delivery_tags;
                $rating->comment = $request->delivery_comment;
                $deliveryGuy->ratings()->save($rating);

                $response = [
                    'success' => true,
                ];
                return response()->json($response);
            } else {
                $response = [
                    'success' => false,
                    'message' => 'No order found',
                ];
                return response()->json($response);
            }
        }
        $response = [
            'success' => false,
            'message' => 'No user found',
        ];
        return response()->json($response);
    }

    public function settings()
    {
        return view('admin.modules.ratingmodule.settings');
    }

    /**
     * @param Request $request
     * @param Factory $cache
     */
    public function updateSettings(Request $request, Factory $cache)
    {
        $allSettings = $request->except(['rarModEnHomeBanner', 'rarModShowBannerRestaurantName']);

        foreach ($allSettings as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting != null) {
                $setting->value = $value;
                $setting->save();
            }
        }

        $setting = Setting::where('key', 'rarModEnHomeBanner')->first();
        if ($request->rarModEnHomeBanner == 'true') {
            $setting->value = 'true';
            $setting->save();
        } else {
            $setting->value = 'false';
            $setting->save();
        }
        $setting = Setting::where('key', 'rarModShowBannerRestaurantName')->first();
        if ($request->rarModShowBannerRestaurantName == 'true') {
            $setting->value = 'true';
            $setting->save();
        } else {
            $setting->value = 'false';
            $setting->save();
        }

        $cache->forget('settings');
        return redirect()->back()->with(['success' => 'Settings Updated']);
    }

    /**
     * @param Request $request
     */
    public function singleRatableOrder(Request $request)
    {
        $user = auth()->user();

        if ($user) {
            if ($request->order_id){
                $userOrder = Order::where('user_id', $user->id)->where('orderstatus_id', 5)
                    ->where('id', $request->order_id)
                    ->with('orderitems', 'restaurant')
                    //->get()
                    ->first();
            }elseif ($request->unique_order_id){
                $userOrder = Order::where('user_id', $user->id)->where('orderstatus_id', 5)
                    ->where('unique_order_id', $request->unique_order_id)
                    ->with('orderitems', 'restaurant')
                    //->get()
                    ->first();
            }else{
                //get latest order
                $userOrder = Order::where('user_id', $user->id)->where('orderstatus_id', 5)
                    ->with('restaurant')
                    ->with('orderitems')
                    ->get()
                    ->last();
            }


            //check if any order exists
            if ($userOrder) {

                //check if order is already rated or not
                $rating = DB::table('ratings')->where('order_id', $userOrder->id)->get();
                if ($rating->isEmpty()) {

                    // Get Delivery Details:
                    $delivery_guy = AcceptDelivery::where('order_id', $userOrder->id)->first();
                    if ($delivery_guy) {
                        $delivery_user = User::where('id', $delivery_guy->user_id)->first();
                        $delivery_details = $delivery_user->delivery_guy_detail;
                        if (!empty($delivery_details)) {
                            $delivery_details = $delivery_details->toArray();
                            $delivery_details['phone'] = $delivery_user->phone;
                        }
                    }
                    $userOrder['delivery_details'] = $delivery_details;




                    $response = [
                        'ratable' => true,
                        'order' => $userOrder->only(['id', 'unique_order_id', 'orderstatus_id', 'address', 'restaurant', 'delivery_details', 'orderitems']),
                    ];
                    return response()->json($response);
                }else{
                    $response = ['ratable' => false, 'message' => "This order is already rated" ];
                    return response()->json($response);
                }

            }else{
                $response = ['ratable' => false, 'message' => 'Order is not delivered yet' ];
                return response()->json($response);
            }
        }
    }

    public function ratings()
    {
        $ratings = DB::table('ratings')
            ->orderBy('order_id')
            ->paginate(20);

        // dd($ratings);

        return view('admin.modules.ratingmodule.ratings', array(
            'ratings' => $ratings,
        ));
    }

    /**
     * @param Request $request
     */
    public function editRating($id)
    {
        $ratings = DB::table('ratings')
            ->where('order_id', $id)
            ->get();

        if (count($ratings) > 0) {
            foreach ($ratings as $key => $rating) {
                if ($key == 0) {
                    $restaurantRating = $rating->rating;
                    $comment = $rating->comment;
                }
                if ($key == 1) {
                    $deliveryRating = $rating->rating;
                }
            }

            return view('admin.modules.ratingmodule.editRating', array(
                'order_id' => $id,
                'restaurantRating' => $restaurantRating,
                'deliveryRating' => $deliveryRating,
                'comment' => $comment,
            ));
        } else {
            return redirect()->route('admin.ratings');
        }
    }

    /**
     * @param Request $request
     */
    public function updateRating(Request $request)
    {
        // dd($request->all());

        $ratings = DB::table('ratings')
            ->where('order_id', $request->id)
            ->get();

        if (count($ratings) > 0) {
            foreach ($ratings as $key => $rating) {
                if ($key == 0) {
                    DB::table('ratings')
                        ->where('id', $rating->id)
                        ->update(['rating' => $request->restaurantRating, 'comment' => $request->comment]);

                }
                if ($key == 1) {
                    DB::table('ratings')
                        ->where('id', $rating->id)
                        ->update(['rating' => $request->deliveryRating, 'comment' => $request->comment]);
                }
            }

            return redirect()->back()->with(['success' => 'Rating Updated']);
        } else {
            return redirect()->route('admin.ratings');
        }
    }

    /**
     * @param $restaurantId
     */
    public function getRestaurantRatings($restaurantId){
        $ratings = RatingStore::where("restaurant_id", $restaurantId)
                ->with('user:id,name,avatar', 'restaurant:id,slug,name,address,image')
                ->get();
        return response()->json($ratings);
    }


    /**
     * @param $driverId
     */
    public function getDriverRatings($driverId){
        $ratings = RatingDeliveryGuy::where("delivery_guy_id", $driverId)
            ->with('user:id,name,avatar', 'restaurant:id,slug,name,address,image')
            ->get();
        return response()->json($ratings);
    }
}
