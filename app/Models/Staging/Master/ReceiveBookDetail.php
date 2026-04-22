<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class ReceiveBookDetail extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'd_terima_buku';

    protected $fillable = [
        'nota_kirim_cab',
        'book_code',
        'book_price',
        'koli',
        'exemplar',
        'total_exemplar',
        'volume',
        'branch_sender',
    ];
}
