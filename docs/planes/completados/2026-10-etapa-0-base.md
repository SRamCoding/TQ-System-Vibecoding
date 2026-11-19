# Plan: Etapa 0 · Base del proyecto

- **Estado:** terminado
- **Módulo(s):** todos (estructura)
- **Etapa:** 0
- **Creado:** 2026-10 · **Actualizado:** 2026-10-09

## Objetivo

El proyecto Laravel corre en desarrollo con PostgreSQL, el harness está activo, `composer check`
pasa en verde (local y CI) y la aplicación abre con login en sus dos modos de navegación, todavía sin
módulos de negocio.

## Contexto que leí

- `docs/setup.md` (pasos de instalación)
- `ARCHITECTURE.md` (estructura de módulos, interfaz de dos modos)
- `docs/convenciones/*.md`
- ADR 0002, 0006, 0009, 0010

## Pasos

- [x] 1. **(Humano)** Repositorio en privado. Entorno listo: WSL2 o Docker, PHP 8.5, Composer, Node,
      Docker Desktop, Claude Code.
- [x] 2. Confirmar versiones estables actuales de Laravel, Filament, Pest y Larastan (publicadas hace
      más de dos semanas). Anotarlas en la bitácora.
- [x] 3. Crear el proyecto Laravel y mover el contenido a la raíz del repositorio (`docs/setup.md` §1).
- [x] 4. `docker-compose.yml` de desarrollo con PostgreSQL 16 (y la app, si se trabaja dentro de Docker).
- [x] 5. Instalar paquetes (§2) y copiar la configuración del harness (§3–§5): `composer check`,
      PostgreSQL en pruebas, suites `Arch` y `Modules`.
- [x] 6. Configuración base: `APP_TIMEZONE=America/Lima`, `APP_LOCALE=es`, `LOG_CHANNEL=daily`,
      `QUEUE_CONNECTION=database`, `.env.example` completo y sin secretos.
- [x] 7. Módulo `Nucleo` mínimo: provider registrado, extensión `unaccent`, macro `auditoria()`,
      usuario de sistema. Migraciones de framework (`users`, `jobs`, `failed_jobs`) verificadas.
- [x] 8. Panel de Filament con login. Navegación de los dos modos: **paneles** (barra lateral) y
      **modular** (página de inicio con cajas), con preferencia guardada por usuario. Sin módulos todavía.
- [x] 9. CI en verde en GitHub.
- [x] 10. `/cerrar-tarea` y revisión de `revisor-arquitectura`.

## Criterio de aceptación

- `composer check` en verde en local y en CI.
- La app abre en el navegador, permite iniciar sesión y alternar entre modo modular y modo paneles.
- `tests/Arch` y `scripts/verificar-migraciones.php` corren sin errores sobre el proyecto vacío.

## Bitácora

- 2026-10 — Plan creado con el harness.
- 2026-10-09 — Paso 1 hecho por el desarrollador: WSL2 Ubuntu 26.04, PHP 8.5.4, Composer 2.9.5, Node 24,
  Docker 29.6 (Compose v5.3). Repositorio privado.
- 2026-10-09 — **Paso 2. Versiones** (Packagist; corte «más de dos semanas» = 2026-09-25). `composer.json`
  con rangos `^`; versiones exactas en `composer.lock`:

  | Paquete | Rango | Lock | Publicada |
  |---|---|---|---|
  | PHP | `^8.5` | 8.5 en dev, CI y producción | — |
  | laravel/laravel (esqueleto) | — | v13.10.1 | 2026-08-25 |
  | laravel/framework | `^13.33` | v13.33.0 | 2026-09-22 |
  | filament/filament | `^5.8` | v5.8.4 | 2026-09-20 |
  | livewire/livewire (transitiva) | — | v4.4.6 | 2026-09-21 |
  | pestphp/pest | `^5.2` | v5.2.1 | 2026-09-17 |
  | pestphp/pest-plugin-laravel | `^5.0` | v5.0.1 | 2026-07-29 |
  | larastan/larastan | `^3.12` | v3.12.2 | 2026-09-19 |
  | phpstan/phpstan (transitiva) | — | 2.2.15 | 2026-09-23 |
  | spatie/laravel-permission | `^8.3` | 8.3.0 | 2026-07-03 |
  | spatie/laravel-backup | `^10.3` | 10.3.3 | 2026-09-14 |

  Por qué: Laravel 13 y Filament 5 son las mayores vigentes (12 solo recibe seguridad; empezar en
  Filament 4 obligaría a migrar pronto). Pest 5 exige PHP ≥ 8.4, de ahí PHP 8.5, que ya estaba instalado;
  se cambió «8.3+» en `setup.md`, en este plan y en CI. Quedan transitivas de parche con menos de dos
  semanas (Symfony 8.1.8, Carbon 3.14.2, Monolog 3.12.1, polyfills 1.43): se aceptan.
