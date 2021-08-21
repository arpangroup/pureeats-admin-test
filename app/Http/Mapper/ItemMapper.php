<?php
namespace App\Http\Mapper;

use App\Http\Dto\ItemCategoryInfo;
use App\Http\Dto\Product;

class ItemMapper{

    public static function mapItems($allItems){
        $recommendedProducts = array();
        $itemGroups = [];

        foreach ($allItems as $item){
            if($item->is_recommended == 1){
                $productDto = Product::getProduct($item);
                array_push($recommendedProducts, $productDto);
            }else{
                $itemGroups[$item['category_name']][] = $item;
            }
        }

        $recommendedItems = array(self::createRecommendedCategory($recommendedProducts));
        $nonRecommendedItems = [];

        foreach ($itemGroups as $categoryName => $items) {
            $nonRecommendedCategory = self::createNonRecommendedCategory($categoryName, $items);
            array_push($nonRecommendedItems, $nonRecommendedCategory);
        }


        $merged = array_merge($recommendedItems, $nonRecommendedItems);
        return  (array)$merged;
    }

    private static function createNonRecommendedCategory($categoryName, array $rawItems){
        $category = new ItemCategoryInfo();
        $category->name = strtolower((string) $categoryName);
        $category->id = (int)$rawItems[0]->item_category_id;
        $category->localized_name = '';
        $category->desc = '';
        $category->localized_desc = '';
        $category->products = array();

        foreach ($rawItems as $item){
            $productDto = Product::getProduct($item);
            array_push($category->products, $productDto);
        }

        return $category;
    }

    private static function createRecommendedCategory(array $recommendedProducts): ItemCategoryInfo
    {
        $category = new ItemCategoryInfo();
        $category->name = strtolower('recommended');
        $category->id = 0;
        $category->localized_name = '';
        $category->desc = '';
        $category->localized_desc = '';
        $category->products = $recommendedProducts;
        return $category;
    }

}

?>