<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'm_delivery';

    protected $fillable = ['delivery_code', 'delivery_date', 'branch_sender', 'branch_code', 'company', 'plat_no', 'info'];
}
