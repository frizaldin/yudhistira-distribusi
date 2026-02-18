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
        Schema::table('sp_branches', function (Blueprint $table) {
            if (!Schema::hasColumn('sp_branches', 'active_data')) {
                $table->string('active_data', 50)->default('yes')->after('trans_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sp_branches', function (Blueprint $table) {
            $table->dropColumn('active_data');
        });
    }
};
