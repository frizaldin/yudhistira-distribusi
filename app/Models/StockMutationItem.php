<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMutationItem extends Model
{
    protected $table = 'stock_mutation_items';

    protected $fillable = [
        'mutation_id',
        'book_code',
        'koli',
        'isi_koli',
        'eceran',
        'total_eksemplar',
    ];

    protected function casts(): array
    {
        return [
            'koli'            => 'integer',
            'isi_koli'        => 'integer',
            'eceran'          => 'integer',
            'total_eksemplar' => 'integer',
        ];
    }

    public function mutation(): BelongsTo
    {
        return $this->belongsTo(StockMutation::class, 'mutation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'book_code', 'book_code');
    }
}
