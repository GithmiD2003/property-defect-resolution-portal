<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defects', function (Blueprint $table) {
            $table->text('repair_notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('repaired_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('defects', function (Blueprint $table) {
            $table->dropColumn([
                'repair_notes',
                'started_at',
                'repaired_at',
            ]);
        });
    }
};
