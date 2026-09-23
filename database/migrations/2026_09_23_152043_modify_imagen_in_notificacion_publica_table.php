<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notificacion_publica', function (Blueprint $table) {
            DB::statement('ALTER TABLE notificacion_publica MODIFY imagen VARCHAR(500) NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificacion_publica', function (Blueprint $table) {
            DB::statement('ALTER TABLE notificacion_publica MODIFY imagen VARCHAR(50) NULL');
        });
    }
};
