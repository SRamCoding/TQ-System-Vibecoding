# Documento 06-A — Cómo está estructurado el sistema, explicado

**Programa:** Ordenamiento Organizacional y Sistemas de Gestión — Techquk S.A.C.
**Versión:** 2.0 · Agosto 2026

---

## Registro de cambios

| Versión | Fecha | Cambios |
|---|---|---|
| 1.0 | Ago 2026 | Los diez mecanismos explicados |
| **2.0** | **Ago 2026** | **Plazo con el equipo real · por qué el núcleo no se reparte** |
**Para quién:** Gerencia, jefaturas y cualquier persona que necesite entender el sistema sin
ser técnica.
**Documento técnico complementario:** `06-Arquitectura-Nucleo-Datos.md`

---

## 1. La idea en una página

Un sistema como este se parece a un edificio.

Los **departamentos** son lo que la gente usa: la pantalla de clientes, la de cotizaciones, la
de proyectos. Es lo que se ve y lo que se pide cuando alguien dice "necesito un sistema".

Pero antes de los departamentos hay que poner **los cimientos y las instalaciones**: la
estructura, el agua, la luz, los ascensores. No son ningún departamento en particular, y sin
ellos ninguno funciona.

Este documento explica los cimientos. Se llaman **el núcleo**.

> **Por qué importa entenderlos.** Los departamentos se pueden remodelar. Los cimientos no. Si
> se equivocan ahora, el costo de arreglarlos crece cada mes que pasa.

---

## 2. De dónde salen estas decisiones

No de un libro. De haber desarmado un ERP real que lleva **veintiséis años funcionando** en una
empresa constructora peruana.

Se restauró una copia de su base de datos y se analizó tabla por tabla: 1,916 tablas, 9,159
rutinas de programación, 1,254 relaciones entre ellas.

De ahí salieron dos cosas igual de valiosas: **cuatro decisiones que acertaron** y que conviene
copiar, y **seis errores que pagaron caro** y que conviene evitar.

Todo lo que este documento afirma sobre ese sistema está verificado con una consulta. Lo que es
propuesta nuestra está marcado como tal.

---

## 3. Los diez mecanismos, explicados

### 3.1 Identidad — que cada persona exista una sola vez

**El problema.** Casi todos los sistemas tienen una tabla de clientes y otra de proveedores.
Suena lógico hasta que aparece una empresa que es las dos cosas.

Entonces se carga dos veces, con dos códigos distintos. Cambia de dirección, se actualiza una y
se olvida la otra. Y cuando alguien pregunta *"¿cuánto le debo y cuánto me debe a esta
empresa?"*, hay que cruzar dos listas a mano.

**La solución.** Una sola lista de personas y organizaciones. La Municipalidad de Lambayeque
existe una vez. Ingram Micro existe una vez. A cada una se le asignan **roles**: cliente,
proveedor, aliado. Si mañana una es las dos cosas, se le agrega el rol y ya.

**La prueba de que funciona.** En el sistema analizado, esa lista es referenciada por 129
tablas — la más usada de toda la base. Y su catálogo de roles pasó de 9 en 1999 a 13 en 2021
**sin crear una sola tabla nueva**. Cuando en 2015 apareció la figura del practicante,
insertaron una fila.

Sin ese diseño, hoy tendrían trece listas de personas.

**Un detalle que da confianza.** Hasta los usuarios del sistema cuelgan de esa lista. Quien
entra al software es antes que nada una persona registrada. No hicieron excepción ni para su
propia tabla de acceso.

---

### 3.2 Autorización — quién puede aprobar qué

**El problema.** La forma natural de programar una aprobación es escribir dentro del sistema:
*"si la compra pasa de 10 mil soles, que la autorice el gerente"*.

Funciona hasta que gerencia dice *"ahora que sea 15 mil"*. Como la regla está adentro del
programa, hay que llamar al programador, que lo modifique, lo pruebe y lo instale. Un cambio de
política se convierte en un proyecto de dos semanas.

**La solución.** La regla vive en una tabla, como cualquier otro dato:

