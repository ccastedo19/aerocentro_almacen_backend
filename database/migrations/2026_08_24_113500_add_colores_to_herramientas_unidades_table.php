<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('herramientas_unidades', function (Blueprint $table) {
            $table->string('color_primario', 20)->nullable()->after('ubicacion_id');
            $table->string('color_secundario', 20)->nullable()->after('color_primario');
        });
    }

    public function down(): void
    {
        Schema::table('herramientas_unidades', function (Blueprint $table) {
            $table->dropColumn(['color_primario', 'color_secundario']);
        });
    }
};
