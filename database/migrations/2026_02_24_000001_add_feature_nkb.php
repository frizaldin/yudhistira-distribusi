<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('features')->where('code', 'nkb')->doesntExist()) {
            DB::table('features')->insert([
                'title' => 'NKB',
                'code' => 'nkb',
                'type' => 'menu',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('features')->where('code', 'nkb')->delete();
    }
};
