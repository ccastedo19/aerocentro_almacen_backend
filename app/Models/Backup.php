<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    use HasUuids;

    public const ESTADO_ELIMINADO = 0;

    public const ESTADO_ACTIVO = 1;

    protected $table = 'backups';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'usuario_id',
        'fecha',
        'nombre_archivo',
        'ruta_archivo',
        'tamano',
        'hash_sha256',
        'estado',
    ];

    protected $hidden = [
        'ruta_archivo',
        'hash_sha256',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'tamano' => 'integer',
        'estado' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }
}
