<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralStockDeduction extends Model
{
    protected $table = 'central_stock_deductions';

    protected $fillable = [
        'book_code',
        'quantity',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    const SOURCE_NKB = 'nkb';
    const SOURCE_DELIVERY_ORDER = 'delivery_order';

    public function product()
    {
        return $this->belongsTo(Product::class, 'book_code', 'book_code');
    }
}
