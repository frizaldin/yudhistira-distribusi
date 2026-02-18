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
        Schema::create('central_stock_kolis', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code', 100);
            $table->string('book_code', 100);
            $table->decimal('volume', 20, 0)->default(0);
            $table->decimal('koli', 20, 0)->default(0);
            $table->foreign('branch_code')->references('branch_code')->on('branches')->cascadeOnDelete();
            $table->foreign('book_code')->references('book_code')->on('books')->cascadeOnDelete();
            $table->index(['branch_code', 'book_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('central_stock_kolis');
    }
};
