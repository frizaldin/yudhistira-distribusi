<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiveBookNote extends Model
{
    use HasFactory;

    protected $table = 'm_terima_buku';

    protected $fillable = [
        'nota_kirim_cab',
        'receive_code',
        'branch_code',
        'retur_date',
        'send_date',
        'info',
        'branch_sender',
        'receive_type',
    ];

    protected $casts = [
        'retur_date' => 'date',
        'send_date' => 'date',
    ];

    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(ReceiveBookNoteDetail::class, 'nota_kirim_cab', 'nota_kirim_cab');
    }
}
