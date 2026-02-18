<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class ReceiveBook extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'm_terima_buku';

    protected $fillable = [
        'nota_kirim_cab',
        'receive_code',
        'branch_code',
        'retur_date',
        'send_date',
        'info',
        'branch_sender'
    ];
}
