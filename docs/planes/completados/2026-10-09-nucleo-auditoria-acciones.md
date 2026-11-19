# Plan: 1.1 · Auditoría y registro de acciones

- **Estado:** terminado
- **Módulo(s):** Nucleo (M4 y registro de acciones)
- **Etapa:** 1 · tarea 1.1
- **Creado:** 2026-10-09 · **Actualizado:** 2026-10-09

## Objetivo

1. Toda fila de un modelo con el trait de auditoría guarda `created_by`/`updated_by` sin que el código
   lo pida, con el usuario correcto (persona, o usuario de sistema en consola y jobs), y los modelos
   marcados como sensibles dejan su bitácora campo por campo en `auditoria_cambios`.
2. Existe el registro de acciones del núcleo: una acción se declara como clase (nombre, campos
   tipados, permiso, `ejecutar()`), se ejecuta y prueba sin interfaz, y Filament la dibuja desde su
   declaración. `CambiarModoNavegacion` es la primera acción real.

## Contexto que leí

- `AGENTS.md`, `ARCHITECTURE.md` (registro de acciones, reglas de dependencia)
- `docs/convenciones/base-de-datos.md` §Auditoría; `docs/convenciones/codigo.md` §Modelos, §Acciones
- `.claude/skills/nueva-accion/SKILL.md`
- `docs/diseno/techquk_modelo.dbml`: `auditoria_cambios` (M4), `users`
- `docs/diseno/06-Arquitectura-Nucleo-Datos.md` §7 (M4: columnas, `auditoria_cambios`, capa de visado)
- `docs/diseno/v4/parte-7-transversal.md` «Numeración, auditoría y reglas generales» (pestaña de historial)
- ADR 0007 (API móvil sobre servicios y acciones), 0009 (precedencia), 0010 (migraciones editables)
- `docs/planes/completados/2026-10-etapa-0-base.md` §Qué sigue
- Código actual: `NucleoServiceProvider` (macro `auditoria()`), `UsuarioSistema`,
  `PreferenciasUsuarioService`, `AppPanelProvider::accionCambiarModo()`, migración `…000002`, `User`
- `docs/decisiones/pendientes.md`: **nada bloquea esta tarea.** La capa de visado (doc 06 §7.3) es `[P]`
  y no entra.

## Deuda de la etapa 0: a qué tarea va

| Deuda | Dónde |
|---|---|
| `users.created_by`/`updated_by` (y el choque con el usuario de sistema) | **1.1**, paso 3 |
| `users.party_id`, `unidad_organizativa_id`, `ultimo_acceso` | 1.4 (sus tablas son de 1.3 y 1.4) |
| ¿`User` pasa a `Nucleo\Models`? Prueba de que nadie fuera del núcleo escribe modelos del núcleo | 1.4 (completar `users`) |
| Acción `CambiarModoNavegacion` + prueba de rechazo del usuario de sistema | **1.1**, paso 7 |
| `verificar-migraciones.php` no revisa `database/migrations` | **1.1**, paso 8 |

## Decisiones de diseño (aprobadas 2026-10-09)

- **D1 · `auditoria_cambios` sigue al DBML v2** (`evento`, `user_id`, `valores_anteriores`,
  `valores_nuevos`, `ip`, `agente`, `fecha`) con dos ajustes:
  - `valores_*` en **`jsonb`**, no `text` (el doc 06 lo pide en `jsonb`; permite consultar dentro).
  - `user_id` **`NOT NULL`**: nunca hay cambio sin autor (en consola es el usuario de sistema). Regla 5.
  - La tabla no lleva `auditoria()` (`// harness:sin-auditoria es la bitácora: user_id y fecha son su
    auditoría`). Solo se inserta; el modelo rechaza actualizar y borrar.
- **D2 · Quién es el autor.** Un servicio `ContextoAuditoria` resuelve el actor en este orden:
  actor fijado explícitamente (lo fija el ejecutor de acciones) › usuario autenticado › usuario de
  sistema si corre en consola o cola › si no, excepción («no se sabe quién hace el cambio»).
  Así una acción ejecutada sin interfaz (pruebas, API futura) firma con su usuario, no con `Auth`.
- **D3 · Dos traits separados.** `ConAuditoria` (todo modelo de negocio: llena `created_by`/`updated_by`)
  y `RegistraCambios` (solo tablas sensibles: escribe `auditoria_cambios` en `created`/`updated`/`deleted`,
  dentro de la misma transacción). Guarda solo los campos que cambiaron; excluye `updated_at`,
  `updated_by`, ocultos (`$hidden`) y los que el modelo liste en `camposSinBitacora()`.
  Límite conocido: un `update()` masivo por query builder no dispara eventos; se documenta en
  `codigo.md` (los servicios escriben por modelo cuando la tabla es sensible).
