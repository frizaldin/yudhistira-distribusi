<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoveWarehouse extends Model
{
    use HasFactory;

    protected $table = 'm_pindah_gudang';

    protected $fillable = [
        'move_code',
        'branch_code',
        'info',
        'mova_date',
        'officer',
        'printed',
        'status',
        'user_id',
        'whouse_head',
    ];

    protected $casts = [
        'mova_date' => 'date',
    ];

    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(MoveWarehouseDetail::class, 'move_code', 'move_code');
    }
}
