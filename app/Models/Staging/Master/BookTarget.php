<?php

namespace App\Models\Staging\Master;

use Illuminate\Database\Eloquent\Model;

class BookTarget extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'r_target_buku';

    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['branch_code', 'period_code', 'book_code', 'exemplar'];
}
