<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'm_employee';

    public $incrementing = false;
    public $timestamps = true;

    protected $primaryKey = 'empl_code';
    protected $keyType = 'string';

    protected $fillable = [
        'empl_code', 'empl_name', 'address', 'city', 'sex_id', 'stat_id',
        'religion_id', 'zip_code', 'marital_id', 'grade_id', 'dept_id',
        'salary', 'join_date', 'resign_date', 'branch_code', 'birth_date',
        'photo', 'phone_no', 'edu_background', 'active', 'ktp', 'spv_code',
    ];

    protected $casts = [
        'join_date'   => 'date',
        'resign_date' => 'date',
        'birth_date'  => 'date',
        'salary'      => 'integer',
        'zip_code'    => 'integer',
        'ktp'         => 'integer',
        'active'      => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'branch_code');
    }
}