| Desde | Hasta | Moneda | Firmas | ¿Obligatoria? |
|---|---|---|---|---|
| 0 | 10,000 | soles | 1 | sí |
| 10,000 | 100,000 | soles | 2 | sí |
| 0 | 5,000 | dólares | 1 | sí |

Cuando gerencia pide el cambio, alguien edita la fila. Cinco minutos, sin programador.

**La lección más importante viene de su falla.** En el sistema analizado, esa estructura existe
y está bien diseñada. Pero al revisar las nueve órdenes de compra emitidas se encontró que
**ninguna pasó por un control capaz de detenerla**.

¿Por qué? Porque un campo llamado "obligatorio" estaba apagado. El sistema registraba las
firmas pero no las exigía. Era un libro de visitas, no un control.

Peor: dos proyectos compraron sin ninguna regla configurada, y nada lo advirtió.

**Lo que agregamos por eso.** Dos pantallas que ese sistema no tiene:

Una que muestre **las reglas vigentes en lenguaje claro**: *"Las órdenes en dólares del proyecto
Municipalidad sobre 5,000 requieren la firma de Gerencia General, que es indispensable."*
Averiguar eso en el sistema analizado exigió cruzar tres tablas.

Otra que **avise cuando un proyecto está comprando sin regla configurada.**

---

### 3.3 Estados — por dónde va cada documento

**El problema.** Un documento no está simplemente "abierto" o "cerrado". Una oportunidad pasa
por prospecto, calificada, propuesta, negociación, y termina ganada o perdida. Una orden de
compra pasa por registrada, aprobada, en almacén.

Si esas etapas son texto libre, cada persona escribe lo suyo y no se puede medir nada.

**La solución.** Un catálogo de etapas por cada tipo de documento, y una lista de qué saltos
están permitidos. Una oportunidad no puede pasar de prospecto a ganada sin haber presentado
propuesta.

**Una regla que vale oro.** Ninguna oportunidad se cierra como perdida sin elegir un motivo de
una lista cerrada: precio, plazo, especificación técnica, requisito administrativo, relación
con el competidor.

Ese campo obligatorio es lo que convierte el sistema en herramienta de aprendizaje. Sin él, a
fin de año nadie puede responder *"¿por qué perdemos?"*.

**Dos errores observados que se evitan.**

En el sistema analizado, el catálogo de etapas de una orden de compra tiene "Por Aprobar" en la
posición 6, **después** de "Anulado" que está en la 5. El orden no tiene sentido porque esa
etapa se agregó años después y le pusieron el siguiente número libre. Ya no se puede arreglar.

Solución: el código identifica, una columna aparte ordena. Cuesta una columna.

Y hay algo peor. En ese sistema, el único lugar donde consta que un pedido se atendió es una
casilla de sí/no. Sin fecha, sin usuario, sin cantidad. Un reporte de pendientes armado de una
forma muestra nueve; armado de otra muestra cero. Ambos correctos según su fuente.

Solución: cada cambio de etapa se registra con fecha, usuario y motivo.

---

### 3.4 Auditoría — quién hizo qué y cuándo

**Para qué sirve.** Para responder *"¿quién cambió este precio?"* y *"¿desde cuándo está
así?"*. En contratación pública no es opcional.

**Cómo se hace.** Cada registro guarda quién lo creó, cuándo, quién lo modificó por última vez
y cuándo. Y para las tablas sensibles —cotizaciones, órdenes, proyectos— se guarda además el
detalle de qué cambió exactamente.

**El error que se evita.** En el sistema analizado hay tres tablas donde la fecha de creación
está **completamente vacía**: 926 filas del plan contable, 809 de formatos de boleta. La
columna existe y nunca se llenó.

Y hay una trampa: los registros creados por caminos irregulares —cargas masivas, correcciones
manuales, arreglos de emergencia— son justo los que más interesaría rastrear, y son los que se
saltan la aplicación y dejan el campo vacío.

**Por eso la regla:** si un dato importa, el sistema no permite guardarlo sin él. Un control a
medias es peor que ninguno, porque da la falsa sensación de que algo está vigilado.

---

### 3.5 Catálogos compartidos — una sola lista para cada cosa

