# Ficha · Presupuesto de la empresa por sede y mes (2.13)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** T2
- **Aprobada:** 2026-11-19 · etiqueta `aprobado/t2-presupuesto-empresa-v1`
- **Fuentes del diseño:** `docs/planes/activos/2026-11-16-presupuesto-empresa.md` (objetivo, alcance y sus D1 a D6,
  decididas por Sebastián en el repo original el 2026-11-16).

## Qué hace

Además del presupuesto de cada proyecto, Techquk presupuesta lo que gasta fuera de los proyectos (oficina, servicios,
movilidad, seguros…) por sede y por mes, y ese presupuesto controla el gasto directo como el árbol controla el de un
proyecto: cada gasto directo lleva su sede (o «Empresa», para lo que es de todas), se compara con el saldo de la sede
en el mes, el exceso exige motivo y el comparativo avisa al 75, 90 y 100 % de consumo. El saldo se puede mover entre
sedes y meses con un traspaso con motivo. Los gastos generales de cada proyecto se quedan en su árbol.

## Decisiones de Sebastián

Del plan del repo original (no se vuelven a decidir; se construyen tal cual):

- **D2** · Se presupuesta por sede, no por centro de costo; el gasto directo lleva además su sede.
- **D3** · La sede es siempre obligatoria; lo de toda la empresa va a la línea «Empresa».
- **D4** · Un gasto directo en una sede y mes sin presupuesto no se carga a nada: primero hay que presupuestarlo.
- **D6** · Los gastos generales de un proyecto se quedan en su árbol; a la sede va solo lo que no es de ningún proyecto.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** caja «Presupuesto de la empresa» en el área ERP.
- **Presupuesto de la empresa** `/presupuesto-empresa`: subtítulo con el estado («Aprobado (base) el 05/01/2026 · los
  cambios van por traspasos»). Tabla «Presupuesto 2026 por sede y rubro (S/)»: sede (rubro debajo), Ene a Dic y Año.
  Debajo, la sección plegable **«Comparativo por sede y mes»**: sede, mes, presupuestado, comprometido, ejecutado,
  variación (en rojo si es negativa) y consumo con su color (75 % ámbar claro, 90 % ámbar, 100 % rojo; «sin
  presupuesto» en rojo).
- **Botones:** Registrar gasto directo (panel deslizante), Traspasar, Aprobar (solo en borrador).

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Registrar gasto directo | sede (o Empresa, obligatoria), rubro (lista), centro de costo, descripción, proveedor, fecha, comprometido (> 0), ejecutado, motivo del exceso | la sede tiene presupuesto aprobado en ese mes; si el comprometido pasa el saldo, exige motivo | gasto `GD-AAAA-NNNN` con su imputación al centro de costo (comprometido y ejecutado) y su sede |
| Traspasar | línea y mes de origen, línea y mes de destino, monto (> 0), motivo | el origen tiene ese saldo presupuestado | baja el origen, sube el destino y queda el traspaso registrado |
| Aprobar | — | borrador | aprobado: es la base; ya no se edita |
| (servicio) Crear el año / copiar el anterior con % de ajuste / guardar una línea | año; presupuesto de origen y %; sede, rubro, 12 montos (≥ 0), nota | borrador | — (sin pantalla en el MVP) |

## Datos

Tablas nuevas `presupuestos_empresa` (año único, estado borrador o aprobado, aprobado el), `presupuesto_empresa_lineas`
(presupuesto, sede o nula = Empresa, rubro, nota, `m01` a `m12`; única por presupuesto, sede y rubro) y
`presupuesto_empresa_traspasos`. `gastos_demo` suma sede, rubro, centro de costo, es gasto directo y motivo del exceso
(y su proyecto y partida pasan a opcionales). La ficha de Techquk queda en el parámetro `empresa.ficha`, con tres sedes.
Permisos `erp.presupuesto-empresa.ver` y `erp.presupuesto-empresa.gestionar`.

## Reglas y números

- Saldo de una sede en un mes = presupuestado (del presupuesto aprobado) − comprometido de sus gastos directos.
- Consumo = comprometido ÷ presupuestado × 100, a un decimal; alerta al pasar 75, 90 y 100 %.
- Demo 2026: sede principal 11,400.00 al mes; Oficina Lima 7,380.00; Almacén La Pampa 3,740.00; Empresa con seguros
  (18,600.00 en marzo), software (2,350.00 al mes), capacitación (6,000.00 en mayo y octubre) y la feria (8,500.00 en
  setiembre). 114 gastos directos de enero a noviembre (noviembre comprometido sin ejecutar).
- Oficina Lima, octubre: presupuestado 7,380.00, comprometido 8,720.40 (la movilidad ×1.85 por el viaje al GORE, con
  motivo) → variación −1,340.40, consumo 118.1 %, en rojo.
- Traspaso del 12/11/2026: 600.00 de la movilidad de diciembre de la sede principal a la de noviembre de la Oficina
  Lima → Lima en noviembre: 7,980.00.

## Supuestos sin confirmar

- Las sedes son las de la ficha de Techquk en Personas y empresas; los rubros, una lista fija.
- El control compara el comprometido (no el ejecutado) con el saldo.
- El exceso pide motivo pero no pasa por la aprobación del núcleo (el plan lo pide).
- Las alertas se ven en el comparativo; no se envían avisos.
- Sin pantalla para formular: crear el año, copiarlo del anterior con un porcentaje y editar líneas existen en el
  servicio. Sin versiones de revisión (solo la base) ni importación desde Excel.
- El selector de imputación no muestra todavía el saldo de la sede.

## Atajos tomados

- Los doce meses como columnas de la línea.
- Gasto directo por el gasto de demostración (MV-003), no por órdenes ni rendiciones.
- Estado como texto y sin acciones registradas; consultas directas a la base.
- El comparativo consulta una vez por sede y mes.

## Dónde está en el código

`app/Modules/Erp/Presupuestos/Services/PresupuestoEmpresaService.php`, `Filament/PresupuestoDeLaEmpresa.php`,
`resources/views/empresa/comparativo.blade.php`, migración `2026_11_19_600001_crear_presupuesto_empresa.php` y los
cambios a `crear_gastos_demo`; datos en `DemoSeeder::empresaYSedes()` y `presupuestoEmpresa()`.
