<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Target extends Model
{
    protected $fillable = [
        'branch_code',
        'book_code',
        'period_code',
        'exemplar',
    ];

    protected $casts = [
        'exemplar' => 'decimal:0',
    ];

    /**
     * Relationship dengan Branch
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'branch_code');
    }

    /**
     * Relationship dengan Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'book_code', 'book_code');
    }
}
