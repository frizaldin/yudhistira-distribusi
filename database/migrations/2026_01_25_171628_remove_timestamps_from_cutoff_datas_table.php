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
        if (Schema::hasColumn('cutoff_datas', 'created_at') || Schema::hasColumn('cutoff_datas', 'updated_at')) {
            Schema::table('cutoff_datas', function (Blueprint $table) {
                $table->dropTimestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cutoff_datas', function (Blueprint $table) {
            $table->timestamps();
        });
    }
};