- **D4 · Registro de acciones sin tabla.** El DBML no tiene tabla de acciones ni bitácora de
  ejecuciones; el registro es un catálogo en código (`RegistroAcciones`, como `RegistroModulos`). Lo que
  cambia queda en `auditoria_cambios` cuando la tabla es sensible. Si se quiere una bitácora de cada
  ejecución, es una tabla nueva en el DBML: no la invento.
- **D5 · Piezas del registro** en `Nucleo/RegistroAcciones/`:
  - `Accion` (abstracta): `clave()`, `nombre()`, `campos(): list<Campo>`, `permiso(): ?string`,
    `ejecutar(array $datos, User $usuario): mixed`.
  - `Campo` + `TipoCampo` (texto, entero, decimal como string, fecha, booleano, opción de un enum):
    etiqueta, obligatorio y reglas de Laravel. Solo los tipos que hacen falta; se agregan con su uso.
  - `EjecutorAcciones::ejecutar(Accion|string, array, User)`: rechaza usuario inactivo o de sistema
    (las acciones son de personas), verifica el permiso con spatie (`HasRoles` en `User`; permisos por
    ámbito quedan para 1.4), valida con los campos, fija el actor en `ContextoAuditoria` y llama
    `ejecutar()`. Excepciones de dominio con mensaje en español.
  - `AccionFilament::desde(Accion)`: arma la `Action` de Filament con el formulario de los campos y
    llama al ejecutor. Sin reglas.

## Pasos

- [x] 1. `ContextoAuditoria` (D2) en `Nucleo/Services/`, singleton. Pruebas: autenticado, consola,
      actor fijado, web sin usuario lanza excepción.
- [x] 2. Trait `ConAuditoria` en `Nucleo/Models/Concerns/`. Pruebas con una tabla creada en la prueba:
      crea con autor, actualiza con autor, consola usa sistema, la base rechaza `created_by` nulo.
- [x] 3. `users`: migración nueva del núcleo que agrega `created_by` (NOT NULL) y `updated_by` con FK e
      índice. Usuario de sistema: `created_by` = su propio id (FK a la misma fila, válida en PostgreSQL).
      `User` usa `ConAuditoria`. Ajustar factory si hace falta. Prueba del usuario de sistema.
- [x] 4. Migración `auditoria_cambios` (`/nueva-migracion`, D1): índices en
      (`auditable_type`, `auditable_id`), `user_id`, `fecha`; CHECK de `evento`. Modelo inmutable.
- [x] 5. Trait `RegistraCambios` (D3) + `HistorialCambiosService::de(Model)` para leer la bitácora
      (la pestaña de historial en Filament llega con la primera ficha sensible, `parties`, en 1.3).
      Pruebas: crear/actualizar/eliminar dejan su fila; solo campos cambiados; ocultos y `camposSinBitacora()`
      no aparecen; un rollback no deja bitácora; ip y agente desde la petición.
- [x] 6. Registro de acciones (D5): `Accion`, `Campo`, `TipoCampo`, `RegistroAcciones`, `EjecutorAcciones`,
      `AccionFilament`, excepciones. Pruebas: con una acción de prueba, camino feliz y cada rechazo
      (inactivo, sistema, sin permiso, validación, clave no registrada, clave duplicada).
- [x] 7. `Nucleo/Acciones/CambiarModoNavegacion` (`/nueva-accion`), registrada en el provider;
      `AppPanelProvider` la invoca por `AccionFilament`. Pruebas sin interfaz (cambia el modo; rechaza
      usuario de sistema e inactivo; rechaza modo inválido) y `PanelTest` sigue en verde.
- [x] 8. `verificar-migraciones.php` también revisa `database/migrations`: tipos prohibidos, PK, FK sin
      índice y `empresa_id`; sin exigir `auditoria()` (tablas de framework y paquetes).
- [x] 9. Documentos: `codigo.md` (actor de auditoría, `RegistraCambios`, límite del update masivo, cómo
      declarar una acción), `base-de-datos.md` si cambia algo, `nueva-accion/SKILL.md` con los nombres
      reales de las clases. Bitácora al día.
- [x] 10. `composer check` en verde, `/cerrar-tarea` y revisión de `revisor-arquitectura`.

## Criterio de aceptación

- `tests/Modules/Nucleo/ContextoAuditoriaTest.php`, `ConAuditoriaTest.php`, `RegistraCambiosTest.php`,
  `RegistroAccionesTest.php`, `CambiarModoNavegacionTest.php` en verde.
- Ninguna fila de `users` ni de una tabla con `auditoria()` queda con `created_by` nulo (lo impide la base).
- El cambio de modo en el panel funciona igual que antes, ahora a través de la acción registrada.

## Bitácora

- 2026-10-09 — Plan creado. Deuda de la etapa 0 repartida (tabla de arriba). D1–D5 por confirmar con
  el desarrollador antes de programar.
