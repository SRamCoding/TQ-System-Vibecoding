# Plan: renombrar a `erp.` los permisos y claves de la 2.1 y la 2.2 (tarea 2.1b)

- **Estado:** terminado
- **Responsable:** Sebastián (área 1, Presupuesto y costo; ADR 0020)
- **Módulo(s):** `Erp/Proyectos`, `Erp/Presupuestos` (y la prueba `tests/Modules/Nucleo/PermisosConAreaTest.php`)
- **Fase:** F2
- **Creado:** 2026-11-06 · **Actualizado:** 2026-11-06

## Objetivo

Ninguna acción, permiso, clave de documento, efecto, alerta ni lista de motivos de `Erp/Proyectos` y `Erp/Presupuestos`
queda sin el prefijo `erp.` (ADR 0021), y `PermisosConAreaTest` pasa sin la excepción `PERMISOS_SIN_AREA_DE_LA_2_1`,
que desaparece.

## Contexto que leí

- `docs/decisiones/0021-permisos-con-area-y-lo-propio.md` (consecuencias: la 2.1b, la fecha límite, la excepción).
- `docs/planes/mapa-etapas.md`, fila 2.1b.
- `docs/convenciones/codigo.md`, «Nombres de permisos y acciones».
- Código: `DefinicionDocumento::permiso()` y `familiaDeMotivos()` derivan de la clave del documento
  (`<clave>.<transicion>`): al cambiar la clave cambian solos los permisos de transición y la lista de motivos de
  «anular». `efectos_aplicados` guarda la clave del efecto para revertir: renombrar después del despliegue dejaría
  efectos que no se encuentran. `DefinicionParametro` y `GrupoAvisos` solo aceptan snake_case sin punto.
- Ninguna decisión de `pendientes.md` afecta. No hay producción: no se migran datos (ADR 0010, las migraciones aún
  se editan; aquí ni hace falta, las claves no están en migraciones).

### Inventario (antes → después)

| Qué | Antes | Después |
|---|---|---|
| Documento | `proyectos.proyecto` | `erp.proyectos.proyecto` |
| Permisos de transición (derivados) | `proyectos.proyecto.anular`, `.cerrar` | `erp.proyectos.proyecto.anular`, `.cerrar` |
| Lista de motivos de anular (derivada) | `proyectos.proyecto.anular` | `erp.proyectos.proyecto.anular` |
| Documento | `presupuestos.arbol` | `erp.presupuestos.arbol` |
| Permiso de transición (derivado) | `presupuestos.arbol.aprobar` | `erp.presupuestos.arbol.aprobar` |
| Acción | `proyectos.crear-interno` (permiso `proyectos.crear`) | `erp.proyectos.crear-interno` (permiso `erp.proyectos.crear`; D1) |
| Acciones | `presupuestos.crear-arbol`, `agregar-partida`, `fijar-presupuestado`, `desactivar-partida` (permiso `presupuestos.arbol.editar`) | `erp.presupuestos.…` (permiso `erp.presupuestos.arbol.editar`; D1) |
| Efectos | `proyectos.avisar-cierre`, `presupuestos.crear-pc99`, `presupuestos.iniciar-ejecucion` | `erp.…` (D2) |
| Alerta (2.2) | `presupuestos.partida-excedida` | `erp.presupuestos.partida-excedida` |
| Listas de motivos | `presupuestos.exceso_partida` (2.2), `presupuestos.gasto_no_presupuestado` (2.1) | `erp.presupuestos.…` |
| Parámetro (2.2) | `presupuestos.alerta_partida_porcentaje` | igual (D3) |
| Grupo de avisos (2.2) | `presupuestos` | igual (D3) |

## Decisiones

Decididas por Sebastián el 2026-11-06, una por una.

- **D1.** Acciones: solo se antepone `erp.`. Las cuatro acciones del árbol siguen compartiendo
  `erp.presupuestos.arbol.editar` y `erp.proyectos.crear-interno` usa `erp.proyectos.crear`: es una tarea de renombre,
  y el núcleo ya tiene acciones que comparten permiso (`tipos_cambio.registrar`). Descartado: un permiso por acción
  (cambia qué se asigna a los roles).
