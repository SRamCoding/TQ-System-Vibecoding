# Licencias de los íconos

Los dibujos propios (`mano-paz`, `interruptor-*`, `persona-edificio`, `redes-sociales`, `tres-personas`) son de Techquk. `apreton-manos` es de Lucide (ISC, aviso dentro
del archivo), con sus trazos agrupados por mano (plan 1.4q).
`redes-sociales` es un boceto a línea de tres redes (Instagram, TikTok y X), dibujado aquí, no copiado de sus logos.

Los de las redes sociales (plan 1.4o) se copiaron tal cual, solo con `fill="currentColor"` para que tomen el color del
texto en los dos modos:

| Archivo | Origen | Licencia |
|---|---|---|
| `red-facebook`, `red-instagram`, `red-tiktok`, `red-youtube`, `red-x` | [Simple Icons](https://simpleicons.org) | CC0 1.0 (dominio público) |
| `red-linkedin` | [Material Design Icons](https://pictogrammers.com) (Simple Icons ya no lo trae) | Pictogrammers Free License (Apache 2.0) |
| `proyecto-cohete` (1.4ad) | Dibujo propio: una lista de tareas con un cohete, de la imagen que dio el desarrollador | De Techquk |

Son marcas de sus dueños: se usan solo para señalar el enlace a esa red.

Los de las aplicaciones del panel (plan 1.4p, paso 5) están en `../aplicaciones/`, copiados tal cual de los íconos de
marca de Fluent UI que publica Microsoft (`res-1.cdn.office.net/.../brand-icons/product/svg/<nombre>_48x1.svg`, 2026-10-28):
`word`, `excel`, `powerpoint`, `outlook`, `onedrive`, `teams` y `onenote`. Son marcas de Microsoft: se usan solo para
señalar el enlace a ese producto de Microsoft 365, como pide su guía de marca. La pantalla los incrusta como imagen
(`IconoAplicacion::imagen()`), sin pedir nada a otro sitio. El genérico es la inicial del nombre, sin dibujo.

Los que se sumaron con la 1.4x (2026-10-29), también en `../aplicaciones/`:

| Archivo | Origen | Licencia |
|---|---|---|
| `claude`, `whatsapp` | [Simple Icons](https://simpleicons.org) 13.21.0, solo el trazo, con el color de cada marca que publica Simple Icons | CC0 1.0 (dominio público) |
| `gmail`, `google_drive` (desde la 1.4z) | Los logos oficiales a color de Google (`Gmail_icon_(2020).svg` y `Google_Drive_icon_(2020).svg` de Wikimedia Commons, 2026-10-29), copiados tal cual salvo el `viewBox`, cuadrado y con margen | Marcas de Google |
| `linkedin` | el mismo de `red-linkedin` (Material Design Icons), con el azul de LinkedIn | Pictogrammers Free License (Apache 2.0) |
| `google_meet` (1.4z) | El logo oficial a color de Google (`Google_Meet_icon_(2020).svg` de Wikimedia Commons, 2026-10-29), tal cual salvo el `viewBox` cuadrado con margen | Marca de Google |
| `excalidraw` (1.4z) | [Simple Icons](https://simpleicons.org) 13.21.0, con su color | CC0 1.0 (dominio público) |
| `whiteboard` (1.4z) | Dibujo propio y neutro de una pizarra (Microsoft no publica el de Whiteboard en su CDN de íconos de marca; administración puede subir el oficial) | De Techquk |

Son marcas de sus dueños (Anthropic, Meta, Google y LinkedIn): se usan solo para señalar el enlace a ese producto, como piden sus guías de marca. (La 1.4x
corría un poco el de PowerPoint en la pantalla; la 1.4z lo quitó: el descentrado venía de cómo se acomodaba el enlace.)
