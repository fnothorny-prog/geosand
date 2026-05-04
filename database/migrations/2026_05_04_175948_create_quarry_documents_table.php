<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarry_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quarry_id')->constrained('quarries')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->string('document_type', 100); // e.g. permit, environmental_clearance, etc.
            $table->string('title', 255);
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size'); // bytes
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarry_documents');
    }
};
