# Plan: oscuro atenuado y cajas más livianas (1.4t)

- **Estado:** terminado
- **Módulo(s):** Nucleo (interfaz)
- **Fase:** F1
- **Creado:** 2026-10-28 · **Actualizado:** 2026-10-28

## Objetivo

El modo oscuro deja de ser casi negro: un gris carbón (claridad 0,25) en todo el sistema, con tarjetas, tablas, bordes
y menús corridos igual, para que nada quede más oscuro que el fondo. Las cajas del inicio pesan menos en los dos temas,
sobre todo en primavera, y su nombre sigue cumpliendo AA.

## Contexto que leí

- Pedido del desarrollador (2026-10-28), tras ver las capturas de la 1.4s: «en el modo oscuro sí lo noto muy cargado las
  cajas y los iconos y creo que el hecho que es puro negro ya debería cambiar». Vio tres niveles del fondo oscuro con la
  app real (0,14 hoy, 0,20 y 0,25) y eligió **el más claro**; «las cajas ya bajarás su intensidad, en versión claro
  también bájalo, lo noto pesado», «sobre todo para la primavera, muy pesado».
- `docs/convenciones/interfaz.md`: «Identidad visual» (el azul de los botones en oscuro), «Barras», «Inicio» (1.4r y
  1.4s).
- `EstiloEstacion` (1.4r), `EntornoEstacion` (1.4s), `ColoresMarca`. Filament pinta el modo oscuro con su escala gris
  (`--gray-950` el fondo, `--gray-900` las tarjetas, `--gray-800` y `--gray-700` relieves y bordes).
- Nada de `pendientes.md` afecta.

## Decisiones

- **D1. Grises oscuros corridos, en todo el sistema y siempre** (también con la apariencia según la estación apagada: el
  negro es del modo oscuro entero). Solo en `html.dark` se redefinen `--gray-950` 0,25, `--gray-900` 0,29, `--gray-800`
  0,34, `--gray-700` 0,41 y `--gray-600` 0,47, con el matiz y la croma mínima de los grises de Filament. Se declaran una
  vez en `ColoresMarca::grisesModoOscuro()` y los pinta la vista de navegación, que está en todas las pantallas
  (también el ingreso). En modo claro no cambia nada.
- **D2. La barra superior oscura** sube de 0,30 a 0,32, para seguir más clara que el fondo. El tinte de la estación en
  oscuro (1.4s) usa las mismas claridades: fondo 0,25 y barra 0,32.
- **D3. Cajas más livianas.** Oscuro: siempre algo más claras que el fondo (0,30 a 0,38), con menos croma. Verano, un
  30 % menos de croma en los dos temas y una sombra más corta. Otoño e invierno, algo menos de croma en íconos y
  franja.
- **D4. Primavera, la más aligerada.** Claro: fondo casi blanco con un degradado leve, la ficha del ícono en pastel y el
  ícono en el color del área (antes blanco sobre una ficha intensa); franja y sombra suaves. Oscuro: la ficha media y
  el ícono claro.
- **D6. Pedido después de ver la app (2026-10-28):** «en verano sigue estando muy intenso el color de las cajas, quiero
  que quites el círculo que enmarca al ícono solo para verano, y en modo oscuro que el estilo de los colores para las
  cajas sea tipo fosforescente, tampoco exagerado, pero que sí se note». Verano: croma 0,10 como máximo y sin ficha (el
  ícono suelto, como en otoño). Oscuro, en las cuatro estaciones: un neón por área (`neon`, claridad 0,80) en el borde,
  un halo y el brillo del ícono; algo más con el mouse.
- **D7. Segundo pedido después de ver la app (2026-10-28):** «en modo claro no se distinguen las cajas del entorno,
  quiero que se pueda distinguir y que haya el efecto de 3D, pero tampoco exagerado; además, para los colores de las
  cajas en verano, una paleta más clara, tipo de playa». Claro, en las cuatro estaciones: borde fino y sombras del color
  de cada caja (`sombra`), luz arriba y sombra corta al pie por dentro; más con el mouse. Verano claro: fondo claro del
  área (cielo y mar) y una franja de arena al pie con matiz propio (`arena`, 85, igual en todas las áreas: la única
  excepción al matiz del área); nombre e ícono en el color del área.
- **D8. Corrección de D7 (2026-10-28):** «no me refería a eso, que por ejemplo el color de Mien sea del sol, un tono
  cercano pero que permita distinguir, y que los del Núcleo más cercano al color del agua de piscina; que permita
  diferenciar es lo más importante; es para verano esos colores». Se quita la franja de arena. En verano, cada área con
  su matiz de playa, en los dos temas: Mien sol 85, Núcleo piscina 195, ERP azul mar 245 (su turquesa, 216, quedaba
  pegado a la piscina) y CRM coral 20; 30° o más entre dos áreas, sin el ajuste del Núcleo (que solo cuidaba su azul
  noche). **Excepción a «el matiz es siempre el del área» (1.4r, D4), solo en verano.**
- **D9. Tercer pedido (2026-10-28):** «en verano que el reflejo del sol sea mayor que en primavera, que también quiero
  que tenga ese efecto; en otoño, a las cajas en la parte izquierda abajo ponle tipo hojas de otoño, pero que no se vean
  igual en todas las cajas, que cambien de colores y la disposición; en invierno un efecto de hielo; y dale un poco más
  de contraste a los íconos». Reflejo en primavera y verano (claro), mayor en verano. `AdornosDeEstacion`: hojas y
  escarcha por caja, fijas por su clave. Íconos: trazo 1,9 y degradados de otoño e invierno más oscuros (el de invierno
  ya no se desvanece a blanco).
