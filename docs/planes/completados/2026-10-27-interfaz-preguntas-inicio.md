# Plan: Preguntas y animación de las cajas del inicio

- **Estado:** completado
- **Módulo(s):** Nucleo (interfaz del inicio)
- **Fase:** F1
- **Creado:** 2026-10-27 · **Actualizado:** 2026-10-27

## Objetivo

Al dejar el mouse sobre una caja del inicio, en vez de la nube con la descripción, la caja crece un poco y muestra una
por una las preguntas a las que responde ese módulo; al terminar, su ícono hace una animación propia.

## Contexto que leí

- Pedido del desarrollador (2026-10-27). Ningún documento de diseño lo menciona.
- Hoy: cuatro cajas (`CajaModulo`, registradas en `NucleoServiceProvider`): Mien, Personas y empresas, Usuarios y
  organigrama, Configuración. La nube sale de `CajaModulo::descripcion`. Vistas `widgets/caja-modulo.blade.php` y
  `cajas-modulos.blade.php`; estilos de las cajas en `navegacion.blade.php` (crecen al pasar el mouse, quietas con
  animaciones reducidas: `interfaz.md`).
- No choca con el panel de aplicaciones (1.4p): ese flota a la derecha; esto es dentro de las cajas.

## Decisiones

Aprobadas por el desarrollador el 2026-10-27.

- **D1 · Dentro de la caja**, debajo del nombre, en lugar de la nube. Al pasar el mouse la caja crece algo más que hoy
  para que quepan.
- **D2 · 3 segundos por pregunta**; cada una entra, se queda y se va.
- **D3 · Al terminar las preguntas, la animación del ícono, y queda quieto** hasta sacar el mouse. Al volver a entrar,
  empieza de nuevo. Al sacar el mouse antes, se corta y la caja vuelve a como estaba.
- **D4 · En el celular, nada:** tocar la caja abre el módulo, como hoy.
- **D5 · Las preguntas** (literales):
  - Mien: «¿Qué tengo pendiente hoy?», «¿Qué espera mi aprobación?», «¿Cómo van los números de mi área?».
  - Personas y empresas: «¿Quién es este cliente y cómo lo contacto?», «¿Con qué proveedores trabajamos?», «¿Quién es el
    contacto principal de cada empresa?», «¿Dónde están sus sedes y sus cuentas bancarias?».
  - Usuarios y organigrama: «¿Quién tiene acceso al sistema?», «¿Qué puede hacer cada persona?», «¿Quién es jefe de
    quién?».
  - Configuración: «¿A cuánto está el dólar hoy?», «¿Qué monedas, unidades y catálogos usamos?», «¿Qué estados y motivos
    tiene cada documento?».
- **D6 · Animación final de cada ícono:** Mien, la vista sube como una cámara y aparece la estrella; Personas y empresas,
  las dos manos llegan desde los costados y se estrechan; Usuarios y organigrama, aparece la persona del medio, luego la
  de la derecha, luego la de la izquierda (ícono propio en tres piezas); Configuración, la rueda gira.
- **D7 · Módulos que lleguen después:** cada uno registra sus preguntas y su animación en su `CajaModulo`; sin animación
  propia, su ícono hace un pulso suave. Sin preguntas, la caja se comporta como hoy.
- **D8 · Con «reducir animaciones»**, las preguntas aparecen sin movimiento y el ícono no se anima.
- **D9 · Maqueta antes de programar** para aprobar el movimiento.

## Pasos

- [x] 1. Plan con las decisiones aprobadas.
- [x] 2. Maqueta de las cuatro cajas con sus preguntas y animaciones; aprobación del desarrollador.
- [x] 3. `CajaModulo` con preguntas y animación (y el pulso por defecto); las cuatro registradas; íconos propios en piezas
  (usuarios en tres, manos en dos); pruebas.
- [x] 4. Vistas y estilos: preguntas en la caja, crecimiento, tiempos, animaciones, D4 y D8; pruebas.
- [x] 5. Pedirle al desarrollador que lo mire en la app; ajustar.
- [x] 6. `revisor-arquitectura`; regla en `docs/convenciones/interfaz.md`; mapa; `composer check` en verde.

