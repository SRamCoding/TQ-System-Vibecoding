# Convenciones de IA

Fuente: especificación v4 (idea 4 y parte 7) y decisiones del desarrollo.

## Cuándo usar IA y cuándo no

Solo cuando la **entrada es desordenada** (una foto, un PDF, un Excel ajeno) o hace falta **criterio
para relacionar** (un pago con su factura, un comprobante con su hito). Lo que tiene una sola respuesta
correcta (costo unitario, penalidad, stock, cruce de tres vías, asientos) se programa de forma fija.

## Las reglas del mecanismo (M12)

1. La IA **sugiere** y una persona **confirma**. Nunca guarda sola. Todo lo que mueve dinero conserva
   la aprobación humana.
2. Siempre existe el **camino manual**; nadie queda bloqueado si la IA falla o está apagada.
3. Cada proceso se **activa o desactiva** en `ia_procesos`, y Gerencia autoriza qué datos pueden
   enviarse a un servicio externo: los tipos de adjunto y, sin adjunto, la lista de campos que el proceso declara.
   También autoriza adónde: los proveedores externos de sus modelos (`ia_proceso_destinos`). La autorización cae si
   cambia lo declarado, si el proceso pasa a un proveedor externo no autorizado, o si un proveedor autorizado cambia
   de implementación o de variable de clave (sería otro servicio u otra cuenta); el proceso encendido se apaga. Pasar
   a un modelo local no la hace caer. Los adjuntos sensibles y los campos de `camposSensibles()`
   (ADR 0016) **nunca** se envían.
4. Cada **llamada** al modelo queda en `ia_llamadas`, salga bien o no (inválida, error, reintento, respaldo):
   proveedor, modelo, versión del prompt, tokens exactos, duración y costo en USD al precio vigente ese día.
   Cada sugerencia queda en `ia_sugerencias`: origen (un registro; el adjunto, opcional), propuesta con su
   evidencia, seguridad por campo, explicación, qué hizo la persona y la llamada que la produjo.
5. **Tope mensual por proceso, en dólares:** antes de llamar se suma el gasto del mes desde `ia_llamadas`; si
   está bajo el tope se llama (la última puede pasarlo un poco) y, superado, el proceso se apaga solo hasta el mes
   siguiente y queda el camino manual. El monto lo fija Gerencia; sin monto decidido, el tope es 0.

## Cada proceso tiene dueño, versión y pruebas

- **Dueño:** una persona del área que sabe si la respuesta es correcta (por ejemplo, Tesorería para las
  notas por foto) y el responsable técnico, que la mantiene.
- **Versión:** el prompt se guarda versionado en `app/Modules/Nucleo/Ia/Prompts/<proceso>/vN.md` con
  fecha y motivo del cambio. Cada sugerencia registra la versión usada.
- **Pruebas:** entre 10 y 20 ejemplos reales anonimizados con la respuesta correcta esperada, en
  `tests/Ia/<proceso>/`. Antes de publicar una versión nueva se compara su acierto con la anterior.
  Estas evaluaciones se corren a mano o en un job aparte, **nunca** dentro de `composer check`.

## Proveedores y modelos

- Los proveedores (la Claude API, un modelo local, otra cuenta) y sus modelos son un catálogo en Configuración → IA.
  Los nombres de modelos son datos; su precio por token es un dato con fecha de vigencia y el pasado no se recalcula.
- Cada proceso usa el modelo por defecto del sistema o el suyo, y puede tener uno de respaldo para cuando el principal
  falla (no cuando se alcanza el tope). Los elige el administrador, nunca el usuario final.
- Cambiar el modelo de un proceso es como cambiar su prompt: antes, sus casos de prueba.
- Cada proveedor tiene un límite de llamadas en paralelo, aparte del tope.

## Evidencia y seguridad por campo

- Cada campo propuesto trae su evidencia en la salida estructurada: con adjunto, la página y el texto exacto; con
  registros, el registro y el campo. No se usa la función de citas de un proveedor.
- El mecanismo verifica la evidencia antes de mostrar la sugerencia; si no la encuentra, el campo queda en seguridad
  baja. Fotos y PDF escaneados: la evidencia es la página.
- La seguridad sale de señales comprobables (reglas fijas, evidencia encontrada, historial de correcciones); lo que
  diga el modelo puede bajarla, nunca subirla. Se muestra como alta, media o baja, nunca como porcentaje.
- Hoy (`SeguridadPorCampo`): **alta** con la regla fija del campo cumplida y la evidencia encontrada; **media** con una
  de las dos (o con una foto o página escaneada, que no se puede verificar, y la regla cumplida); **baja** sin ninguna,
  si la regla falla, si la evidencia no está o si el PDF no se pudo leer. Las reglas por campo las declara el proceso
  (`DefinicionProcesoIa::$reglas`).
- La cita se compara normalizada (`CitaIa`): espacios y saltos de línea, guiones de corte, guion blando, ligaduras y
  mayúsculas no cuentan. El texto de un PDF lo lee `TextoDeAdjunto` (`smalot/pdfparser`, ADR 0019: nunca se copia ni
  se parcha la librería).

## Cómo se arma una llamada

1. **Prompt fijo** de la versión vigente.
2. **Contexto variable:** las opciones válidas en ese momento (partidas del proyecto, acciones del
   puesto), para que el modelo elija entre opciones reales y no invente.
3. **Salida estructurada** con campos definidos, cada uno con su evidencia; nunca texto libre.
4. **Validación** de lo que vuelve (RUC de 11 dígitos, partida existente, montos que cuadran) antes de
   mostrarlo como sugerencia.

