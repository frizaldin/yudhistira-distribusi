<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrderItem extends Model
{
    protected $table = 'delivery_order_items';

    protected $fillable = [
        'delivery_order_id',
        'nkb_id',
        'koli',
        'ex',
        'total_ex',
    ];

    protected $casts = [
        'koli' => 'decimal:0',
        'ex' => 'decimal:0',
        'total_ex' => 'decimal:0',
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function nkb()
    {
        return $this->belongsTo(Nkb::class, 'nkb_id', 'id');
    }
}
