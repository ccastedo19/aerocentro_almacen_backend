<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ubicacion extends Model
{
    use HasFactory, HasUuids;

    public const ESTADO_ELIMINADO = 0;

    public const ESTADO_ACTIVO = 1;

    protected $table = 'ubicaciones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    protected $hidden = [
        'nombre_unico',
    ];

    protected $casts = [
        'estado' => 'integer',
    ];

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function tieneUnidadesActivas(): bool
    {
        return DB::table('herramientas_unidades')
            ->where('ubicacion_id', $this->id)
            ->where('estado', '<>', self::ESTADO_ELIMINADO)
            ->exists();
    }
}
