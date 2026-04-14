<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketAlternative extends Model
{
     protected $fillable = ['value', 'value_classification', 'api_timestamp'];
}
