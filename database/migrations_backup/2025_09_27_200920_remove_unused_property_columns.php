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
        // 由於 SQLite 不支援直接刪除欄位，我們需要重建表格
        // 先刪除依賴 properties 表的表
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('anomalies');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('predictions');

        Schema::dropIfExists('properties');

        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // 基本位置資訊
            $table->string('city')->index();                    // 縣市
            $table->string('district')->index();                 // 行政區
            $table->decimal('latitude', 10, 8)->nullable();     // 緯度
            $table->decimal('longitude', 11, 8)->nullable();    // 經度
            $table->boolean('is_geocoded')->default(false);     // 是否已地理編碼

            // 租賃核心資訊
            $table->string('rental_type')->index();             // 租賃類型
            $table->decimal('total_rent', 10, 2);                // 總租金
            $table->decimal('rent_per_ping', 8, 2);              // 每坪租金
            $table->date('rent_date');                          // 租賃日期

            // 建物基本資訊
            $table->string('building_type');                    // 建物類型
            $table->decimal('area_ping', 8, 2);                  // 面積(坪)
            $table->integer('building_age')->nullable();         // 建物年齡

            // 格局資訊
            $table->integer('bedrooms')->nullable();             // 臥室數
            $table->integer('living_rooms')->nullable();         // 客廳數
            $table->integer('bathrooms')->nullable();            // 衛浴數

            // 設施資訊
            $table->boolean('has_elevator')->default(false);     // 是否有電梯
            $table->boolean('has_management_organization')->default(false); // 是否有管理組織
            $table->boolean('has_furniture')->default(false);   // 是否有傢俱

            $table->timestamps();
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
        // 如果需要回滾，重建原來的表格結構
        // 先刪除依賴 properties 表的表
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('anomalies');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('predictions');

        Schema::dropIfExists('properties');

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('district');
            $table->string('full_address');
            $table->string('rental_type');
            $table->decimal('total_rent', 10, 2);
            $table->decimal('rent_per_ping', 8, 2);
            $table->date('rent_date');
            $table->string('building_type');
            $table->decimal('area_ping', 8, 2);
            $table->integer('building_age')->nullable();
            $table->integer('total_floors')->nullable();
            $table->string('main_use')->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('living_rooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->string('compartment_pattern')->nullable();
            $table->boolean('has_elevator')->default(false);
            $table->boolean('has_management_organization')->default(false);
            $table->boolean('has_furniture')->default(false);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_geocoded')->default(false);
            $table->timestamps();
        });
    }
};
