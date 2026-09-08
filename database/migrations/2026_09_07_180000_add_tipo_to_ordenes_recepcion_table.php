<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_recepcion', function (Blueprint $table) {
            $table->string('tipo', 30)->default('motor')->after('numero_orden')
                ->comment('motor | ndt (extensible para futuros tipos)');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_recepcion', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
