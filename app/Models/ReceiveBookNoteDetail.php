<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiveBookNoteDetail extends Model
{
    use HasFactory;

    protected $table = 'd_terima_buku';

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

    public function product()
    {
        return $this->belongsTo(Product::class, 'book_code', 'book_code');
    }
}
