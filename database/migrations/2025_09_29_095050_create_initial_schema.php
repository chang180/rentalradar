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
        // 基礎表已經由 Laravel 的基礎遷移創建，這裡只創建自定義表

        // 創建 properties 表（最終結構）
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('city')->index();
            $table->string('district')->index();
            $table->string('serial_number')->unique();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_geocoded')->default(false);
            $table->string('rental_type')->index();
            $table->decimal('total_rent', 10, 2);
            $table->decimal('rent_per_ping', 8, 2);
            $table->date('rent_date');
            $table->string('building_type');
            $table->decimal('area_ping', 8, 2);
            $table->integer('building_age')->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('living_rooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->boolean('has_elevator')->default(false);
            $table->boolean('has_management_organization')->default(false);
            $table->boolean('has_furniture')->default(false);
            $table->timestamps();
        });

        // 創建 predictions 表
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

        // 創建 recommendations 表
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

        // 創建 anomalies 表
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

        // 創建 risk_assessments 表
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

        // 創建 file_uploads 表
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('original_filename');
            $table->integer('file_size');
            $table->string('file_type');
            $table->string('upload_path');
            $table->string('upload_status');
            $table->text('processing_result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // 創建 schedule_settings 表
        Schema::create('schedule_settings', function (Blueprint $table) {
            $table->id();
            $table->string('task_name')->unique();
            $table->string('frequency');
            $table->text('execution_days')->nullable();
            $table->time('execution_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 創建 schedule_executions 表
        Schema::create('schedule_executions', function (Blueprint $table) {
            $table->id();
            $table->string('task_name');
            $table->datetime('scheduled_at');
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->string('status');
            $table->text('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // 創建 district_statistics 表
        Schema::create('district_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('district');
            $table->integer('property_count');
            $table->decimal('avg_rent', 10, 2);
            $table->decimal('avg_rent_per_ping', 8, 2);
            $table->decimal('min_rent', 10, 2);
            $table->decimal('max_rent', 10, 2);
            $table->decimal('avg_area_ping', 8, 2);
            $table->decimal('avg_building_age', 5, 2);
            $table->decimal('elevator_ratio', 5, 4);
            $table->decimal('management_ratio', 5, 4);
            $table->decimal('furniture_ratio', 5, 4);
            $table->datetime('last_updated_at');
            $table->timestamps();
            $table->unique(['city', 'district']);
            $table->index('city');
            $table->index('last_updated_at');
        });

        // 創建 city_statistics 表
        Schema::create('city_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('city')->unique();
            $table->integer('district_count');
            $table->integer('total_properties');
            $table->decimal('avg_rent_per_ping', 8, 2);
            $table->decimal('min_rent_per_ping', 8, 2);
            $table->decimal('max_rent_per_ping', 8, 2);
            $table->datetime('last_updated_at');
            $table->timestamps();
        });

        // 添加性能索引到 properties 表
        Schema::table('properties', function (Blueprint $table) {
            $table->index(['city', 'district', 'total_rent'], 'idx_city_district_rent');
            $table->index(['is_geocoded', 'city', 'district'], 'idx_geocoded_city_district');
            $table->index(['rent_date', 'city'], 'idx_rent_date_city');
            $table->index(['building_type', 'rental_type'], 'idx_building_rental_type');
            $table->index('total_rent', 'idx_total_rent');
            $table->index('rent_per_ping', 'idx_rent_per_ping');
            $table->index('area_ping', 'idx_area_ping');
            $table->index('building_age', 'idx_building_age');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 按依賴順序刪除自定義表
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('anomalies');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('file_uploads');
        Schema::dropIfExists('schedule_executions');
        Schema::dropIfExists('schedule_settings');
        Schema::dropIfExists('district_statistics');
        Schema::dropIfExists('city_statistics');
    }
};
