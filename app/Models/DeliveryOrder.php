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
        'driver',
        'driver_phone',
        'note',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
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
     * Generate nomor Surat Jalan berikutnya. Format: SJ-YYYYMMDD-XXXX (4 digit urut per hari).
     */
    public static function generateNextNumber(): string
    {
        $prefix = 'SJ-' . date('Ymd') . '-';
        $last = self::where('number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
