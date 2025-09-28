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
        Schema::table('properties', function (Blueprint $table) {
            $table->string('serial_number', 255)->nullable()->comment('政府資料序號，用於資料驗證和去重');
        });

        // 為 SQLite 添加 unique index
        Schema::table('properties', function (Blueprint $table) {
            $table->unique('serial_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('serial_number');
        });
    }
};
