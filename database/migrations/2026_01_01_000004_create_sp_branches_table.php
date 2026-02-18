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
        Schema::create('sp_branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code', 100);
            $table->string('book_code', 100);
            $table->decimal('ex_sp', 20, 0)->default(0);
            $table->decimal('ex_ftr', 20, 0)->default(0);
            $table->decimal('ex_ret', 20, 0)->default(0);
            $table->decimal('ex_rec_pst', 20, 0)->default(0);
            $table->decimal('ex_rec_gdg', 20, 0)->default(0);
            $table->decimal('ex_stock', 20, 0)->default(0);
            $table->date('trans_date')->nullable();
            $table->string('active_data', 50)->default('yes');
            $table->timestamps();
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
        Schema::dropIfExists('sp_branches');
    }
};
