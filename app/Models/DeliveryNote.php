<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryNote extends Model
{
    use HasFactory;

    protected $table = 'delivery_notes';

    protected $fillable = [
        'nota_kirim_cab',
        'branch_code',
        'branch_sender',
        'send_date',
        'info',
        'nppb',
        'sj',
    ];

    protected $casts = [
        'send_date' => 'date',
    ];
    public $timestamps = false;
}
