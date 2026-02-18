<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpBranch extends Model
{
    protected $connection = 'mysql';

    protected $table = 'sp_branches';

    protected $guarded = [];
    
    protected $fillable = [
        'branch_code',
        'book_code',
        'ex_sp',
        'ex_ftr',
        'ex_ret',
        'ex_rec_pst',
        'ex_rec_gdg',
        'ex_stock',
        'trans_date',
        'active_data',
    ];
    
    protected $casts = [
        'active_data' => 'string',
        'trans_date' => 'date',
    ];
}
