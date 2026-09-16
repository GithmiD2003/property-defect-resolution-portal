<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('defect_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('event', 50);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['defect_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_activities');
    }
};
