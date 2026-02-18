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
        Schema::table('nppb_centrals', function (Blueprint $table) {
            if (!Schema::hasColumn('nppb_centrals', 'volume')) {
                $table->decimal('volume', 20, 0)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nppb_centrals', function (Blueprint $table) {
            if (Schema::hasColumn('nppb_centrals', 'volume')) {
                $table->dropColumn('volume');
            }
        });
    }
};
