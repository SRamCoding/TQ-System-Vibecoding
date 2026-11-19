---
name: nuevo-modulo
description: Crea el esqueleto de un módulo del ERP/CRM (carpetas, service provider, registro, pruebas). Usar cuando se empieza un módulo de ARCHITECTURE.md que todavía no existe en app/Modules.
---

# Crear un módulo

1. **Confirma que corresponde y que es su etapa.** El módulo debe estar en `ARCHITECTURE.md` y su etapa
   en `docs/planes/mapa-etapas.md` debe ser la actual. Si no, detente y pregunta.
2. **Revisa bloqueos** en `docs/decisiones/pendientes.md` (columna «Bloquea»).
3. **Lee solo lo necesario:** su grupo en la tabla de correspondencia de `ARCHITECTURE.md`, sus tablas en
   `docs/diseno/techquk_modelo.dbml`, su flujo en `docs/diseno/v4/parte-4-flujo-NN.md` y las fichas de
   sus documentos en `parte-6`.
4. **Plan** en `docs/planes/activos/` con `docs/planes/plantilla.md`.
5. **Esqueleto** en `app/Modules/<Grupo>/<Modulo>/`: `<Modulo>ServiceProvider.php`, `Models/`,
   `Services/`, `Acciones/`, `Efectos/`, `Events/`, `Listeners/`, `Jobs/`, `Policies/`, `Enums/`,
   `Filament/`, `database/{migrations,factories,seeders}/`; y `tests/Modules/<Grupo>/<Modulo>/`.
   Nombres con mayúsculas exactas.
6. **Provider:** carga migraciones, registra eventos, políticas, acciones y recursos de Filament
   (en los dos modos de navegación). Se agrega a `bootstrap/providers.php`.
7. **Verifica:** `composer check:tarea` (registrar el proveedor en `bootstrap/` ya hace que corra todas las
   pruebas). Las pruebas de arquitectura incluyen el módulo solas.

Las tablas se crean con `/nueva-migracion`; los documentos con estados, con `/nuevo-documento`.