## Criterio de aceptación

- Cada caja del inicio muestra sus preguntas, una cada 3 segundos, y al final la animación de su ícono, que queda quieto.
- Un módulo registrado sin animación propia usa el pulso; sin preguntas, se ve como hoy.
- Con animaciones reducidas, nada se mueve.

## Bitácora

- 2026-10-27 — Rama `interfaz-preguntas-inicio`, apilada sobre `interfaz-foto-y-redes` (sin fusionar). Decisiones
  conversadas y aprobadas en la sesión de la foto y las redes.
- 2026-10-27 — Paso 2. Maqueta aprobada («perfecto»): https://claude.ai/artifact/CF5g6u7Sf7pVS13WALAksb. Ajuste pedido:
  la caja crece un poco menos (de unos 16 × 11 rem a unos 14,5 × 10 rem; letra de las preguntas 0,875 rem).
- 2026-10-27 — Pasos 3 y 4. `CajaModulo` con `preguntas` (no vacías) y `animacion` (`AnimacionCaja`: pulso por
  defecto, cámara, apretón, personas, rueda). Las cuatro cajas con sus preguntas. Íconos: `tq-tres-personas` (nuevo,
  en tres piezas) para Usuarios y organigrama, y `tq-apreton-manos` con sus trazos agrupados por mano. Cada caja va en
  una celda (`tq-celda-modulo`) que guarda el lugar; la caja crece con `inset` y, junto al borde del grupo, hacia
  adentro. El script (`window.tqCajaConPreguntas`, en `cajas-modulos.blade.php`) es de Alpine y solo corre con mouse.
  Regla en `docs/convenciones/interfaz.md`. Pruebas en `CajasPreguntasTest`; ajustadas `CajasModulosTest`,
  `CatalogosPantallaTest` y `MienTest` (la descripción de Mien ya no sale en la nube).
- 2026-10-27 — Paso 5, primer ajuste (desarrollador: «la parte de abajo de la caja se achata»; propuso desplazar las
  vecinas). La caja ya no crece encima: crece su celda (14,5 × 10 rem) en una fila flexible y empuja a las demás; con
  mouse, cada fila reserva 5,5 rem (`max-width: calc(100% - 5.5rem)`), así el número de cajas por fila deja siempre
  lugar para una crecida y ninguna salta de fila. La reserva vuelve 300 ms después de soltar, cuando la caja ya se
  achicó. En el celular no se reserva nada.
- 2026-10-27 — Paso 5, segundo ajuste: crece 3/4 de lo que crecía (de +5,5 × +3,5 rem a +4,125 × +2,625 rem): la caja
  crecida mide 13,125 × 9,125 rem y la reserva de cada fila es 4,125 rem.
- 2026-10-27 — Paso 5, tercer ajuste: las vecinas de una caja crecida quedan a la altura de su centro (`align-items: center`).
- 2026-10-27 — Paso 5, cuarto ajuste: el clic en la caja corta la animación (se abre el módulo en otra pestaña); el foco
  solo la arranca si llega con el teclado (`:focus-visible`), para que no se reanude sola al volver a la pestaña.
- 2026-10-27 — Paso 5, quinto ajuste: mientras pasan las preguntas no se ve el ícono, solo el nombre y la pregunta; el
  ícono vuelve al final con su animación (3 rem). Así la caja crece menos: 11,5 × 7,75 rem, reserva de 2,5 rem por fila.
- 2026-10-27 — Paso 5, sexto ajuste: al pasar al final, en todas las cajas, la cámara sube (el ícono entra desde 1,25 rem
  más arriba y el nombre baja 1,75 rem a su lugar, 700 ms). La cámara propia de Mien, más corta (−60 %).
- 2026-10-27 — Cierre. `revisor-arquitectura` atendido (ver la entrada del cierre de la 1.4o). `composer check` en verde
  (pint, phpstan, 40 migraciones, 1799 pruebas). Una corrida anterior falló por Postgres sin memoria de bloqueos al
  reconstruir 6 bases a la vez: `docker-compose.yml` sube `max_locks_per_transaction` a 256 (aprobado por el
  desarrollador). Plan a `completados/`; fila del mapa marcada.
