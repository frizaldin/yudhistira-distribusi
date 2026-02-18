<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'm_cabang';
    
    public $incrementing = false;
    public $timestamps = false;
    
    protected $primaryKey = 'branch_code';

    protected $fillable = ['branch_code', 'branch_name', 'address', 'phone_no', 'contact_person', 'fax_no', 'warehouse_head', 'city', 'email_address', 'area_code', 'active', 'ans_code', 'branch_head', 'region', 'warehouse_code', 'warehouse_code2', 'tanggal_aktif'];
}
