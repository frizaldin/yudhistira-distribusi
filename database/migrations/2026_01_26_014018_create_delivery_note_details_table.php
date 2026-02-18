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
        Schema::create('delivery_note_details', function (Blueprint $table) {
            $table->id();
            $table->string('nota_kirim_cab', 100);
            $table->string('book_code', 100)->nullable();
            $table->string('book_price', 100)->nullable();
            $table->decimal('koli', 20, 0)->default(0);
            $table->decimal('exemplar', 20, 0)->default(0);
            $table->decimal('total_exemplar', 20, 0)->default(0);
            $table->decimal('volume', 20, 0)->default(0);
            $table->string('branch_sender', 100)->nullable();
            // No timestamps - using $timestamps = false in model
            
            // Index untuk performa query
            $table->index('nota_kirim_cab');
            $table->index('book_code');
            $table->index('branch_sender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_note_details');
    }
};
