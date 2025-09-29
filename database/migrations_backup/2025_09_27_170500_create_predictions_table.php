<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
