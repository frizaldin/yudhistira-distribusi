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
        Schema::create('periods', function (Blueprint $table) {
            $table->string('period_code', 100)->primary();
            $table->string('period_name', 200)->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('period_before', 100)->nullable();
            $table->boolean('status')->default(true);
            $table->string('period_codes', 100)->nullable();
            $table->string('branch_code', 100)->nullable();
            $table->date('tanggal_aktif')->nullable();
            $table->foreign('branch_code')->references('branch_code')->on('branches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
