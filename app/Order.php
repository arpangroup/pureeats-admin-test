<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
        'orderstatus_id' => 'integer',
        'restaurant_charge' => 'float',
        'total' => 'float',
        'payable' => 'float',
        'wallet_amount' => 'float',
        'tip_amount' => 'float',
        'tax_amount' => 'float',
        'coupon_amount' => 'float',
        'sub_total' => 'float',
    ];

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /**
     * @return mixed
     */
    public function orderstatus()
    {
        return $this->belongsTo('App\Orderstatus');
    }

    /**
     * @return mixed
     */
    public function restaurant()
    {
        return $this->belongsTo('App\Restaurant');
    }

    /**
     * @return mixed
     */
    public function orderitems()
    {
        return $this->hasMany('App\Orderitem');
    }

    /**
     * @return mixed
     */
    public function gpstable()
    {
        return $this->hasOne('App\GpsTable');
    }

    /**
     * @return mixed
     */
    public function accept_delivery()
    {
        return $this->hasOne('App\AcceptDelivery');
        }
                
        /*addisRatingCode*/
        protected $appends = ['isRating', 'isRatingDeliveryGuy'];
    
        public function getIsRatingAttribute()
        {
            if(!auth()->check()) {
                return false;
            }
    
            return !empty(\Modules\RatingSystemPro\Entities\RatingStore::where('order_id', $this->id)->where('restaurant_id', $this->restaurant_id)->where('user_id', auth()->user()->id)->first());
        }
    
        public function getIsRatingDeliveryGuyAttribute()
        {
            if(!auth()->check()) {
                return false;
            }
    
            if(isset($this->accept_delivery->user_id)) {
                return !empty(\Modules\RatingSystemPro\Entities\RatingDeliveryGuy::where('order_id', $this->id)->where('delivery_guy_id', $this->accept_delivery->user_id)->where('user_id', auth()->user()->id)->first());
            }
    }

}