- 2026-10-09 — D1–D5 aprobadas por el desarrollador. Se empieza el paso 1.
- 2026-10-09 — **Incidente:** `php artisan migrate:fresh --env=testing` corrió contra la base de
  **desarrollo** (no existe `.env.testing`; las pruebas toman `techquk_test` de `phpunit.xml`). Se perdió
  el usuario de desarrollo; se restauró con `db:seed`. Para migrar la base de pruebas: `DB_DATABASE=techquk_test
  php artisan migrate:fresh`, o dejar que `RefreshDatabase` lo haga.
- 2026-10-09 — Pasos 1–3. `ContextoAuditoria` (singleton; recibe `enConsola` y la petición por closure para
  poder probar el caso web). `ConAuditoria` con `autorSinContexto()`: `User` lo redefine (sin sesión, quien
  cambia su propia fila es él mismo: «recordarme» guarda el token antes de fijar la sesión, igual el
  restablecimiento de clave). `User` usa además `HasRoles` (spatie). Migración `…000003`: el sistema es su
  propio autor. El seeder de desarrollo dejaba `WithoutModelEvents`, que apaga el trait: quitado.
- 2026-10-09 — Pasos 4–5. `auditoria_cambios` con `bigInteger` en `auditable_id` (polimórfica, sin FK),
  CHECK de `evento`, enum `EventoAuditoria`. `auditable_type` guarda la clase (`getMorphClass()`): si se
  renombra un modelo auditado hay que migrar esos valores o declarar un morph map. Fixtures en
  `tests/Modules/Nucleo/Fixtures/` (tabla creada dentro de la prueba).
- 2026-10-09 — Pasos 6–7. Registro en `Nucleo/RegistroAcciones/`; excepciones en `Nucleo/Excepciones/`.
  Nombre de la acción en Filament = clave en camelCase (`nucleoCambiarModoNavegacion`); `PanelTest` ajustado.
- 2026-10-09 — Paso 8. El script ignora `->change()` (no crea FK) y, en `database/migrations`, no exige
  auditoría ni la forma de la PK (sessions, cache y pivotes de spatie tienen la suya).
- 2026-10-09 — Paso 9. PHPStan no analiza un trait sin uso en `app/`: se agregó
  `tests/Modules/Nucleo/Fixtures` a sus paths. `composer check` en verde: 70 pruebas.
- 2026-10-09 — Paso 10. Revisión de `revisor-arquitectura`: sin bloqueantes. Atendido:
  - `RegistraCambios` abre transacción en `save()`/`delete()`: sin ella, un fallo al escribir la bitácora
    dejaba el cambio guardado sin rastro. Con prueba (fallo después de escribir, sin transacción externa).
  - `ConAuditoria` sobrescribe siempre `created_by` (antes respetaba uno puesto a mano). Con prueba.
  - `camposSinBitacora()` abstracto: cada tabla sensible decide qué no guarda (regla 10). `RegistraCambios`
    exige `autorSinContexto()`, o sea, `ConAuditoria`.
  - `EjecutorAcciones` relee `es_activo` de la base. Con prueba.
  - `AccionFilament` pasa a `Nucleo/Filament/` y se oculta sin el permiso (el ejecutor sigue decidiendo).
  - Script: reglas de PK en lista aparte (antes se eximían comparando el texto del mensaje).
  - Migración `…000003` falla con mensaje claro si falta el usuario de sistema. Plan corregido (`camposSinBitacora()`).
  Aceptado sin cambio, con su razón:
  - Inmutabilidad de `auditoria_cambios` solo por modelo: triggers prohibidos por convención y `REVOKE`
    no sirve con el mismo usuario dueño. Documentado en `codigo.md`.
  - `User::autorSinContexto()` firma como el propio usuario cualquier cambio a su fila sin sesión. Hoy solo
    lo usan login y restablecimiento de clave; revisar si aparece una ruta pública que toque `users`.
  - En pruebas el contexto es siempre consola (autor = sistema sin `actingAs`); el caso web se prueba
    reemplazando `ContextoAuditoria`.
  - Sin prueba de la rama «acción rechazada → aviso» de `AccionFilament` (no hay aún pantalla donde un
    usuario con sesión sea rechazado); llega con la primera acción con permiso (1.2 o 1.3).
  - Una tabla de negocio puesta por error en `database/migrations` quedaría sin exigencia de auditoría:
    ninguna regla lo impide aún.
  `composer check` en verde: 74 pruebas. Plan terminado.

## Qué sigue (deuda)

- **Mensajes de validación en español:** no hay `lang/es`; un error de validación sale como
  `validation.required`. Decidido en ADR 0011: archivo propio, al empezar la 1.2.
- Pestaña de historial en Filament (lee `HistorialCambiosService::de()`): con `parties`, en 1.3.
- `User` a `Nucleo\Models` y prueba de que solo el núcleo escribe sus modelos: 1.4.
- Morph map (`Relation::enforceMorphMap`) para `auditoria_cambios` y las tablas de spatie, antes de datos
  reales: 1.4, junto con el traslado de `User`.
