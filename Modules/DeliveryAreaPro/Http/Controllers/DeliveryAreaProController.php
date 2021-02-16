<?php

namespace Modules\DeliveryAreaPro\Http\Controllers;

use App\Restaurant;
use App\Setting;
use Artisan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\DeliveryAreaPro\Entities\DeliveryArea;

class DeliveryAreaProController extends Controller
{
    public function settings()
    {
        $areas = DeliveryArea::paginate(30);
        $stores = Restaurant::all();
        return view('deliveryareapro::areas', array(
            'areas' => $areas,
            'stores' => $stores,
        ));
    }

    /**
     * @param Request $request
     */
    public function saveArea(Request $request)
    {
        $data = json_decode($request->geojson);
        if (empty($data->features)) {
            return redirect()->back()->with(['message' => 'You need to draw atleast one area on the map.']);
        }

        $area = new DeliveryArea();
        $area->name = $request->name;
        $area->description = $request->description;
        $area->geojson = $request->geojson;
        $area->save();

        return redirect()->back()->with(['success' => 'New Area Added']);
    }

    /**
     * @param $id
     */
    public function editArea($id)
    {
        $area = DeliveryArea::where('id', $id)->firstOrFail();

        return view('deliveryareapro::editArea', array(
            'area' => $area,
        ));
    }

    /**
     * @param Request $request
     */
    public function updateArea(Request $request)
    {
        $data = json_decode($request->geojson);
        if (empty($data->features)) {
            return redirect()->back()->with(['message' => 'You need to draw atleast one area on the map.']);
        }

        $area = DeliveryArea::where('id', $request->area_id)->firstOrFail();

        $area->name = $request->name;
        $area->description = $request->description;
        $area->geojson = $request->geojson;
        $area->save();

        return redirect()->back()->with(['success' => 'Area Updated']);
    }

    /**
     * @param $id of store
     * get single store and attach to multiple areas
     */
    public function assignAreasToStore($id)
    {
        $restaurant = Restaurant::where('id', $id)->firstOrFail();

        $areas = DeliveryArea::all();

        $restaurantAreaIds = $restaurant->delivery_areas->pluck('id')->toArray();
        return view('deliveryareapro::assignAreasToStore', array(
            'restaurant' => $restaurant,
            'areas' => $areas,
            'restaurantAreaIds' => $restaurantAreaIds,
        ));
    }

    /**
     * @param $id of area
     * get single area and attach to multiple stores
     */
    public function assignStoresToArea($id)
    {
        $area = DeliveryArea::where('id', $id)->firstOrFail();

        $restaurants = Restaurant::all();

        $restaurantAreaIds = $area->restaurants->pluck('id')->toArray();
        // dd($restaurants[0]->name);
        return view('deliveryareapro::assignStoresToArea', array(
            'restaurants' => $restaurants,
            'area' => $area,
            'restaurantAreaIds' => $restaurantAreaIds,
        ));
    }

    /**
     * @param Request $request
     */
    public function updateStoreArea(Request $request)
    {
        if ($request->has('stores_to_area')) {
            $area = DeliveryArea::where('id', $request->id)->firstOrFail();
            $area->restaurants()->sync($request->delivery_areas);
        } else {
            $restaurant = Restaurant::where('id', $request->id)->firstOrFail();
            $restaurant->delivery_areas()->sync($request->delivery_areas);
        }

        return redirect()->back()->with(['success' => 'Store Areas Updated']);
    }

    /**
     * @param Request $request
     */
    public function saveSettings(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting != null) {
                $setting->value = $value;
                $setting->save();
            }
        }
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        return redirect()->back()->with(['success' => 'Settings Updated']);
    }
}
