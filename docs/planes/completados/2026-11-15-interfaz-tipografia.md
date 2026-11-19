# Plan: escala de letra del prototipo y logo en Inter (1.4ab)

- **Estado:** terminado
- **Responsable:** Sebastián (interfaz, a su cargo por `AGENTS.md`)
- **Entrega:** `entrega/proyectos-portada`
- **Módulo(s):** Nucleo (vistas del panel)
- **Fase:** —
- **Creado:** 2026-11-15 · **Actualizado:** 2026-11-15

## Objetivo

Todas las pantallas usan la escala de letra del prototipo de Proyectos aprobado
(`docs/diseno/fuentes/prototipos/proyectos-portada-ficha.html`), el nombre del logo va en Inter negrita espaciada y la
línea bajo la barra superior es más fina.

## Contexto que leí

- `docs/convenciones/interfaz.md`, «Identidad visual» y «Letras».
- `nucleo::filament.logo` (el nombre en Audiowide, 2026-10-26) y `nucleo::filament.navegacion` (la barra superior).
- Sin pendientes que la afecten.

## Decisiones

- **D1.** (desarrollador, 2026-11-15, «me gusta el estilo de letra del primer prototipo para todo el proyecto») La
  letra sigue siendo Inter; cambia la escala: títulos de página de 1.5 rem en negrita, de sección de 0.9375 rem;
  etiquetas de grupo de la barra lateral chicas, en mayúsculas y espaciadas; encabezados de tabla y etiquetas de ficha
  chicos y en gris; cifras tabulares. Se aplica con CSS sobre las clases de Filament en una vista propia,
  `nucleo::filament.tipografia`, que el proveedor del panel pone en el `<head>` de todas las pantallas.
- **D2.** (desarrollador, 2026-11-15, «la 1 pero sabes que me gusta más el logo que pusiste en el prototipo») El nombre
  **TECHQUK** en Inter negrita, mayúsculas y espaciado ancho (0.12 em), como en el prototipo. Sale Audiowide y su
  carpeta `public/fonts/audiowide/`. La pieza gráfica del logo (las figuras animadas) no cambia.
- **D3.** (desarrollador, 2026-11-15, «la línea divisora horizontal que siempre aparece arriba un poquitito más fina»)
  La línea bajo la barra superior pasa a media línea (0.5 px: más fina en pantallas de alta densidad; en las de
  densidad 1, el navegador la dibuja de 1 px), con el mismo gris tenue, en claro y oscuro.
- **D4.** (desarrollador, 2026-11-15, «solo lo del color según si es ERP o CRM, eso nomás») El estado **«en curso»**
  (`TonoEstado::EnCurso`) toma **el color del área** de su documento, como en el prototipo de Proyectos: en el ERP,
  turquesa (letra `#0E7490` sobre `#E0F7FB`); en el CRM, el morado del área (`morado`, interfaz.md); en el núcleo, el de
  hoy. **Los demás tonos no cambian** (neutro y cerrado gris, por aprobar y alerta ámbar, aprobado verde, anulado rojo).
  El área sale de la clave del documento (`erp.` o `crm.`), sin que cada módulo la declare.

## Pasos

- [x] 1. Vista `nucleo::filament.tipografia` y su gancho en `AppPanelProvider`.
- [x] 2. Logo: el nombre en Inter negrita espaciada; fuera Audiowide.
- [x] 3. `docs/convenciones/interfaz.md`, «Letras» y «Escala de letra».
- [x] 4. `TipografiaTest`.
- [x] 5. Revisar en pantalla: que la línea que se afina sea la de la barra superior (Filament la dibuja con sombra) y
  que ningún título quede cortado.
- [x] 6. D4: el tono «en curso» con el color del área (ERP turquesa, CRM morado) y su prueba.
- [x] 7. `composer check:tarea` en verde.

## Preparado para lo diferido

Nada se pospone. Una pantalla nueva no escribe tamaños de letra propios: usa estos (anotado en `interfaz.md`).

## Criterio de aceptación

- `TipografiaTest`: la página trae la escala y la línea fina; el nombre del logo sin Audiowide; la carpeta de la letra ya
  no existe.
- En pantalla: títulos más compactos, etiquetas de la barra lateral en mayúsculas chicas, números alineados, el logo con
  el nombre en Inter y una línea más fina bajo la barra superior.

## Bitácora

- 2026-11-15 — Código escrito en una sesión en la nube **sin poder correr las pruebas**: el proxy de la sesión bloquea
  `api.github.com` y `codeload.github.com`, de donde Composer descarga los paquetes. Falta correr
  `composer check:tarea` y revisar en pantalla (pasos 5 y 6). Los nombres de clase de Filament que usa la vista
  (`fi-header-heading`, `fi-section-header-heading`, `fi-sidebar-group-label`, `fi-ta-header-cell-label`,
  `fi-in-entry-label`) se escribieron sin poder abrir `vendor/`: si alguno no existe en Filament 5, esa regla no hace
  nada y se corrige al revisar en pantalla.
- 2026-11-15 — D4 del desarrollador: solo el estado «en curso» toma el color del área (ERP o CRM), como en el
  prototipo de Proyectos; los demás tonos siguen igual. Paso 6 nuevo para hacerlo.
- 2026-11-15 — Pasos 5 y 6, ya con las pruebas corriendo. Revisadas contra `vendor/filament`: dos clases de la vista no
  existían en Filament 5 (`fi-ta-header-cell-label` y `fi-in-entry-wrp-label`); el encabezado de tabla es
  `fi-ta-header-cell` (más su botón de orden). La línea de la barra superior es el `ring-1` + `shadow-xs` de `.fi-topbar`:
  la regla la reemplaza entera. Un comentario CSS del logo nombraba la letra vieja y hacía fallar `TipografiaTest`.
  D4: `TonoEstado::colorFilament(?AreaModulo)`, `colorDeDocumento(clave)` y `Estado::colorEnPantalla()`, usado en todas
  las insignias de estado; prueba `TonoEnCursoPorAreaTest`. El ERP ya era turquesa (`info`); cambia el CRM a morado.
- 2026-11-16 — Verde en la corrida de toda la entrega.