## Código

- Todo acceso al modelo de lenguaje pasa por `IaService` del núcleo y la interfaz `ClienteModeloLenguaje`; cada
  proveedor es una implementación registrada en el contenedor.
- Un módulo pide con `IaService::sugerir($proceso, $origen, $adjunto)`: revisa los candados (encendido, origen y
  adjunto permitidos, autorización vigente, modelo, tope, clave) y encola `SugerirConIa`; devuelve `ResultadoPedidoIa`
  (`Encolado` o por qué no, con su texto para la pantalla). El job vuelve a revisar los candados antes de llamar. Cada
  intento queda en `ia_llamadas`; la sugerencia, con número `SUG-AAAA-NNNNNN`, en `ia_sugerencias`.
- Acciones (`Nucleo/Acciones/Ia`): el catálogo (proveedores, modelos, precios, modelo por defecto) y cada proceso
  (modelos, encendido) con `ia.configurar`, del administrador; autorizar lo que sale y fijar el tope con `ia.autorizar`,
  de Gerencia. Un precio nuevo empieza hoy o después; el modelo por defecto no se desactiva; encender un proceso que envía datos fuera
  exige autorización vigente.
- **Regla para todo proceso: la sugerencia la resuelve el módulo.** Cada proceso declara el permiso de su módulo para
  resolver sus sugerencias (`DefinicionProcesoIa::$permisoResolver`) y qué registros se crean con ellas (`$produce`, del
  morph map). Al aceptarla o corregirla, **la acción del módulo que crea el registro** llama a `IaService::resolver()`
  dentro de su misma transacción, con ese registro como resultado; cualquier otro tipo se rechaza, y fuera de una
  transacción no se acepta. Rechazarla (no se usó, no se creó nada) es la acción del núcleo
  `nucleo.ia-rechazar-sugerencia`, con el permiso del proceso. No hay un permiso global para resolver.
- **Pantalla:** Configuración → IA (`InteligenciaArtificial`): procesos (estado, autorización, gasto, acierto) y
  proveedores con sus modelos y precios. Entran `ia.configurar` e `ia.autorizar`; cada bloque se muestra según el
  permiso. Sin bandeja de sugerencias: cada una aparece en el formulario de la acción de su proceso.
- **Un reintento nunca paga dos veces** (`SugerirConIa`): el pedido es único por proceso, origen y adjunto mientras
  espera (un doble clic no encola dos); mientras corre queda reservado (`WithoutOverlapping`, sin devolverlo a la cola:
  si la cola lo entrega de nuevo porque tarda más que su `retry_after`, la copia se descarta; la reserva vence si el
  worker muere); y con una sola excepción (`maxExceptions = 1`) un fallo después de pagar no repite la llamada. Si
  falla, aviso `nucleo.ia-pedido-fallido` (uno por proceso y día).
- **Su propia cola** (plan 1.11c): `SugerirConIa` va en la conexión y la cola `ia`, con su worker y un `retry_after`
  (960 s) mayor que su `timeout` (600 s). Un job de IA nuevo va en la misma cola.
- **Lo que sale, declarado:** los campos enviados son columnas reales del origen (nunca una relación ni un accessor) y
  se leen en crudo; las opciones del contexto salen solo con las listas y campos que el proceso declara
  (`opcionesEnviadas`), que entran en la huella: cambiarlos hace caer la autorización.
- **Permiso para resolver:** hoy `can()` global. Si un módulo da el permiso de su proceso por ámbito (unidad,
  proyecto), habrá que revisarlo con `SeguridadService` y los ámbitos del origen antes de usar ese proceso.
- **Para TI:** con la configuración en caché (`config:cache`), las claves `IA_CLAVE_…` quedan copiadas en la caché:
  cambiar una exige volver a generarla. Una llamada cortada por tiempo se registra con 0 tokens y costo 0; si el
  proveedor la cobra igual, el gasto del mes queda un poco por debajo de lo real.
- Cupo por proveedor (`llamadas_en_paralelo`) con un candado de la caché por llamada: sin cupo, el job espera y vuelve.
- Avisos: `nucleo.ia-tope-alcanzado` (uno por proceso y mes) y `nucleo.ia-proveedor-no-disponible` (uno por
  proveedor, motivo y día), al administrador; Configuración → Alertas cambia a quién.
- La clave de cada proveedor es un secreto de producción en manos de TI, en una variable de entorno. Nunca en el
  repositorio, en la base en claro ni en registros. Sin clave, camino manual sin error para el usuario.
- **Regla de operación:** cambiar el **valor** de una variable `IA_CLAVE_…` por la clave de otra cuenta u otro servicio
  exige avisar antes a Gerencia. El sistema no lo ve (solo conoce el nombre de la variable), así que la autorización no
  cae sola: Gerencia decide si la vuelve a dar.
- Reintento corto solo por saturación; crédito agotado (también el 400 «credit balance is too low») o clave inválida no
  se reintentan y avisan a su responsable, y pasan al respaldo solo si es de **otro** proveedor (la misma cuenta
  fallaría igual).
- **Sin precio vigente no hay llamada:** un modelo sin precio no se asigna (principal, respaldo ni por defecto) y, si
  lo pierde después, no se llama (camino manual y aviso «proveedor no disponible», motivo sin precio). Un modelo propio
  lleva su precio cargado en 0. Cada llamada guarda el precio que se tomó antes de llamar.
- Las pruebas usan una implementación falsa o `Http::fake`: **nunca** llaman al servicio real.
- La llamada nunca ocurre dentro de una transacción de base de datos.

Ver `/automatizacion-ia`.
