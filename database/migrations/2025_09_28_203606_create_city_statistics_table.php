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
        Schema::create('city_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('city', 50)->unique();
            $table->integer('district_count')->default(0);
            $table->integer('total_properties')->default(0);
            $table->decimal('avg_rent_per_ping', 10, 2)->nullable();
            $table->decimal('min_rent_per_ping', 10, 2)->nullable();
            $table->decimal('max_rent_per_ping', 10, 2)->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_statistics');
    }
};
