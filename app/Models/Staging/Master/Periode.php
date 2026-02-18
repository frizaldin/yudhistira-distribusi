<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'm_period';
    
    public $incrementing = false;
    public $timestamps = false;
    
    protected $primaryKey = 'period_code';

    protected $fillable = ['period_code', 'period_name', 'from_date', 'to_date', 'period_before', 'status', 'period_codes', 'branch_code', 'tanggal_aktif'];
}
