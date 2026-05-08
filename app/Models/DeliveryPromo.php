<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPromo extends Model
{
    use HasFactory;

    protected $table = 'm_kirim_promosi';

    protected $fillable = [
        'nota_kirim_promo',
        'approve_by',
        'branch_sender',
        'deliver_by',
        'info',
        'printed',
        'sales_code',
        'send_date',
        'status',
        'user_id',
        'whouse_head',
    ];

    protected $casts = [
        'send_date' => 'date',
    ];

    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(DeliveryPromoDetail::class, 'nota_kirim_promo', 'nota_kirim_promo');
    }
}
