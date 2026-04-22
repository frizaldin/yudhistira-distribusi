<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMutation extends Model
{
    protected $table = 'stock_mutations';

    protected $fillable = [
        'no_mutasi',
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
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockMutationItem::class, 'mutation_id');
    }
}
