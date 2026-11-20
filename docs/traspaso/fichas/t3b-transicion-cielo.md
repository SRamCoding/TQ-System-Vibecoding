# Ficha · El tema «Sistema» sigue el cielo

- **Estado:** aprobada (MV-009)
- **Versión:** v1
- **Tramo de la ruta:** entre T3 y T4 (MV-011)
- **Aprobada:** 2026-11-20 · etiqueta `aprobado/t3b-transicion-cielo-v1`
- **Fuentes del diseño:** pedido de Sebastián (MV-010, MV-012, MV-013); la ambientación de clima y hora que ya existía
  (`LuzDelDia`, el clima del navegador en `data-tq-clima`).

## Qué hace

Los temas «Claro» y «Oscuro» siguen fijos, a elección de cada persona. En «Sistema» la pantalla ya no copia al sistema
operativo: sigue el cielo real del lugar. De día es el tema claro tal cual; de noche, el oscuro tal cual. En el
crepúsculo el ambiente cambia de forma continua, como afuera: luz dorada mientras el sol baja, naranja y rosado al
ocultarse, la hora azul y la oscuridad; al amanecer, al revés. No hay una hora fija: depende de la posición del sol en
la fecha y el lugar, y con nubes o lluvia oscurece antes y los colores se apagan.

## Decisiones de Sebastián

- **MV-010** (cruza módulos) · Reemplazada por MV-012.
- **MV-011** (ficha) · Se construye después del tramo 3.
- **MV-012** (cruza módulos) · Ubicación: la del navegador si ya está permitida; si no, la de la sede. No se pide permiso.
- **MV-013** (ficha) · Cambio continuo, como en la realidad, sin saltos ni hora fija.
- **MV-009** (general) · Aprobada sin revisión, como todas hasta terminar el sistema; los cambios vienen al final.

## Pantallas y navegación

Todas las pantallas del panel, solo con el tema «Sistema» (Mis preferencias → Apariencia, o el selector de tema).
Para verlo sin esperar al atardecer: en la consola del navegador, `tqCielo.cielo()` dice el estado (`p` de 0 a 1) y
`tqCielo.alturaDelSol(lat, lon, new Date('…'))` la altura del sol a otra hora.

## Operaciones

Ninguna: es comportamiento de la interfaz.

## Datos

Ninguna tabla nueva. Las sedes de la empresa usan su latitud y longitud (en la demo, la sede principal en −6.7714,
−79.8409 y la Oficina Lima en −12.0931, −77.0465). Si la sede no tiene coordenadas, se usa el parámetro
`interfaz.clima_ubicacion_respaldo`.

## Reglas y números

- Altura del sol con la aproximación de la NOAA. Crepúsculo: el sol entre +6° y −8°; p = (6 − altura) ÷ 14, de 0
  (día) a 1 (noche). Nubes o niebla restan 3° a la altura; llovizna, lluvia o tormenta, 5°.
- p < 0.5: tema claro con un velo que multiplica, de color dorado → naranja → rosado → violeta → azul noche, cuya
  opacidad sube hasta 0.88. p ≥ 0.5: tema oscuro con un velo que aclara (trama), azul grisáceo, de 0.5 a 0. En p =
  0.5 el tema cambia y todos los colores se funden en 10 s mientras un velo se apaga y el otro se enciende.
- Se recalcula cada 20 s y cada cambio de velo se funde en 20 s.
- Ejemplo: sede principal el 20/11/2026: 17:30 sol a 10.5° (día); 18:00 a 3.5° (p 0.18, dorado); 18:15 a 0.0° (p 0.43,
  rosado); 18:30 a −3.5° (p 0.68, ya en oscuro aclarado); 18:45 a −7.0° (p 0.93); 19:00 de noche.

## Supuestos sin confirmar

- Los límites del crepúsculo (+6° y −8°) y la paleta de colores son elección del agente.
- El paso del texto oscuro al claro no puede ser continuo: se funde en 10 s en el punto más oscuro.
- Con «reducir animaciones» los cambios son inmediatos (sin fundidos).

## Atajos tomados

- El cálculo del sol y los velos viven en una vista del núcleo con su script, sin pruebas.
- Los velos son capas encima de toda la pantalla (no se recolorean los componentes).

## Dónde está en el código

`app/Modules/Nucleo/resources/views/filament/cielo.blade.php`, enganchada al final del cuerpo en
`app/Providers/Filament/AppPanelProvider.php`; coordenadas de las sedes en `DemoSeeder::empresaYSedes()`.
