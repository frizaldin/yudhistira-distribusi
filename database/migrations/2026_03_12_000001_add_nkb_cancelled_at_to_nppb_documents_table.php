<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Dicatat ketika NKB yang dibuat dari NPPB ini dibatalkan; dipakai untuk menandai baris di preparation-notes (merah).
     */
    public function up(): void
    {
        Schema::table('nppb_documents', function (Blueprint $table) {
            $table->timestamp('nkb_cancelled_at')->nullable()->after('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nppb_documents', function (Blueprint $table) {
            $table->dropColumn('nkb_cancelled_at');
        });
    }
};