**El problema.** Monedas, unidades de medida, distritos, tipos de comprobante. Los usan todos
los módulos y ninguno es su dueño. Si el área comercial arma su lista de monedas y compras arma
otra, ya hay dos verdades.

**Lo que se observó.** En el sistema analizado conviven **dos tablas de unidades de medida** y
**dos registros de entidades tributarias**. Una de las de unidades tiene más datos que la otra
y no la usa nadie.

**Los catálogos del núcleo:** monedas, tipo de cambio diario, unidades de medida, ubicación
geográfica, tipos de documento de identidad, tipos de comprobante, líneas de solución de
Techquk, y el CUBSO.

**Sobre el CUBSO.** Es el Catálogo Único de Bienes, Servicios y Obras del Estado peruano:
57,610 códigos de uso obligatorio en toda contratación pública. Las entidades lo usan para su
Plan Anual de Contrataciones y los proveedores para inscribirse en el RNP.

**Es el único elemento del sistema analizado que aplica a Techquk tal cual**, porque vende al
Estado.

Con una advertencia: ahí se cargó en 2018 y nunca se actualizó. Por eso nuestro diseño incluye
un campo que registra qué versión está cargada.

---

### 3.6 Numeración — que los correlativos no se repitan

**El problema.** Cada cotización, orden y factura necesita un número. Si cada módulo lo genera
por su cuenta, tarde o temprano hay duplicados o saltos.

En contratación pública, un correlativo con hueco es una pregunta incómoda.

**El detalle técnico que importa.** Si dos personas guardan una cotización en el mismo segundo,
pueden obtener el mismo número. El sistema usa un mecanismo que bloquea el contador mientras lo
toma.

Suena menor y no lo es: es un error que aparece recién cuando hay varios usuarios trabajando a
la vez, o sea cuando el sistema ya está en producción y todos confían en él.

---

### 3.7 Adjuntos — los archivos van fuera de la base

**El problema.** Toda cotización tiene su PDF, todo proyecto sus actas y fotos de obra, toda
licitación sus bases.

Hay dos formas de guardarlos: dentro de la base de datos, o en el disco dejando en la base solo
la dirección de dónde están.

**Por qué la segunda.** En el sistema analizado eligieron la primera, y el resultado se puede
medir: una tabla de **280 MB con apenas 508 registros**. Son 565 KB por fila, cuando el resto
de las tablas va de 100 bytes a 2 KB — trescientas veces más pesada.

La consecuencia: **dos tercios del espacio de esa base de datos no son información de negocio.**
Son archivos y bitácoras internas del fabricante. De casi dos gigabytes, la operación real de la
empresa ocupa unos 350 MB.

Eso hace que cada respaldo diario arrastre cientos de megabytes que casi nunca cambian.

**Un agregado nuestro.** Cada tipo de adjunto puede marcarse como obligatorio. Así una
licitación no se marca como presentada sin tener cargadas las bases y la propuesta.

---

### 3.8 Notificaciones — que el sistema avise

**Para qué.** Cuando una orden espera aprobación, alguien tiene que enterarse. Cuando un
compromiso de servicio está por vencer, también.

**Por qué es núcleo y no un módulo.** Si cada módulo arma sus propios correos, terminas con
siete formatos distintos y ninguno se puede cambiar sin llamar al programador.

**Los tres avisos mínimos:** aprobación pendiente, cotización por vencer, y compromiso de
servicio próximo a expirar. Con esos tres, el mecanismo se justifica desde la fase dos.

---

### 3.9 Configuración — cambiar reglas sin tocar el programa

**Para qué.** El porcentaje de IGV, cuántos días vale una cotización por defecto, qué margen
mínimo dispara una alerta. Son cosas que cambian y no deberían requerir un programador.

**La decisión pendiente.** Si el sistema va a servir para una sola empresa o para varias.

Suena a detalle técnico y no lo es: **si Techquk contempla vender este ERP a otras empresas del
rubro, el aislamiento entre ellas se diseña ahora o no se diseña nunca.** Agregarlo después
implica tocar todas las tablas.

---

### 3.10 Seguridad — permisos que reflejan el organigrama

**Cómo funciona.** Los permisos no se asignan persona por persona sino por rol, y los roles
salen del organigrama del documento 03.

