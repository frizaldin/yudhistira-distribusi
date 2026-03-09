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
        Schema::table('nppb_documents', function (Blueprint $table) {
            $table->string('creator_name', 255)->default('')->after('note_more');
            $table->string('known_name', 255)->default('')->after('creator_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nppb_documents', function (Blueprint $table) {
            $table->dropColumn(['creator_name', 'known_name']);
        });
    }
};
