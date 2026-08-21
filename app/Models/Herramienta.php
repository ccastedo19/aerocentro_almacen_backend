<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Herramienta extends Model
{
    use HasFactory, HasUuids;

    public const ESTADO_ELIMINADO = 0;

    public const ESTADO_ACTIVO = 1;

    public const ESTADO_INACTIVO = 2;

    protected $table = 'herramientas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'categoria_id',
        'usuario_id',
    ];

    protected $hidden = [
        'nombre_unico',
    ];

    protected $casts = [
        'estado' => 'integer',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(HerramientaUnidad::class, 'herramienta_id');
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function tieneUnidadesNoEliminadas(): bool
    {
        return $this->unidades()
            ->where('estado', '<>', HerramientaUnidad::ESTADO_ELIMINADA)
            ->exists();
    }

    public function tieneUnidadesPrestadas(): bool
    {
        return $this->unidades()
            ->where('estado', HerramientaUnidad::ESTADO_PRESTADA)
            ->exists();
    }
}
