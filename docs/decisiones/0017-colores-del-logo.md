# 0017 — Los colores de la marca salen del logo

- **Estado:** aceptada
- **Fecha:** 2026-10-19
- **Decide:** el desarrollador

## Contexto

Los colores del sistema eran aproximados: salían de una foto de material impreso (`#3D4772`, `#A6DAE5`, `#CF92BB`),
y el pendiente «Identidad visual» esperaba el manual de marca o el logo en vectores. En la tarea 1.4b el desarrollador
entregó el logo (la T de tres piezas sobre fondo azul noche) y se dibujó en el sistema con sus colores exactos, que no
coincidían con los aproximados: la barra mostraba un logo turquesa y morado junto a botones de otro azul.

## Decisión

Los colores de la marca son los del logo: azul noche `#01006F` (principal), turquesa `#00DAFF` (acento, `info`) y
morado `#7C6EFF` (acento secundario, `morado`). El claro `#F6F0F0` queda. Se escriben solo en
`Nucleo\Navegacion\ColoresMarca`; el proveedor del panel, el logo y el agradecimiento del aniversario los leen de ahí.

## Consecuencias

- Botones, enlaces y cajas del inicio modular toman los colores del logo; el área CRM pasa de malva a morado y el ERP
  de menta a turquesa.
- El nombre del color de Filament `malva` pasa a `morado`.
- Los valores salen de los píxeles de un PNG, no de un archivo vectorial ni de un manual. Si Marketing entrega el
  manual de marca con otros códigos, se cambian en `ColoresMarca` y en ningún otro lugar.
- Los dibujos de la ambientación (D8 de la 1.4b) tienen su propia paleta y no cambian.

## Alternativas descartadas

- **Seguir con los aproximados hasta el manual de marca:** dejaba el logo y la interfaz en colores distintos, sin fecha
  para el manual.
- **Usar el turquesa como color principal:** es muy claro para texto blanco en botones; el azul noche da el contraste.
