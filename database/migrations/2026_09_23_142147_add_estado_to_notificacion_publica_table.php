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
        Schema::table('notificacion_publica', function (Blueprint $table) {
            $table->unsignedTinyInteger('estado')->default(1)->after('imagen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificacion_publica', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
