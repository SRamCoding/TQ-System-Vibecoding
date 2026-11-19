# Ficha · Catálogo comercial (CRM-06)

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** T1
- **Aprobada:** 2026-11-19 · etiqueta `aprobado/t1-catalogo-v1`
- **Fuentes del diseño:** `docs/diseno/v4/parte-2-crm.md` (paso 6, «Catálogo comercial»); `crm-pantallas.md` (CRM-06:
  catálogo, ficha de producto, nuevo ítem, carga masiva de precios, equivalencias del Estado); DBML `productos`,
  `familias_producto`, `producto_precios`, `producto_cubso`.

## Qué hace

Es la lista de lo que vende Techquk, la misma que usan cotizaciones, presupuestos, compras y almacén («casi nadie lo
abre y todo el mundo lo usa»). Cada ítem tiene familia, unidad, marca, modelo, código del Estado (CUBSO) y un precio
referencial con su costo y su margen objetivo. Los precios no se sobrescriben: cada cambio crea un registro con su
vigencia y el anterior queda en la historia. Los precios se pueden cargar en bloque pegando desde Excel. Al cotizar
un ítem del catálogo, la cotización trae su precio vigente y su costo referencial.

## Decisiones de Sebastián

- **MV-005** (ficha) · Va en el tramo 1, después de la agenda.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

- **Inicio:** caja «Catálogo» en el área CRM.
- **Catálogo comercial** `/catalogo`: subtítulo «Es el mismo catálogo que usan compras y almacén…». Tabla agrupada
  por familia («Videovigilancia › Cámaras», plegable) con Código, Descripción (marca y modelo debajo), Und, Precio («sin
  precio» en rojo), Margen obj. y Cód. Estado («falta» en ámbar; «—» en servicios). Filtro «Ver» con sus cuentas:
  Activos · 30, Sin precio vigente · 6, Sin código del Estado · 8, Descontinuados · 2. Botones: **Carga masiva de
  precios** y **Nuevo ítem** (paneles deslizantes).
- **Ficha del ítem** `/catalogo/{id}`: datos del ítem (familia, unidad, tipo, marca, modelo, código del Estado, precio
  vigente desde cuándo, estado, «Lo usan: cotizaciones, presupuestos, compras y almacén») y la tabla **Historia de
  precios** (desde, hasta, precio, costo, margen, origen manual o carga masiva, vigente o histórico). Botones: **Nuevo
  precio**, **Editar**, **Descontinuar / Reactivar**.
- **Cotización → «Del catálogo»:** al elegir el producto se llenan solos el precio vigente y el costo referencial
  (se pueden cambiar).

## Operaciones

| Operación | Campos | Valida | Qué produce |
|---|---|---|---|
| Nuevo ítem | código (≤ 40, único, en mayúsculas), descripción (≤ 500), descripción corta, familia, unidad, línea de solución, marca, modelo, código del Estado, es servicio, pide serie; precio, costo y margen opcionales | código único; descripción | ítem activo; si trae precio, su primer precio vigente desde hoy |
| Nuevo precio | precio (> 0), costo referencial, margen objetivo (0 a 99.99), vigente desde | lo mismo | el vigente se cierra el día anterior; el nuevo queda vigente (uno solo por ítem, lo asegura la base) |
| Carga masiva de precios | vigentes desde; filas pegadas «código, precio, costo, margen» (tabulador, punto y coma o coma; encabezado opcional) | cada fila: el código existe y el precio es un número | un precio nuevo por fila válida (origen «carga»); aviso con cuántos se cargaron y las filas con error («Fila 3: no hay ningún ítem con el código «NOEXISTE».») |
| Editar | los del ítem salvo el código | — | — |
| Descontinuar / reactivar | — | — | el ítem deja de ofrecerse al cotizar y comprar; lo ya hecho no cambia |

## Datos

- Tabla nueva `familias_producto` (padre, código único, nombre, activa).
- Tabla nueva `producto_precios` (ítem, moneda, precio, costo referencial, margen objetivo, vigente desde, vigente
  hasta, es vigente, origen, auditoría), con un solo vigente por ítem.
- `productos` suma familia, línea de solución, marca (texto), modelo y `cubso_id` (un código del Estado principal).
  El DBML tiene `marcas` y `producto_cubso` como tablas aparte; aquí son columnas.
- `CatalogoService` suma `precioVigente()` para cotizaciones y presupuestos. Permisos `crm.catalogo.ver` y
  `crm.catalogo.gestionar`.

## Reglas y números

- CAM-D4M: 1,095.00 desde el 05/01/2026 (cerrado el 31/08/2026) y 1,180.00 desde el 01/09/2026 por carga masiva, costo
  850.00, margen objetivo 28 %. Al cotizarla, la línea trae 1,180.00 y 850.00.
- Demo: 17 familias (4 de primer nivel), 11 códigos del Estado inventados, 32 ítems (30 activos, 2 descontinuados), 6
  sin precio vigente, 8 bienes sin código del Estado, 10 con dos vigencias.

## Supuestos sin confirmar

- La carga masiva es «pegar desde Excel», no subir un archivo.
- Un solo código del Estado principal por ítem.
- Los servicios no necesitan código del Estado (no cuentan como faltantes).
- Quedan fuera: la vista «Por catalogar» (líneas libres de las cotizaciones para volverlas ítems), la pantalla
  «Equivalencias del Estado», marcas como tabla y proveedores por ítem (`producto_proveedores`, llega con Compras).

## Atajos tomados

- Sin acciones registradas ni bloqueo optimista: los botones escriben con el servicio o el modelo.
- Marca como texto y código del Estado como columna.
- El precio es siempre en soles.
- La lista calcula el código del Estado de cada fila con una consulta aparte.
- El seeder inserta los códigos del Estado directo en la tabla del núcleo.

## Dónde está en el código

`app/Modules/Crm/Catalogo/` (migración `crear_productos`, modelos `FamiliaProducto` y `PrecioProducto`,
`Services/PreciosCatalogoService.php`, `PrecioVigente.php`, `Filament/Resources/Catalogo/`); en Cotizaciones,
`Filament/LineasDeCotizacion.php` (precio sugerido); datos en `CarteraDemo` (`familias`, `cubso`, `productos`) y
`DemoSeeder::catalogo()`.
