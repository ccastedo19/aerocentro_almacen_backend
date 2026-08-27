<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HerramientaCombinada extends Model
{
    use HasFactory, HasUuids;

    public const ESTADO_ELIMINADA = 0;

    public const ESTADO_ACTIVA = 1;

    public const ESTADO_INACTIVA = 2;

    protected $table = 'herramientas_combinadas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'usuario_id',
    ];

    protected $hidden = [
        'nombre_unico',
    ];

    protected $casts = [
        'estado' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(
            HerramientaUnidad::class,
            'herramientas_combinadas_unidades',
            'combinada_id',
            'herramienta_unidad_id',
        )->withTimestamps();
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }

    public function tieneUnidadesPrestadas(): bool
    {
        return $this->unidades()
            ->where('herramientas_unidades.estado', HerramientaUnidad::ESTADO_PRESTADA)
            ->exists();
    }
}
