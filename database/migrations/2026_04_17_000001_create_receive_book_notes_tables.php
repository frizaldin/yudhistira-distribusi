<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirror nota terima dari staging (PostgreSQL) ke MySQL — nama tabel sama konvensi sumber.
     */
    public function up(): void
    {
        Schema::create('m_terima_buku', function (Blueprint $table) {
            $table->id();
            $table->string('nota_kirim_cab', 100)->nullable();
            $table->string('receive_code', 100);
            $table->string('branch_code', 100)->nullable();
            $table->date('retur_date')->nullable();
            $table->date('send_date')->nullable();
            $table->string('info', 500)->nullable();
            $table->string('branch_sender', 100)->nullable();

            $table->unique('receive_code');
            $table->index('nota_kirim_cab');
            $table->index('branch_code');
            $table->index('branch_sender');
        });

        Schema::create('d_terima_buku', function (Blueprint $table) {
            $table->id();
            $table->string('nota_kirim_cab', 100);
            $table->string('book_code', 100);
            $table->string('book_price', 100)->nullable();
            $table->decimal('koli', 20, 0)->default(0);
            $table->decimal('exemplar', 20, 0)->default(0);
            $table->decimal('total_exemplar', 20, 0)->default(0);
            $table->decimal('volume', 20, 0)->default(0);
            $table->string('branch_sender', 100)->nullable();

            $table->unique(['nota_kirim_cab', 'book_code']);
            $table->index('book_code');
            $table->index('branch_sender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('d_terima_buku');
        Schema::dropIfExists('m_terima_buku');
    }
};
