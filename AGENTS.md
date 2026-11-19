# AGENTS.md — TechQuk Integral System

ERP + CRM propio de Techquk S.A.C. (integrador tecnológico, Chiclayo). Reemplaza al ERP S10.
La pregunta que justifica todo el sistema: **¿cuánto se gana realmente en cada proyecto?**

Este archivo es un mapa, no una enciclopedia. Lee solo lo que la tarea necesita.

## Stack

PHP 8.3+ · Laravel · Filament · PostgreSQL 16 · Pest · Docker. Monolito modular.
Versiones exactas: `composer.json`, no los documentos de diseño.

## Comandos

```bash
composer check:tarea      # lo de abajo, pero solo las pruebas de lo que cambió la rama. Al cerrar cada tarea
composer check            # todo lo de abajo con todas las pruebas, en paralelo (~6 min). Una vez por etapa, al cerrarla
vendor/bin/pint           # formato
vendor/bin/phpstan analyse
php scripts/verificar-migraciones.php
php artisan test          # incluye tests/Arch (límites entre módulos y nombres)
```

No existe `.env.testing`: `--env=testing` apunta a la base de desarrollo. Las pruebas migran su propia
base (`RefreshDatabase`); nunca correr `migrate:fresh` a mano. Las de `tests/Concurrencia` lanzan procesos de verdad
contra su propia base, `techquk_concurrencia`, que crean y reconstruyen ellas mismas (nunca otra).

## Dónde está cada cosa

| Necesito… | Leer |
|---|---|
| En qué versión y estado está el análisis, y de dónde viene | `docs/analisis/` (empieza por su README) |
| Qué construir ahora y en qué orden | `docs/planes/mapa-etapas.md` y `docs/planes/activos/` |
| Cómo se organiza el código | `ARCHITECTURE.md` |
| Reglas de tablas y migraciones | `docs/convenciones/base-de-datos.md` |
| Reglas de PHP, servicios, efectos, acciones, concurrencia, pruebas | `docs/convenciones/codigo.md` |
| Reglas de IA (prompts, costos, datos) | `docs/convenciones/ia.md` |
| Colores e identidad visual de las pantallas | `docs/convenciones/interfaz.md` |
| Un término del negocio | `docs/glosario.md` |
| Qué se decidió y qué falta decidir | `docs/decisiones/` |
| Quién lleva cada área de la etapa 2 y cómo se hablan las áreas | `docs/decisiones/0020-costuras-etapa-2.md` |
| La guía del equipo para leer fácil (HTML; se abre con doble clic). Repite lo de aquí: si no coinciden, manda este archivo | `docs/equipo/` |
| El **qué** de un flujo | `docs/diseno/v4/parte-4-flujo-NN.md` + fichas en `parte-6` |
| Las pantallas del CRM | `docs/analisis/bloques/crm-pantallas.md` (diseño del 13 oct 2026) |
| Los números que una prueba debe dar | `docs/diseno/v4/parte-8-criterios-aceptacion.md` |
| Permisos, aprobaciones, alertas, integraciones | `docs/diseno/v4/parte-7-transversal.md` |
| Columnas de una tabla | `docs/diseno/techquk_modelo.dbml` (v2) |
| Qué cambió respecto del análisis de agosto | `docs/diseno/00-Reconciliacion-con-v4.md` |
| El paquete original de agosto o la evidencia de S10 (solo historia; no manda) | `docs/analisis/archivo/` |
| Servidor, despliegue, respaldos, monitoreo | `docs/operaciones/` |

No leas el PDF completo de `docs/diseno/fuentes/`: usa las partes en Markdown. Ahorra cupo.

## Reglas que no se negocian

1. **Precedencia:** convenciones > decisiones aceptadas > especificación v4 > documentos de agosto.
   Los fragmentos de código de los documentos de diseño son ilustrativos.
2. **Un módulo no toca los modelos de otro.** Usa sus `Services/` o escucha sus eventos.
3. **Toda línea de costo tiene destino:** una partida de control o el gasto directo con centro
   de costo. Nunca una línea sin imputación.
4. **Nunca `float`, nunca `char`, nunca código de negocio como PK, nunca `empresa_id`.**
   Toda llave foránea lleva índice.
5. **Si un dato importa, es `NOT NULL` y su unicidad se garantiza en la base.**
6. **Estados y transiciones son datos; cada efecto es una pieza separada.** Un documento
   aprobado no se edita; anular exige motivo y revierte los efectos de la última transición.
7. **Toda acción de usuario está registrada con sus campos y se puede ejecutar sin la interfaz.**
   La lógica vive en servicios, nunca en Filament.
