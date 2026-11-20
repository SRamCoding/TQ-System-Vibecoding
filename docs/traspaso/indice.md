# Índice de fichas

Una fila por ficha, en el orden en que se abrieron. El repo original empieza por aquí: las fichas **aprobadas** son
las que puede construir; las que están **en curso** todavía pueden cambiar.

| Ficha | Tramo | Estado | Versión | Etiqueta |
|---|---|---|---|---|
| [t0-datos-demo](fichas/t0-datos-demo.md) | T0 | aprobada | v4 | `aprobado/t0-datos-demo-v4` |
| [t1-puente-erp](fichas/t1-puente-erp.md) | T1 | aprobada | v2 | `aprobado/t1-puente-erp-v2` |
| [t1-licitaciones](fichas/t1-licitaciones.md) | T1 | en curso (v2 aprobada) | v3 | `aprobado/t1-licitaciones-v2` |
| [t1-agenda](fichas/t1-agenda.md) | T1 | en curso (v1 aprobada) | v2 | `aprobado/t1-agenda-v1` |
| [t1-catalogo](fichas/t1-catalogo.md) | T1 | aprobada | v1 | `aprobado/t1-catalogo-v1` |
| [t1-campanias](fichas/t1-campanias.md) | T1 | en curso (v1 aprobada) | v2 | `aprobado/t1-campanias-v1` |
| [t2-contratos](fichas/t2-contratos.md) | T2 | aprobada | v2 | `aprobado/t2-contratos-v2` |
| [t2-formula-polinomica](fichas/t2-formula-polinomica.md) | T2 | aprobada | v1 | `aprobado/t2-formula-polinomica-v1` |
| [t2-presupuesto-empresa](fichas/t2-presupuesto-empresa.md) | T2 | aprobada | v1 | `aprobado/t2-presupuesto-empresa-v1` |
| [t3-compras](fichas/t3-compras.md) | T3 | en curso (v1 aprobada) | v2 | `aprobado/t3-compras-v1` |
| [t3b-transicion-cielo](fichas/t3b-transicion-cielo.md) | T3b | aprobada | v1 | `aprobado/t3b-transicion-cielo-v1` |
| [t4-personal-tareo](fichas/t4-personal-tareo.md) | T4 | aprobada | v1 | `aprobado/t4-personal-tareo-v1` |
| [t4-valorizaciones](fichas/t4-valorizaciones.md) | T4 | aprobada | v1 | `aprobado/t4-valorizaciones-v1` |
| [t5-finanzas](fichas/t5-finanzas.md) | T5 | en curso (v3 aprobada) | v4 | `aprobado/t5-finanzas-v3` |
| [op-solicitudes-permiso](fichas/op-solicitudes-permiso.md) | Opcional | aprobada | v1 | `aprobado/op-solicitudes-permiso-v1` |
| [op-asistente](fichas/op-asistente.md) | Opcional | en curso (v1 aprobada) | v2 | `aprobado/op-asistente-v1` |
| [op-gran-momento](fichas/op-gran-momento.md) | Opcional | aprobada | v1 | `aprobado/op-gran-momento-v1` |
| [t6-postventa](fichas/t6-postventa.md) | Flujo 8 | aprobada | v1 | `aprobado/t6-postventa-v1` |
| [t7-personas](fichas/t7-personas.md) | Flujo 9 | en curso (v1 aprobada) | v2 | `aprobado/t7-personas-v1` |
| [t8-tableros](fichas/t8-tableros.md) | Parte 5 | en curso (v1 aprobada) | v2 | `aprobado/t8-tableros-v1` |
| [t9-cierre](fichas/t9-cierre.md) | Flujo 6 | aprobada | v1 | `aprobado/t9-cierre-v1` |
| [r1-inicio-procesos](fichas/r1-inicio-procesos.md) | Ronda 1 | en curso | v10 | — |
| [r1-personas-empresas](fichas/r1-personas-empresas.md) | Ronda 1 | en curso | v1 | — |

## Registro del repositorio

Lo que no es de ninguna ficha: el arranque y los cambios generales.

- 2026-11-20 · Ronda de cambios 1 de Sebastián: reorganizar el sistema por procesos (MV-016 en adelante), en siete
  pasos con un commit cada uno. Las fichas que toca suben de versión y quedan «en curso» hasta su «así me gusta».

- 2026-11-19 · Arranque: copia del repo original en el commit `a5e5972`, sin pruebas, CI ni sitio de documentación;
  app en el puerto 8001 y base en el 5433.
- 2026-11-20 · Ruta completa: tramos 0 a 5, la transición del cielo y los tres opcionales construidos y aprobados
  (MV-009, MV-014). Falta la ronda de cambios de Sebastián y, cuando diga que está terminado, la auditoría de cierre.
- 2026-11-19 · Checkpoint: tramos 0, 1 y 2 terminados y aprobados (MV-009). Sigue el tramo 3 (requerimientos,
  cuadro comparativo, órdenes de compra y servicio, almacén, recepción y despacho, reclasificación), que reemplaza el
  gasto de demostración (MV-003) por órdenes reales. La base de demo se rehace con `php artisan migrate:fresh --seed`.

## Cierre

Vacío hasta que Sebastián diga que el MVP está terminado. Entonces el agente hace aquí la auditoría de cierre de
`COMO-IMPORTAR.md`.
