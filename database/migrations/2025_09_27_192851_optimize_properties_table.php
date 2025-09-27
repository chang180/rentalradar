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
        // SQLite 不支援直接刪除欄位，需要重建表
        Schema::dropIfExists('properties');

        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // 基本資訊
            $table->string('city'); // 縣市
            $table->string('district'); // 鄉鎮市區
            $table->string('full_address'); // 完整地址

            // 租賃資訊
            $table->string('rental_type'); // 租賃類型
            $table->decimal('total_rent', 12, 2); // 總租金
            $table->decimal('rent_per_ping', 10, 2); // 每坪租金
            $table->date('rent_date'); // 租賃日期

            // 建物資訊
            $table->string('building_type'); // 建物型態
            $table->decimal('area_ping', 10, 2); // 面積（坪）
            $table->integer('building_age')->nullable(); // 建物年齡
            $table->integer('total_floors')->nullable(); // 總樓層數
            $table->string('main_use'); // 主要用途

            // 格局資訊
            $table->integer('bedrooms')->default(0); // 房數
            $table->integer('living_rooms')->default(0); // 廳數
            $table->integer('bathrooms')->default(0); // 衛數
            $table->string('compartment_pattern')->nullable(); // 格局描述

            // 設施資訊
            $table->boolean('has_elevator')->default(false); // 有無電梯
            $table->boolean('has_management_organization')->default(false); // 有無管理組織
            $table->boolean('has_furniture')->default(false); // 有無附傢俱

            // 地理位置
            $table->decimal('latitude', 10, 8)->nullable(); // 緯度
            $table->decimal('longitude', 11, 8)->nullable(); // 經度
            $table->boolean('is_geocoded')->default(false); // 是否已地理編碼

            $table->timestamps();

            // 索引
            $table->index(['city', 'district']);
            $table->index(['latitude', 'longitude']);
            $table->index(['rent_date']);
            $table->index(['is_geocoded']);
            $table->index(['rental_type']);
            $table->index(['building_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // 重新添加欄位
            $table->string('serial_number')->unique()->after('id');
            $table->json('raw_data')->nullable()->after('is_geocoded');
            $table->text('notes')->nullable()->after('raw_data');
            $table->string('data_source')->default('government')->after('notes');
            $table->boolean('is_processed')->default(false)->after('data_source');
            $table->string('rental_period')->nullable()->after('rent_date');
            $table->string('main_building_materials')->nullable()->after('main_use');
            $table->year('construction_completion_year')->nullable()->after('main_building_materials');
            $table->string('equipment')->nullable()->after('has_furniture');
            $table->decimal('area_sqm', 10, 2)->after('building_type');
        });
    }
};
