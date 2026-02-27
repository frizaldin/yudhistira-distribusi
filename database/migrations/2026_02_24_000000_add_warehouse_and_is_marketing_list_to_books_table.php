<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Kolom warehouse dari import kolom GUDANG; is_marketing_list = 'Y' untuk data yang di-import dari Excel.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('warehouse', 255)->nullable()->after('serial');
            $table->enum('is_marketing_list', ['Y', 'N'])->nullable()->after('warehouse');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['warehouse', 'is_marketing_list']);
        });
    }
};
