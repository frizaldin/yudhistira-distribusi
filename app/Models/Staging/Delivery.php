<?php

namespace App\Models\Staging;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'd_delivery';
}
