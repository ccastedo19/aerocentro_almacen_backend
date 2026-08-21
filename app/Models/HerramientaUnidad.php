<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HerramientaUnidad extends Model
{
    use HasFactory, HasUuids;

    public const ESTADO_ELIMINADA = 0;

    public const ESTADO_DISPONIBLE = 1;

    public const ESTADO_PRESTADA = 2;

    protected $table = 'herramientas_unidades';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'herramienta_id',
        'marca_id',
        'ubicacion_id',
        'fecha_calibracion',
        'proxima_calibracion',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'estado' => 'integer',
        'fecha_calibracion' => 'date',
        'proxima_calibracion' => 'date',
    ];

    public function herramienta(): BelongsTo
    {
        return $this->belongsTo(Herramienta::class, 'herramienta_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function detallesPrestamos(): HasMany
    {
        return $this->hasMany(DetallePrestamo::class, 'herramienta_unidad_id');
    }

    public function estaDisponible(): bool
    {
        return $this->estado === self::ESTADO_DISPONIBLE;
    }

    public function estaPrestada(): bool
    {
        return $this->estado === self::ESTADO_PRESTADA;
    }
}
