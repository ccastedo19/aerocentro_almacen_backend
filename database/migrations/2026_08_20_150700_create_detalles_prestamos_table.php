<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_prestamos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prestamo_id')
                ->constrained('prestamos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('herramienta_unidad_id')
                ->constrained('herramientas_unidades')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('fecha_devolucion')->nullable();
            $table->unsignedTinyInteger('estado')->default(1)
                ->comment('0: devuelto, 1: prestamo en curso');
            $table->text('observaciones_devolucion')->nullable();
            $table->timestamps();

            $table->unique(['prestamo_id', 'herramienta_unidad_id']);
            $table->index(['herramienta_unidad_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_prestamos');
    }
};
