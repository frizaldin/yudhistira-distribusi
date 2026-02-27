<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NkbItem extends Model
{
    protected $table = 'nkb_items';

    public $timestamps = true;

    protected $fillable = [
        'nkb_code',
        'book_code',
        'book_name',
        'koli',
        'pls',
        'exp',
        'volume',
    ];

    protected $casts = [
        'koli' => 'decimal:0',
        'pls' => 'decimal:0',
        'exp' => 'decimal:0',
        'volume' => 'decimal:0',
    ];

    public function nkb()
    {
        return $this->belongsTo(Nkb::class, 'nkb_code', 'number');
    }
}
