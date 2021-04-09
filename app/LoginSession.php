<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LoginSession extends Model{
    protected $casts = [
        "location" => "array",
   ];
    
}
