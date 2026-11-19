# AGENTS.md — TechQuk MVP (modo rápido)

Este repositorio es una copia del sistema de Techquk (repo `SRamCoding/TechQuk-IntegralSystem`, congelado el
2026-11-19 en el commit `a5e5972`) para llegar **rápido** a un MVP funcional que se pueda ver y usar de punta a punta.
El repo original sigue siendo el serio: aquí se trabaja para ver el producto terminado, no para dejarlo listo para
producción.

Lo trabaja **Sebastián solo**, con sus agentes: nadie más del equipo entra a este repositorio. Las decisiones son
suyas y no hay áreas ni dueños que coordinar.

La pregunta que justifica el sistema sigue siendo la misma: **¿cuánto se gana realmente en cada proyecto?** Todo lo
que se construya tiene que poder responderla en pantalla al final.

## Cómo se trabaja aquí

Sebastián decide y mira; el agente construye. El ciclo es siempre el mismo: Sebastián pide algo, el agente lo
construye hasta que **se vea y funcione en la pantalla con datos de demostración**, se lo dice en pocas líneas con las
direcciones (URL) que tiene que abrir, y Sebastián lo prueba y pide cambios. Nada más.

Lo que **no** se hace en este repositorio:

- No se escriben ni se corren pruebas (no hay carpeta `tests/`). Tampoco phpstan ni CI.
- No hay planes en `docs/planes/`, ni entregas, ni ramas, ni PR. Se trabaja directo en `main`.
- No se endurece la seguridad: el usuario de desarrollo tiene todos los permisos y basta con eso. Los permisos nuevos
  se registran igual (las pantallas los piden), pero no se diseñan roles.
- No se optimiza. Si funciona y se ve bien con los datos de demo, está listo.

Lo que **sí** se hace, porque sin eso la app deja de funcionar o deja de responder la pregunta del sistema:

1. **Todo lo nuevo se ve.** Cada módulo o cambio llega con sus datos de demostración (seeder) para que Sebastián lo
   vea lleno, no vacío. Los datos son inventados y creíbles: nombres de empresas, proyectos y personas ficticios,
   montos en soles con dos decimales. Nunca datos reales ni nombres de personas reales de Techquk.
2. **Se respeta cómo se ve el sistema.** Las pantallas nuevas copian los patrones que ya existen (cajas con buscador,
   panel deslizante, portada con fotos, ficha con tablero, colores por área): `docs/convenciones/interfaz.md` y las
   pantallas vecinas. Si Sebastián pide un cambio visual, se hace; si no, no se rediseña nada por iniciativa propia.
3. **El dinero cuadra.** Montos en `decimal`, nunca `float`. Toda línea de costo cae en una partida de control o en
   gasto directo con su centro de costo (usa el selector de imputación que ya existe). Los totales que muestra una
   pantalla salen del mismo cálculo que usan las demás.
4. **La estructura de siempre, sin ceremonia.** Módulos en `app/Modules/{Nucleo,Crm,Erp}/<Modulo>`, la lógica en
   `Services/`, las pantallas en Filament llamando a esos servicios. Un módulo usa los servicios de otro, no sus
   modelos. Nombres del dominio en español. Esto no es por pureza: es para que el agente siguiente encuentre las
   cosas donde espera encontrarlas.
5. **Las migraciones se editan libremente** (no hay producción). Si un cambio exige reconstruir la base, el agente
   avisa antes, porque `migrate:fresh --seed` borra lo que Sebastián haya cargado a mano.
6. **Los agentes nunca tocan producción** ni servidores de Techquk. Nada de `ssh`, nada de credenciales reales.
7. **La IA no se llama de verdad en los datos de demo**: si una pantalla usa IA, el seeder deja resultados ya
   «sugeridos» para que se vean. Llamarla en vivo desde la pantalla sí se puede, si Sebastián la configuró en su
   `.env`.

## Cuando algo no está decidido

Mucho de lo que falta tiene preguntas abiertas (`docs/decisiones/pendientes.md`). En el repo original eso detenía
el trabajo; aquí no. El agente hace **una** pregunta corta a Sebastián con dos o tres opciones y la que recomienda.
Si Sebastián no está o dice «decide tú», el agente toma la más simple que siga mostrando el flujo completo, la
construye y la anota en su ficha de traspaso, en «Supuestos sin confirmar», para revisarla después.

Si la navegación de una pantalla no está clara, se pregunta (Sebastián quiere decidir cómo se navega).

## Verificación mínima antes de decir «listo»

No hay pruebas, pero el agente no entrega algo roto. Antes de avisar:

- `php artisan migrate --seed` (o el seeder del módulo) corre sin error;
- `php artisan route:list` no falla y `php artisan view:cache` compila las vistas;
- las páginas tocadas responden: con `php artisan serve --port=8001` levantado, se pide cada URL nueva y no devuelve
  error 500 (si hace falta sesión, revisar `storage/logs/laravel.log` después de que Sebastián la abra);
- `vendor/bin/pint` sobre lo tocado (el hook ya formatea cada archivo PHP que se edita).

Al terminar: la ficha de traspaso al día (ver abajo), commit en `main` con un mensaje claro en español (código y
ficha en el mismo commit) y `git push`.

## Traspaso al repo original (obligatorio)

