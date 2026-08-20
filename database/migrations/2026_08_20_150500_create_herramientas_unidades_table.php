<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('herramientas_unidades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('herramienta_id')
                ->constrained('herramientas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('marca_id')
                ->constrained('marcas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('ubicacion_id')
                ->constrained('ubicaciones')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->date('fecha_calibracion')->nullable();
            $table->date('proxima_calibracion')->nullable();
            $table->unsignedTinyInteger('estado')->default(1)
                ->comment('0: eliminada, 1: disponible, 2: prestada');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['herramienta_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('herramientas_unidades');
    }
};
