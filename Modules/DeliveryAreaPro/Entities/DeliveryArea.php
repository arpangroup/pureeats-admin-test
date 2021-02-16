<?php

namespace Modules\DeliveryAreaPro\Entities;

use Illuminate\Database\Eloquent\Model;

class DeliveryArea extends Model
{
    /**
     * @var array
     */
    protected $fillable = [];

    /**
     * @return mixed
     */
    public function restaurants()
    {
        return $this->belongsToMany(\App\Restaurant::class);
    }
}
