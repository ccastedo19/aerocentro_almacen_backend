# SCD — Documento de Contexto del Software (Backend)

> **SCD** (Software Context Document): Referencia técnica viva del proyecto backend para desarrolladores y agentes de IA.

---

## Resumen del Proyecto

El backend de **Aerocentro Almacén** es un servicio web API RESTful desarrollado sobre Laravel 10 para la gestión integral del almacén de una empresa dedicada al mantenimiento y reparación de avionetas. Controla el catálogo de herramientas de aviación, unidades físicas codificadas y serializadas, préstamos a mecánicos aeronáuticos, ubicaciones físicas en estantes/hangar, categorías jerárquicas y respaldos de base de datos.

### Objetivo
Proveer una API robusta, segura y rápida que gestione la trazabilidad completa del inventario y los préstamos activos/devueltos de herramientas e insumos.

### Estado Actual
- API REST con autenticación basada en tokens mediante **Laravel Sanctum**.
- Control de acceso por roles (Administrador y Almacenista) y middleware de estado de usuario activo (`usuario.activo`).
- Módulos completos: Autenticación, Usuarios, Mecánicos, Categorías (Árbol), Ubicaciones (Árbol), Marcas, Herramientas base, Unidades físicas serializadas, Herramientas combinadas (Kits), Préstamos y Devoluciones, Historial de movimientos y Respaldos (Backups SQL).
- Integración con **Cloudinary** para almacenamiento de imágenes de herramientas y mecánicos.

---

## Metadatos

- **Proyecto**: `aerocentro_almacen_backend`
- **Framework**: Laravel `10.10+`
- **Lenguaje**: PHP `^8.1`
- **Base de Datos**: MySQL (XAMPP local / Servidor remoto)
- **Última Actualización**: 2026-09-01

---

## Stack Tecnológico

### Dependencias Principales (`composer.json`)
- **php**: `^8.1`
- **laravel/framework**: `^10.10`
- **laravel/sanctum**: `^3.3` (Autenticación de API por token)
- **cloudinary/cloudinary_php**: `~3.1` (Gestión de almacenamiento multimedia)
- **guzzlehttp/guzzle**: `^7.2` (Cliente HTTP)
- **laravel/tinker**: `^2.8`

### Entorno y Ejecución Local
- **Servidor Web / DB**: XAMPP (Apache + MySQL)
- **Comando de inicio local**: `php artisan serve` (por defecto en `http://127.0.0.1:8000`)
- **Migraciones y Seeders**: `php artisan migrate` / `php artisan db:seed`

---

## Arquitectura de Directorios

```
aerocentro_almacen_backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Controladores API REST
│   │   │   ├── AuthController.php
│   │   │   ├── BackupController.php
│   │   │   ├── CategoriaController.php
│   │   │   ├── HerramientaController.php
│   │   │   ├── HerramientaUnidadController.php
│   │   │   ├── HerramientaCombinadaController.php
│   │   │   ├── HistorialMovimientoController.php
│   │   │   ├── InicioController.php
│   │   │   ├── MarcaController.php
│   │   │   ├── MecanicoController.php
│   │   │   ├── PrestamoController.php
│   │   │   ├── RolController.php
│   │   │   ├── UbicacionController.php
│   │   │   └── UsuarioController.php
│   │   └── Middleware/           # Middleware personalizado (CheckUsuarioActivo, etc.)
│   ├── Models/                   # 12 Modelos Eloquent de dominio
│   │   ├── Backup.php
│   │   ├── Categoria.php
│   │   ├── DetallePrestamo.php
│   │   ├── Herramienta.php
│   │   ├── HerramientaCombinada.php
│   │   ├── HerramientaUnidad.php
│   │   ├── Marca.php
│   │   ├── Mecanico.php
│   │   ├── Prestamo.php
│   │   ├── Rol.php
│   │   ├── Ubicacion.php
│   │   └── User.php
│   └── Services/                 # Servicios auxiliares (Cloudinary, etc.)
├── database/
│   ├── migrations/               # 18 migraciones de esquemas relacionales
│   ├── seeders/                  # Datos iniciales (Roles, Admin por defecto)
│   └── factories/
├── routes/
│   ├── api.php                   # Definición de todos los endpoints de la API REST
│   └── web.php
└── config/                       # Archivos de configuración (sanctum, database, filesystems)
```

---

## Modelo de Datos (Base de Datos MySQL)

