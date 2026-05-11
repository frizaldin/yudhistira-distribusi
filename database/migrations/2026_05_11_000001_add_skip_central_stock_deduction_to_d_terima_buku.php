<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jika dicentang di form NTB: baris tidak membuat pengurangan stock pusat (central_stock_deductions).
     */
    public function up(): void
    {
        Schema::table('d_terima_buku', function (Blueprint $table) {
            $table->boolean('skip_central_stock_deduction')
                ->default(false)
                ->after('branch_sender')
                ->comment('1 = tidak mengurangi stock pusat');
        });
    }

    public function down(): void
    {
        Schema::table('d_terima_buku', function (Blueprint $table) {
            $table->dropColumn('skip_central_stock_deduction');
        });
    }
};
