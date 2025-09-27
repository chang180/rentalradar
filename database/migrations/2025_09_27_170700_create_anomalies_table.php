<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};
