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
        Schema::create('district_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('city', 50);
            $table->string('district', 50);
            $table->integer('property_count')->default(0);
            $table->decimal('avg_rent', 10, 2)->nullable();
            $table->decimal('avg_rent_per_ping', 10, 2)->nullable();
            $table->decimal('min_rent', 10, 2)->nullable();
            $table->decimal('max_rent', 10, 2)->nullable();
            $table->decimal('avg_area_ping', 8, 2)->nullable();
            $table->decimal('avg_building_age', 5, 1)->nullable();
            $table->decimal('elevator_ratio', 5, 2)->nullable();
            $table->decimal('management_ratio', 5, 2)->nullable();
            $table->decimal('furniture_ratio', 5, 2)->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['city', 'district']);
            $table->index('city');
            $table->index('last_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('district_statistics');
    }
};
