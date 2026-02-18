<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Kolom untuk import identifikasi buku: SERIAL (kolom G) dan KATEGORI (kolom H).
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('category_manual', 200)->nullable()->after('category');
            $table->string('serial', 200)->nullable()->after('category_manual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['category_manual', 'serial']);
        });
    }
};
