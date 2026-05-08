<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoveWarehouseDetail extends Model
{
    use HasFactory;

    protected $table = 'd_pindah_gudang';

    protected $fillable = [
        'move_code',
        'branch_code',
        'book_code',
        'exemplar',
        'koli',
        'total_exemplar',
        'volume',
    ];

    public $timestamps = false;
}