| Tabla | Modelo Eloquent | Descripción |
|-------|-----------------|-------------|
| `roles` | `Rol` | Roles del sistema (`Administrador`, `Almacenista`) |
| `usuarios` | `User` | Credenciales, token Sanctum, referencia a rol y estado |
| `categorias` | `Categoria` | Categorías de herramientas con estructura jerárquica (`parent_id`) |
| `ubicaciones` | `Ubicacion` | Ubicaciones físicas en almacén/estantes (`parent_id`) |
| `marcas` | `Marca` | Fabricantes/marcas de las herramientas |
| `mecanicos` | `Mecanico` | Personal técnico de aviación (cédula, apodo, color, foto) |
| `herramientas` | `Herramienta` | Catálogo de herramientas base (categoría, marca, ubicación) |
| `herramientas_unidades` | `HerramientaUnidad` | Unidades físicas con código único, serial, estado, tamaño, color |
| `herramientas_combinadas` | `HerramientaCombinada` | Kits o juegos de herramientas compuestas |
| `herramientas_combinadas_unidades` | — | Relación pivote entre kits y unidades individuales |
| `prestamos` | `Prestamo` | Cabecera del préstamo asignado a un mecánico en una fecha |
| `detalles_prestamos` | `DetallePrestamo` | Detalle individual por herramienta prestada y fecha de devolución |
| `backups` | `Backup` | Registro y metadatos de respaldos SQL generados |

---

## Endpoints Principales de la API (`routes/api.php`)

### Autenticación (`/api`)
- `POST /login` - Autenticación de usuario (`throttle:5,1`)
- `POST /logout` - Cierre de sesión y revocación del token
- `GET /me` - Obtener datos del usuario autenticado

### Inicio & Métricas
- `GET /inicio` - Resumen del dashboard (herramientas prestadas, disponibles, insumos críticos)

### Catálogos y Entidades Maestras
- `apiResource('categorias', CategoriaController::class)` (+ `PATCH /categorias/{id}/estado`)
- `apiResource('ubicaciones', UbicacionController::class)` (+ `PATCH /ubicaciones/{id}/estado`)
- `apiResource('marcas', MarcaController::class)` (+ `PATCH /marcas/{id}/estado`)
- `apiResource('mecanicos', MecanicoController::class)` (+ `PATCH /mecanicos/{id}/estado`)

### Herramientas e Inventario
- `apiResource('herramientas', HerramientaController::class)` (+ `PATCH /herramientas/{id}/estado`)
- `apiResource('herramientas-unidades', HerramientaUnidadController::class)`
- `apiResource('herramientas-combinadas', HerramientaCombinadaController::class)` (+ `PATCH /herramientas-combinadas/{id}/estado`)

### Préstamos y Punto de Atención
- `GET /prestamos/punto` - Vista consolidada para punto de atención
- `GET /prestamos/unidades-disponibles` - Unidades físicas listas para préstamo
- `GET /prestamos/en-uso` - Unidades actualmente prestadas a mecánicos
- `GET /prestamos/mecanicos/{mecanico}` - Préstamos activos de un mecánico
- `POST /prestamos` - Registrar nuevo préstamo
- `POST /api/prestamos/detalles/{detalle_prestamo}/devolver` — Registrar devolución de una unidad.
- `POST /api/prestamos/mecanicos/{mecanico}/devolver-todas` — Devolver todas las herramientas de un mecánico.
- `POST /api/prestamos/devolver-todas-absoluto` — Devolver absolutamente todas las herramientas prestadas del almacén.

### Historial de Movimientos
- `GET /prestamos/historial` - Filtros y reporte general de historial
- `GET /prestamos/historial/mecanicos` & `GET /prestamos/historial/mecanicos/{mecanico}`

### Administración (Requiere middleware `can:administrar-usuarios`)
- `GET /roles`
- `apiResource('usuarios', UsuarioController::class)` (+ `PATCH /usuarios/{id}/estado`)
- `GET/POST/DELETE /backups` - Gestión y restauración de copias de seguridad de la base de datos

---

## Convenciones y Guía de Desarrollo

1. **Respuestas API**: Mantener formato JSON estructurado `{ success: boolean, data: ..., message: string }`.
2. **Autenticación**: Todos los endpoints protegidos llevan la cabecera `Authorization: Bearer <token>`.
3. **Middleware**:
   - `usuario.activo`: Bloquea peticiones si la cuenta fue desactivada por un administrador.
   - `can:administrar-usuarios`: Restringe módulos delicados (Usuarios y Backups).
4. **Almacenamiento de Imágenes**: Utilizar el servicio integrado de Cloudinary mediante el helper/controller correspondiente para optimizar cargas.
