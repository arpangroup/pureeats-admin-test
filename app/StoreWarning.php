<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StoreWarning extends Model
{

    /**
     * @var array
     */
    protected $casts = ['is_read' => 'integer'];
}
