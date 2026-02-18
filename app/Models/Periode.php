<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    protected $table = 'periods';

    public $incrementing = false;
    protected $primaryKey = 'period_code';
    protected $keyType = 'string';

    protected $fillable = [
        'period_code',
        'period_name',
        'from_date',
        'to_date',
        'period_before',
        'status',
        'period_codes',
        'branch_code',
        'tanggal_aktif',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'tanggal_aktif' => 'date',
        'status' => 'boolean',
    ];

    public $timestamps = false;

    /**
     * Relationship dengan Branch
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'branch_code');
    }
}
