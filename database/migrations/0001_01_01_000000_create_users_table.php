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
        Schema::create('authorities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->string('branch_code', 100)->primary();
            $table->string('branch_name', 200)->nullable();
            $table->text('address')->nullable();
            $table->string('phone_no', 100)->nullable();
            $table->string('contact_person', 200)->nullable();
            $table->string('fax_no', 100)->nullable();
            $table->string('warehouse_head', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('email_address', 200)->nullable();
            $table->string('area_code', 100)->nullable();
            $table->string('active', 50)->nullable();
            $table->string('ans_code', 100)->nullable();
            $table->string('branch_head', 200)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('warehouse_code', 100)->nullable();
            $table->string('warehouse_code2', 100)->nullable();
            $table->date('tanggal_aktif')->nullable();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('book_code', 100)->unique();
            $table->string('book_title', 500)->nullable();
            $table->string('pages', 50)->nullable();
            $table->string('paper_size', 100)->nullable();
            $table->string('paper_code', 100)->nullable();
            $table->string('c_color_code', 100)->nullable();
            $table->decimal('sale_price', 20, 2)->nullable();
            $table->string('writer', 200)->nullable();
            $table->string('book_tipe', 100)->nullable();
            $table->string('isbn', 100)->nullable();
            $table->string('mulok', 100)->nullable();
            $table->string('aktif', 50)->nullable();
            $table->string('jenjang', 100)->nullable();
            $table->string('category', 100)->nullable();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->nullable();
            $table->string('code', 100)->nullable();
            $table->string('type', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authority_id')->nullable()->constrained('authorities')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('photo', 500)->nullable();
            $table->string('branch_code', 100)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->foreign('branch_code')->references('branch_code')->on('branches')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('features');
        Schema::dropIfExists('books');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('authorities');
    }
};
