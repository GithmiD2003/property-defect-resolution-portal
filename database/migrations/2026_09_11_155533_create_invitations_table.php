<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('role');
            $table->string('token_hash', 64)->unique();

            $table->foreignId('invited_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->restrictOnDelete();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
