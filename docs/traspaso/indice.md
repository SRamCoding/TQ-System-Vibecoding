# Índice de fichas

Una fila por ficha, en el orden en que se abrieron. El repo original empieza por aquí: las fichas **aprobadas** son
las que puede construir; las que están **en curso** todavía pueden cambiar.

| Ficha | Tramo | Estado | Versión | Etiqueta |
|---|---|---|---|---|
| [t0-datos-demo](fichas/t0-datos-demo.md) | T0 | aprobada | v4 | `aprobado/t0-datos-demo-v4` |
| [t1-puente-erp](fichas/t1-puente-erp.md) | T1 | aprobada | v2 | `aprobado/t1-puente-erp-v2` |
| [t1-licitaciones](fichas/t1-licitaciones.md) | T1 | aprobada | v1 | `aprobado/t1-licitaciones-v1` |
| [t1-agenda](fichas/t1-agenda.md) | T1 | aprobada | v1 | `aprobado/t1-agenda-v1` |
| [t1-catalogo](fichas/t1-catalogo.md) | T1 | aprobada | v1 | `aprobado/t1-catalogo-v1` |
| [t1-campanias](fichas/t1-campanias.md) | T1 | aprobada | v1 | `aprobado/t1-campanias-v1` |
| [t2-contratos](fichas/t2-contratos.md) | T2 | aprobada | v2 | `aprobado/t2-contratos-v2` |
| [t2-formula-polinomica](fichas/t2-formula-polinomica.md) | T2 | aprobada | v1 | `aprobado/t2-formula-polinomica-v1` |
| [t2-presupuesto-empresa](fichas/t2-presupuesto-empresa.md) | T2 | aprobada | v1 | `aprobado/t2-presupuesto-empresa-v1` |
| [t3-compras](fichas/t3-compras.md) | T3 | aprobada | v1 | `aprobado/t3-compras-v1` |
| [t3b-transicion-cielo](fichas/t3b-transicion-cielo.md) | T3b | aprobada | v1 | `aprobado/t3b-transicion-cielo-v1` |
| [t4-personal-tareo](fichas/t4-personal-tareo.md) | T4 | aprobada | v1 | `aprobado/t4-personal-tareo-v1` |
| [t4-valorizaciones](fichas/t4-valorizaciones.md) | T4 | aprobada | v1 | `aprobado/t4-valorizaciones-v1` |
| [t5-finanzas](fichas/t5-finanzas.md) | T5 | aprobada | v2 | `aprobado/t5-finanzas-v2` |

## Registro del repositorio

Lo que no es de ninguna ficha: el arranque y los cambios generales.

- 2026-11-19 · Arranque: copia del repo original en el commit `a5e5972`, sin pruebas, CI ni sitio de documentación;
  app en el puerto 8001 y base en el 5433.
- 2026-11-19 · Checkpoint: tramos 0, 1 y 2 terminados y aprobados (MV-009). Sigue el tramo 3 (requerimientos,
  cuadro comparativo, órdenes de compra y servicio, almacén, recepción y despacho, reclasificación), que reemplaza el
  gasto de demostración (MV-003) por órdenes reales. La base de demo se rehace con `php artisan migrate:fresh --seed`.

## Cierre

Vacío hasta que Sebastián diga que el MVP está terminado. Entonces el agente hace aquí la auditoría de cierre de
`COMO-IMPORTAR.md`.