- **D2.** Efectos: también con `erp.` (`erp.proyectos.avisar-cierre`, `erp.presupuestos.crear-pc99`,
  `erp.presupuestos.iniciar-ejecucion`). `efectos_aplicados` guarda la clave para revertir: después del despliegue ya
  no se podrían renombrar sin migrar.
- **D3.** El parámetro `presupuestos.alerta_partida_porcentaje` y el grupo de avisos `presupuestos` quedan igual: el
  núcleo exige snake_case sin punto en ambos, y esos grupos van por tema, sin área (`adjuntos`, `parties`, `control`).
  Descartados: `erp_presupuestos` (una forma nueva que nadie usa) y admitir punto en el núcleo (más alcance).
- **D4.** La regla cubre más que permisos y acciones. `codigo.md` («Nombres de permisos y acciones») lista qué lleva
  área (permisos, acciones, claves de documento, efectos, alertas, listas de motivos) y qué no (grupos de parámetros y
  de avisos, por D3). `PermisosConAreaTest` vigila además claves de documento y efectos (la clase de cada uno dice su
  módulo). `RegistroAlertas` y las listas sueltas de `RegistroDocumentos::familiaDeMotivos` no guardan desde qué
  provider se registraron: alertas y listas sueltas quedan por convención, y `codigo.md` lo dice.

## Pasos

- [x] 1. Decisiones confirmadas en este plan.
- [x] 2. Renombrar en `Erp/Proyectos` y `Erp/Presupuestos` (claves, acciones, efectos, alerta, listas) y en sus pruebas,
      `tests/Costuras` y `tests/Concurrencia`; quitar `PERMISOS_SIN_AREA_DE_LA_2_1`. Pruebas de lo tocado, pint, phpstan.
- [x] 3. Documentos: fila 2.1b del mapa ✅; lo que cambie de regla según D4.
- [x] 4. Revisión del subagente `revisor-arquitectura`.
- [x] 5. `composer check` en verde; plan a `completados/`; rama subida.

## Preparado para lo diferido

No se pospone nada: la tarea es cerrar la excepción congelada antes del primer despliegue con datos reales.

## Criterio de aceptación

- `PermisosConAreaTest` pasa sin excepciones.
- `grep` de `'proyectos.` y `'presupuestos.` sin prefijo en `app/` y `tests/` no encuentra claves de negocio (salvo lo
  que D3 deje a propósito).
- Las pruebas de `tests/Modules/Erp`, `tests/Costuras` y `tests/Concurrencia` pasan con los nombres nuevos.

## Bitácora

- 2026-11-06 — Rama `etapa-2-renombrar-permisos` desde `main` (d2ab115). Inventario hecho (tabla de arriba).
- 2026-11-06 — D1 a D4 decididas. Renombre aplicado con `sed` solo sobre `app/Modules/Erp`, `tests/Modules/Erp`,
  `tests/Costuras` y `tests/Concurrencia` (nunca en todo el repositorio). Las claves de las piezas de prueba de la 2.2
  (`erp.presupuestos.orden_prueba`, `…prueba.comprometer`) ya tenían prefijo. `PermisosConAreaTest` reescrita sin la
  excepción; los efectos se recorren desde las transiciones con `claseDeEfecto()`, sin tocar el núcleo.
- 2026-11-06 — Revisión de arquitectura: sin bloqueantes. Corregido en `codigo.md`: la D1 es una salvedad a «el permiso
  y la acción se llaman igual», y la prueba no vigila los permisos de `RegistroPermisos`, ni los efectos que ninguna
  transición usa, ni el tramo del módulo (van por convención). La base de desarrollo conserva filas con los nombres
  viejos (permisos, tipos de motivo, quizá `efectos_aplicados`): sin uso y sin producción; un efecto aplicado antes del
  renombre ya no se podría revertir en desarrollo. Se arregla rehaciendo la base de desarrollo si molesta.
- 2026-11-06 — Error del agente, ya corregido: un `git checkout --` para deshacer una prueba de humo devolvió a `main` dos
  archivos con el renombre aún sin commit; se volvió a aplicar el `sed` antes del commit.
- 2026-11-06 — `composer check` en verde: 2520 pruebas, pint, phpstan y migraciones. Plan a `completados/`.
