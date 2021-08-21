<?php
namespace App\Http\Repository;

use App\Http\Mapper\ItemMapper;
use App\Http\Mapper\RestaurantMapper;
use App\Item;
use App\Restaurant;
use Illuminate\Support\Facades\DB;

class RestaurantRepository{

    public static function getAllStores1(){
        /*
        $restaurants = Restaurant::where('is_active', '1')
            ->with(['coupons' => function($query){
                //$query->where('is_exclusive', 1);
                $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                //$query->take(1);
                //$query->get();
            }])
            ->with('items')
            //->ordered()
            ->orderBy('is_accepted', 'DESC')
            ->get();
        */
        $restaurants = Restaurant::where('is_active', '1')
            ->with(['coupons' => function($query){
                //$query->where('is_exclusive', 1);
                $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                //$query->take(1);
                //$query->get();
            }])
            //->with('items')
            ->with(['items' => function($query){
                //$query->where('name', 1);
                //$query->select('name', 'price')->get();
                //$query->get();
                $query->join('item_categories', function ($join) {
                    $join->on('items.item_category_id', '=', 'item_categories.id');
                    $join->where('is_enabled', '1');
                })
//                    ->with('addon_categories')
//                    ->with(array('addon_categories.addons' => function ($query) {
//                        $query->where('is_active', 1);
//                    }))
                    ->get(array('items.*', 'item_categories.name as category_name'));
            }])
            ->orderBy('is_accepted', 'DESC')
            ->get();

        /*
        $items = Item::where('restaurant_id', $restaurant->id)
            ->join('item_categories', function ($join) {
                $join->on('items.item_category_id', '=', 'item_categories.id');
                $join->where('is_enabled', '1');
            })
            ->with('addon_categories')
            ->with(array('addon_categories.addons' => function ($query) {
                $query->where('is_active', 1);
            }))
            ->get(array('items.*', 'item_categories.name as category_name'));
        */



        $storeResult =[];
        foreach ($restaurants as $restaurant){
            $store = RestaurantMapper::mapTo($restaurant);
            array_push($storeResult, $store);
        }
        return $storeResult;
    }

    public static function getAllStores(){
        $restaurants = Restaurant::where('is_active', '1')
            ->with(['coupons' => function($query){
                //$query->where('is_exclusive', 1);
                $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                //$query->take(1);
                //$query->get();
            }])
            ->with('menuList')
            ->orderBy('is_accepted', 'DESC')
            ->get();

        $storeResult =[];



        //group the items based on category
        /*foreach ($restaurants as $restaurant) {
            $items = $restaurant->itemsAll;
            $items = json_decode($items, true);
            $itemGroups = [];
            foreach ($items as $item) {
                $itemGroups[$item['category_name']][] = $item;
            }
            $restaurant['items'] = $itemGroups;
            $store = RestaurantMapper::mapTo($restaurant);
            array_push($storeResult, $store);
        }*/


        //$restaurants = json_decode(json_encode($restaurants), true);
        //return ($storeResult->items;
        //return $storeResult[0]['items'];
        //return $restaurants[0];
        return ItemMapper::mapItems($restaurants[0]['menuList']);
    }




    public static function getAllStoresByActive($active = null){
        $active = $active == '1' ? 1 : 0;

        $activeRestaurants = Restaurant::where('is_active', $active)
            //->where('is_active', 1)
            //->whereIn('delivery_type', [1, 2, 3])
            ->with(['coupons' => function($query){
                //$query->where('is_exclusive', 1);
                $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                //$query->take(1);
                //$query->get();
            }])
            ->ordered()
            ->get();
        return $activeRestaurants;
    }

    public static function getStoreById($id){
        $restaurant = Restaurant::where('id', $id)
            ->with(['coupons' => function($query){
                $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                //$query->take(1);
                //$query->get();
            }])
            ->first();
        return $restaurant;
    }
    public static function getStoreBySlug($slug){
        $restaurant = Restaurant::where('slug', $slug)
            ->with(['coupons' => function($query){
                $query->select('name', 'code', 'description', 'min_subtotal', 'is_exclusive')->get();
                //$query->take(1);
                //$query->get();
            }])
            ->first();
        return $restaurant;
    }


}

?>