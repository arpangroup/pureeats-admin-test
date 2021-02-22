<?php

namespace Modules\RatingSystemPro\Http\Controllers;

use App\AcceptDelivery;
use App\Restaurant;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\RatingSystemPro\Entities\RatingDeliveryGuy;
use Modules\RatingSystemPro\Entities\RatingStore;
use Modules\RatingSystemPro\Providers\SettingServiceProvider;

class RatingSystemProController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('ratingsystempro::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function settings()
    {
        return view('ratingsystempro::index', [
            'version' => SettingServiceProvider::$version
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $messages = [

        ];

        $rules = [
            'restaurant_id' => 'required',
            'order_id' => 'required',
            'message' => 'required|max:100',
            'rating' => 'required',
        ];

        $validator = \Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'error'=>$validator->errors()->all()], 400);
        }

        switch ($request->type) {
            case 'store':
                $prepareData = [
                    'restaurant_id' => $request->restaurant_id,
                    'order_id'      => $request->order_id,
                    'user_id'       => auth()->user()->id,
                    'rating'        => $request->rating,
                    'comment'       => $request->message,
                ];

                if(RatingStore::create($prepareData)) {
                    return response()->json(['status' => true, 'message'=> 'Your review has been successfully published'], 200);
                }
                break;
            case 'delivery_guy':
                $accept_delivery = AcceptDelivery::where('order_id', $request->order_id)->first();

                if(!empty($accept_delivery)) {
                    $prepareData = [
                        'delivery_guy_id'   => $accept_delivery->user_id,
                        'order_id'          => $request->order_id,
                        'user_id'           => auth()->user()->id,
                        'rating'            => $request->rating,
                        'comment'           => $request->message,
                    ];

                    if(RatingDeliveryGuy::create($prepareData)) {
                        return response()->json(['status' => true, 'message'=> 'Your review has been successfully published'], 200);
                    }
                }
                break;
        }


    }

    /**
     * Show the specified resource.
     * @param Request $request
     * @return Response
     */
    public function reports(Request $request)
    {
        $ratingStores           = [];
        $ratingDataStores       = [];
        $ratingDeliverys        = [];
        $ratingDataDeliverys    = [];
        $restaurants            = Restaurant::where('is_active', 1)->get();
        $delivery_guys          = User::role('Delivery Guy')->where('is_active', 1)->get();

        if(isset($request->restaurant_id) && !empty($request->restaurant_id)) {
            $ratingStores = RatingStore::where('restaurant_id', $request->restaurant_id)->get();
            $ratingDataStores = RatingStore::with(['restaurant', 'order', 'user'])->where('restaurant_id', $request->restaurant_id)->paginate(10);
        }

        if(isset($request->delivery_guy_id) && !empty($request->delivery_guy_id)) {
            $ratingDeliverys = RatingDeliveryGuy::where('delivery_guy_id', $request->delivery_guy_id)->get();
            $ratingDataDeliverys = RatingDeliveryGuy::with(['delivery_guy', 'restaurant', 'order', 'user'])->where('delivery_guy_id', $request->delivery_guy_id)->paginate(10);
        }

        return view('ratingsystempro::reports', compact(['restaurants', 'delivery_guys', 'ratingStores', 'ratingDeliverys', 'ratingDataDeliverys', 'ratingDataStores',  'ratingDataStores']));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        return view('ratingsystempro::edit');
    }

    /**
     * @param Request $request
     * @return string
     */
    public function deliveryguy(Request $request)
    {
        return RatingDeliveryGuy::where('delivery_guy_id', auth()->user()->id)->average('rating');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function list(Request $request, $id)
    {
        return RatingStore::with(['restaurant', 'user', 'order'])->where('restaurant_id', $id)->get();
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
