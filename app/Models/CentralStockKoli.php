<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralStockKoli extends Model
{
    protected $table = 'central_stock_kolis';

    protected $fillable = [
        'branch_code',
        'book_code',
        'volume',
        'koli',
    ];

    public $timestamps = false;

    /**
     * Relasi ke CentralStock (menggunakan query scope karena composite key)
     */
    public function centralStock()
    {
        return CentralStock::where('branch_code', $this->branch_code)
            ->where('book_code', $this->book_code)
            ->first();
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
