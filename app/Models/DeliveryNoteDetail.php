<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryNoteDetail extends Model
{
    use HasFactory;

    protected $table = 'delivery_note_details';

    protected $fillable = [
        'nota_kirim_cab',
        'book_code',
        'book_price',
        'koli',
        'exemplar',
        'total_exemplar',
        'volume',
        'branch_sender',
    ];

    protected $casts = [
        'koli' => 'decimal:0',
        'exemplar' => 'decimal:0',
        'total_exemplar' => 'decimal:0',
        'volume' => 'decimal:0',
    ];

    public $timestamps = false;
}
