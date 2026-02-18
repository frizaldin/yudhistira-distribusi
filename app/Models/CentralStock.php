<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralStock extends Model
{
    protected $table = 'central_stocks';
    
    protected $fillable = [
        'branch_code',
        'book_code',
        'exemplar',
    ];

    /**
     * Relasi ke CentralStockKoli (hasMany karena bisa banyak koli per stock)
     * Menggunakan query scope untuk composite key (branch_code, book_code)
     */
    public function stockKolis()
    {
        return $this->hasMany(CentralStockKoli::class, 'branch_code', 'branch_code')
            ->whereColumn('central_stock_kolis.book_code', 'central_stocks.book_code');
    }

    /**
     * Relasi ke Branch
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'branch_code');
    }

    /**
     * Relasi ke Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'book_code', 'book_code');
    }
}
