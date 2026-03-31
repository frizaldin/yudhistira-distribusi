<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMutation extends Model
{
    protected $table = 'stock_mutations';

    protected $fillable = [
        'book_code',
        'koli',
        'isi_koli',
        'eceran',
        'total_eksemplar',
        'nama_pt_produksi',
        'tanggal_penerimaan',
        'nama_penerima',
        'nomor_surat_jalan',
        'nomor_jo',
        'keterangan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penerimaan' => 'date',
            'koli' => 'integer',
            'isi_koli' => 'integer',
            'eceran' => 'integer',
            'total_eksemplar' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'book_code', 'book_code');
    }
}
