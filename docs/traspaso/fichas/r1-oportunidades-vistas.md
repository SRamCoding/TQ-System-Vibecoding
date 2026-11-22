# Ficha · Las vistas de Oportunidades

- **Estado:** en curso (ronda de cambios; espera su «así me gusta»)
- **Versión:** v1
- **Tramo de la ruta:** observaciones de Sebastián, quinta tanda
- **Fuentes del diseño:** `docs/vibe/pedidos/observaciones-5.md` (puntos 3, 4 y 6) y sus lienzos copiados en
  `docs/vibe/pedidos/lienzos/` (`embudo/`, `linea-de-tiempo/`); las vistas del repo original (planes 2.21b a 2.21d).

## Qué hace

Oportunidades tiene tres vistas, con pestañas arriba: **Mis oportunidades** (la que se abre), **Embudo de
oportunidades** y **Línea de tiempo**. Encima de las tres, las pestañas de la portada (Por calificar · Oportunidades ·
Licitaciones) y la fila de cifras de siempre.

## Decisiones de Sebastián

- **MV-057** (ficha) · Mis oportunidades por defecto; Embudo de oportunidades; sin Lista ni Faja 3D.

## Pantallas y navegación

- **Mis oportunidades** `/oportunidades` (antes «Tarjetas», en `/oportunidades/tarjetas`): las tarjetas de siempre.
- **Embudo de oportunidades** `/oportunidades/embudo` (antes «Tablero», que era la de entrada en `/oportunidades`).
- **Línea de tiempo** `/oportunidades/linea-de-tiempo`.
- **Direcciones viejas:** `/oportunidades/lista`, `/faja` y `/tarjetas` llevan a Mis oportunidades; `/tablero`, al
  Embudo. Un aviso que antes abría la Lista con su filtro ahora abre Mis oportunidades.
- Entrar al proceso Conseguir el negocio abre Mis oportunidades (su caja del medio).

## Supuestos sin confirmar

- **«Mis oportunidades»** cambió de nombre pero no de contenido: muestra las mismas tarjetas y filtros que «Tarjetas»
  (no se filtró a las de la persona).
- **Lo que hacía solo la Lista** se perdió con ella: la tabla con «Reasignar» en lote y los avisos de revisión con «Ver
  cuáles». Reasignar sigue en cada ficha.

## Atajos tomados

- **Lo que se borró:** las páginas `ListarOportunidades`, `FajaOportunidades` y `TableroAnterior`, el rasgo
  `AbreLaTablaDeUnLugar`, la clase `FajaDeVidrio` y las vistas `faja/pantalla`, `camara`, `hombrecito`, `caja`, `pila` y
  `despiece-script`. **Se quedó** lo que usa la ficha deslizante de las otras vistas: `TablaDeLaFaja`,
  `DespieceDeOportunidad` (con su vista `faja/despiece`) y los servicios `LugarFaja`, `FilaFaja`, `PaginaFaja`.
- Las direcciones viejas se desvían con una ruta registrada antes que las del panel.

## Dónde está en el código

`app/Modules/Crm/Oportunidades/Filament/VistasDeOportunidades.php` (las pestañas), `Filament/Resources/Oportunidades/`
(`OportunidadResource::getPages`, `Pages/TarjetasOportunidades.php`, `TableroOportunidades.php`,
`LineaDeTiempoOportunidades.php`), `resources/views/vistas/cabecera.blade.php`, la desviación en
`OportunidadesServiceProvider::register`.
