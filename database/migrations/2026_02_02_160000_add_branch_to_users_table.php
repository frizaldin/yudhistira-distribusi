<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Kolom branch (JSON) khusus untuk akun ADP: menyimpan array kode cabang yang menjadi wewenang user, contoh ["dy01","dy02","dy03"].
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('branch')->nullable()->after('branch_code')->comment('Otoritas cabang untuk user ADP (array branch_code)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('branch');
        });
    }
};
