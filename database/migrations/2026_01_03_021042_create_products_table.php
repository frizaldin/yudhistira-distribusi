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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100);
            $table->text('title');
            $table->decimal('number_book', 20, 0)->nullable();
            $table->string('book_segment', 100)->nullable();
            $table->string('curriculum', 100)->nullable();
            $table->text('bid_study')->nullable();
            $table->string('class', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