- 2026-10-09 — Pasos 3–6. Esqueleto copiado sin pisar `AGENTS.md`, `CLAUDE.md`, `README.md`, `.editorconfig`
  ni `.gitattributes` del harness (se les agregaron las líneas útiles del esqueleto). `docker-compose.yml`
  solo con PostgreSQL 16 (`techquk` y `techquk_test`). Pruebas contra PostgreSQL, suites `Arch` y `Modules`.
  `config/app.php` leía la zona fija `UTC`: ahora `env('APP_TIMEZONE', 'America/Lima')`; idioma `es`.
  `LOG_DAILY_DAYS=30` queda pendiente de TI. Pint agregó `declare(strict_types=1)` a los archivos del esqueleto
  y reformateó `tests/Arch/*` y `scripts/verificar-migraciones.php` (solo espacios).
- 2026-10-09 — Paso 7. `Nucleo` mínimo: provider, macro `auditoria()`, `unaccent`, usuario de sistema
  (índice único parcial: uno solo; no entra al panel). Migraciones del núcleo con fecha `2026_10_10_*`, después
  de las de `database/migrations`: verificado con `migrate:status` y con prueba
  (`tests/Modules/Nucleo/MigracionesTest.php`). **Desviación del DBML:** `users` sin `party_id` ni
  `unidad_organizativa_id` (sus tablas son de etapa 1) y sin `created_by`/`updated_by` (tabla de framework);
  se agregan en la etapa 1.
- 2026-10-09 — Paso 8. Panel `app` en `/`. Modo por defecto: paneles. El modo modular oculta la barra lateral
  y muestra las cajas de `RegistroModulos` (vacío por ahora). El cambio de modo está en el menú de usuario y
  llama a `PreferenciasUsuarioService`; su clase en `Acciones/` llega con el registro de acciones (etapa 1).
  `composer check` en verde: 22 pruebas.
- 2026-10-09 — Paso 10 (revisión de `revisor-arquitectura`). Atendido:
  - **Bloqueante:** `tests/Arch/ArquitecturaTest.php` revisaba `Actions` en vez de `Acciones` y no incluía
    `Efectos`. Corregido (error del harness, no del proyecto).
  - **Bloqueante:** `users` pasa a `timestamptz` (también `email_verified_at` y `password_reset_tokens.created_at`).
  - La conexión `pgsql` fija `timezone` = zona de la app (sin esto, CI y producción guardaban en UTC). Con prueba.
  - `phpunit.xml` fuerza `DB_CONNECTION`/`DB_DATABASE` de prueba y `composer check` limpia la configuración
    cacheada primero: las pruebas no pueden caer en la base de desarrollo.
  - La prueba de orden exige que el núcleo vaya después de `0001_01_01_*` (las de Laravel), no de todas las
    de `database/migrations`: un paquete publicado mañana no la rompe.
  - Prueba de que la base acepta cada caso de `ModoNavegacion` (enum y CHECK no se desincronizan).
  - Correo único sin importar mayúsculas (`lower(email)`), con prueba.
  - `CajaModulo::$permiso` sin valor por defecto: una caja visible para todos se declara con `null` explícito.
  - `LOG_DAILY_DAYS` por defecto 30 también en `config/logging.php`.
  `composer check` en verde: 26 pruebas.
- 2026-10-09 — Paso 9. Commit `bb9a1e1` en `etapa-0-base`; CI en verde en el PR a `main` (revisado por
  el desarrollador en GitHub). Nota: el workflow solo corre en push a `main` y en pull requests; un push a
  otra rama no lo dispara.
- 2026-10-09 — Criterio de aceptación cumplido: `composer check` verde en local y CI; login y cambio de
  modo probados (`tests/Feature/PanelTest.php`); en el servidor local, `/` redirige a `/login` y el login carga en español; `tests/Arch` y
  `verificar-migraciones.php` corren sin errores. Plan terminado.

## Qué sigue (deuda para la etapa 1)

- `users`: agregar `party_id`, `unidad_organizativa_id`, `ultimo_acceso`, `created_by`, `updated_by` del DBML.
  `created_by` choca con el usuario de sistema (él mismo es el primer usuario): resolverlo al migrarla.
- Decidir si `User` pasa a `App\Modules\Nucleo\Models` y cómo se impide que otro módulo lo escriba
  (la regla 1 permite leer modelos del núcleo, no escribirlos; hoy ninguna prueba de arquitectura lo verifica).
- Acción registrada `CambiarModoNavegacion` en `Nucleo/Acciones/` cuando exista el registro de acciones;
  `AppPanelProvider` la invoca en vez de llamar al servicio. Con prueba de rechazo (usuario de sistema).
- `scripts/verificar-migraciones.php` no revisa `database/migrations` (tablas de framework y paquetes).
