<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('herramientas_combinadas_unidades', function (Blueprint $table) {
            $table->foreignUuid('combinada_id')
                ->constrained('herramientas_combinadas')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('herramienta_unidad_id')
                ->constrained('herramientas_unidades')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->primary(['combinada_id', 'herramienta_unidad_id']);
            $table->index('herramienta_unidad_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('herramientas_combinadas_unidades');
    }
};
