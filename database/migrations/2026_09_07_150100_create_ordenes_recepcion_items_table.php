<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_recepcion_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_id')
                ->constrained('ordenes_recepcion')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('numero_item');
            $table->string('part_number', 100)->nullable();
            $table->string('componente', 200);
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->string('serie', 100)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_recepcion_items');
    }
};
