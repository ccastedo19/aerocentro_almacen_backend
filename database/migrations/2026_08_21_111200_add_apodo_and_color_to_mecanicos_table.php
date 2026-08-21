<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mecanicos', function (Blueprint $table) {
            $table->string('apodo', 100)->nullable()->after('apellido');
            $table->string('color', 20)->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('mecanicos', function (Blueprint $table) {
            $table->dropColumn(['apodo', 'color']);
        });
    }
};
