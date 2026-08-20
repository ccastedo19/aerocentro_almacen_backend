<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mecanico_id')
                ->constrained('mecanicos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('fecha_prestamo')->useCurrent();
            $table->timestamp('fecha_limite')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['mecanico_id', 'fecha_prestamo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};
