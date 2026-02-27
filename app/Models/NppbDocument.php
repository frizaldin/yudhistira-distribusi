<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NppbDocument extends Model
{
    /** Format: SP00 (kode pusat) + 1 abjad (A-Z) + 6 digit urutan. Saat urutan 999999, abjad naik (A→B→…→Z→A). */
    const NUMBER_PREFIX = 'SP00';
    const NUMBER_MAX_SEQ = 999999;

    protected $table = 'nppb_documents';

    protected $fillable = [
        'number',
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

    public function nppbCentrals()
    {
        return $this->hasMany(NppbCentral::class, 'document_id', 'id');
    }

    /** Cabang pengirim (dari tabel branch). */
    public function senderBranch()
    {
        return $this->belongsTo(Branch::class, 'sender_code', 'branch_code');
    }

    /** Cabang penerima (dari tabel branch). */
    public function recipientBranch()
    {
        return $this->belongsTo(Branch::class, 'recipient_code', 'branch_code');
    }

    /**
     * Generate nomor dokumen berikutnya: SP00 + abjad (A-Z) + 6 digit.
     * Urutan 1–999999 per abjad; setelah 999999 abjad naik (A→B→…→Z→A).
     * Dipanggil di dalam transaksi dengan lock agar aman untuk request bersamaan.
     */
    public static function generateNextNumber(): string
    {
        $last = DB::table('nppb_documents')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('number');

        if (!$last || !preg_match('/^' . preg_quote(self::NUMBER_PREFIX, '/') . '([A-Z])(\d{6})$/', $last, $m)) {
            return self::NUMBER_PREFIX . 'A' . str_pad('1', 6, '0', STR_PAD_LEFT);
        }

        $letter = $m[1];
        $seq = (int) $m[2];
        $seq++;

        if ($seq > self::NUMBER_MAX_SEQ) {
            $seq = 1;
            $letter = ($letter === 'Z') ? 'A' : chr(ord($letter) + 1);
        }

        return self::NUMBER_PREFIX . $letter . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
