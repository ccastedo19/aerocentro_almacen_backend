<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public const ESTADO_ELIMINADO = 0;

    public const ESTADO_ACTIVO = 1;

    public const ESTADO_INACTIVO = 2;

    protected $table = 'usuarios';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nombre',
        'apellido',
        'nombre_usuario',
        'email',
        'password',
        'estado',
        'rol_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'nombre_usuario_unico',
        'email_unico',
    ];

    protected $casts = [
        'password' => 'hashed',
        'estado' => 'integer',
    ];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function mecanicos(): HasMany
    {
        return $this->hasMany(Mecanico::class, 'usuario_id');
    }

    public function herramientas(): HasMany
    {
        return $this->hasMany(Herramienta::class, 'usuario_id');
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class, 'usuario_id');
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class, 'usuario_id');
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }
}
