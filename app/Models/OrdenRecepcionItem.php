<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenRecepcionItem extends Model
{
    use HasUuids;

    protected $table = 'ordenes_recepcion_items';

    protected $fillable = [
        'orden_id',
        'numero_item',
        'part_number',
        'componente',
        'cantidad',
        'serie',
        'observacion',
    ];

    protected $casts = [
        'numero_item' => 'integer',
        'cantidad'    => 'integer',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenRecepcion::class, 'orden_id');
    }
}
