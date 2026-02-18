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
        Schema::create('nppb_centrals', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code', 100)->nullable();
            $table->string('branch_name', 200)->nullable();
            $table->string('book_code', 100)->nullable();
            $table->string('book_name', 500)->nullable();
            $table->decimal('koli', 20, 0)->default(0);
            $table->decimal('pls', 20, 0)->default(0);
            $table->decimal('exp', 20, 0)->default(0);
            $table->date('date')->nullable();
            $table->decimal('volume', 20, 0)->default(0);
            $table->timestamps();
            $table->foreign('branch_code')->references('branch_code')->on('branches')->nullOnDelete();
            $table->index('branch_code');
            $table->index('book_code');
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nppb_centrals');
    }
};