8. **Cada flujo tiene su escenario de aceptación del v4 como prueba, con los números exactos.**
9. **La IA sugiere y una persona confirma.** Nada que mueva dinero se aprueba sin una persona.
   Las pruebas nunca llaman al servicio real de IA.
10. **Nada de datos reales** en el repositorio, en las pruebas ni en los registros. Nada de
    datos sensibles (sueldos, salud) en registros ni mensajes de error.
11. **Los agentes nunca tocan producción**: ni el servidor, ni sus credenciales, ni su base.
12. **No inventes respuestas a lo pendiente.** Si la tarea depende de algo en
    `docs/decisiones/pendientes.md`, detente y pregunta.

## Cómo trabajar

- **Entregas** (decisión del desarrollador, 2026-11-10; reemplaza «una tarea = una rama = un PR = una corrida
  completa»). Una **entrega** es un grupo de tareas relacionadas que juntas dan algo usable; no sigue las cuatro áreas
  del ADR 0020. Se define al empezar, con su lista de tareas. Se cierra cuando lo que agrupa se puede usar, o al llegar
  a lo primero de: 6 tareas, 7 días de trabajo o ~60 archivos cambiados (lo que se revisa de una sentada). Esos números
  se revisan después de 2 o 3 entregas.
- **Una rama por entrega**, `entrega/<nombre>`; sus tareas se apilan en ella. Cada tarea sigue con su fila en el mapa,
  su plan (que dice a qué entrega pertenece) y commit y push por paso con la bitácora al día. Una sesión por tarea.
  Antes de escribir código: plan en `docs/planes/activos/` con `docs/planes/plantilla.md`. La bitácora del plan es el
  punto de guardado: si la sesión se corta, la siguiente lee el plan y continúa.
- **Nombres de ramas** (minúsculas, guiones, en español): `entrega/` (una entrega = un PR), `plan/` (solo el plan de
  algo que no empezó), `spike/` (exploración que quizá nunca se fusione), `arreglo/` (un arreglo chico y urgente fuera
  de una entrega). Una rama viva se renombra en un buen momento, nunca cortando una sesión a mitad de un paso.
- Flujos de trabajo: `/nuevo-modulo`, `/nueva-migracion`, `/nuevo-documento`, `/nueva-accion`,
  `/automatizacion-ia`, `/cerrar-tarea`.
- **Pruebas por niveles** (decisión del desarrollador, 2026-11-12; antes, 2026-11-10, una corrida completa por
  entrega, y 2026-10-27, una por tarea):
  1. **En cada paso y commit:** solo los archivos de prueba de lo que se tocó (`php artisan test <archivos>`), pint y
     phpstan.
  2. **Al cerrar cada tarea:** `composer check:tarea`. Elige las pruebas con `scripts/pruebas-de-la-tarea.php`
     (`--listar` dice cuáles) y prueba solo lo cambiado desde su última corrida verde en esa copia (o desde
     `origin/main` si no hay una; con `--base=origin/main`, la rama entera). Si le tocan más de la cuarta parte de los
     archivos de prueba (pasa al cambiar el núcleo), corre todas en paralelo: en un solo proceso, 175 archivos tardaron
     una hora; las 227 en paralelo, media (desarrollador, 2026-11-15).
  3. **Al cerrar la entrega, antes del PR:** `composer check:tarea` otra vez, en verde. Sin corrida completa.
  4. **Al cerrar una etapa del mapa:** `composer check` completo, **una sola vez por etapa, en local**, en segundo
     plano (en paralelo: 6 procesos, cada uno con su base `techquk_test_test_N`).
  Si una tarea toca `tests/Pest.php`, `config/`, rutas o `composer.json`, `check:tarea` ya corre todas las pruebas
  hasta su siguiente corrida verde: conviene evitarlos.
  «La corrida completa ya no pues, solo lo que se cambió… hay que correrlo una vez por etapa» (desarrollador).
  Nunca dos corridas a la vez **en la misma copia**, y antes de lanzar una, que no quede un proceso viejo vivo:
  comparten la base y se rompen. Cada copia de trabajo tiene su propia base (ver «Varias sesiones en paralelo»), así
  que una corrida de otra copia no te rompe la tuya.
- Al terminar una tarea: `composer check:tarea` en verde, bitácora al día, y si cambió una regla, su documento. Al
  terminar la entrega: `composer check:tarea` otra vez en verde (ver «Cada entrega» en «Trabajo en equipo»). Al
  cerrar la etapa: `composer check` en verde.
- Verificaciones (pint, phpstan, pruebas) y commit en comandos separados o encadenados con `&&`, nunca con `;`: el
  2026-10-29 se subió un commit con pint fallando.
