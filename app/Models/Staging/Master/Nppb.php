<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class Nppb extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'm_nppb';

    protected $fillable = [
        'nppb',
        'branch_code',
        'branch_sender',
        'trans_date',
        'period_code',
        'up_percent',
        'info'
    ];
}
