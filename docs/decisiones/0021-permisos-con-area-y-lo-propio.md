# 0021 — Los permisos llevan su área, y «lo propio» se pregunta en un solo lugar

- **Estado:** aceptada
- **Fecha:** 2026-11-05
- **Decide:** desarrollador (modo actual de AGENTS.md: las reglas que cruzan áreas las decide Sebastián, con la IA
  proponiendo opciones). Viene de las decisiones D7 y D14 del plan 2.20 (Cuentas) y se construye en la 2.20a.

## Contexto

En la etapa 2, cuatro áreas crean a la vez sus permisos y acciones (ADR 0020). Hasta ahora el núcleo los nombraba
`<cosa>.<verbo>` (`parties.ver`) y la convención ponía como ejemplo de negocio `compras.orden.anular`. Con varios
módulos de negocio ese esquema choca: «cuentas» son a la vez las cuentas del CRM, las de usuario, las bancarias y, en la
etapa 3, las por pagar y por cobrar. Además, la pantalla de Roles y permisos agrupaba por el primer tramo de la clave,
así que cada módulo de negocio salía suelto.

Al decidir Cuentas apareció también una necesidad que tendrán Oportunidades, Cotizaciones y otras: que unos editen solo
lo suyo (donde son el responsable) y otros todo. El mecanismo de ámbitos del núcleo (`permisos_por_ambito`) acota un rol
a una cosa (hoy, solo a una unidad del organigrama, que está en espera); «lo mío» es una relación del registro con la
persona, no un rol dado en cada registro.

## Decisión

1. **Prefijo de área:** en los módulos de negocio, permisos y acciones se llaman `<area>.<modulo>.<verbo>`
   (`crm.cuentas.ver`, `erp.compras.orden.anular`), y la acción se llama igual que su permiso. El núcleo sigue sin
   prefijo (`parties.ver`). Roles y permisos agrupa los de negocio por área y módulo («CRM · Cuentas»). Los permisos que
   no son acciones los declara cada módulo en su provider (`RegistroPermisos`), con nombre legible.
2. **Lo propio y lo de todos:** son dos permisos, `<cosa>.editar` y `<cosa>.editar-propias` («propia» = soy su
   responsable), preguntados siempre con `SeguridadService::puedeSobrePropio()` y, para una lista, `filtrarPropios()`.
   Tener los dos es tener el de todas, no un cruce de funciones. Cambiar de responsable es otra acción con su permiso.

El detalle está en `docs/convenciones/codigo.md` («Nombres de permisos y acciones» y «Lo propio y lo de todos»).

## Consecuencias

- Las sesiones de las cuatro áreas nombran sus permisos con el prefijo desde su primera tarea. `PermisosConAreaTest`
  falla si una acción o transición de `Crm` o `Erp` no lleva su área delante.
- Ningún módulo repite la regla de «lo propio»: si algún día los ámbitos del núcleo admiten «propios», solo cambian
  `puedeSobrePropio` y `filtrarPropios`.
- Los permisos ya creados del núcleo no cambian de nombre.
- **La 2.1 llegó a `main` antes que este ADR**, con diez nombres sin prefijo (`proyectos.crear`,
  `presupuestos.arbol.editar`, `proyectos.proyecto.anular`…). Quedan en una excepción congelada de
  `PermisosConAreaTest` y los renombra la tarea 2.1b del área 1 (mapa de etapas), incluidas las claves de documento
  guardadas en las tablas de estados, **antes del primer despliegue con datos reales** (ADR 0010): después dejaría
  asignaciones de roles huérfanas. **Desde ya, todo permiso o transición nueva de la 2.2 va con prefijo `erp.`**: la
  excepción no crece, y se vacía sola al renombrar.
- Quien tenga nombres sin prefijo en otra rama abierta los renombra antes de fusionar.

## Alternativas descartadas

- **`<modulo>.<verbo>` sin área (`cuentas.ver`):** ambiguo entre cuentas del CRM, de usuario, bancarias y por
  pagar/cobrar; los grupos de la pantalla salen sueltos.
- **Agregar los permisos de lectura de cada módulo a una lista del núcleo:** el núcleo terminaría conociendo los
  permisos de cada módulo.
- **Un tipo de ámbito «propios» en `permisos_por_ambito`:** cambia la tabla y su `CHECK`, `SeguridadService`, la
  asignación de roles acotados y la pantalla de la 1.4c; es una tarea del núcleo en sí, y la regla en un solo método
  la deja lista para ese cambio.
- **Que cada servicio compruebe «soy el responsable» por su cuenta:** la regla se repetiría y se desviaría módulo por
  módulo.
