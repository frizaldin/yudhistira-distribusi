<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EraseItemDetail extends Model
{
    use HasFactory;

    protected $table = 'd_hapus_barang';

    protected $fillable = [
        'erase_code',
        'book_code',
        'book_price',
        'branch_code',
        'exemplar',
        'koli',
        'total_exemplar',
        'volume',
    ];

    public $timestamps = false;
}
