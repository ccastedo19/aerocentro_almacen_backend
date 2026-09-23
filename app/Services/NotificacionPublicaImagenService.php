<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class NotificacionPublicaImagenService
{
    private const CARPETA = 'Notificacion publica Aerocentro';

    public function __construct(private readonly Cloudinary $cloudinary)
    {
    }

    public function subir(UploadedFile $imagen, string $notificacionId): string
    {
        $rutaTemporal = $imagen->getRealPath();

        if ($rutaTemporal === false) {
            throw new RuntimeException('No se pudo leer la imagen temporal de la notificacion.');
        }

        $resultado = $this->cloudinary->uploadApi()->upload($rutaTemporal, [
            'folder' => self::CARPETA,
            'public_id' => $notificacionId,
            'overwrite' => true,
            'invalidate' => true,
            'resource_type' => 'image',
        ]);

        return $resultado['secure_url'];
    }

    public function eliminar(string $notificacionId): void
    {
        $this->cloudinary->uploadApi()->destroy($this->publicId($notificacionId), [
            'invalidate' => true,
            'resource_type' => 'image',
        ]);
    }

    private function publicId(string $notificacionId): string
    {
        return self::CARPETA.'/'.$notificacionId;
    }
}
