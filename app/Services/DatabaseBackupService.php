<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    private const DISK = 'local';

    private const CARPETA = 'backups';

    private const CARPETA_CLOUDINARY = 'Backups Aerocentro';

    private const PREFIJO_CLOUDINARY = 'cloudinary:';

    private const URL_CLOUDINARY = 'https://res.cloudinary.com/';

    private const SEGMENTO_RAW = '/raw/upload/';

    private const MENSAJE_ARCHIVO_PERDIDO = 'El archivo de este backup ya no esta en el servidor porque se genero antes de guardar las copias en la nube. Genera uno nuevo o sube tu .sql.';

    /**
     * @var list<string>
     */
    private const TABLAS_EXCLUIDAS = [
        'backups',
        'personal_access_tokens',
        'failed_jobs',
        'jobs',
        'job_batches',
        'cache',
        'cache_locks',
        'sessions',
        'password_reset_tokens',
    ];

    public function crear(User $usuario): Backup
    {
        set_time_limit(180);
        $this->asegurarMysql();

        $backup = new Backup;
        $backup->id = (string) Str::uuid();

        $nombre = 'aerocentro-almacen-'.now()->format('Y-m-d-His').'.sql';
        $ruta = self::CARPETA.'/'.$backup->id.'.sql';

        Storage::disk(self::DISK)->makeDirectory(self::CARPETA);
        $absoluta = Storage::disk(self::DISK)->path($ruta);
        $this->escribirDump($absoluta);

        $rutaGuardada = $this->persistirArchivo($absoluta, $backup->id, $ruta);

        $backup->fill([
            'usuario_id' => $usuario->id,
            'fecha' => now(),
            'nombre_archivo' => $nombre,
            'ruta_archivo' => $rutaGuardada,
            'tamano' => filesize($absoluta) ?: 0,
            'hash_sha256' => hash_file('sha256', $absoluta) ?: null,
            'estado' => Backup::ESTADO_ACTIVO,
        ]);
        $backup->save();

        return $backup;
    }

    public function guardarContenido(User $usuario, string $sql, string $nombre): Backup
    {
        $this->validarNombreSql($nombre);
        $this->validarContenidoSql($sql);

        $backup = new Backup;
        $backup->id = (string) Str::uuid();

        $ruta = self::CARPETA.'/'.$backup->id.'.sql';

        Storage::disk(self::DISK)->makeDirectory(self::CARPETA);
        Storage::disk(self::DISK)->put($ruta, $sql);

        $absoluta = Storage::disk(self::DISK)->path($ruta);

        if (! is_file($absoluta)) {
            throw new RuntimeException('No se pudo guardar el archivo de backup.');
        }

        $rutaGuardada = $this->persistirArchivo($absoluta, $backup->id, $ruta);

        $backup->fill([
            'usuario_id' => $usuario->id,
            'fecha' => now(),
            'nombre_archivo' => $this->nombreSeguro($nombre),
            'ruta_archivo' => $rutaGuardada,
            'tamano' => strlen($sql),
            'hash_sha256' => hash('sha256', $sql),
            'estado' => Backup::ESTADO_ACTIVO,
        ]);
        $backup->save();

        return $backup;
    }

    public function restaurar(Backup $backup): void
    {
        set_time_limit(180);
        $this->asegurarMysql();

        if (! $backup->estaActivo()) {
            throw new RuntimeException('El backup no esta disponible.');
        }

        $sql = $this->contenido($backup);
        $this->validarContenidoSql($sql);

        $pdo = DB::connection()->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->separarSentencias($sql) as $sentencia) {
                $pdo->exec($sentencia);
            }
        } catch (Throwable $excepcion) {
            throw new RuntimeException(
                'No se pudo restaurar el backup. Revisa que el archivo SQL sea valido.',
                0,
                $excepcion,
            );
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function contenido(Backup $backup): string
    {
        $ruta = (string) $backup->ruta_archivo;

        if ($ruta === '') {
            throw new RuntimeException(self::MENSAJE_ARCHIVO_PERDIDO);
        }

        if ($this->esRutaCloudinary($ruta)) {
            return $this->descargarCloudinary($ruta);
        }

        $absoluta = Storage::disk(self::DISK)->path($ruta);

        if (! is_file($absoluta)) {
            throw new RuntimeException(self::MENSAJE_ARCHIVO_PERDIDO);
        }

        return (string) file_get_contents($absoluta);
    }

    public function estaDisponible(Backup $backup): bool
    {
        $ruta = (string) $backup->ruta_archivo;

        if ($ruta === '') {
            return false;
        }

        if ($this->esRutaCloudinary($ruta)) {
            return true;
        }

        return is_file(Storage::disk(self::DISK)->path($ruta));
    }

    public function eliminarArchivo(Backup $backup): void
    {
        $ruta = (string) $backup->ruta_archivo;

        if ($ruta === '') {
            return;
        }

        if (! $this->esRutaCloudinary($ruta)) {
            Storage::disk(self::DISK)->delete($ruta);

            return;
        }

        $publicId = $this->publicIdCloudinary($ruta);

        if ($publicId === null || ! $this->cloudinaryConfigurado()) {
            return;
        }

        try {
            $this->cloudinary()->uploadApi()->destroy($publicId, [
                'resource_type' => 'raw',
                'invalidate' => true,
            ]);
        } catch (Throwable) {
            // El registro se borra igual aunque el archivo remoto ya no exista.
        }
    }

    private function persistirArchivo(string $absoluta, string $backupId, string $rutaLocal): string
    {
        if (! $this->cloudinaryConfigurado()) {
            return $rutaLocal;
        }

        try {
            $resultado = $this->cloudinary()->uploadApi()->upload($absoluta, [
                'folder' => self::CARPETA_CLOUDINARY,
                'public_id' => $backupId,
                'resource_type' => 'raw',
                'overwrite' => true,
                'invalidate' => true,
            ]);
        } catch (Throwable $excepcion) {
            throw new RuntimeException(
                'No se pudo guardar el backup en Cloudinary.',
                0,
                $excepcion,
            );
        }

        $url = is_string($resultado['secure_url'] ?? null) ? $resultado['secure_url'] : '';

        if ($url !== '') {
            return $url;
        }

        $publicId = is_string($resultado['public_id'] ?? null)
            ? $resultado['public_id']
            : self::CARPETA_CLOUDINARY.'/'.$backupId;

        return self::PREFIJO_CLOUDINARY.$publicId;
    }

    private function descargarCloudinary(string $ruta): string
    {
        $url = str_starts_with($ruta, self::PREFIJO_CLOUDINARY)
            ? $this->urlRawCloudinary(substr($ruta, strlen(self::PREFIJO_CLOUDINARY)))
            : $ruta;

        try {
            $respuesta = Http::timeout(60)->get($url);
        } catch (Throwable $excepcion) {
            throw new RuntimeException(
                'No se pudo descargar el archivo del backup desde Cloudinary.',
                0,
                $excepcion,
            );
        }

        if (! $respuesta->successful()) {
            throw new RuntimeException(
                'No se pudo descargar el archivo del backup desde Cloudinary (HTTP '.$respuesta->status().').',
            );
        }

        return $respuesta->body();
    }

    private function urlRawCloudinary(string $publicId): string
    {
        $cloud = (string) config('services.cloudinary.cloud_name');
        $partes = array_map('rawurlencode', explode('/', $publicId));

        return self::URL_CLOUDINARY.$cloud.self::SEGMENTO_RAW.implode('/', $partes);
    }

    private function publicIdCloudinary(string $ruta): ?string
    {
        if (str_starts_with($ruta, self::PREFIJO_CLOUDINARY)) {
            return substr($ruta, strlen(self::PREFIJO_CLOUDINARY));
        }

        $posicion = strpos($ruta, self::SEGMENTO_RAW);

        if ($posicion === false) {
            return null;
        }

        $resto = substr($ruta, $posicion + strlen(self::SEGMENTO_RAW));
        $resto = preg_replace('#^v\d+/#', '', $resto) ?? $resto;

        return rawurldecode($resto);
    }

    private function esRutaCloudinary(string $ruta): bool
    {
        return str_starts_with($ruta, self::PREFIJO_CLOUDINARY)
            || str_starts_with($ruta, self::URL_CLOUDINARY);
    }

    private function cloudinaryConfigurado(): bool
    {
        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.api_key'))
            && filled(config('services.cloudinary.api_secret'));
    }

    private function cloudinary(): Cloudinary
    {
        return app(Cloudinary::class);
    }

    private function escribirDump(string $absoluta): void
    {
        $handle = fopen($absoluta, 'wb');

        if ($handle === false) {
            throw new RuntimeException('No se pudo crear el archivo SQL.');
        }

        try {
            $conexion = DB::connection();
            $pdo = $conexion->getPdo();
            $tablas = $this->tablasDumpables();

            fwrite($handle, "-- Aerocentro Almacen\n");
            fwrite($handle, '-- Fecha: '.now()->toDateTimeString()."\n");
            fwrite($handle, "SET NAMES utf8mb4;\n");
            fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($tablas as $tabla) {
                $this->escribirTabla($handle, $pdo, $tabla);
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function escribirTabla($handle, PDO $pdo, string $tabla): void
    {
        $identificador = $this->identificar($tabla);
        $creacion = $pdo->query('SHOW CREATE TABLE '.$identificador)->fetch(PDO::FETCH_ASSOC);

        if (! is_array($creacion) || ! isset($creacion['Create Table'])) {
            throw new RuntimeException("No se pudo leer la estructura de {$tabla}.");
        }

        fwrite($handle, "DROP TABLE IF EXISTS {$identificador};\n");
        fwrite($handle, $creacion['Create Table'].";\n\n");

        $columnas = array_map(
            fn (string $columna) => $this->identificar($columna),
            $this->columnas($tabla),
        );
        $listaColumnas = implode(', ', $columnas);
        $lote = [];

        $consulta = $pdo->query('SELECT * FROM '.$identificador);

        if ($consulta === false) {
            throw new RuntimeException("No se pudieron leer los datos de {$tabla}.");
        }

        while ($fila = $consulta->fetch(PDO::FETCH_ASSOC)) {
            $lote[] = $this->valoresInsert($pdo, $fila);

            if (count($lote) >= 100) {
                $this->escribirInsert($handle, $identificador, $listaColumnas, $lote);
                $lote = [];
            }
        }

        if ($lote !== []) {
            $this->escribirInsert($handle, $identificador, $listaColumnas, $lote);
        }

        fwrite($handle, "\n");
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $lote
     */
    private function escribirInsert($handle, string $tabla, string $columnas, array $lote): void
    {
        fwrite(
            $handle,
            "INSERT INTO {$tabla} ({$columnas}) VALUES\n".implode(",\n", $lote).";\n",
        );
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function valoresInsert(PDO $pdo, array $fila): string
    {
        $valores = [];

        foreach ($fila as $valor) {
            $valores[] = $this->valorSql($pdo, $valor);
        }

        return '('.implode(', ', $valores).')';
    }

    private function valorSql(PDO $pdo, mixed $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        return $pdo->quote((string) $valor);
    }

    /**
     * @return list<string>
     */
    private function tablasDumpables(): array
    {
        $filas = DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);
        $tablas = [];

        foreach ($filas as $fila) {
            $valores = array_values((array) $fila);
            $tabla = (string) ($valores[0] ?? '');

            if ($tabla === '' || in_array($tabla, self::TABLAS_EXCLUIDAS, true)) {
                continue;
            }

            $tablas[] = $tabla;
        }

        return $tablas;
    }

    /**
     * @return list<string>
     */
    private function columnas(string $tabla): array
    {
        return DB::connection()->getSchemaBuilder()->getColumnListing($tabla);
    }

    /**
     * @return list<string>
     */
    private function separarSentencias(string $sql): array
    {
        $sentencias = [];
        $buffer = '';
        $enCadena = false;
        $comilla = '';
        $largo = strlen($sql);

        for ($i = 0; $i < $largo; $i++) {
            $char = $sql[$i];

            if ($enCadena) {
                $buffer .= $char;

                if ($char === '\\' && $i + 1 < $largo) {
                    $i++;
                    $buffer .= $sql[$i];

                    continue;
                }

                if ($char === $comilla) {
                    if ($i + 1 < $largo && $sql[$i + 1] === $comilla) {
                        $i++;
                        $buffer .= $sql[$i];

                        continue;
                    }

                    $enCadena = false;
                }

                continue;
            }

            if ($char === '-' && $i + 1 < $largo && $sql[$i + 1] === '-') {
                while ($i < $largo && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $enCadena = true;
                $comilla = $char;
                $buffer .= $char;

                continue;
            }

            if ($char === ';') {
                $sentencia = trim($buffer);

                if ($sentencia !== '') {
                    $sentencias[] = $sentencia;
                }

                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        $sentencia = trim($buffer);

        if ($sentencia !== '') {
            $sentencias[] = $sentencia;
        }

        return $sentencias;
    }

    private function validarNombreSql(string $nombre): void
    {
        $extension = strtolower((string) pathinfo($nombre, PATHINFO_EXTENSION));

        if (! in_array($extension, ['sql', 'txt'], true)) {
            throw new RuntimeException('El archivo debe ser .sql.');
        }
    }

    private function validarContenidoSql(string $sql): void
    {
        if (trim($sql) === '') {
            throw new RuntimeException('El archivo SQL esta vacio.');
        }

        if (! preg_match('/\b(CREATE|INSERT|DROP|ALTER|UPDATE)\b/i', $sql)) {
            throw new RuntimeException('El archivo no parece un backup SQL valido.');
        }
    }

    private function nombreSeguro(string $nombre): string
    {
        $base = basename(str_replace('\\', '/', $nombre));
        $base = preg_replace('/[^A-Za-z0-9._-]/', '-', $base) ?: 'backup.sql';

        if (! str_ends_with(strtolower($base), '.sql') && ! str_ends_with(strtolower($base), '.txt')) {
            $base .= '.sql';
        }

        return $base;
    }

    private function identificar(string $nombre): string
    {
        return '`'.str_replace('`', '``', $nombre).'`';
    }

    private function asegurarMysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('El backup solo esta disponible para MySQL.');
        }
    }
}
