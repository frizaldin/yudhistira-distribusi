<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class SpBranch extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'r_sp_faktur_stok';

    protected $fillable = [
        'branch_code',
        'book_code',
        'ex_sp',
        'ex_ftr',
        'ex_ret',
        'ex_rec_pst',
        'ex_rec_gdg',
        'ex_stock',
        'trans_date'
    ];
}
