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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // 基本租屋資訊
            $table->string('district'); // 鄉鎮市區
            $table->string('village')->nullable(); // 村里
            $table->string('road')->nullable(); // 路名
            $table->string('land_section')->nullable(); // 土地段名
            $table->string('land_subsection')->nullable(); // 土地小段名
            $table->string('land_number')->nullable(); // 地號

            // 建物資訊
            $table->string('building_type'); // 建物型態
            $table->decimal('total_floor_area', 10, 2); // 總樓地板面積平方公尺
            $table->string('main_use'); // 主要用途
            $table->string('main_building_materials'); // 主要建材
            $table->year('construction_completion_year'); // 建築完成年月
            $table->integer('total_floors'); // 總樓層數
            $table->string('compartment_pattern'); // 格局
            $table->boolean('has_management_organization'); // 有無管理組織

            // 租賃資訊
            $table->decimal('rent_per_month', 10, 2); // 單價元平方公尺
            $table->decimal('total_rent', 12, 2); // 總價元
            $table->date('rent_date'); // 租賃年月日
            $table->string('rental_period'); // 租賃期間

            // 地理位置資訊
            $table->decimal('latitude', 10, 8)->nullable(); // 緯度
            $table->decimal('longitude', 11, 8)->nullable(); // 經度
            $table->string('full_address')->nullable(); // 完整地址
            $table->boolean('is_geocoded')->default(false); // 是否已地理編碼

            // 資料來源與處理狀態
            $table->string('data_source')->default('government'); // 資料來源
            $table->boolean('is_processed')->default(false); // 是否已處理
            $table->json('processing_notes')->nullable(); // 處理註記

            $table->timestamps();

            // 索引
            $table->index(['district', 'village']);
            $table->index(['latitude', 'longitude']);
            $table->index(['rent_date']);
            $table->index(['is_geocoded']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
