<?php

namespace Modules\DeliveryAreaPro;

use App\Restaurant;
use Cache;

class DeliveryArea
{

    /**
     * @param $userLat
     * @param $userLng
     * @param $delivery_areas
     * @return boolean
     */
    public function checkArea($userLat, $userLng, $delivery_areas)
    {
        $geofence = new \Location\Polygon();

        foreach ($delivery_areas as $area) {
            $geoJson = json_decode($area->geojson);
            foreach ($geoJson->features as $feature) {
                foreach ($feature->geometry->coordinates[0] as $key => $value) {
                    $geofence->addPoint(new \Location\Coordinate($value[1], $value[0]));
                }
            }
        }

        $outsidePoint = new \Location\Coordinate($userLat, $userLng);

        $check = $geofence->contains($outsidePoint);

        return $check;
    }
}
