<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Supir bisa 2: ganti kolom driver (string) jadi drivers (JSON array).
     */
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->json('drivers')->nullable()->after('plate_number');
        });

        // Pindahkan isi driver lama ke drivers[0]
        $rows = DB::table('delivery_orders')->whereNotNull('driver')->where('driver', '!=', '')->get(['id', 'driver']);
        foreach ($rows as $row) {
            DB::table('delivery_orders')->where('id', $row->id)->update([
                'drivers' => json_encode([$row->driver]),
            ]);
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('driver');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('driver', 200)->nullable()->after('plate_number');
        });

        $rows = DB::table('delivery_orders')->whereNotNull('drivers')->get(['id', 'drivers']);
        foreach ($rows as $row) {
            $arr = json_decode($row->drivers, true);
            $first = is_array($arr) && count($arr) > 0 ? ($arr[0] ?? null) : null;
            if ($first !== null) {
                DB::table('delivery_orders')->where('id', $row->id)->update(['driver' => $first]);
            }
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('drivers');
        });
    }
};
