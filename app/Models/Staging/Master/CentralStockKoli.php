<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class CentralStockKoli extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'r_stock_pusat_koli';

    protected $fillable = ['branch_code', 'book_code', 'volume', 'koli'];
}
