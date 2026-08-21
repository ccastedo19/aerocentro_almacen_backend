<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class Mecanico extends Model
{
    use HasFactory, HasUuids;

    public const ESTADO_ELIMINADO = 0;

    public const ESTADO_ACTIVO = 1;

    public const ESTADO_FUERA_DE_SERVICIO = 2;

    protected $table = 'mecanicos';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nombre',
        'apellido',
        'nro_licencia',
        'cargo',
        'telefono',
        'imagen',
        'estado',
        'usuario_id',
    ];

    protected $hidden = [
        'nro_licencia_unico',
    ];

    protected $appends = [
        'nombre_completo',
    ];

    protected $casts = [
        'estado' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class, 'mecanico_id');
    }

    public function detallesPrestamos(): HasManyThrough
    {
        return $this->hasManyThrough(
            DetallePrestamo::class,
            Prestamo::class,
            'mecanico_id',
            'prestamo_id',
        );
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido}");
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estaFueraDeServicio(): bool
    {
        return $this->estado === self::ESTADO_FUERA_DE_SERVICIO;
    }

    public function tienePrestamosEnCurso(): bool
    {
        return DB::table('prestamos')
            ->join('detalles_prestamos', 'detalles_prestamos.prestamo_id', '=', 'prestamos.id')
            ->where('prestamos.mecanico_id', $this->id)
            ->where('detalles_prestamos.estado', DetallePrestamo::ESTADO_EN_CURSO)
            ->exists();
    }
}
