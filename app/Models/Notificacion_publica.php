<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacion_publica extends Model
{
    use HasFactory, HasUuids;

    public const ESTADO_OCULTO = 0;

    public const ESTADO_MOSTRAR = 1;

    protected $table = 'notificacion_publica';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'titulo',
        'mensaje',
        'imagen',
        'estado',
    ];

    protected $casts = [
        'estado' => 'integer',
    ];

    public function estaVisible(): bool
    {
        return $this->estado === self::ESTADO_MOSTRAR;
    }
}
