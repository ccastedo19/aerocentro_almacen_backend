<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    private const DISK = 'local';

    private const CARPETA = 'backups';

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
        $absoluta = $this->rutaAbsoluta($ruta);

        Storage::disk(self::DISK)->makeDirectory(self::CARPETA);
        $this->escribirDump($absoluta);

        $backup->fill([
            'usuario_id' => $usuario->id,
            'fecha' => now(),
            'nombre_archivo' => $nombre,
            'ruta_archivo' => $ruta,
            'tamano' => filesize($absoluta) ?: 0,
            'hash_sha256' => hash_file('sha256', $absoluta) ?: null,
            'estado' => Backup::ESTADO_ACTIVO,
        ]);
        $backup->save();

        return $backup;
    }

    public function guardarArchivo(User $usuario, UploadedFile $archivo): Backup
    {
        $this->validarArchivoSql($archivo);

        $backup = new Backup;
        $backup->id = (string) Str::uuid();

        $ruta = self::CARPETA.'/'.$backup->id.'.sql';
        $nombre = $this->nombreSeguro($archivo->getClientOriginalName());

        $archivo->storeAs(self::CARPETA, $backup->id.'.sql', self::DISK);

        $absoluta = $this->rutaAbsoluta($ruta);

        if (! is_file($absoluta)) {
            throw new RuntimeException('No se pudo guardar el archivo de backup.');
        }

        $this->validarContenidoSql((string) file_get_contents($absoluta));

        $backup->fill([
            'usuario_id' => $usuario->id,
            'fecha' => now(),
            'nombre_archivo' => $nombre,
            'ruta_archivo' => $ruta,
            'tamano' => filesize($absoluta) ?: 0,
            'hash_sha256' => hash_file('sha256', $absoluta) ?: null,
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

        $absoluta = $this->rutaAbsoluta($backup->ruta_archivo);

        if (! is_file($absoluta)) {
            throw new RuntimeException('No se encontro el archivo del backup.');
        }

        $sql = (string) file_get_contents($absoluta);
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

    public function rutaAbsoluta(string $ruta): string
    {
        return Storage::disk(self::DISK)->path($ruta);
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

    private function validarArchivoSql(UploadedFile $archivo): void
    {
        $extension = strtolower((string) $archivo->getClientOriginalExtension());

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
