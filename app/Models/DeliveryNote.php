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

    public function details()
    {
        return $this->hasMany(DeliveryNoteDetail::class, 'nota_kirim_cab', 'nota_kirim_cab');
    }

    public function receiveNote()
    {
        return $this->hasOne(ReceiveBookNote::class, 'nota_kirim_cab', 'nota_kirim_cab');
    }
}
