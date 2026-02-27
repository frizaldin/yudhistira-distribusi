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

    public function items()
    {
        return $this->hasMany(NkbItem::class, 'nkb_code', 'number');
    }

    /**
     * Generate nomor NKB berikutnya per sender_code.
     * Format: {sender_code}-{abjad}{nomor_urut}
     * Contoh: PS00-A000001. Jika nomor urut mencapai 999999, abjad naik (A→B→…→Z→A) dan urut reset 1.
     */
    public static function generateNextNumber(string $senderCode): string
    {
        $prefix = $senderCode . '-';
        $pattern = '/^' . preg_quote($prefix, '/') . '([A-Z])(\d{6})$/';

        $last = DB::table('nkbs')
            ->where('number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('number');

        if (!$last || !preg_match($pattern, $last, $m)) {
            return $prefix . 'A' . str_pad('1', 6, '0', STR_PAD_LEFT);
        }

        $letter = $m[1];
        $seq = (int) $m[2] + 1;
        $maxSeq = 999999;

        if ($seq > $maxSeq) {
            $seq = 1;
            $letter = ($letter === 'Z') ? 'A' : chr(ord($letter) + 1);
        }

        return $prefix . $letter . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
