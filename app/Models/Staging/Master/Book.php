<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'm_book';
    
    public $incrementing = false;
    public $timestamps = false;
    
    protected $primaryKey = 'book_code';

    protected $fillable = ['book_code', 'book_title', 'pages', 'paper_size', 'paper_code', 'c_color_code', 'sale_price', 'writer', 'book_tipe', 'isbn', 'mulok', 'aktif', 'jenjang', 'category'];
}