Lo que se construye aquí termina construyéndose en serio en el repo original, con pruebas y seguridad, **sin volver
a decidir nada**. Para eso, todo lo que se aprende aquí queda escrito en `docs/traspaso/` (léelo: `README.md`). Es la
única ceremonia de este repositorio y no se salta:

- **Cada funcionalidad tiene su ficha** en `docs/traspaso/fichas/`, con `plantilla-ficha.md`, y su fila en
  `indice.md`. Se abre al empezar, no al final.
- **Cada pedido de Sebastián actualiza la ficha** en el mismo commit que el código: sus decisiones vigentes, la
  navegación, las operaciones con sus campos, los datos, las reglas con números, los supuestos y los atajos.
- **Los atajos se confiesan.** Cada vez que el agente hace algo rápido que en el repo original no pasaría (lógica en
  la pantalla, un estado como texto, un permiso sin revisar, un cálculo repetido), lo anota en «Atajos tomados». Lo
  que vale para todo va en `atajos-generales.md`.
- **Cada decisión de Sebastián se registra con número** en `docs/traspaso/decisiones.md` (`MV-001`…), con sus
  palabras, su alcance (ficha, cruza módulos o negocio) y el commit; la ficha la cita entre sus vigentes. El registro
  **nunca se reescribe**: si Sebastián cambia de idea, va una decisión nueva y la vieja pasa a «reemplazada por». Así
  el repo original tiene la historia completa, no solo el resultado. Lo que el agente eligió solo va en «Supuestos sin
  confirmar» de la ficha, nunca en el registro: no se mezclan.
- **Aprobar.** Cuando Sebastián dice que así le gusta, la ficha pasa a «aprobada» con fecha y commit, y el agente
  crea y sube la etiqueta `aprobado/<ficha>-v<n>` (`git tag` y `git push origin <etiqueta>`). Si después se cambia
  algo aprobado, la ficha sube de versión y dice qué cambió.
- **Cierre del MVP.** Cuando Sebastián diga que está terminado, el agente hace la auditoría de cierre de
  `docs/traspaso/COMO-IMPORTAR.md` antes de que se haga el zip: todo aprobado, nada en el código que no esté en una
  ficha, los supuestos juntos. El zip va con `.git`. Nada de `docs/traspaso/` se sube al repo original antes de ese
  día: la importación se hace una sola vez, al final.
- **Sin código en las fichas.** Describen comportamiento (el repo original pasa a FastAPI y Vue): las operaciones
  serán endpoints y la navegación será la del frontend.

## Qué construir y en qué orden

`docs/vibe/ruta-mvp.md`: lo que ya está, lo que falta en tramos y cómo se ve cada tramo cuando está listo.

## Dónde está el «qué» (referencia, no proceso)

La documentación del repo original viene completa y sirve para saber **qué** hace cada parte. Se lee solo lo que la
tarea necesita, por partes:

| Necesito… | Leer |
|---|---|
| Qué hace un flujo del ERP | `docs/diseno/v4/parte-4-flujo-NN.md` y sus fichas en `parte-6-fichas-documentos.md` |
| Qué hace el CRM y sus pantallas | `docs/diseno/v4/parte-2-crm.md` y `docs/analisis/bloques/crm-pantallas.md` |
| Los números de ejemplo de cada flujo (sirven para los datos de demo) | `docs/diseno/v4/parte-8-criterios-aceptacion.md` |
| Columnas de una tabla | `docs/diseno/techquk_modelo.dbml` |
| Cómo está hecho el código existente | `ARCHITECTURE.md` y `docs/convenciones/codigo.md` |
| Cómo se ve el sistema | `docs/convenciones/interfaz.md` |
| Un término del negocio | `docs/glosario.md` |
| Lo que estaba pensado para tareas a medio planear | `docs/planes/activos/` (solo como idea de qué hacer) |

Las reglas de proceso de esos documentos (planes, entregas, pruebas por niveles, revisor de arquitectura, reparto
por áreas, PR) **no aplican aquí**; manda este archivo.

## Lecciones del repo original que sí aplican (evitan horas de depuración)

- Una dependencia que usa un solo método va como parámetro de ese método o de `handle()`, no en el constructor: así
  no se cierran ciclos en el contenedor, que cuelgan la app sin error.
- En los closures de Filament (`modifyQueryUsing`, `defaultSort`, `color`…), los parámetros se llaman como Filament
  los inyecta (`$query`, `$record`, `$state`); con otro nombre llega un `Builder` vacío.
- En Livewire, `exception()` lleva `$e` y `$stopPropagation`; con otro nombre la página se cuelga.
- Un selector que ofrece solo activos incluye también el valor que el registro ya tiene, o el formulario no guarda.
- Para ocultar una acción de `AccionFilament::desde()` según la fila, `->hidden()`, nunca `->visible()`.
- La versión del bloqueo optimista se captura al abrir el formulario (`Campo::deControl()` + `fillForm`).
- Una acción nueva necesita su permiso sembrado (lo hace `PermisosSeeder` con el registro de acciones); después,
  `php artisan db:seed` se lo da al rol `desarrollo`. Si no, su botón no aparece.
- Nombres de clase, archivo y carpeta con mayúsculas exactas: corre en Linux.
- Contenido festivo o decorativo: nada de Halloween, nada contrario a la fe cristiana, sin santos.
- El desarrollador trabaja en Windows con WSL2 o Docker: si hace falta un script, en PHP.
