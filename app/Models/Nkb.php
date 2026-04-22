<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Nkb extends Model
{
    protected $table = 'nkbs';

    protected $fillable = [
        'number',
        'nppb_code',
        'note',
        'creator_name',
        'known_name',
        'sender_code',
        'recipient_code',
        'send_date',
        'total_type_books',
        'total_exemplar',
        'note_more',
        'created_by',
    ];

    protected $casts = [
        'send_date' => 'date',
        'total_type_books' => 'integer',
        'total_exemplar' => 'integer',
        'created_by' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function senderBranch()
    {
        return $this->belongsTo(Branch::class, 'sender_code', 'branch_code');
    }

    public function recipientBranch()
    {
        return $this->belongsTo(Branch::class, 'recipient_code', 'branch_code');
    }

    /** Dokumen NPPB asal (jika NKB dibuat dari preparation notes). */
    public function document()
    {
        return $this->belongsTo(NppbDocument::class, 'nppb_code', 'number');
    }

    public function items()
    {
        return $this->hasMany(NkbItem::class, 'nkb_code', 'number');
    }

    /**
     * Generate nomor NKB berikutnya.
     * Format: {YY}-{NNNNNN}
     * Contoh: 26-000001 (tahun 2026, nomor urut ke-1).
     * Nomor urut reset ke 1 setiap ganti tahun.
     */
    public static function generateNextNumber(string $senderCode = ''): string
    {
        $yy      = date('y');          // 2-digit tahun, misal '26'
        $prefix  = $yy . '-';
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{6})$/';

        $last = DB::table('nkbs')
            ->where('number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('number');

        if (!$last || !preg_match($pattern, $last, $m)) {
            return $prefix . str_pad('1', 6, '0', STR_PAD_LEFT);
        }

        $seq = (int) $m[1] + 1;

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
