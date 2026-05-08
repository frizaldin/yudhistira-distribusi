<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPromoDetail extends Model
{
    use HasFactory;

    protected $table = 'd_kirim_promosi';

    protected $fillable = [
        'nota_kirim_promo',
        'book_code',
        'book_price',
        'branch_sender',
        'exemplar',
        'koli',
        'total_exemplar',
        'volume',
    ];

    public $timestamps = false;
}
