<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hapus duplikat (satu baris per branch_code + book_code) lalu tambah unique index
     * agar sync pakai upsert = 1 data saja, selalu active_data = yes.
     */
    public function up(): void
    {
        // Hapus duplikat: simpan baris dengan id terkecil per (branch_code, book_code)
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                DELETE t1 FROM sp_branches t1
                INNER JOIN sp_branches t2
                ON t1.branch_code = t2.branch_code AND t1.book_code = t2.book_code AND t1.id > t2.id
            ');
        } else {
            $keepIds = DB::table('sp_branches')
                ->select(DB::raw('MIN(id) as id'))
                ->groupBy('branch_code', 'book_code')
                ->pluck('id');
            DB::table('sp_branches')->whereNotIn('id', $keepIds)->delete();
        }

        Schema::table('sp_branches', function (Blueprint $table) {
            $table->unique(['branch_code', 'book_code'], 'sp_branches_branch_book_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sp_branches', function (Blueprint $table) {
            $table->dropUnique('sp_branches_branch_book_unique');
        });
    }
};
