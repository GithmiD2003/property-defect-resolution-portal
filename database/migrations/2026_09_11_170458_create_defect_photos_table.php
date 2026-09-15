<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_photos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('defect_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('disk', 50)->default('local');
            $table->string('path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('type', 20)->default('before');

            $table->timestamps();

            $table->index(['defect_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_photos');
    }
};
