<?php

namespace Modules\RatingSystemPro\Entities;

use App\Order;
use App\Restaurant;
use App\User;
use Illuminate\Database\Eloquent\Model;

class RatingDeliveryGuy extends Model
{
    protected $fillable = [
        'delivery_guy_id',
        'order_id',
        'user_id',
        'rating',
        'comment',
        'tags'
    ];

    /**
     * @return mixed
     */
    public function delivery_guy()
    {
        return $this->hasOne(User::class, 'id', 'delivery_guy_id');
    }

    /**
     * @return mixed
     */
    public function restaurant()
    {
        return $this->hasOne(Restaurant::class, 'id', 'restaurant_id');
    }

    /**
     * @return mixed
     */
    public function order()
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
