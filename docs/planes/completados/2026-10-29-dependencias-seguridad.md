# Plan: seguridad de dependencias (1.11b)

- **Estado:** terminado
- **Módulo(s):** — (dependencias)
- **Fase:** F1
- **Creado:** 2026-10-29 · **Actualizado:** 2026-10-29

## Objetivo

`composer audit` sin advertencias: Livewire y `symfony/mime` en versiones corregidas, sin cambiar nada más.

## Contexto que leí

- `composer audit` (2026-10-28, al agregar `smalot/pdfparser` en la 1.11) avisó dos fallas de gravedad media en paquetes
  que ya estaban:
  - `livewire/livewire` < 4.4.8: salto de autorización en `#[Authorize]` (claves de parámetros reordenadas y el orden de
    `#[Json]`). Instalada: 4.4.6.
  - `symfony/mime` 8.x < 8.1.10: CVE-2026-45070, inyección en cabeceras de correo por caracteres no permitidos en los
    nombres de parámetros. Instalada: 8.1.7.
- El desarrollador la pidió como tarea corta, siguiente tras la 1.11 y antes de cualquier despliegue.
- Versiones corregidas (Packagist, 2026-10-29): Livewire 4.4.8 (publicada el 2026-10-21) y `symfony/mime` 8.1.10
  (publicada el 2026-10-28). Son de parche: no cambian la interfaz pública.

## Decisiones

- **D1.** Solo estos dos paquetes (`composer update livewire/livewire symfony/mime`), sin subir el resto: así el cambio
  se revisa en una línea del `composer.lock` por paquete.
- **D2.** El proyecto no usa `#[Authorize]` ni `#[Json]` de Livewire (se revisa con `grep`): la falla no se podía
  explotar hoy, pero se corrige igual porque Filament y lo que venga sí podrían usarlos.

## Pasos

- [x] 1. Actualizar los dos paquetes; `composer audit` sin advertencias.
- [x] 2. `composer check` en verde; plan a completados; PR.

## Criterio de aceptación

- `composer audit` sin advertencias y `composer check` en verde.

## Bitácora

- 2026-10-29 — Plan escrito. Rama `dependencias-seguridad`, apilada sobre `nucleo-ia` (1.11, sin fusionar) para no
  sacar la app de su rama.
- 2026-10-29 — Paso 1. `composer update livewire/livewire symfony/mime`: Livewire 4.4.6 → 4.4.8 y `symfony/mime`
  8.1.7 → 8.1.10; nada más cambió en el `composer.lock`. `composer audit`: «No security vulnerability advisories
  found». El proyecto no usa `#[Authorize]` ni `#[Json]` (D2). No hay archivos de Livewire publicados en `public/` que
  regenerar (los sirve su propia ruta; `filament:upgrade` corre solo al terminar Composer).
- 2026-10-29 — Cierre. `composer check`: pint y phpstan sin errores, 43 migraciones bien, pest 2086/2086 (10352
  aserciones). Sin revisión de arquitectura: solo dos versiones de parche, sin cambios de código.
