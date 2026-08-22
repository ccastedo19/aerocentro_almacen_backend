<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class MecanicoImagenService
{
    private const CARPETA = 'Mecanicos Aerocentro';

    public function __construct(private readonly Cloudinary $cloudinary)
    {
    }

    public function subir(UploadedFile $imagen, string $mecanicoId): string
    {
        $rutaTemporal = $imagen->getRealPath();

        if ($rutaTemporal === false) {
            throw new RuntimeException('No se pudo leer la imagen temporal del mecanico.');
        }

        $resultado = $this->cloudinary->uploadApi()->upload($rutaTemporal, [
            'folder' => self::CARPETA,
            'public_id' => $mecanicoId,
            'overwrite' => true,
            'invalidate' => true,
            'resource_type' => 'image',
        ]);

        return $resultado['secure_url'];
    }

    public function eliminar(string $mecanicoId): void
    {
        $this->cloudinary->uploadApi()->destroy($this->publicId($mecanicoId), [
            'invalidate' => true,
            'resource_type' => 'image',
        ]);
    }

    private function publicId(string $mecanicoId): string
    {
        return self::CARPETA.'/'.$mecanicoId;
    }
}