- **D10. La barra superior (2026-10-28):** «que la barra superior tenga el patrón que te dije en las cajas: en primavera
  un reflejo de sol, en verano ese reflejo más intenso, en otoño que cada cierto tiempo caigan hojas, algo sutil, cada un
  tiempo razonable». Reflejo en los dos temas (en oscuro, tenue). Hojas: cada 45 s (la primera a los 6 s), 3 o 4, dentro
  de la barra y detrás de su contenido; nunca con la pestaña oculta ni con el movimiento reducido. **Excepción a
  «ningún bucle»** de la ambientación, anotada en `interfaz.md`. Invierno no se pidió: la barra queda como estaba.
- **D5. Lo que no cambia:** los matices de cada área, la estructura de cada estación (ficha, brillo, marca de agua,
  escarcha), el hover y lo de la 1.4q.

## Pasos

- [x] 1. Grises oscuros y barra superior (D1, D2), con su prueba (la página los trae; el texto gris claro de Filament
  sigue en AA sobre el fondo y las tarjetas).
- [x] 2. Cajas más livianas (D3, D4) y el tinte oscuro de la 1.4s a las nuevas claridades; `CajasEstacionTest` y
  `EntornoEstacionTest` (AA del nombre, cajas oscuras más claras que el fondo).
- [x] 3. Documentos (`interfaz.md`, mapa de etapas) y capturas reales de las cuatro estaciones en claro y oscuro.
- [x] 4. `composer check` en verde; plan a completados; PR.

## Criterio de aceptación

- Pruebas en verde con AA del nombre en 4 estaciones × 2 temas y el texto gris sobre el nuevo fondo oscuro.
- En las capturas, el modo oscuro es gris carbón y las cajas se ven más livianas, sobre todo en primavera.

## Bitácora

- 2026-10-28 — Plan escrito con el nivel «más claro» elegido. Rama `interfaz-oscuro-atenuado`, apilada sobre
  `interfaz-entorno-estacion` (1.4s).
- 2026-10-28 — Paso 2. Al bajar la croma del verano claro, el nombre blanco cayó de AA (4,36): se oscureció un poco
  (fondo 0,60 → 0,40) y quedó entre 4,9 y 7,2. En oscuro, el Núcleo se oscurece 0,08 en los tonos medios, y con 0,36 el
  otoño y la primavera quedaban al ras del fondo: arrancan en 0,34, fuera de ese tramo. Contraste del nombre: verano
  4,9–8,6; otoño, invierno y primavera, de 9,4 a 12,3.
- 2026-10-28 — D6. El desarrollador lo vio en la app y pidió el verano menos intenso, sin ficha, y el oscuro
  fosforescente. Se detuvo la corrida completa que estaba en curso (se repite al final). En oscuro, el verano del
  Núcleo con 0,36 quedaba al ras del fondo: arranca en 0,34. Con el primer neón (borde 55 %, halo 22 %) apenas se
  notaba en las capturas: se subió a 65 % y 30 %.
- 2026-10-28 — D7. Se detuvo otra vez la corrida completa. Con el verano de playa, el nombre pasa de blanco a oscuro y
  su contraste sube de 4,9 a 9,7. La prueba del matiz del área deja fuera solo la arena.
- 2026-10-28 — D8. Se detuvo otra vez la corrida completa. `EstiloEstacion::lch()` reúne el cálculo de cada parte (matiz
  de la estación o del área) y lo usan el CSS y el contraste del nombre. Contraste del nombre en verano: 8,6–8,8 en
  claro y 7,6–8,2 en oscuro. El coral y el azul mar quedan muy pálidos en claro (la pantalla no admite más croma tan
  cerca del blanco), pero se distinguen.
- 2026-10-28 — «Está bien ese color, pero atenúalo más, no tan intenso, y dale un reflejo como que le choca el sol a las
  cajas.» Verano claro: croma del fondo de 0,13 a 0,075; un destello arriba a la izquierda y un reflejo en diagonal (doble
  línea de luz). Solo en claro: en oscuro, con nombre blanco, un reflejo blanco le restaría contraste.
- 2026-10-28 — D9. Se detuvo otra vez la corrida completa. El primer intento dejó un hueco: el contenedor de los adornos
  era un hijo más de la caja (columna con espacio entre hijos) y la segunda línea del nombre se cortaba; ahora va fuera
  del flujo (`position: absolute`). Las hojas, de 46 a 66 px para que se vean. `AdornosEstacionTest`: fijas por caja,
  distintas entre cajas, escarcha a veces doble e íconos a 3:1.
- 2026-10-28 — «Más pequeñas las hojas y que tengan forma de hojas, parecen estrellas.» Hojas de 66 a 48 px y escala
  0,70–0,95; se quitó la de arce (chica parecía estrella); formas ovalada con punta, redondeada y de roble, con tallo y
  nervaduras.
- 2026-10-28 — D10. Se detuvo otra vez la corrida completa. La captura sin pantalla congela la animación en su primer
  cuadro (hojas invisibles): para la foto se fijaron a mitad de la caída. A 14 px parecían motitas: 18 px.
- 2026-10-28 — Cierre. `composer check`: pint y phpstan sin errores, 42 migraciones bien, pest 1925/1925 (9769
  aserciones). Sin revisión de arquitectura: no hay migraciones, módulos ni efectos entre módulos (un gancho de Filament
  más, `TOPBAR_START`, y clases de interfaz del Núcleo). El desarrollador aprobó el resultado en la app.