- Un comando encadenado con `&&` no se pasa por `| tail` ni `| head`: la tubería devuelve el código del último comando y
  el `&&` sigue aunque el primero haya fallado (pasó el 2026-11-05). Si hace falta recortar la salida, `set -o pipefail`.
- Una dependencia que usa un solo método (o un comando) va como parámetro de ese método o de `handle()`, no en el
  constructor: así no cierra ciclos en el contenedor, que cuelgan la app sin error (pasó el 2026-10-29).
  `ContenedorSinCiclosTest` los vigila.
  Excepción: un oyente que va en la cola (`ShouldQueue`) recibe solo el evento en `handle()` (`CallQueuedListener` no
  inyecta más); ahí el servicio se pide con `app()` dentro de `handle()` (pasó el 2026-11-06, plan 2.21).
- Una acción nueva suma su permiso a la lista exacta de `tests/Modules/Nucleo/CatalogosSeederTest.php`: si no, esa
  prueba solo falla en la corrida completa (pasó el 2026-11-06).
- Idioma: dominio en español (`cotizaciones`, `ImputacionService`); framework en su idioma.
  Nombres de clase, archivo y carpeta con mayúsculas exactas: producción corre en Linux.
- Las personas que nombra el v4 son personas reales de Techquk: en pruebas y ejemplos, nombres inventados, y nunca
  atribuirles funciones (quién hace qué no está decidido).
- Cada archivo de pruebas debe pasar solo: Pest comparte las funciones globales entre archivos, y una
  prueba que usa una función de otro archivo pasa en el conjunto y falla sola.
- En pantallas, la versión del bloqueo optimista se captura **al abrir** el formulario
  (`Campo::deControl()` + `fillForm`), nunca al guardar: Filament relee la fila y el conflicto no saltaría.
- Un selector que ofrece solo elementos activos incluye también el valor que el registro ya tiene: Filament
  rechaza un valor que no está entre las opciones y el formulario no se podría guardar.
- En los closures que recibe Filament (`modifyQueryUsing`, `defaultSort`, `color`…), los parámetros van con el
  nombre que Filament inyecta (`$query`, `$record`, `$state`), no en español: con otro nombre, Filament pide la clase
  al contenedor y llega un `Builder` vacío.
- En un componente Livewire, `exception()` lleva los parámetros con el nombre que Livewire inyecta (`$e`,
  `$stopPropagation`): con otro nombre los pide al contenedor, falla y la vuelve a llamar sin fin (la prueba se cuelga
  sin decir por qué).
- Para ocultar una acción de `AccionFilament::desde()` según la fila, `->hidden()`, nunca `->visible()`:
  `visible()` reemplaza la comprobación de permiso que ya puso `AccionFilament`.
- En pruebas, `Livewire::test()` no dibuja los componentes Livewire que hay dentro de una pantalla (las pestañas de
  una ficha, su historial): prueba el componente hijo directamente. Si no, la prueba pasa sin revisar lo que dice.
- En pruebas, una acción del encabezado de una sección se monta con la misma llamada que hace su botón:
  `call('mountAction', '<nombre>', [], ['schemaComponent' => 'form.<clave>'])`. `TestAction::schemaComponent()` no
  la encuentra y la prueba falla sin decir por qué.
- Un reemplazo masivo de un nombre (`sed` sobre todo el repositorio) excluye `tests/Modules/Nucleo/SinModoNavegacionTest.php`:
  su lista de palabras prohibidas no se renombra, o deja de vigilar la palabra que debía (pasó el 2026-10-27).
- `@js()` escribe un texto con comillas simples (`'dark'`, no `"dark"`): en pruebas, lo esperado se arma con
  `Js::from()`, no a mano.

## Trabajo en equipo

Desde la etapa 2 trabajan cuatro personas, cada una dueña de un área (ADR 0020): Sebastián (Presupuesto y costo),
Ander (Comercial), Lennart (Contratos y abastecimiento) y Yair (Obra y personas). Todo lo que pasa queda en el
repositorio:

> **Reparto aplicado (2026-11-16, decidido por Sebastián):** se terminó el modo de trabajar solo. Cuatro personas, dos
> a tiempo completo (Sebastián y Yair) y dos a tiempo parcial (Ander y Lennart); quién lleva qué está en el ADR 0020,
> «El reparto aplicado». Cada sesión trabaja para su dueño y solo en su área; lo de otra área se le pide a su dueño por
> la costura. El corte que lo precedió está en `docs/planes/completados/corte-reparto.md`.

