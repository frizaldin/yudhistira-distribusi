<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EraseItem extends Model
{
    use HasFactory;

    protected $table = 'm_hapus_barang';

    protected $fillable = [
        'erase_code',
        'branch_code',
        'edit_date',
        'empl_code',
        'info',
        'printed',
        'status',
        'trans_date',
        'user_id',
        'whouse_head',
    ];

    protected $casts = [
        'edit_date' => 'date',
        'trans_date' => 'date',
    ];

    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(EraseItemDetail::class, 'erase_code', 'erase_code');
    }
}
