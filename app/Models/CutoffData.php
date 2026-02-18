<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CutoffData extends Model
{
    use HasFactory;

    protected $table = 'cutoff_datas';

    public $timestamps = false;

    protected $fillable = [
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => 'string',
    ];

    /**
     * Apakah filter pakai rentang (start s.d. end) atau hanya s.d. end_date.
     */
    public function hasStartDate(): bool
    {
        return $this->start_date !== null;
    }
}
