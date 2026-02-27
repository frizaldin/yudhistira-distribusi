<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NppbCentral extends Model
{
    protected $table = 'nppb_centrals';

    protected $fillable = [
        'branch_code',
        'branch_name',
        'book_code',
        'book_name',
        'koli',
        'pls',
        'exp',
        'date',
        'volume',
        'stack',
        'document_id',
        'created_by',
    ];

    protected $casts = [
        'koli' => 'decimal:0',
        'pls' => 'decimal:0',
        'exp' => 'decimal:0',
        'volume' => 'decimal:0',
        'date' => 'date',
    ];

    /**
     * Relationship dengan Branch
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'branch_code');
    }

    /**
     * User yang membuat record (simpan NPPB).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Dokumen NPPB (setelah Approve Rencana).
     */
    public function nppbDocument()
    {
        return $this->belongsTo(NppbDocument::class, 'document_id', 'id');
    }
}
