<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'm_employee';

    public $incrementing = false;
    public $timestamps = false;

    protected $primaryKey = 'empl_code';

    protected $fillable = [
        'empl_code', 'empl_name', 'address', 'city', 'sex_id', 'stat_id',
        'religion_id', 'zip_code', 'marital_id', 'grade_id', 'dept_id',
        'salary', 'join_date', 'resign_date', 'branch_code', 'birth_date',
        'photo', 'phone_no', 'edu_background', 'active', 'ktp', 'spv_code',
    ];
}
