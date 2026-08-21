<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePrestamo extends Model
{
    use HasFactory, HasUuids;

    public const ESTADO_DEVUELTO = 0;

    public const ESTADO_EN_CURSO = 1;

    protected $table = 'detalles_prestamos';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'prestamo_id',
        'herramienta_unidad_id',
        'fecha_devolucion',
        'estado',
        'observaciones_devolucion',
    ];

    protected $casts = [
        'estado' => 'integer',
        'fecha_devolucion' => 'datetime',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(HerramientaUnidad::class, 'herramienta_unidad_id');
    }

    public function estaEnCurso(): bool
    {
        return $this->estado === self::ESTADO_EN_CURSO;
    }
}