- **Dónde va cada cosa:** lo que se decide dentro del área, en la sección «Decisiones» del plan de la tarea; lo que
  cruza áreas o crea o cambia una costura, en un ADR, decidido en equipo (la IA ayuda a proponer opciones) y avisando a
  quien la usa; si no hay acuerdo, desempata Sebastián; lo que depende de la empresa, en
  `docs/decisiones/pendientes.md` (y se pregunta, regla 12).
- **Cada tarea:** su fila en el mapa con su responsable, su plan con «Responsable» y «Entrega», en la rama de su
  entrega. Commit y push por paso, con la bitácora al día. Al terminar, `composer check:tarea` en verde.
- **Cada entrega** (decisión del desarrollador, 2026-11-10; pruebas, 2026-11-12): al cerrarla, el revisor de arquitectura
  (`revisor-arquitectura`) si hubo cambios de estructura, `composer check:tarea` en verde, y el agente sube la rama y
  avisa con los enlaces a los planes. El PR se titula `Entrega <nombre>: <resumen>` y su descripción lista las tareas
  y sus planes. El PR lo abre Sebastián y lo fusiona con un *merge commit*, y borra la rama. **Ningún agente abre ni
  fusiona PR.** Las demás entregas vivas traen `main` con *merge*, nunca con *rebase*.
- **Orden en el repositorio:** ninguna rama queda sin push al final del día; las ramas fusionadas se borran; las
  copias de trabajo (`git worktree`) que no se usan se quitan con `git worktree remove` desde la carpeta principal y
  se vuelven a crear con `php scripts/nueva-copia.php` cuando hacen falta.
- **Lo que el agente aprende trabajando va a este archivo o a `docs/convenciones/`, nunca a la memoria personal del
  agente:** esa memoria vive en la máquina de cada uno y los demás no la ven.
- **Un cambio en un módulo de otra área** lo revisa su dueño, aunque el ADR 0020 asigne esa pieza a otro.
- **Lo que se pospone deja su punto de extensión listo** (una interfaz, un registro ampliable, un servicio que la
  etapa futura solo completa): anotarlo como deuda no basta. Va en «Preparado para lo diferido» del plan.
- **Contenido festivo o decorativo:** respeta el cristianismo. Nada de Halloween ni nada contrario a la fe, y
  tampoco santos. Valen Navidad, Semana Santa, Año Nuevo, Fiestas Patrias, Día de la Madre y del Padre, cumpleaños y
  el aniversario de Techquk. Si una fecha es dudosa, se pregunta.
- **Las pantallas siguen los patrones que ya tiene el sistema** (`docs/convenciones/interfaz.md`: cajas con buscador,
  panel deslizante, los mismos componentes). Nadie rediseña la interfaz por su cuenta: la mejora visual se hace al
  final, a cargo de Sebastián.
- **Varias sesiones en paralelo** (plan 2026-11-04 «trabajo en paralelo»): cada sesión trabaja en su propia copia
  (`git worktree`, hermana de la carpeta principal), creada con `php scripts/nueva-copia.php <nombre>` desde la
  principal; la corre la persona, porque copia el `.env`. La carpeta principal queda para la app de Sebastián, en
  `main`: ninguna sesión cambia de rama ahí. En una copia, `tests/bootstrap.php` usa su propia base de pruebas
  (`techquk_test_<nombre>` y `techquk_concurrencia_<nombre>`). Las copias se coordinan solo por el repositorio:
  después de cada fusión, cada una trae `main` a su rama con *merge*. Dos corridas completas de copias distintas no se rompen,
  pero cargan la máquina: si `pgrep` muestra la de otra copia, mejor esperar o correr solo lo tocado.
- **El organigrama está en espera** (desde 2026-11-03): la empresa está definiendo el actual. No se toca su
  documentación, la fila «Organigrama real» de `pendientes.md` ni datos de unidades y puestos reales hasta que
  Sebastián lo entregue. Se construye con datos de prueba.

## Mantenimiento de este archivo

Cada regla aquí viene de un error real o de una decisión registrada. Si un agente se equivoca de
forma repetible, la corrección va aquí (si es breve) o en el documento que corresponda.

Las reglas de los agentes son del equipo (desarrollador, 2026-11-04):

- **Agregar una lección** (un error del agente y cómo evitarlo): en un PR, revisado por al menos un compañero.
- **Cambiar o quitar una regla**, aquí, en `CLAUDE.md` o en `docs/convenciones/`, o tocar lo compartido de `.claude/`
  (flujos, revisor, hooks, `settings.json`): se conversa en equipo antes, y el PR dice por qué cambió. Si no hay
  acuerdo, desempata Sebastián.
- `.claude/settings.local.json` es personal y no está en el repositorio: cada uno lo ajusta sin pasar por nadie.
