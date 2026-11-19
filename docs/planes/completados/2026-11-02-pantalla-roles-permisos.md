# Plan: Pantalla de roles y permisos (1.4c)

- **Estado:** terminado
- **Módulo(s):** Nucleo (M10)
- **Fase:** F1
- **Creado:** 2026-11-02 · **Actualizado:** 2026-11-02

## Objetivo

Que un administrador, sin programar, cree roles, marque sus permisos (también los de campos sensibles), vea los
conflictos con su control y mantenga el catálogo de permisos incompatibles. Con esto, el día que Gerencia defina la
grilla real se carga desde la pantalla. Suma el paso «Roles y permisos» al asistente.

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.4c.
- `docs/planes/completados/2026-10-16-nucleo-seguridad.md`, D9 y D10: la 1.4c no agrega lógica.
- `docs/diseno/v4/parte-7-transversal.md`, «Restricciones de campo».
- `app/Modules/Nucleo/Services/SeguridadService.php` y `Acciones/Seguridad/`.
- Pendiente **«Grilla de roles real»** (Gerencia): el desarrollador decidió el 2026-11-02 hacer la pantalla sin
  esperarlo. La pantalla no inventa roles ni pares: sale con los que hay. El pendiente sigue abierto para los datos.

## Decisiones

- **D1 · Tres pestañas en el clúster Seguridad**, todas con `roles.gestionar` (no se agrega un permiso): «Roles y
  permisos», «Conflictos» y «Permisos incompatibles».
- **D2 · Roles y permisos:** una caja por rol con buscador (patrón de pantallas), y debajo la grilla de solo lectura,
  roles × permisos, agrupada, con los campos sensibles primero. Se edita en un panel deslizante por rol, y un solo
  guardado es un solo cambio en el historial. No hay casillas sueltas en la grilla: cada una sería un cambio aparte
  y cada una podría pedir un control.
- **D3 · Lo que el servicio rechazaría no se ofrece:** `administrador` no se edita; `administrador` y `gerencia general`
  no se renombran; quien tiene un rol no edita sus permisos (la caja dice por qué).
- **D4 · Nombres legibles:** `PermisosLegibles`, solo presentación. Un mapa para los permisos de hoy y, para los que
  traigan los módulos nuevos, un nombre derivado de la clave. Sin tabla ni registro nuevo.
- **D5 · Conflictos:** tabla de cruces, abiertos de entrada, con filtro. Estado: Pendiente (lo que diga
  `crucesPendientes()`, la misma fuente de la alerta), Controlado, Sin efecto hoy (cruce abierto sin control vigente de quien hoy no junta los dos permisos) y Cerrado.
  «Registrar control» o «Renovar control» en cada fila, nunca en un cruce propio.
- **D6 · Permisos incompatibles:** agregar, desactivar y reactivar. Reactivar es la misma acción de registrar.
- **D7 · Fuera:** borrar un rol (no hay acción y la 1.4 no la pidió). El rol no tiene `version`: en su lugar, el panel
  compara los permisos leídos al abrir con los de ahora antes de guardar, para que dos personas no se pisen.
- **D8 · Asistente:** paso `nucleo.roles`, obligatorio, después de Usuarios.

## Pasos

- [x] 1. `PermisosLegibles`, selector de permisos y consulta `titularesPorRol()` (solo lectura).
- [x] 2. Pantalla «Roles y permisos»: cajas, grilla y panel de edición.
- [x] 3. «Conflictos».
- [x] 4. «Permisos incompatibles».
- [x] 5. Paso del asistente, textos que apuntan a estas pantallas, script de carga y documentos.
- [x] 6. Recorrido en el navegador, revisión de arquitectura y `composer check` en verde.

## Criterio de aceptación

- `tests/Modules/Nucleo/PantallaRolesPermisosTest.php` y `PantallaConflictosTest.php`.
- Un administrador crea un rol, le marca permisos y, si abre un cruce, el panel pide el control ahí mismo.

## Bitácora

- 2026-11-02 — Plan escrito. El desarrollador pidió hacer la pantalla sin esperar la grilla de Gerencia.
- 2026-11-02 — `titularesPorRol()` y el scope `conControlVigente` son consultas, no reglas nuevas: el scope es la regla que
  ya estaba en `estaControlado`, ahora en un solo lugar para que la pestaña «Controlados» no la repita.
- 2026-11-02 — `flatMap` renumera las claves enteras: el selector de permisos mandaba posiciones, no ids. Lo halló la prueba.
- 2026-11-02 — El control compensatorio solo aparece cuando un guardado lo pide, y sin `required()` de Filament: si no,
  quien se arrepiente y quita el permiso no podría guardar.
- 2026-11-02 — El interceptor del asistente también tomaba los clics de las cajas de Catálogos y Alertas (desde la 1.13).
  Se agregó `data-tq-abre-aqui`.
- 2026-11-02 — Fuera de la tarea, en commit aparte: los botones «+ dato» de Alertas no funcionaban desde la 1.10.
- 2026-11-02 — Revisión de arquitectura atendida: pestañas al día tras una acción (Filament las lee antes de que corra), el
  control solo vale para los cruces mostrados (`$frenadoCon`), «Desactivar» no se ofrece en un par con conflicto propio,
  `$leidos` y `$cruces` con `#[Locked]`, «Programado» pasó a «Sin efecto hoy» (cubre también el rol vencido) y Renovar no
  propone a un responsable inactivo. Dos guardados simultáneos sobre un rol: queda en `pendientes.md`.
- 2026-11-02 — Esc no cerraba ningún panel deslizante: un modal oculto siempre presente hacía creer que había uno abierto. Se
  corrigió en Alertas, Catálogos, IA, Roles y el asistente (mira si la capa se ve), y el asistente deja el Esc al panel que
  tiene adentro. Comprobado en el navegador, salvo IA; no tiene prueba automática.
