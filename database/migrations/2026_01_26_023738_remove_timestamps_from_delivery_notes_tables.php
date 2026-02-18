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
        // Remove timestamps from delivery_notes table
        if (Schema::hasColumn('delivery_notes', 'created_at') || Schema::hasColumn('delivery_notes', 'updated_at')) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->dropTimestamps();
            });
        }

        // Remove timestamps from delivery_note_details table
        if (Schema::hasColumn('delivery_note_details', 'created_at') || Schema::hasColumn('delivery_note_details', 'updated_at')) {
            Schema::table('delivery_note_details', function (Blueprint $table) {
                $table->dropTimestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add timestamps back to delivery_notes table
        if (!Schema::hasColumn('delivery_notes', 'created_at')) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->timestamps();
            });
        }

        // Add timestamps back to delivery_note_details table
        if (!Schema::hasColumn('delivery_note_details', 'created_at')) {
            Schema::table('delivery_note_details', function (Blueprint $table) {
                $table->timestamps();
            });
        }
    }
};
