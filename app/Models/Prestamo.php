<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prestamo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'prestamos';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'mecanico_id',
        'usuario_id',
        'fecha_prestamo',
        'fecha_limite',
        'observaciones',
    ];

    protected $casts = [
        'fecha_prestamo' => 'datetime',
        'fecha_limite' => 'datetime',
    ];

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(Mecanico::class, 'mecanico_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePrestamo::class, 'prestamo_id');
    }

    public function detallesEnCurso(): HasMany
    {
        return $this->detalles()->where('estado', DetallePrestamo::ESTADO_EN_CURSO);
    }
}
