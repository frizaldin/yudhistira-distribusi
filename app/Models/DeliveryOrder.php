<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    protected $table = 'delivery_orders';

    protected $fillable = [
        'number',
        'sender_code',
        'recipient_code',
        'date',
        'expedition',
        'plate_number',
        'drivers',
        'driver_phone',
        'note',
        'creator_name',
        'known_name',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'koli',
        'pack',
        'terbilang',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'drivers' => 'array',
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
        return $this->hasMany(DeliveryOrderItem::class);
    }

    /**
     * Generate nomor Surat Jalan berikutnya.
     * Format: SJ{YY}{NNNNN}
     * Contoh: SJ2600001 (tahun 2026, nomor urut ke-1).
     * Nomor urut reset ke 1 setiap ganti tahun.
     */
    public static function generateNextNumber(): string
    {
        $yy      = date('y');          // 2-digit tahun, misal '26'
        $prefix  = 'SJ' . $yy;
        $pattern = '/^SJ' . $yy . '(\d{5})$/';

        $last = self::where('number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('number');

        $seq = 1;
        if ($last && preg_match($pattern, $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
