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
        // 先刪除依賴 properties 表的表
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('anomalies');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('predictions');

        // 刪除舊表
        Schema::dropIfExists('properties');

        // 建立新表
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // 基本資訊
            $table->string('city'); // 縣市
            $table->string('district'); // 鄉鎮市區
            $table->string('serial_number')->unique(); // 編號（用於關聯建物表）
            $table->string('full_address'); // 完整地址

            // 租賃資訊
            $table->string('rental_type'); // 租賃類型（整棟、分租等）
            $table->decimal('total_rent', 12, 2); // 總租金
            $table->decimal('rent_per_ping', 10, 2); // 每坪租金
            $table->date('rent_date'); // 租賃日期
            $table->string('rental_period')->nullable(); // 租賃期間

            // 建物資訊
            $table->string('building_type'); // 建物型態
            $table->decimal('area_sqm', 10, 2); // 面積（平方公尺）
            $table->decimal('area_ping', 10, 2); // 面積（坪）
            $table->integer('building_age')->nullable(); // 建物年齡
            $table->integer('total_floors')->nullable(); // 總樓層數
            $table->string('main_use'); // 主要用途
            $table->string('main_building_materials')->nullable(); // 主要建材
            $table->year('construction_completion_year')->nullable(); // 建築完成年

            // 格局資訊
            $table->integer('bedrooms')->default(0); // 房數
            $table->integer('living_rooms')->default(0); // 廳數
            $table->integer('bathrooms')->default(0); // 衛數
            $table->string('compartment_pattern')->nullable(); // 格局描述

            // 設施資訊
            $table->boolean('has_elevator')->default(false); // 有無電梯
            $table->boolean('has_management_organization')->default(false); // 有無管理組織
            $table->boolean('has_furniture')->default(false); // 有無附傢俱
            $table->string('equipment')->nullable(); // 附屬設備

            // 地理位置
            $table->decimal('latitude', 10, 8)->nullable(); // 緯度
            $table->decimal('longitude', 11, 8)->nullable(); // 經度
            $table->boolean('is_geocoded')->default(false); // 是否已地理編碼

            // 資料來源與處理狀態
            $table->string('data_source')->default('government'); // 資料來源
            $table->boolean('is_processed')->default(false); // 是否已處理
            $table->json('raw_data')->nullable(); // 原始資料
            $table->text('notes')->nullable(); // 備註

            $table->timestamps();

            // 索引
            $table->index(['city', 'district']);
            $table->index(['latitude', 'longitude']);
            $table->index(['rent_date']);
            $table->index(['is_geocoded']);
            $table->index(['serial_number']);
            $table->index(['rental_type']);
            $table->index(['building_type']);
        });

        // 重新創建依賴表
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('model_version')->index();
            $table->decimal('predicted_price', 12, 2);
            $table->decimal('confidence', 5, 4);
            $table->decimal('range_min', 12, 2)->nullable();
            $table->decimal('range_max', 12, 2)->nullable();
            $table->json('breakdown')->nullable();
            $table->json('explanations')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'created_at']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->default('general');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('reasons')->nullable();
            $table->json('metadata')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();
            $table->index(['type', 'created_at']);
        });

        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('severity');
            $table->text('description');
            $table->json('context')->nullable();
            $table->json('resolution')->nullable();
            $table->timestamps();
            $table->index(['category', 'severity']);
        });

        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('risk_level');
            $table->decimal('risk_score', 5, 2);
            $table->json('factors')->nullable();
            $table->json('suggestions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['risk_level', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 先刪除依賴 properties 表的表
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('anomalies');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('predictions');

        Schema::dropIfExists('properties');
    }
};
