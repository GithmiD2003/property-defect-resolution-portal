<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('room_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('reported_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title', 255);
            $table->text('description');
            $table->string('category', 50);
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('reported');
            $table->date('due_date')->nullable();

            $table->timestamps();

            $table->index(['property_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defects');
    }
};
