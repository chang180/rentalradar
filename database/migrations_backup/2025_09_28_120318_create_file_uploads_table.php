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
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('filename', 255);
            $table->string('original_filename', 255);
            $table->bigInteger('file_size');
            $table->string('file_type', 100);
            $table->string('upload_path', 500);
            $table->enum('upload_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('processing_result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
