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
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('nota_kirim_cab', 100);
            $table->string('branch_code', 100)->nullable();
            $table->string('branch_sender', 100)->nullable();
            $table->date('send_date')->nullable();
            $table->string('info', 100)->nullable();
            $table->string('nppb', 100)->nullable();
            $table->string('sj', 100)->nullable();
            // No timestamps - using $timestamps = false in model
            
            // Index untuk performa query
            $table->index('nota_kirim_cab');
            $table->index('branch_code');
            $table->index('send_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
