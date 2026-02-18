<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'books';

    protected $fillable = [
        'book_code',
        'book_title',
        'pages',
        'paper_size',
        'paper_code',
        'c_color_code',
        'sale_price',
        'writer',
        'book_tipe',
        'isbn',
        'mulok',
        'aktif',
        'jenjang',
        'category',
        'category_manual',
        'serial',
    ];

    public $timestamps = false;
}
