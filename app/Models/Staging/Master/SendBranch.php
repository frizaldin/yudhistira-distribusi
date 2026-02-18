<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class SendBranch extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'm_kirim_cabang';

    protected $fillable = [
        'nota_kirim_cab',
        'branch_code',
        'branch_sender',
        'send_date',
        'info',
        'nppb',
        'sj'
    ];
}
