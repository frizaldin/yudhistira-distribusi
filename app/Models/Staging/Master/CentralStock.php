<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class CentralStock extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'r_stock_pusat';

    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['branch_code', 'book_code', 'exemplar'];
}
