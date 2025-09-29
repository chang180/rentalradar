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
            // 添加關鍵效能索引
            $table->index('total_rent', 'idx_total_rent');
            $table->index('rent_per_ping', 'idx_rent_per_ping');
            $table->index('building_age', 'idx_building_age');
            $table->index('area_ping', 'idx_area_ping');
            
            // 添加複合索引
            $table->index(['city', 'district', 'total_rent'], 'idx_city_district_rent');
            $table->index(['is_geocoded', 'city', 'district'], 'idx_geocoded_city_district');
            $table->index(['building_type', 'rental_type'], 'idx_building_rental_type');
            $table->index(['rent_date', 'city'], 'idx_rent_date_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // 移除索引
            $table->dropIndex('idx_total_rent');
            $table->dropIndex('idx_rent_per_ping');
            $table->dropIndex('idx_building_age');
            $table->dropIndex('idx_area_ping');
            $table->dropIndex('idx_city_district_rent');
            $table->dropIndex('idx_geocoded_city_district');
            $table->dropIndex('idx_building_rental_type');
            $table->dropIndex('idx_rent_date_city');
        });
    }
};