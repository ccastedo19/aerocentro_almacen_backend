<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenRecepcion extends Model
{
    use HasUuids;

    protected $table = 'ordenes_recepcion';

    const ESTADO_ELIMINADO  = 0;
    const ESTADO_BORRADOR   = 1;
    const ESTADO_FINALIZADO = 2;

    const MARCAS = ['lycoming', 'continental'];

    const POSICIONES_MOTOR = ['izquierdo', 'derecho'];

    protected $fillable = [
        'numero_orden',
        'marca',
        'modelo',
        'serie',
        'matricula',
        'bimotor',
        'motor_posicion',
        'cliente_id',
        'estado',
        'usuario_id',
    ];

    protected $casts = [
        'bimotor' => 'boolean',
        'estado'  => 'integer',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrdenRecepcionItem::class, 'orden_id')->orderBy('numero_item');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function esFinalizado(): bool
    {
        return $this->estado === self::ESTADO_FINALIZADO;
    }
}
