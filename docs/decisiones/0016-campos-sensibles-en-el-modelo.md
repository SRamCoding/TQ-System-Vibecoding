# 0016 — Campos sensibles declarados en el modelo, ocultos salvo permiso

- **Estado:** aceptada
- **Fecha:** 2026-10-18
- **Decide:** responsable técnico (plan 1.4, D2 y D6, aprobadas el 2026-10-16)

## Contexto

La parte 7 del v4 pide restricciones por **campo**, no por módulo: un auxiliar abre un proyecto pero no ve costos
ni márgenes; los sueldos solo los ven RRHH, Gerencia y Contabilidad; el detalle médico, solo RRHH. El DBML v2 lo
resuelve con la tabla `restricciones_campo` (`rol_id`, `recurso`, `campo`, `tipo` oculto o solo lectura), que
**quita** campos a un rol.

Dos problemas con esa tabla:

- Una persona ve la suma de sus roles. Si un rol quita un campo y otro no, ¿lo ve o no? Hay que inventar una regla
  de combinación.
- Falla abierta: un campo nuevo que nadie registró en la tabla se ve. Un olvido expone sueldos o costos.

## Decisión

Un campo sensible está **oculto salvo que un permiso lo muestre**. Cada modelo declara junto a sus columnas cuáles
son sensibles y de qué grupo (`camposSensibles(): array<string, GrupoSensible>`). Los grupos son un enum
(`costos`, `margen`, `remuneracion`, `detalle_medico`, `precio_compra`, `datos_bancarios`) y cada uno tiene su
permiso `campos.ver-<grupo>`. Lo configurable es qué rol tiene cada permiso; la sensibilidad no se apaga desde una
pantalla.

Un **valor calculado hereda el grupo** de los campos de donde sale (un margen, un total, una cifra de reporte o de
tablero) y se oculta igual: en accessors, columnas de tabla, exportaciones y tarjetas de tablero.

Contra el olvido, `CamposSensiblesTest` lee las columnas reales de cada modelo en la base y falla si una con nombre
sospechoso (`costo`, `precio`, `margen`, `sueldo`, `remuneracion`, `salario`, `cci`, `cuenta`, `medic`, `examen`,
`utilidad`, por palabra) no está en `camposSensibles()` ni en `camposNoSensibles()`, que exige un motivo.

## Consecuencias

- Encaja con «ve la suma de sus roles»: basta con que un rol tenga el permiso.
- Falla cerrado: si se olvida dar un permiso, el dato no se ve. Si se olvida declarar una columna con nombre
  sospechoso, `composer check` falla.
- `restricciones_campo` del DBML **no se crea**. Tampoco el modo «solo lectura»: editar ya lo controla el permiso de
  cada acción.
- Lo filtra el servidor (`CamposVisiblesPorPermiso`): el historial y toda vista que muestre datos fuera de la ficha
  la consultan. Lo oculto no llega al navegador.
- Primer uso: número y CCI de `PartyCuentaBancaria` (`datos_bancarios`), que reemplaza a
  `parties.ver-cuentas-bancarias`. Sin el permiso, las cuentas tampoco se leen para la ficha (decisión de la 1.3b);
  el filtro por campo es una segunda barrera.
- Una columna con nombre neutro (`monto`, `importe`) no la detecta la prueba: quien la crea decide su grupo. La lista
  de nombres se amplía en `RevisorCamposSensibles::SOSPECHOSOS`.
- Pendiente: si los campos sensibles deben depender también del ámbito (los costos de su proyecto, no de todos),
  `CamposVisiblesPorPermiso` puede recibir el ámbito y llamar a `puede()` con él.

## Alternativas descartadas

- **`restricciones_campo` del DBML (quitar campos por rol):** falla abierta y obliga a inventar cómo se combinan
  roles que se contradicen.
- **La sensibilidad como dato en una tabla editable:** cualquiera con acceso a esa pantalla podría apagarla; y un
  campo nuevo seguiría naciendo visible.
- **Ocultar solo en la vista (CSS o `->hidden()` de Filament):** el dato llega al navegador igual.
