# Puesta en marcha

Orden: entorno, Laravel, paquetes, harness, configuración. Plan detallado:
`docs/planes/completados/2026-10-etapa-0-base.md`.

## 0. Entorno (Windows)

- **WSL2 con Ubuntu** (recomendado) o Docker Desktop: el código debe correr en Linux, como en producción.
- PHP 8.5 (el mismo en desarrollo, CI e imagen de producción) con extensiones `pgsql`, `pdo_pgsql`, `bcmath`, `intl`, `zip`; Composer; Node (para Vite);
  Docker para PostgreSQL 16; Claude Code.
- El repositorio clonado **dentro del sistema de archivos de WSL**, no en `C:\`.

## 1. Laravel en el repositorio

**Ya hecho en la etapa 0.** Para un clon nuevo basta con:

```bash
docker compose up -d              # PostgreSQL 16 con las bases techquk y techquk_test
composer install                  # instala exactamente lo de composer.lock
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # datos maestros del núcleo; en local, además, tu usuario de desarrollo (ver abajo)
php artisan serve                 # http://localhost:8000
```

**Usuario de desarrollo.** Con `APP_ENV=local`, el seeder crea un usuario con todos los permisos y clave `password`,
y la persona que lo respalda, con DNI inventado `00000000`. Nombre y correo salen de tu `.env`:

```
DEV_USER_NOMBRES=Ana
DEV_USER_APELLIDOS=Pérez Soto
DEV_USER_EMAIL=ana@techquk.test
```

Sin esas líneas usa `dev@techquk.test` («de Desarrollo Usuario»). Se leen por `config/nucleo.php`
(`usuario_desarrollo`). Volver a sembrar no cambia una cuenta que ya existe: para cambiar el nombre o el correo,
reconstruye la base de desarrollo (`php artisan migrate:fresh --seed`). En otro ambiente que no sea `local`, el
seeder no crea este usuario.

Cómo se hizo: `composer create-project` exige una carpeta vacía; se creó en una temporal y se movió a la
raíz sin pisar los archivos del harness. Versiones: rangos `^` en `composer.json` y versiones exactas en
`composer.lock`, todas publicadas hace más de dos semanas al fijarlas (`composer update --with=paquete:versión`).
La lista y las fechas están en la bitácora de `docs/planes/completados/2026-10-etapa-0-base.md`.

## 2. Paquetes

```bash
composer require filament/filament spatie/laravel-permission spatie/laravel-backup
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan
php artisan filament:install --panels
```

Los assets de Filament (`public/{css,js,fonts}/filament`) no se versionan: los publica
`filament:upgrade` en cada `composer install`.

Laravel Pulse se agrega al construir la página de salud (etapa 1).

## 3. Copiar el harness

Descomprimir en la raíz (no sobrescribir `README.md`). Agregar al `.gitignore`:

```
.claude/settings.local.json
CLAUDE.local.md
storage/phpstan
```

## 4. `composer.json`: el comando `check`

```json
"check": [
    "@php vendor/bin/pint --test",
    "@php vendor/bin/phpstan analyse --memory-limit=1G",
    "@php scripts/verificar-migraciones.php",
    "@php artisan test"
]
```

## 5. Pruebas contra PostgreSQL

En `phpunit.xml`, suites `Arch` (`tests/Arch`) y `Modules` (`tests/Modules`); en `<php>`:
`DB_CONNECTION=pgsql` y `DB_DATABASE=techquk_test`.

## 6. Configuración base (`.env.example`)

```
APP_TIMEZONE=America/Lima
APP_LOCALE=es
DB_CONNECTION=pgsql
LOG_CHANNEL=daily
LOG_DAILY_DAYS=30            # a confirmar con TI
QUEUE_CONNECTION=database
```

La IA tiene su propia cola (plan 1.11c): `php artisan dev` atiende solo la de siempre. Para probar una sugerencia de IA
en local, en otra terminal: `php artisan queue:work ia --queue=ia --timeout=660`.

## 7. Núcleo mínimo: macro de auditoría y usuario de sistema

```php
use Illuminate\Database\Schema\Blueprint;

Blueprint::macro('auditoria', function () {
    /** @var Blueprint $this */
    $this->foreignId('created_by')->index()->constrained('users');
    $this->timestampTz('created_at')->useCurrent();
    $this->foreignId('updated_by')->nullable()->index()->constrained('users');
    $this->timestampTz('updated_at')->nullable();
});
```

La primera migración del núcleo activa `unaccent` y crea el usuario de sistema que usan seeders y jobs.

## 8. Verificar y publicar

```bash
composer check
git add . && git commit -m "Base: Laravel, Filament, PostgreSQL y harness"
git push -u origin <rama>
```