**Lo que se agrega.** Permisos acotados a un ámbito: alguien puede aprobar compras **de su
proyecto**, no de todos. Ver el almacén **de su sede**, no de todas.

**Un dato del sistema analizado que vale la pena.** Sus tablas de permisos fueron **las primeras
en recibir datos** de toda la implementación: el 4 de junio, casi un mes antes de que se
registrara la primera operación.

La lectura es clara y conviene copiarla: **primero se define quién puede ver qué, después la
gente empieza a registrar.**

---

## 4. Cuánto es y cuánto toma

| Concepto | Cantidad |
|---|---|
| Tablas del núcleo | unas 38 |
| Esfuerzo con **una persona** | 5 a 6 semanas |
| Esfuerzo con **dos personas** | 4 a 5 semanas |
| Esfuerzo con tres personas | 4 a 5 semanas — no mejora |
| Incluye | Pruebas automatizadas, datos de ejemplo, documentación |

### Por qué tres personas no lo hacen más rápido

`[S]` Es la parte del sistema que **peor se reparte**. Los diez mecanismos son decisiones
entrelazadas: cómo se identifica a una persona condiciona cómo se le dan permisos, que
condiciona cómo se resuelve una regla de aprobación.

Si tres personas lo construyen en paralelo, cada una toma criterios distintos y después hay que
reconciliarlos — que suele costar más que haberlo hecho de a dos.

**Lo que sí conviene** es que una o dos personas construyan el núcleo mientras las demás
preparan lo que sigue: el catálogo de productos y la ficha de personal se pueden diseñar sin
esperar a que el núcleo esté terminado.

**Por qué vale la pena antes que el CRM.** Los diez mecanismos los usan los diecinueve módulos.
Si se construyen dentro del CRM quedan con forma de CRM, y cuando llegue Compras en el mes seis
habrá que rehacerlos con medio año de datos encima.

Es la diferencia entre poner las tuberías antes de levantar las paredes, o romper las paredes
después.

---

## 5. Lo que este documento no resuelve

**No reemplaza el levantamiento.** Todo lo anterior es estructura. Qué campos lleva una
cotización de Techquk, qué etapas tiene realmente un proyecto de videovigilancia y quién aprueba
qué — eso sale de las entrevistas de la Fase 0, no de aquí.

**No convierte el análisis del sistema ajeno en requisitos.** Ese ERP es de una constructora.
Sus presupuestos de obra y análisis de precios unitarios no aplican a Techquk. Lo aprovechable
son los patrones estructurales y los errores documentados.

**Y hay un límite honesto que conviene tener presente.** Todo lo observado en aquella base
describe **cómo se registró** cada operación, no necesariamente cómo ocurrió en la realidad.

Aplicado a Techquk: el sistema que se construya va a registrar lo que la gente decida
registrar. Los controles del núcleo —firmas obligatorias, campos que no admiten vacío, etapas
validadas— existen precisamente para reducir esa distancia.

---

## 6. La idea que resume todo

Los cuatro aciertos que se observaron en veintiséis años de un ERP real son variaciones de la
misma decisión:

> **Poner en tablas lo que la tentación sería programar en código.**

Una lista de personas con roles asignables, en vez de una lista por cada rol.
Una fila por regla de aprobación, en vez de una instrucción por cada límite de monto.
Un catálogo de tipos de movimiento con opciones, en vez de una pantalla por cada variante.

En los tres casos el beneficio es el mismo: **agregar algo nuevo es insertar una fila, no
reprogramar el sistema.**

Y en los tres casos viene con la misma contra, que también se observó: **cada cosa que se
configura es una cosa que alguien puede configurar mal.**

Por eso el núcleo no incluye solo los mecanismos, sino también las pantallas que muestran su
estado y las alertas cuando quedan sin configurar.

La flexibilidad sin visibilidad se convierte en reglas fantasma que nadie recuerda haber
puesto. Eso fue exactamente lo que le ocurrió al sistema analizado — y es lo único de su
experiencia que Techquk puede evitar por completo desde el primer día.

---

*Fin del documento 06-A. El detalle técnico de cada tabla está en
`06-Arquitectura-Nucleo-Datos.md`.*