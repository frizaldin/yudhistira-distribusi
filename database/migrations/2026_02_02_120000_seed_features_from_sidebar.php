<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data feature dari menu sidebar (resources/views/components/nav/sidebar/div.blade.php).
     * Feature dipakai di Menu component: Feature::where('code', $this->key)->get()
     * Authority->code menyimpan JSON array id feature yang boleh diakses.
     */
    public function up(): void
    {
        $features = [
            ['title' => 'Dashboard', 'code' => 'dashboard', 'type' => 'menu'],
            ['title' => 'Rangkuman', 'code' => 'rangkuman', 'type' => 'menu'],
            ['title' => 'Product (Buku)', 'code' => 'product', 'type' => 'menu'],
            ['title' => 'Branch (Cabang)', 'code' => 'branch', 'type' => 'menu'],
            ['title' => 'Central Stock', 'code' => 'central_stock', 'type' => 'menu'],
            ['title' => 'Target', 'code' => 'target', 'type' => 'menu'],
            ['title' => 'Period (Periode)', 'code' => 'period', 'type' => 'menu'],
            ['title' => 'Staging', 'code' => 'staging', 'type' => 'menu'],
            ['title' => 'Pesanan', 'code' => 'pesanan', 'type' => 'menu'],
            ['title' => 'SP vs Stock', 'code' => 'sp_v_stock', 'type' => 'menu'],
            ['title' => 'SP vs Target', 'code' => 'sp_v_target', 'type' => 'menu'],
            ['title' => 'NPPB Central', 'code' => 'nppb-central', 'type' => 'menu'],
            ['title' => 'NPPB Warehouse', 'code' => 'nppb-warehouse', 'type' => 'menu'],
            ['title' => 'Recap', 'code' => 'recap', 'type' => 'menu'],
            ['title' => 'User Pusat', 'code' => 'user-pusat', 'type' => 'menu'],
            ['title' => 'User Cabang', 'code' => 'user-cabang', 'type' => 'menu'],
        ];

        $now = now();
        foreach ($features as $row) {
            if (DB::table('features')->where('code', $row['code'])->doesntExist()) {
                DB::table('features')->insert([
                    'title' => $row['title'],
                    'code' => $row['code'],
                    'type' => $row['type'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $codes = [
            'dashboard', 'rangkuman', 'product', 'branch', 'central_stock', 'target', 'period',
            'staging', 'pesanan', 'sp_v_stock', 'sp_v_target', 'nppb-central', 'nppb-warehouse',
            'recap', 'user-pusat', 'user-cabang',
        ];
        DB::table('features')->whereIn('code', $codes)->delete();
    }
};
