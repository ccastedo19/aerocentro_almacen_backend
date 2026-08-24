<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('herramientas_unidades', function (Blueprint $table) {
            $table->string('tamano', 50)->nullable()->after('color_secundario');
        });
    }

    public function down(): void
    {
        Schema::table('herramientas_unidades', function (Blueprint $table) {
            $table->dropColumn('tamano');
        });
    }
};
