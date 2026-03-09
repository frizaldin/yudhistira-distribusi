<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pengurangan stock pusat karena NKB / Delivery Order (eksemplar yang sudah keluar).
     */
    public function up(): void
    {
        Schema::create('central_stock_deductions', function (Blueprint $table) {
            $table->id();
            $table->string('book_code', 100);
            $table->decimal('quantity', 20, 0)->default(0)->comment('Eksemplar yang dikurangi');
            $table->string('source_type', 50)->comment('nkb, delivery_order');
            $table->string('source_id', 100)->comment('Nomor NKB atau ID Delivery Order');
            $table->timestamps();
            $table->foreign('book_code')->references('book_code')->on('books')->cascadeOnDelete();
            $table->index(['book_code', 'source_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('central_stock_deductions');
    }
};
