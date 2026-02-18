<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Kolom sudah ada, hanya update nullable jika perlu
        // Migration ini sudah dijalankan atau kolom sudah ditambahkan manual
        // Tidak perlu melakukan apa-apa karena struktur sudah sesuai
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: struktur targets sudah diperbarui di create_targets_table
    }
};
