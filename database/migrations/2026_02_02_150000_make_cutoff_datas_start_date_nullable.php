<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Hanya end_date yang required; start_date boleh null.
     * Jika start_date null, seluruh data di menu lain mengambil data <= end_date.
     */
    public function up(): void
    {
        Schema::table('cutoff_datas', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cutoff_datas', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
        });
    }
};
