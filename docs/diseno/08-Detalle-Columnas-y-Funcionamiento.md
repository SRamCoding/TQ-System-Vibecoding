# Documento 08 — Detalle de columnas y funcionamiento

> ## ⚠ Versión 3.0 — ampliado con la especificación v4 (octubre 2026)
> Fuente de verdad: `Techquk-Sistema-CRM-ERP-v4.pdf` (21 set 2026), `Techquk-Flujograma-de-gastos-1.pdf` y `techquk-prototipo_4_.html`. Índice de cambios: `00-Reconciliacion-con-v4.md`.
> El cuerpo de este documento describe el modelo de agosto y sigue vigente **salvo** para las tablas listadas como obsoletas en el **Anexo v3.0** (al final). Allí están las tablas nuevas y los cambios a las existentes.


**Programa:** Ordenamiento Organizacional y Sistemas de Gestión — Techquk S.A.C.
**Versión:** 2.0 · Agosto 2026
**Complementa:** documento 07 v2.2 (modelo conceptual)

---

## Registro de cambios

| Versión | Fecha | Cambios |
|---|---|---|
| 1.0 | Ago 2026 | Columnas de 12 módulos · seis mecanismos · cinco flujos · cálculo del margen |
| **2.0** | **Ago 2026** | **Cuatro módulos nuevos · cinco entidades sueltas · dos módulos que faltaban · D5 resuelta** |

### Qué cambió

| Cambio | Origen |
|---|---|
| **ERP-10 Contratos** | Rol *Administración de Contratos*, con penalidad de S/ 250,498.54 |
| **ERP-11 Suscripciones** | Rol *Ciberseguridad y Monitoreo*, vigencias de hasta 372 días |
| **ERP-12 Desarrollo** | `partes_horas` aparece en cinco roles del organigrama |
| **ERP-13 Integración contable** | Consecuencia de reemplazar S10 en lugar de convivir |
| **CRM-05 y CRM-07** | Faltaban desde la v1.0 |
| Cinco entidades sueltas | `guias_remision`, `reclamos_garantia`, `traslados`, `contratos_laborales`, `partes_horas` |
| **D5 resuelta** | `[V]` 80 operaciones de importación en 2024 |

---

> ## ⚠ ADVERTENCIA SOBRE ESTE DOCUMENTO
>
> **Todo lo que sigue es inferencia.** A diferencia del documento 06 —donde el núcleo se apoya
> en patrones verificados en un ERP real— aquí se propone el detalle de módulos cuyos procesos
> **todavía no se han levantado**.
>
> Qué campos lleva una cotización de Techquk, cuántas etapas tiene un proyecto de
> videovigilancia y qué datos exige una participación en convocatoria son preguntas de la
> Fase 0. Este documento las responde con criterio profesional, no con evidencia.
>
> **Se estima que entre un tercio y la mitad de estas columnas cambiará** después del
> levantamiento. Eso no es una falla del documento: es su naturaleza.
>
> **Cómo usarlo bien.** Como punto de partida para las entrevistas —"¿esto es así?"— y como
> guía de dimensionamiento. **Cómo usarlo mal:** como especificación cerrada que alguien
> defienda porque ya está escrita.

---

## 1. Cómo funciona la base en su conjunto

Antes del detalle conviene entender los seis mecanismos que hacen funcionar todo lo demás.

### 1.1 Todo cuelga de una sola tabla de personas

`parties` es el centro. Cliente, proveedor, banco, colaborador, entidad convocante, agente de
aduanas y transportista son la misma tabla con roles distintos.

Cuando una tabla necesita apuntar a "el cliente", apunta a `parties`. Cuando necesita "el
proveedor", apunta a `parties`. La diferencia está en el nombre de la columna y en el rol que
esa entidad tiene asignado.

**Consecuencia práctica:** para saber todo lo que ocurrió con una organización —cuánto se le
vendió, cuánto se le compró, qué tickets abrió— se consulta un solo identificador.

### 1.2 Cabecera y detalle

Casi todo documento se guarda en dos tablas: la cabecera con los datos generales, y el detalle
con las líneas.

Una cotización tiene un cliente, una moneda y una fecha de validez —eso va en la cabecera— y
diez productos con su cantidad y precio, que van en el detalle.

**Regla que se aplica sin excepción:** los totales de la cabecera se calculan desde el detalle,
nunca al revés. Si alguien edita una línea, el total se recalcula.

### 1.3 Los documentos se encadenan por línea, no por documento

Este es el patrón más valioso del sistema analizado y se aplica en toda la cadena:

```
Requerimiento → Cotización proveedor → Orden de compra → Recepción → Factura
```

Cada eslabón guarda **a qué línea del anterior corresponde**, no solo a qué documento.

**Por qué importa.** Permite responder, para una cámara específica: quién la pidió, en qué
cotización se comparó, en qué orden se compró, en qué guía llegó y en qué factura se cobró.

Sin ese encadenamiento, se sabe que se compraron cámaras y que alguien pidió cámaras, pero no
que sean las mismas.

### 1.4 Los estados no viven en la tabla del documento

Un documento no guarda su estado como texto libre. Apunta al catálogo de estados del núcleo, y
cada cambio queda registrado en `historial_estados` con fecha, usuario y motivo.

**Consecuencia:** en cualquier momento se puede reconstruir por dónde pasó un documento y quién
lo movió. Es lo que exige la contratación pública.

### 1.5 El costo se acumula en un solo lugar

`proyecto_costos` es donde converge todo lo que un proyecto consume: materiales despachados del
almacén, **horas efectivas imputadas**, traslados, servicios de terceros y gastos de importación
prorrateados.

Cada uno de esos módulos escribe ahí. Ninguno calcula el margen por su cuenta.

**Por qué así.** Si cada módulo llevara su propia acumulación, el margen dependería de cuál
consultes. Con una sola tabla, la respuesta es única.

### 1.6 Nada se borra

Los documentos no se eliminan: se anulan cambiando de estado. Los catálogos no se borran: se
desactivan con una bandera.

**Por qué.** Un comprobante anulado tiene que seguir existiendo para la auditoría tributaria. Y
un producto que se dejó de vender tiene que seguir siendo consultable en las cotizaciones
antiguas.

---

## 2. Convenciones aplicadas en todas las tablas

Cada tabla de negocio lleva estas columnas sin excepción, y no se repiten en el detalle de cada
una:

| Columna | Tipo | Regla |
|---|---|---|
| `id` | `bigint` | Llave primaria autoincremental |
| `created_by` | `FK users` | **NOT NULL** |
| `created_at` | `timestamp` | **NOT NULL DEFAULT now()** |
| `updated_by` | `FK users` | Nullable |
| `updated_at` | `timestamp` | Nullable |

Y donde aplique:

| Columna | Cuándo aparece |
|---|---|
| `empresa_id` | Solo si la decisión D1 resuelve multiempresa |
| `estado_id` | En todo documento con ciclo de vida |
| `numero` | En todo documento con correlativo |

---

## 3. CRM-01 · Cuentas

### `cuentas`

Extiende un party con su información comercial.

| Columna | Tipo | Para qué |
|---|---|---|
| `party_id` | `FK` único | La organización. Uno a uno con `parties` |
| `ejecutivo_user_id` | `FK` | Quién la atiende |
| `clasificacion` | `varchar(1)` | A, B, C según importancia comercial |
| `sector` | `varchar(40)` | Público, privado, mixto |
| `nivel_gobierno` | `varchar(30)` nullable | Nacional, regional, local — solo si es público |
| `fecha_primer_contacto` | `date` | Desde cuándo se le sigue |
| `fecha_ultima_actividad` | `date` | Calculada desde `actividades` |
| `es_activa` | `boolean` | Si sigue en cartera |

`[S]` `nivel_gobierno` es específico del negocio de Techquk: los procedimientos de contratación
y los montos difieren entre una municipalidad distrital y un ministerio.

### `cuenta_verticales`

| Columna | Tipo | Para qué |
|---|---|---|
| `cuenta_id` | `FK` | La cuenta |
| `vertical` | `varchar(20)` | Gobierno, Educación, Salud |

---

## 4. CRM-02 · Oportunidades

### `oportunidades`

| Columna | Tipo | Para qué |
|---|---|---|
| `numero` | `varchar(20)` | Correlativo del núcleo |
| `cuenta_id` | `FK` | Cliente potencial |
| `nombre` | `varchar(200)` | "Videovigilancia Municipalidad de Lambayeque" |
| `linea_solucion_id` | `FK` | Qué línea del portafolio |
| `tipologia` | `varchar(4)` | T1 a T6 — anticipa la plantilla de proyecto |
| `monto_estimado` | `decimal(14,2)` | Valor probable |
| `moneda_id` | `FK` | Soles o dólares |
| `probabilidad` | `smallint` | 0 a 100 |
| `fecha_cierre_estimada` | `date` | Cuándo se decidiría |
| `estado_id` | `FK` | Etapa del embudo |
| `responsable_user_id` | `FK` | Dueño de la oportunidad |
| `origen_lead_id` | `FK` nullable | De dónde vino |
| `convocatoria_id` | `FK` nullable | Si nace de una licitación |
| `motivo_perdida_id` | `FK` nullable | **Obligatorio al cerrar como perdida** |
| `observacion_cierre` | `text` nullable | Detalle adicional |

`[S]` **`monto_estimado × probabilidad` es la fórmula del embudo ponderado.** Es el indicador
que Gerencia va a mirar primero, y la razón por la que `probabilidad` no puede ser texto libre.

### `oportunidad_lineas`

| Columna | Tipo | Para qué |
|---|---|---|
| `oportunidad_id` | `FK` | La oportunidad |
| `producto_id` | `FK` nullable | Si ya se sabe qué producto |
| `descripcion` | `varchar(255)` | Si aún no hay producto definido |
| `cantidad` | `decimal(14,4)` | Cuánto |
| `precio_referencial` | `decimal(14,2)` | Estimación |

### `oportunidad_competidores`

| Columna | Tipo | Para qué |
|---|---|---|
| `oportunidad_id` | `FK` | La oportunidad |
| `competidor_party_id` | `FK` | Quién compite — también es un party |
| `precio_estimado` | `decimal(14,2)` nullable | Si se conoce |
| `es_ganador` | `boolean` | Se marca al cerrar |

`[S]` **Ese `es_ganador` es lo que convierte el CRM en herramienta de inteligencia comercial.**
Después de veinte oportunidades se puede responder contra quién se pierde y a qué precio.

---

## 5. CRM-03 · Cotizaciones

### `cotizaciones`

| Columna | Tipo | Para qué |
|---|---|---|
| `numero` | `varchar(20)` | Correlativo |
| `oportunidad_id` | `FK` nullable | De dónde nace |
| `party_id` | `FK` | A quién se cotiza |
| `contacto_id` | `FK` nullable | Persona destinataria |
| `fecha_emision` | `date` | Cuándo se emitió |
| `dias_validez` | `smallint` | Cuántos días vale — por defecto desde parámetros |
| `fecha_vencimiento` | `date` | Calculada |
| `moneda_id` | `FK` | Moneda de la oferta |
| `tipo_cambio` | `decimal(10,4)` | Congelado al emitir |
| `condicion_comercial_id` | `FK` | Forma de pago y plazo |
| `estado_id` | `FK` | Borrador, emitida, aceptada, rechazada, vencida |
| `version_vigente_id` | `FK` | Cuál versión es la buena |

### `cotizacion_versiones`

| Columna | Tipo | Para qué |
|---|---|---|
| `cotizacion_id` | `FK` | La cotización |
| `numero_version` | `smallint` | 1, 2, 3 |
| `motivo_cambio` | `varchar(255)` | Por qué se revisó |
| `subtotal`, `igv`, `total` | `decimal(14,2)` | Totales calculados desde las líneas |
| `costo_total` | `decimal(14,2)` | Suma de costos, para el margen |
| `margen_porcentaje` | `decimal(5,2)` | Calculado |
| `emitida_at` | `timestamp` nullable | Cuándo se envió al cliente |

`[S]` **`costo_total` y `margen_porcentaje` viven en la versión, no en la cabecera.** Así se
puede ver cómo se fue erosionando el margen entre revisiones — dato incómodo y muy útil.

### `cotizacion_lineas`

| Columna | Tipo | Para qué |
|---|---|---|
| `cotizacion_version_id` | `FK` | **De la versión, no de la cotización** |
| `orden` | `smallint` | Posición en el documento |
| `producto_id` | `FK` nullable | Del catálogo |
| `descripcion` | `varchar(500)` | Texto del ítem |
| `cantidad` | `decimal(14,4)` | Cuánto |
| `unidad_medida_id` | `FK` | En qué se mide |
| `costo_unitario` | `decimal(14,4)` | Lo que cuesta |
| `precio_unitario` | `decimal(14,4)` | Lo que se cobra |
| `descuento_porcentaje` | `decimal(5,2)` | Descuento aplicado |
| `subtotal` | `decimal(14,2)` | Calculado |
| `es_opcional` | `boolean` | Si el cliente puede excluirlo |

`[S]` **`es_opcional` es común en propuestas técnicas:** permite ofrecer un alcance base con
adicionales, sin emitir dos cotizaciones.

---

## 6. CRM-04 · Licitaciones

### `convocatorias`

| Columna | Tipo | Para qué |
|---|---|---|
| `codigo_convocatoria` | `varchar(60)` | El identificador oficial del procedimiento |
| `entidad_party_id` | `FK` | Quién convoca |
| `objeto` | `varchar(500)` | Qué se contrata |
| `tipo_procedimiento_id` | `FK` | Licitación pública, adjudicación simplificada, etc. |
| `valor_referencial` | `decimal(14,2)` nullable | Monto publicado |
| `moneda_id` | `FK` | Moneda |
| `fecha_publicacion` | `date` | Cuándo se publicó |
| `cubso_id` | `FK` nullable | Clasificación del objeto |
| `url_fuente` | `varchar(500)` nullable | Enlace al portal |
| `estado_id` | `FK` | Publicada, en evaluación, adjudicada, desierta, nula |

### `convocatoria_cronograma`

| Columna | Tipo | Para qué |
|---|---|---|
| `convocatoria_id` | `FK` | La convocatoria |
| `etapa` | `varchar(60)` | Registro, consultas, absolución, presentación, buena pro |
| `fecha_inicio`, `fecha_fin` | `datetime` | Ventana de la etapa |
| `es_critica` | `boolean` | Si su vencimiento dispara alerta |

`[S]` **`es_critica` alimenta las notificaciones del núcleo.** Perder el plazo de presentación
por olvido es el error más caro y más evitable de este módulo.

### `participaciones`

| Columna | Tipo | Para qué |
|---|---|---|
| `convocatoria_id` | `FK` único | En qué convocatoria |
| `oportunidad_id` | `FK` nullable | Vínculo con el embudo |
| `decision` | `varchar(20)` | Participar, no participar, en evaluación |
| `motivo_no_participar` | `varchar(255)` nullable | Por qué se descartó |
| `monto_ofertado` | `decimal(14,2)` nullable | Con cuánto se presentó |
| `responsable_user_id` | `FK` | Quién la maneja |
| `estado_id` | `FK` | Estado del seguimiento |

`[S]` **`motivo_no_participar` es tan valioso como el resultado.** Registrar por qué se dejó
pasar una convocatoria —fuera de rubro, plazo imposible, requisito no cumplido— muestra dónde
está el techo comercial de la empresa.

### `participacion_resultado`

| Columna | Tipo | Para qué |
|---|---|---|
| `participacion_id` | `FK` único | La participación |
| `resultado` | `varchar(20)` | Ganada, perdida, descalificada, desierta |
| `ganador_party_id` | `FK` nullable | Quién adjudicó |
| `monto_ganador` | `decimal(14,2)` nullable | A qué precio |
| `posicion` | `smallint` nullable | En qué puesto quedó Techquk |
| `motivo_no_adjudicacion` | `varchar(255)` nullable | Precio, técnico, administrativo |

`[S]` **Estas cinco columnas son el activo del módulo.** Acumuladas, responden a qué precio hay
que competir en cada entidad — información que hoy no existe en ningún lado.

---

## 7. CRM-06 · Catálogo comercial

### `productos`

| Columna | Tipo | Para qué |
|---|---|---|
| `codigo` | `varchar(30)` único | Código interno |
| `codigo_fabricante` | `varchar(60)` nullable | Part number del fabricante |
| `descripcion` | `varchar(500)` | Nombre completo |
| `marca_id` | `FK` nullable | Fabricante |
| `familia_id` | `FK` | Agrupación |
| `linea_solucion_id` | `FK` nullable | Línea del portafolio |
| `unidad_medida_id` | `FK` | Unidad |
| `tipo` | `varchar(20)` | Bien, servicio, licencia, suscripción |
| `requiere_serie` | `boolean` | Si se controla por número de serie |
| `meses_garantia` | `smallint` nullable | Garantía estándar del fabricante |
| `es_activo` | `boolean` | Si sigue en catálogo |

`[S]` **`requiere_serie` es la bandera que conecta el catálogo con el almacén.** Decide si al
recibir una unidad el sistema exige registrar su número de serie.

`[S]` **`tipo` con cuatro valores** refleja que Techquk vende bienes, servicios de instalación,
licencias de software y suscripciones en nube. Cada uno se factura y se costea distinto.

### `producto_precios`

| Columna | Tipo | Para qué |
|---|---|---|
| `producto_id` | `FK` | El producto |
| `moneda_id` | `FK` | Moneda del precio |
| `costo_referencial` | `decimal(14,4)` | Lo que cuesta traerlo |
| `precio_referencial` | `decimal(14,4)` | Lo que se cobra |
| `margen_objetivo` | `decimal(5,2)` | Margen esperado |
| `vigente_desde`, `vigente_hasta` | `date` | Periodo de vigencia |

---

## 7-bis. CRM-05 · Actividades

### `actividades`

| Columna | Tipo | Para qué |
|---|---|---|
| `tipo_actividad_id` | `FK` | Visita, llamada, correo, reunión técnica, demostración |
| `actividable_type`, `actividable_id` | polimórfico | A qué cuelga: cuenta, oportunidad, proyecto o ticket |
| `asunto` | `varchar(200)` | Título de la actividad |
| `fecha` | `datetime` | Cuándo ocurrió o cuándo está prevista |
| `duracion_minutos` | `smallint` nullable | Cuánto duró |
| `resultado` | `text` nullable | Qué salió de ahí |
| `proxima_accion` | `varchar(255)` nullable | Qué sigue |
| `proxima_fecha` | `date` nullable | Para cuándo |
| `es_realizada` | `boolean` | Si ya ocurrió o está agendada |
| `responsable_user_id` | `FK` | Quién la ejecuta |

`[S]` **`proxima_accion` y `proxima_fecha` son el motor del seguimiento comercial.** Sin ellas,
una actividad es un registro histórico; con ellas, el sistema puede avisar que una oportunidad
lleva quince días sin movimiento.

### `actividad_participantes`

| Columna | Tipo | Para qué |
|---|---|---|
| `actividad_id` | `FK` | La actividad |
| `party_contacto_id` | `FK` nullable | Del lado del cliente |
| `user_id` | `FK` nullable | Del lado de Techquk |
| `es_principal` | `boolean` | Quién lideró la reunión |

---

## 7-ter. CRM-07 · Campañas y prospección

### `leads`

| Columna | Tipo | Para qué |
|---|---|---|
| `campania_id` | `FK` nullable | De qué campaña vino |
| `origen_lead_id` | `FK` | Sitio web, tienda, referido, feria, visita en frío |
| `nombre_contacto` | `varchar(150)` | Quién escribió |
| `organizacion` | `varchar(200)` nullable | Dónde trabaja, si lo declaró |
| `email`, `telefono` | `varchar` | Cómo contactarlo |
| `mensaje` | `text` nullable | Qué pidió |
| `party_id` | `FK` nullable | **Si se convirtió, a qué organización se enlazó** |
| `oportunidad_id` | `FK` nullable | Si generó una oportunidad |
| `estado` | `varchar(20)` | Nuevo, contactado, calificado, descartado |
| `motivo_descarte` | `varchar(120)` nullable | Por qué no prosperó |
| `asignado_user_id` | `FK` nullable | Quién lo atiende |

`[S]` **`party_id` es la columna que evita duplicados.** Un lead que se convierte no crea una
organización nueva: se enlaza a la que ya existe. Sin ese campo, cada formulario web del mismo
cliente genera una cuenta más.

### `campanias`

| Columna | Tipo | Para qué |
|---|---|---|
| `nombre` | `varchar(150)` | Nombre de la campaña |
| `tipo` | `varchar(40)` | Feria, correo masivo, publicidad, evento |
| `desde`, `hasta` | `date` | Periodo |
| `presupuesto` | `decimal(14,2)` nullable | Cuánto se invirtió |
| `objetivo` | `text` nullable | Qué se buscaba |

---

## 7-quater. ERP-10 · Contratos y garantías contractuales ★

> `[V]` **Origen:** rol *Administración de Contratos*, con indicador "penalidades incurridas:
> meta cero" y una penalidad registrada de **S/ 250,498.54**.

### `contratos`

| Columna | Tipo | Para qué |
|---|---|---|
| `numero` | `varchar(30)` | Correlativo interno |
| `numero_cliente` | `varchar(60)` nullable | El número que le da el cliente |
| `party_id` | `FK` | La contraparte |
| `cotizacion_id` | `FK` nullable | De dónde nace |
| `participacion_id` | `FK` nullable | Si nace de una buena pro |
| `objeto` | `varchar(500)` | Qué se contrata |
| `monto` | `decimal(14,2)` | Valor del contrato |
| `moneda_id` | `FK` | Moneda |
| `fecha_firma` | `date` | Cuándo se firmó |
| `fecha_inicio` | `date` | Desde cuándo corre el plazo |
| `plazo_dias` | `smallint` | Días de plazo contractual |
| `fecha_fin_contractual` | `date` | Calculada, y actualizada por adendas |
| **`penalidad_diaria`** | `decimal(14,2)` | **Cuánto cuesta cada día de atraso** |
| `penalidad_tope_porcentaje` | `decimal(5,2)` | Tope legal, normalmente 10% |
| `estado_id` | `FK` | Vigente, ampliado, cerrado, resuelto |
| `responsable_user_id` | `FK` | Quién lo administra |

`[S]` **`penalidad_diaria` es el campo que hace útil al módulo.** Con el monto, el plazo y la
fecha comprometida, el sistema puede mostrar cuánto cuesta cada día de atraso **antes** de que
ocurra. Sin ese campo, el módulo solo registra lo que ya pasó.

`[S]` **Y `penalidad_tope_porcentaje` importa** porque la penalidad no crece indefinidamente:
llegado el tope, la consecuencia deja de ser económica y pasa a ser la resolución del contrato.
Son dos conversaciones distintas y el sistema debería distinguirlas.

### `contrato_hitos`

| Columna | Tipo | Para qué |
|---|---|---|
| `contrato_id` | `FK` | El contrato |
| `descripcion` | `varchar(255)` | Qué hay que entregar |
| `fecha_comprometida` | `date` | Para cuándo |
| `fecha_real` | `date` nullable | Cuándo se cumplió |
| `dias_atraso` | `smallint` | Calculado al cumplirse |
| `monto_asociado` | `decimal(14,2)` nullable | Si el hito habilita un pago |
| `es_penalizable` | `boolean` | Si su atraso genera penalidad |
| `estado_id` | `FK` | Pendiente, cumplido, en riesgo, incumplido |

`[S]` **`es_penalizable` distingue los hitos de gestión de los contractuales.** No todo hito
interno tiene consecuencia económica, y mezclarlos hace que la alerta pierda credibilidad.

### `cartas_fianza`

| Columna | Tipo | Para qué |
|---|---|---|
| `contrato_id` | `FK` | El contrato que garantiza |
| `tipo` | `varchar(40)` | Fiel cumplimiento, adelanto, monto diferencial |
| `entidad_party_id` | `FK` | Banco o entidad emisora |
| `numero` | `varchar(60)` | Número de la carta |
| `monto` | `decimal(14,2)` | Monto garantizado |
| `moneda_id` | `FK` | Moneda |
| `emitida_el` | `date` | Fecha de emisión |
| **`vence_el`** | `date` | **Vencimiento — dispara alerta** |
| `es_renovable` | `boolean` | Si admite renovación |
| `estado` | `varchar(20)` | Vigente, renovada, devuelta, ejecutada |
| `adjunto_id` | `FK` nullable | El documento escaneado |

`[S]` **Una fianza que vence sin renovarse puede significar el incumplimiento del contrato.** Es
de las pocas alertas del sistema donde el costo de no avisar es inmediato.

### `penalidades`

| Columna | Tipo | Para qué |
|---|---|---|
| `contrato_id` | `FK` | El contrato |
| `contrato_hito_id` | `FK` nullable | Qué hito la originó |
| `dias_atraso` | `smallint` | Cuántos días |
| `monto_calculado` | `decimal(14,2)` | Días por penalidad diaria |
| `monto_aplicado` | `decimal(14,2)` | Lo que efectivamente se descontó |
| `motivo` | `text` | Por qué ocurrió el atraso |
| `es_imputable` | `boolean` | Si la causa fue de Techquk o del cliente |
| `estado` | `varchar(20)` | Estimada, notificada, aplicada, exonerada |

`[S]` **`es_imputable` es el campo que convierte el registro en aprendizaje.** Acumulado, dice
qué proporción de las penalidades vino de causas propias y cuáles de demoras del cliente o del
proveedor. Sin esa distinción, el indicador "penalidades meta cero" no se puede gestionar.

### `contrato_adendas` y `conformidades`

| Entidad | Columnas principales |
|---|---|
| `contrato_adendas` | `contrato_id` · `numero` · `tipo` (ampliación de plazo, de monto, de alcance) · `dias_ampliados` · `monto_adicional` · `resolucion` · `fecha` · `adjunto_id` |
| `conformidades` | `contrato_id` · `contrato_hito_id` · `fecha_solicitud` · `fecha_otorgada` · `observaciones` · `adjunto_id` |

`[S]` **La adenda actualiza `fecha_fin_contractual`.** Es la única forma legítima de mover el
plazo, y por eso conviene que quede como registro y no como edición del contrato original.

---

## 8. ERP-01 · Proyectos

### `proyectos`

| Columna | Tipo | Para qué |
|---|---|---|
| `numero` | `varchar(20)` | Correlativo |
| `nombre` | `varchar(200)` | Nombre del proyecto |
| `party_id` | `FK` | Cliente |
| `tipologia` | `varchar(4)` | T1 a T6 |
| `cotizacion_id` | `FK` nullable | De dónde nace |
| `participacion_id` | `FK` nullable | Si nace de una buena pro |
| `numero_contrato` | `varchar(60)` nullable | Contrato u orden de compra del cliente |
| `fecha_inicio`, `fecha_fin_contractual` | `date` | Plazo comprometido |
| `fecha_fin_real` | `date` nullable | Cuándo terminó de verdad |
| `monto_contratado` | `decimal(14,2)` | Valor del contrato |
| `moneda_id` | `FK` | Moneda |
| `responsable_user_id` | `FK` | Jefe de proyecto |
| `estado_id` | `FK` | Estado del proyecto |
| `plantilla_fase_id` | `FK` | Qué plantilla se instanció |

`[S]` **`fecha_fin_contractual` y `fecha_fin_real` separadas** permiten medir cumplimiento de
plazo, que en contratación pública determina penalidades.

### `proyecto_presupuesto`

| Columna | Tipo | Para qué |
|---|---|---|
| `proyecto_id` | `FK` | El proyecto |
| `partida_costo` | `varchar(60)` | Materiales, mano de obra, servicios, importación, otros |
| `descripcion` | `varchar(255)` | Detalle |
| `monto_presupuestado` | `decimal(14,2)` | Lo estimado |

### `proyecto_costos`

**La tabla más importante del ERP.** Donde converge todo el gasto real.

| Columna | Tipo | Para qué |
|---|---|---|
| `proyecto_id` | `FK` | El proyecto |
| `partida_costo` | `varchar(60)` | Contra qué partida se imputa |
| `origen_type`, `origen_id` | polimórfico | De dónde vino: movimiento de almacén, asignación de personal, orden de compra, gasto de importación |
| `fecha` | `date` | Cuándo se incurrió |
| `monto` | `decimal(14,2)` | Cuánto |
| `moneda_id` | `FK` | Moneda |
| `monto_local` | `decimal(14,2)` | Convertido a soles al tipo de cambio del día |
| `descripcion` | `varchar(255)` | Detalle legible |

`[S]` **El campo polimórfico `origen` es lo que hace auditable el margen.** Ante un costo
inesperado se puede navegar hasta el documento que lo generó, sin buscar a mano.

`[S]` **`monto_local` congelado** evita que el margen histórico cambie cuando se mueve el tipo
de cambio.

### `valorizaciones`

| Columna | Tipo | Para qué |
|---|---|---|
| `proyecto_id` | `FK` | El proyecto |
| `numero` | `smallint` | Correlativa dentro del proyecto |
| `periodo_desde`, `periodo_hasta` | `date` | Periodo valorizado |
| `porcentaje_avance` | `decimal(5,2)` | Avance acumulado |
| `monto_valorizado` | `decimal(14,2)` | Monto del periodo |
| `estado_id` | `FK` | Presentada, aprobada, observada, facturada |
| `comprobante_id` | `FK` nullable | Con qué factura se cobró |

---

## 9. ERP-02 · Compras

### `requerimientos`

| Columna | Tipo | Para qué |
|---|---|---|
| `numero` | `varchar(20)` | Correlativo |
| `proyecto_id` | `FK` nullable | Para qué proyecto |
| `unidad_organizativa_id` | `FK` nullable | Si es de consumo interno |
| `fecha_necesidad` | `date` | Para cuándo se necesita |
| `justificacion` | `text` | Por qué |
| `solicitante_user_id` | `FK` | Quién pide |
| `estado_id` | `FK` | Estado |

### `orden_compra_lineas`

| Columna | Tipo | Para qué |
|---|---|---|
| `orden_compra_id` | `FK` | La orden |
| `requerimiento_linea_id` | `FK` nullable | **El enlace línea a línea** |
| `producto_id` | `FK` nullable | Del catálogo |
| `descripcion` | `varchar(500)` | Texto del ítem |
| `cantidad` | `decimal(14,4)` | Cuánto se ordena |
| `cantidad_recibida` | `decimal(14,4)` | Cuánto llegó — actualizado por almacén |
| `cantidad_facturada` | `decimal(14,4)` | Cuánto facturó el proveedor |
| `precio_unitario` | `decimal(14,4)` | Precio pactado |
| `costo_importacion_prorrateado` | `decimal(14,4)` | Sumado por el módulo de importaciones |
| `costo_final_unitario` | `decimal(14,4)` | Precio más prorrateo — el costo real |

> `[V]` **Las tres columnas de cantidad vienen de evidencia.** En el ERP analizado, la tabla
> puente entre orden y requerimiento lleva `Cantidad`, `CantidadAtendida` y `CantidadAlmacen`.
>
> **Y con una advertencia verificada:** en esa instalación, `CantidadAtendida` está en nulo en
> las trece líneas registradas. La columna existe y nadie la llena. Si aquí se declara, tiene
> que alimentarse.

`[S]` **`costo_final_unitario` es lo que cierra el círculo del costeo.** Es el número que se
lleva a `proyecto_costos` cuando el material se despacha.

---

## 10. ERP-03 · Importaciones

### `ordenes_importacion`

| Columna | Tipo | Para qué |
|---|---|---|
| `numero` | `varchar(20)` | Correlativo |
| `agente_aduanas_party_id` | `FK` nullable | Quién nacionaliza |
| `incoterm` | `varchar(10)` | FOB, CIF, EXW — define qué gastos asume Techquk |
| `moneda_origen_id` | `FK` | Moneda de la compra |
| `valor_fob` | `decimal(14,2)` | Valor de la mercadería |
| `estado_id` | `FK` | Estado de la operación |

### `gastos_importacion`

| Columna | Tipo | Para qué |
|---|---|---|
| `orden_importacion_id` | `FK` | La operación |
| `concepto_id` | `FK` | Flete, seguro, derechos, IGV, agente, almacenaje |
| `proveedor_party_id` | `FK` nullable | Quién lo cobra |
| `monto` | `decimal(14,2)` | Cuánto |
| `moneda_id` | `FK` | Moneda |
| `tipo_cambio` | `decimal(10,4)` | Del día del gasto |
| `base_prorrateo` | `varchar(20)` | Valor, peso, volumen o unidades |

`[S]` **`base_prorrateo` por concepto y no global.** El flete se reparte por peso o volumen; los
derechos, por valor. Usar una sola base distorsiona el costo de los ítems pesados y baratos.

### `prorrateo_importacion`

| Columna | Tipo | Para qué |
|---|---|---|
| `gasto_importacion_id` | `FK` | Qué gasto |
| `orden_compra_linea_id` | `FK` | A qué ítem se imputa |
| `factor` | `decimal(10,6)` | Proporción que le toca |
| `monto_asignado` | `decimal(14,4)` | Resultado |

---

## 11. ERP-04 · Almacén

### `tipos_movimiento`

| Columna | Tipo | Para qué |
|---|---|---|
| `codigo` | `varchar(4)` | Identificador |
| `descripcion` | `varchar(100)` | Nombre legible |
| `sentido` | `enum` | Ingreso o egreso |
| `afecta_stock` | `boolean` | Si suma o resta saldo |
| `afecta_costo` | `boolean` | Si modifica el costo promedio |
| `requiere_documento` | `boolean` | Si exige comprobante del proveedor |
| `requiere_orden_previa` | `boolean` | Si exige orden de compra |
| `requiere_proyecto` | `boolean` | Si exige imputar a un proyecto |
| `requiere_serie` | `boolean` | Si exige registrar números de serie |
| `genera_costo_proyecto` | `boolean` | Si escribe en `proyecto_costos` |

> `[V]` **Este es el tercer patrón del documento 05.** En el ERP analizado, 55 tipos de
> movimiento con un solo formulario, gobernados por banderas como estas.

`[S]` **`genera_costo_proyecto` es un agregado propio.** Es la bandera que decide si un despacho
impacta el margen. Un traslado entre almacenes no debería; un despacho a obra, sí.

### `movimiento_lineas`

| Columna | Tipo | Para qué |
|---|---|---|
| `movimiento_id` | `FK` | La guía |
| `producto_id` | `FK` | Qué se mueve |
| `cantidad` | `decimal(14,4)` | Cuánto |
| `costo_unitario` | `decimal(14,4)` | A qué costo |
| `orden_compra_linea_id` | `FK` nullable | Contra qué orden se recibe |
| `proyecto_id` | `FK` nullable | A qué proyecto se despacha |

### `series`

| Columna | Tipo | Para qué |
|---|---|---|
| `producto_id` | `FK` | Qué modelo es |
| `numero_serie` | `varchar(80)` | El número del fabricante |
| `estado` | `varchar(20)` | En almacén, instalado, en garantía, de baja |
| `almacen_id` | `FK` nullable | Dónde está, si está en almacén |
| `proyecto_id` | `FK` nullable | En qué proyecto se instaló |
| `proyecto_ubicacion_id` | `FK` nullable | En qué punto exacto |
| `fecha_ingreso` | `date` | Cuándo entró |
| `fecha_instalacion` | `date` nullable | Cuándo se instaló |
| `garantia_hasta` | `date` nullable | Vencimiento de garantía |

**Restricción única:** `(producto_id, numero_serie)`.

`[S]` **Esta tabla no existe en el ERP analizado y para Techquk es central.** Es la que permite
responder qué cámara exacta está en qué poste, desde cuándo y si su garantía sigue vigente.

---

## 12. ERP-05 · Servicios

### `contratos_servicio`

| Columna | Tipo | Para qué |
|---|---|---|
| `numero` | `varchar(20)` | Correlativo |
| `party_id` | `FK` | Cliente |
| `proyecto_id` | `FK` nullable | De qué proyecto deriva |
| `tipo` | `varchar(30)` | Mantenimiento, soporte, monitoreo, arrendamiento |
| `vigente_desde`, `vigente_hasta` | `date` | Periodo |
| `monto_periodico` | `decimal(14,2)` | Cuánto factura por periodo |
| `periodicidad` | `varchar(20)` | Mensual, trimestral, anual |
| `renovacion_automatica` | `boolean` | Si se renueva solo |
| `estado_id` | `FK` | Estado |

`[S]` **Este es el módulo del ingreso recurrente**, que es el punto 3 de validación pendiente
del documento 00: *¿hay clientes que le pagan a Techquk todos los meses?* Si los hay, esta tabla
es la que lo hace visible.

### `tickets`

| Columna | Tipo | Para qué |
|---|---|---|
| `numero` | `varchar(20)` | Correlativo |
| `party_id` | `FK` | Quién reporta |
| `contrato_servicio_id` | `FK` nullable | Bajo qué contrato |
| `proyecto_id` | `FK` nullable | De qué instalación |
| `serie_id` | `FK` nullable | Qué equipo específico |
| `prioridad` | `varchar(20)` | Crítica, alta, media, baja |
| `tipo_incidencia` | `varchar(40)` | Falla, consulta, mejora, preventivo |
| `descripcion` | `text` | Qué pasa |
| `sla_respuesta_at` | `timestamp` | Cuándo vence el plazo de respuesta |
| `sla_solucion_at` | `timestamp` | Cuándo vence el plazo de solución |
| `respondido_at`, `resuelto_at` | `timestamp` nullable | Cuándo se cumplió |
| `cumplio_sla` | `boolean` nullable | Calculado al cerrar |
| `asignado_user_id` | `FK` nullable | Quién atiende |
| `estado_id` | `FK` | Estado |

`[S]` **Los dos plazos separados** reflejan cómo se pactan los SLA en la práctica: responder en
dos horas y resolver en veinticuatro no son el mismo compromiso.

---

## 13. ERP-06 · Facturación

### `comprobantes`

| Columna | Tipo | Para qué |
|---|---|---|
| `tipo_comprobante_id` | `FK` | Factura, boleta, nota de crédito |
| `serie` | `varchar(4)` | F001, B001 |
| `correlativo` | `int` | Número dentro de la serie |
| `party_id` | `FK` | A quién se emite |
| `proyecto_id` | `FK` nullable | De qué proyecto |
| `fecha_emision` | `date` | Fecha del comprobante |
| `fecha_vencimiento` | `date` | Cuándo se debe pagar |
| `moneda_id` | `FK` | Moneda |
| `tipo_cambio` | `decimal(10,4)` | Congelado |
| `subtotal`, `igv`, `total` | `decimal(14,2)` | Importes |
| `total_local` | `decimal(14,2)` | En soles |
| `comprobante_referencia_id` | `FK` nullable | Para notas de crédito |
| `motivo_nota` | `varchar(255)` nullable | Por qué se emite la nota |
| `estado_id` | `FK` | Estado |

**Restricción única:** `(tipo_comprobante_id, serie, correlativo)`.

### `comprobantes_electronicos`

| Columna | Tipo | Para qué |
|---|---|---|
| `comprobante_id` | `FK` único | El comprobante |
| `estado_sunat` | `varchar(20)` | Pendiente, enviado, aceptado, rechazado, observado |
| `codigo_respuesta` | `varchar(10)` nullable | Código devuelto |
| `mensaje_respuesta` | `varchar(500)` nullable | Descripción del resultado |
| `hash_cpe` | `varchar(100)` nullable | Resumen del comprobante |
| `xml_ruta`, `cdr_ruta` | `varchar(500)` nullable | Rutas en almacenamiento |
| `enviado_at`, `aceptado_at` | `timestamp` nullable | Marcas de tiempo |
| `intentos` | `smallint` | Cuántas veces se reintentó |

> `[V]` **`intentos` viene de evidencia.** En el ERP analizado, la tabla de errores de
> facturación electrónica tiene **1,763 registros contra 1,340 documentos emitidos** — más
> errores que documentos. Sin un contador de intentos, ese problema es invisible hasta que
> alguien revisa a mano.

### `detracciones`

| Columna | Tipo | Para qué |
|---|---|---|
| `comprobante_id` | `FK` único | El comprobante afecto |
| `codigo_bien_servicio` | `varchar(10)` | Código del anexo de SUNAT |
| `porcentaje` | `decimal(5,2)` | Tasa aplicada |
| `monto` | `decimal(14,2)` | Monto detraído |
| `numero_constancia` | `varchar(30)` nullable | Constancia de depósito |
| `fecha_deposito` | `date` nullable | Cuándo se depositó |

---

## 14. ERP-07 · Personal

### `colaboradores`

| Columna | Tipo | Para qué |
|---|---|---|
| `party_id` | `FK` único | La persona |
| `codigo` | `varchar(20)` | Código interno |
| `unidad_organizativa_id` | `FK` | Dónde está en el organigrama |
| `cargo` | `varchar(120)` | Puesto |
| `fecha_ingreso` | `date` | Desde cuándo |
| `fecha_cese` | `date` nullable | Hasta cuándo |
| `tipo_contrato` | `varchar(30)` | Planilla, locación, practicante |
| `es_tecnico_campo` | `boolean` | Si se asigna a obra |

### `colaborador_certificaciones`

| Columna | Tipo | Para qué |
|---|---|---|
| `colaborador_id` | `FK` | Quién |
| `certificacion_id` | `FK` | Cuál |
| `numero_certificado` | `varchar(60)` nullable | Número |
| `obtenida_el` | `date` | Cuándo |
| `vence_el` | `date` nullable | **Cuándo caduca** |
| `adjunto_id` | `FK` nullable | El certificado escaneado |

`[S]` **`vence_el` alimenta una alerta del núcleo.** Varias convocatorias públicas exigen
certificaciones vigentes del personal propuesto. Saber cuáles vencen en noventa días es
información comercial, no administrativa.

### `asignaciones`

| Columna | Tipo | Para qué |
|---|---|---|
| `colaborador_id` | `FK` | Quién |
| `proyecto_id` | `FK` | A qué proyecto |
| `rol_proyecto` | `varchar(60)` | Jefe, técnico, supervisor |
| `desde`, `hasta` | `date` | Periodo |
| `dedicacion_porcentaje` | `smallint` | Cuánto de su tiempo |
| `costo_hora` | `decimal(10,2)` nullable | Para imputar a `proyecto_costos` |

`[S]` **`dedicacion_porcentaje` es lo que evita asignar la misma persona a dos frentes el mismo
día**, que es el problema que el documento 01 menciona explícitamente.

---

## 14-bis. ERP-11 · Suscripciones y servicios gestionados ★

> `[V]` **Origen:** rol *Ciberseguridad y Monitoreo*, con contratos de nube de vigencias de hasta
> 372 días.

### `suscripciones`

| Columna | Tipo | Para qué |
|---|---|---|
| `numero` | `varchar(30)` | Correlativo |
| `party_id` | `FK` | El cliente que contrata |
| `proveedor_party_id` | `FK` | A quién le paga Techquk |
| `proyecto_id` | `FK` nullable | De qué proyecto deriva |
| `servicio` | `varchar(200)` | Qué servicio es |
| `vigente_desde`, `vigente_hasta` | `date` | El periodo contratado |
| `dias_vigencia` | `smallint` | Calculado — hasta 372 en los casos observados |
| **`costo_periodo`** | `decimal(14,2)` | **Lo que Techquk paga** |
| **`precio_periodo`** | `decimal(14,2)` | **Lo que Techquk cobra** |
| `periodicidad` | `varchar(20)` | Mensual, trimestral, anual |
| `disponibilidad_comprometida` | `decimal(5,2)` | Porcentaje acordado |
| `renovacion_automatica` | `boolean` | Si se renueva sola |
| `dias_aviso_renovacion` | `smallint` | Cuántos días antes alertar |
| `estado_id` | `FK` | Vigente, por renovar, renovada, dada de baja |

`[S]` **Que costo y precio estén ambos en la fila es lo que distingue este módulo.** En un
proyecto el costo se acumula aparte; aquí el margen se calcula por periodo con dos números que
viven juntos, porque ambos corren mientras la suscripción esté activa.

### `disponibilidad_servicio`

| Columna | Tipo | Para qué |
|---|---|---|
| `suscripcion_id` | `FK` | Qué servicio |
| `periodo` | `date` | Qué mes se mide |
| `disponibilidad_efectiva` | `decimal(5,2)` | Lo realmente logrado |
| `minutos_caida` | `int` | Tiempo fuera de servicio |
| `cumple` | `boolean` | Calculado contra lo comprometido |
| `origen_medicion` | `varchar(20)` | `manual` o `plataforma` |

`[P]` **`origen_medicion` deja abierta la decisión D7.** Empezar en `manual` es aceptable; el
campo permite migrar a medición automática sin cambiar la estructura.

### `suscripcion_renovaciones` e `incidentes_seguridad`

| Entidad | Columnas principales |
|---|---|
| `suscripcion_renovaciones` | `suscripcion_id` · `numero_renovacion` · `desde` · `hasta` · `costo_anterior` · `costo_nuevo` · `precio_anterior` · `precio_nuevo` · `motivo` |
| `incidentes_seguridad` | `suscripcion_id` · `fecha` · `criticidad` · `descripcion` · `contencion` · `resuelto_at` · `reportado_al_cliente` |

`[S]` **Guardar los cuatro montos en la renovación** permite ver si el margen se está
erosionando: los proveedores de nube suben precios y ese aumento no siempre se traslada.

---

## 14-ter. ERP-12 · Desarrollo de software ★

### `partes_horas`

**La tabla más importante de este módulo**, y la que aparece en cinco roles del organigrama.

| Columna | Tipo | Para qué |
|---|---|---|
| `colaborador_id` | `FK` | Quién trabajó |
| `proyecto_id` | `FK` | En qué proyecto |
| `asignacion_id` | `FK` nullable | Bajo qué asignación |
| `fecha` | `date` | Qué día |
| `horas` | `decimal(5,2)` | Cuántas horas |
| `tipo_trabajo` | `varchar(40)` | Desarrollo, análisis, pruebas, soporte, reunión |
| `descripcion` | `varchar(500)` | Qué se hizo |
| `es_facturable` | `boolean` | Si se imputa al costo del proyecto |
| `costo_hora` | `decimal(10,2)` | **Congelado al momento de imputar** |
| `aprobado_por` | `FK` nullable | Quién validó |

`[S]` **`costo_hora` se congela en la fila y no se lee de la ficha de la persona.** Si mañana
sube el sueldo, el costo histórico del proyecto no debe cambiar. Es el mismo criterio con que se
congela el tipo de cambio en una factura.

`[S]` **`es_facturable` distingue el trabajo que carga al proyecto del que no.** Una reunión
interna consume tiempo pero no siempre corresponde imputarla a un contrato de cliente.

`[V]` **Por qué es innegociable.** El **55.3% del portafolio son contratos híbridos**, con frente
de hardware y de software. Sin horas imputadas, ese segundo frente no tiene costo y el margen
sale inflado.

### Las demás entidades del módulo

| Entidad | Columnas principales |
|---|---|
| `especificaciones` | `proyecto_id` · `codigo` · `titulo` · `descripcion` · `criterios_aceptacion` · `prioridad` · `estado_id` · `estimacion_horas` |
| `especificacion_cambios` | `especificacion_id` · `fecha` · `solicitante` · `descripcion` · `impacto_horas` · `aprobado` |
| `casos_prueba` | `especificacion_id` · `codigo` · `pasos` · `resultado_esperado` · `version_id` · `resultado` |
| `defectos` | `proyecto_id` · `caso_prueba_id` · `severidad` · `descripcion` · `estado_id` · `version_detectada` · `version_corregida` |
| `versiones` | `proyecto_id` · `numero` · `fecha_entrega` · `notas_version` · `certificada_por` |
| `integraciones_cliente` | `proyecto_id` · `party_id` · `sistema_origen` · `sistema_destino` · `frecuencia` · `ultima_ejecucion` · `estado` |

`[P]` **Decisión D6:** si estas seis viven en el ERP o en herramienta especializada. `[S]`
`partes_horas` va en el ERP en cualquier caso, porque alimenta el margen.

---

## 14-quater. ERP-13 · Integración contable ★

### `cuentas_pagar`

`[S]` **Esta tabla faltaba con independencia de la decisión de reemplazar S10.** El modelo tenía
lo que te deben pero no lo que debes.

| Columna | Tipo | Para qué |
|---|---|---|
| `party_id` | `FK` | A quién se le debe |
| `orden_compra_id` | `FK` nullable | Contra qué orden |
| `tipo_comprobante_id` | `FK` | Factura, recibo por honorarios |
| `serie`, `correlativo` | `varchar`, `int` | El comprobante del proveedor |
| `fecha_emision`, `fecha_vencimiento` | `date` | Cuándo llegó y cuándo vence |
| `monto`, `saldo` | `decimal(14,2)` | Total y pendiente |
| `moneda_id`, `tipo_cambio` | `FK`, `decimal(10,4)` | Moneda y cambio congelado |
| `proyecto_id` | `FK` nullable | A qué proyecto se imputa |
| `estado_id` | `FK` | Pendiente, parcial, pagada, anulada |

### `pagos`, `asientos_generados` y `configuracion_contable`

| Entidad | Columnas principales |
|---|---|
| `pagos` | `cuenta_pagar_id` · `fecha` · `monto` · `medio_pago` · `cuenta_bancaria_id` · `numero_operacion` · `detraccion_id` |
| `asientos_generados` | `origen_type` · `origen_id` · `periodo` · `cuenta_contable` · `debe` · `haber` · `glosa` · `estado_exportacion` · `exportado_at` |
| `configuracion_contable` | `tipo_operacion` · `cuenta_debe` · `cuenta_haber` · `centro_costo` · `vigente_desde` |

`[S]` **`configuracion_contable` es lo que evita programar la contabilización.** Qué cuenta
corresponde a cada tipo de operación vive como dato editable, no como instrucción en el código.

### `bitacora_integraciones`

`[S]` **Sirve a las siete integraciones del programa**, no solo a la contable.

| Columna | Tipo | Para qué |
|---|---|---|
| `integracion` | `varchar(40)` | `facturacion_electronica`, `padron_ruc`, `contable`, `planillas` |
| `documentable_type`, `documentable_id` | polimórfico | Qué documento se procesó |
| `ejecutado_at` | `timestamp` | Cuándo |
| `resultado` | `varchar(20)` | Éxito, error, pendiente |
| `mensaje` | `text` nullable | Detalle del error |
| **`intentos`** | `smallint` | **Cuántas veces se reintentó** |

`[V]` **El contador de reintentos viene de evidencia.** En S10, la tabla de errores de
facturación electrónica tiene **1,763 registros contra 1,340 documentos**. Sin contador, eso son
1,763 líneas sueltas en lugar de "estos veinte documentos fallaron muchas veces".

---

## 15. Los cinco flujos que atraviesan el sistema

`[S]` Entender estos cinco recorridos explica el sistema mejor que cualquier lista de tablas.

### 15.1 De la oportunidad al proyecto

```
oportunidades → cotizaciones → cotizacion_versiones → (aceptada)
                                                        ↓
                                                    proyectos
                                                        ↓
                                             proyecto_fases (desde plantilla)
```

Cuando una cotización se acepta, el proyecto nace con el cliente, el monto y las líneas ya
cargadas. **Nadie vuelve a escribir esos datos** — que es donde hoy se pierde información entre
quien vende y quien ejecuta.

### 15.2 De la necesidad al costo

```
requerimientos → cotizaciones_proveedor → cuadros_comparativos → ordenes_compra
                                                                       ↓
                                              movimientos (ingreso) ← recepción
                                                       ↓
                                              movimientos (egreso a proyecto)
                                                       ↓
                                                 proyecto_costos
```

Cada eslabón guarda la línea del anterior. El costo llega al proyecto con su origen rastreable.

### 15.3 Del costo importado al margen

```
ordenes_importacion → gastos_importacion → prorrateo_importacion
                                                   ↓
                                    orden_compra_lineas.costo_final_unitario
                                                   ↓
                                    movimiento_lineas.costo_unitario
                                                   ↓
                                          proyecto_costos
```

El flete y los derechos terminan afectando el margen del proyecto sin que nadie los calcule a
mano. Es el criterio de salida que el documento 01 fija para la fase de abastecimiento.

### 15.4 Del proyecto al cobro

```
proyectos → valorizaciones → comprobantes → comprobantes_electronicos
                                  ↓
                            cuentas_cobrar → cobranzas
```

### 15.5 Del servicio de vuelta a la venta

```
proyectos → contratos_servicio → tickets → visitas_tecnicas
                                    ↓
                              oportunidades (renovación o ampliación)
```

Es la flecha punteada del documento 01. Vender a quien ya conoce a Techquk cuesta una fracción
de conseguir un cliente nuevo.

---

## 16. Cómo se calcula el margen

`[S]` Es la pregunta que originó el proyecto, así que conviene dejar explícito cómo se responde.

| Componente | De dónde sale |
|---|---|
| **Ingreso contratado** | `proyectos.monto_contratado` |
| **Ingreso facturado** | Suma de `comprobantes` del proyecto |
| **Ingreso cobrado** | Suma de `cobranzas` aplicadas |
| **Costo presupuestado** | Suma de `proyecto_presupuesto` |
| **Costo real** | Suma de `proyecto_costos.monto_local` |
| **Margen bruto** | Ingreso contratado − costo real |
| **Margen porcentual** | Margen bruto ÷ ingreso contratado |

**Las tres desviaciones que Gerencia necesita ver:**

Presupuestado contra real, por partida. Dónde se gastó de más.

Contratado contra facturado. Cuánto falta cobrar del contrato.

Facturado contra cobrado. Cuánto está en la calle.

`[S]` **Todo esto se resuelve con vistas**, sin tablas nuevas. Es lo que hace que el módulo de
analítica sea el más barato de los quince.

---

## 17. Lo que falta decidir antes de escribir estas tablas

| # | Decisión | Qué columnas cambia |
|---|---|---|
| **D1** | Multiempresa | `empresa_id` en las 118 tablas, o en ninguna |
| **D2** | Unidad organizadora | Si es el proyecto, `proyecto_id` se vuelve casi obligatorio |
| **D3** | CUBSO en el núcleo | `producto_cubso` y `convocatorias.cubso_id` |
| **D4** | Emisión electrónica | El alcance completo de `comprobantes_electronicos` |
| **D5** | Importación directa o por terceros | Seis tablas del módulo ERP-03 |

`[P]` **Y las decenas de decisiones que resuelve la Fase 0:** cuántas etapas tiene realmente
una oportunidad de Techquk, qué campos exige una cotización, qué prioridades de ticket se
manejan, cómo se calcula el avance de un proyecto.

---

## 18. Cierre: cómo usar este documento

**Como guion de entrevista.** Llevar la tabla de columnas de un módulo a la sesión de
validación y preguntar campo por campo: *"¿esto lo usan? ¿falta algo? ¿este dato de dónde
sale?"*. Es mucho más productivo que preguntar en abstracto qué necesitan.

**Como estimación de esfuerzo.** 118 entidades dan una idea del tamaño real del sistema, que es
información que Gerencia necesita para decidir sobre la dedicación.

**Como referencia de decisiones.** Cada `[S]` marcado es una decisión que alguien tomó y que se
puede discutir. Están explícitas justamente para eso.

**Lo que no hay que hacer:** tratarlo como especificación. Entre un tercio y la mitad de estas
columnas va a cambiar, y eso es señal de que el levantamiento funcionó — no de que el documento
estaba mal.

---

*Fin del documento 08. El modelo conceptual está en el 07, el núcleo verificado en el 06, y la
evidencia de todo lo marcado `[V]` en `S10_firstSteps.md`.*

---

## Anexo v3.0 — Tablas nuevas y cambios por la especificación v4

> `[S]` Generado a partir de `techquk_modelo.dbml` v2. Los **diseños de tabla son propuesta** derivada de los documentos, estados y efectos de la v4; la v4 no define tablas. Todas las tablas nuevas llevan además las cuatro columnas de auditoría (`created_at`, `created_by`, `updated_at`, `updated_by`).

### A. Tablas obsoletas (no migrar)

| Tabla | Se reemplaza por |
|---|---|
| `proyecto_presupuesto` | usar presupuestos + presupuesto_partidas + partida_arboles + partidas_control |
| `proyecto_costos` | usar imputaciones (selector de imputacion) |
| `partes_horas` | usar partes_semanales + parte_lineas |
| `asientos_generados` | usar asientos + asiento_lineas |
| `configuracion_contable` | usar plantillas_contables + plantilla_contable_lineas |

### B. Columnas agregadas a tablas existentes

| Tabla | Columna | Tipo | Detalle |
|---|---|---|---|
| `contratos` | `tipo_contrato` | `varchar(20)` | obligatorio · def. bienes_servicios · bienes_servicios / obra |
| `contratos` | `forma_pago_id` | `bigint` | FK formas_pago |
| `contratos` | `penalidad_coeficiente` | `decimal(4,2)` | def. 0 · Formula: coeficiente x monto / (F x plazo) |
| `contratos` | `penalidad_factor_f` | `decimal(4,2)` | F segun bases y reglamento vigente. v4: 0.40 hasta 60 dias. POR VALIDAR contra Ley 32069 |
| `contratos` | `plazo_vigente_dias` | `smallint` | Calculado: plazo original mas adendas aprobadas con resolucion |
| `contratos` | `garantia_modalidad` | `varchar(10)` | def. fianza · fianza / retencion |
| `proyectos` | `es_interno` | `boolean` | obligatorio · def. false · Un proyecto siempre tiene contrato salvo los internos |
| `cartas_fianza` | `participacion_id` | `bigint` | FK participaciones |
| `cartas_fianza` | `codigo` | `varchar(20)` | única · GAR-AAAA-NNNN |
| `cartas_fianza` | `orden_costo_id` | `bigint` | FK ordenes_compra · Orden de servicio tipo garantia financiera con la comision |
| `cartas_fianza` | `condicion_devolucion` | `varchar(300)` | Que conformidad la libera |
| `cartas_fianza` | `conformidad_liberadora_id` | `bigint` | FK conformidades |
| `cartas_fianza` | `liberada_at` | `date` |  |
| `cartas_fianza` | `devuelta_at` | `date` |  |
| `valorizaciones` | `contrato_id` | `bigint` | FK contratos |
| `valorizaciones` | `subcontrato_id` | `bigint` | FK subcontratos |
| `valorizaciones` | `direccion` | `varchar(12)` | obligatorio · def. cliente · cliente (se cobra) / subcontrato (se paga) |
| `valorizaciones` | `monto_avance` | `decimal(14,2)` | Metrado ejecutado x precio contractual |
| `valorizaciones` | `factor_reajuste` | `decimal(10,7)` | Formula polinomica con los indices del periodo |
| `valorizaciones` | `monto_reajuste` | `decimal(14,2)` |  |
| `valorizaciones` | `monto_amortizacion` | `decimal(14,2)` |  |
| `valorizaciones` | `monto_retencion` | `decimal(14,2)` |  |
| `valorizaciones` | `monto_penalidades` | `decimal(14,2)` |  |
| `valorizaciones` | `monto_neto` | `decimal(14,2)` | Calculado. Habilita el comprobante por el neto |
| `requerimiento_lineas` | `tipo_compra_id` | `bigint` | obligatorio · FK tipos_compra |
| `requerimiento_lineas` | `partida_control_id` | `bigint` | FK partidas_control |
| `requerimiento_lineas` | `centro_costo_id` | `bigint` | FK centros_costo |
| `requerimiento_lineas` | `fecha_necesaria` | `date` |  |
| `requerimiento_lineas` | `atencion` | `varchar(10)` | stock (reserva) / compra |
| `requerimiento_lineas` | `motivo_exceso_id` | `bigint` | FK motivos |
| `orden_compra_lineas` | `tipo_compra_id` | `bigint` | obligatorio · FK tipos_compra |
| `orden_compra_lineas` | `partida_control_id` | `bigint` | FK partidas_control |
| `orden_compra_lineas` | `centro_costo_id` | `bigint` | FK centros_costo |
| `orden_compra_lineas` | `motivo_exceso_id` | `bigint` | FK motivos |
| `ordenes_compra` | `clase` | `varchar(10)` | obligatorio · def. compra · compra (OC) / servicio (OS) |
| `ordenes_compra` | `lugar_entrega` | `varchar(20)` | almacen_central / almacen_obra |
| `movimiento_lineas` | `partida_control_id` | `bigint` | FK partidas_control · Destino de costo en un despacho a obra |
| `movimiento_lineas` | `centro_costo_id` | `bigint` | FK centros_costo |
| `cuentas_pagar` | `xml_adjunto_id` | `bigint` | FK adjuntos · Fuente de los datos. Si solo hay PDF, lectura por vision con revision |
| `cuentas_pagar` | `validada_sunat_at` | `timestamptz` |  |
| `cuentas_pagar` | `cruce_estado` | `varchar(15)` | def. pendiente · Cruce de tres vias: pendiente / coincide / retenida |
| `cuentas_pagar` | `cruce_detalle` | `jsonb` |  |
| `cuentas_pagar` | `retenida_motivo` | `varchar(300)` |  |
| `comprobantes` | `xml_adjunto_id` | `bigint` | FK adjuntos · XML subido desde el facturador |
| `comprobantes` | `origen` | `varchar(20)` | def. facturador_xml · facturador_xml / manual |
| `comprobantes` | `hito_id` | `bigint` | FK contrato_hitos · Hito confirmado por la persona |
| `comprobantes` | `hito_sugerido_id` | `bigint` | FK contrato_hitos · Sugerido por el sistema; no se asocia hasta confirmar |
| `comprobantes` | `validado_sunat_at` | `timestamptz` |  |
| `colaboradores` | `puesto_id` | `bigint` | FK puestos |
| `colaboradores` | `categoria_costo_id` | `bigint` | FK categorias_costo_hora |
| `colaboradores` | `estado_id` | `bigint` | FK estados · candidato / activo / no_habilitado / en_salida / cesado |
| `contratos_laborales` | `remuneracion` | `decimal(14,2)` | DATO SENSIBLE: solo RRHH, Gerencia y contabilidad |
| `contratos_laborales` | `regimen_pensiones` | `varchar(20)` | AFP / ONP |
| `contratos_laborales` | `proyecto_id` | `bigint` | FK proyectos · Personal eventual enlazado a un proyecto |
| `contratos_laborales` | `es_eventual` | `boolean` | obligatorio · def. false |
| `adjuntos` | `es_sensible` | `boolean` | obligatorio · def. false · Examenes medicos, sueldos. La IA no los procesa y nunca salen a un servicio externo |

Además: `cartas_fianza.contrato_id` pasa a ser **nullable** (la garantía de seriedad de oferta existe antes del contrato) y `contratos.penalidad_diaria` pasa a ser un valor **calculado**.

### C. Tablas nuevas

#### M11_Imputacion

**`centros_costo`** — Destino del gasto directo sin proyecto. Iniciales segun v4: Administracion, Comercial, Operaciones, Suministro, RRHH. POR CONFIRMAR con Contabilidad

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `codigo` | `varchar(20)` | única · obligatorio |
| `nombre` | `varchar(120)` | obligatorio |
| `es_activo` | `boolean` | obligatorio · def. true |

**`tipos_motivo`** — Familias de listas cerradas de motivos. v4 parte 6: el motivo es lista cerrada configurable, no texto libre

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `codigo` | `varchar(40)` | única · obligatorio · perdida_oportunidad / gasto_no_presupuestado / exceso_partida / anulacion / habilitacion_sin_requisito / otros |
| `nombre` | `varchar(120)` | obligatorio |

**`motivos`** — Motivos configurables desde pantalla. Convive con motivos_perdida (CRM) hasta unificar

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `tipo_motivo_id` | `bigint` | obligatorio · FK tipos_motivo |
| `codigo` | `varchar(40)` | obligatorio |
| `nombre` | `varchar(160)` | obligatorio |
| `es_cambio_cliente` | `boolean` | obligatorio · def. false · Si es true el gasto se marca como POSIBLE ADICIONAL |
| `es_activo` | `boolean` | obligatorio · def. true |

**`imputaciones`** — SELECTOR DE IMPUTACION (v4 idea 2). Toda linea de costo tiene exactamente un destino: una partida de control o el gasto directo con centro de costo. Sin linea sin destino. [S] diseno propuesto: una fila por linea de origen con sus tres montos. Regla de base: partida_control_id o centro_costo_id (al menos uno); comprometido >= ejecutado

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `origen_type` | `varchar(120)` | obligatorio · linea de orden / requerimiento / despacho / parte de horas / rendicion / gasto de importacion / valorizacion de subcontrato / servicio con vigencia |
| `origen_id` | `bigint` | obligatorio |
| `proyecto_id` | `bigint` | FK proyectos |
| `partida_control_id` | `bigint` | FK partidas_control · Proyecto > partida. PC-99 si es gasto no presupuestado |
| `centro_costo_id` | `bigint` | FK centros_costo · Obligatorio si no hay partida; referencia si es PC-99 |
| `tipo_compra_id` | `bigint` | FK tipos_compra |
| `fecha` | `date` | obligatorio |
| `moneda_id` | `bigint` | obligatorio · FK monedas |
| `tipo_cambio` | `decimal(10,4)` |  |
| `monto_comprometido` | `decimal(14,2)` | obligatorio · def. 0 · Al aprobar orden, reserva o semana de tareo |
| `monto_ejecutado` | `decimal(14,2)` | obligatorio · def. 0 · Al despachar, dar conformidad, cerrar tareo o aprobar rendicion. Nunca mayor que el comprometido |
| `monto_pagado` | `decimal(14,2)` | obligatorio · def. 0 · Al aplicar pago. Medida de caja, no de costo |
| `motivo_id` | `bigint` | FK motivos |
| `comentario` | `varchar(500)` |  |
| `es_posible_adicional` | `boolean` | obligatorio · def. false |

**`posibles_adicionales`** — Gasto no presupuestado causado por un cambio pedido por el cliente: podria cobrarse con una adenda. Alerta a Administracion de Contratos (v4 flujo 7)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `imputacion_id` | `bigint` | única · obligatorio · FK imputaciones |
| `contrato_id` | `bigint` | obligatorio · FK contratos |
| `estado` | `varchar(15)` | obligatorio · def. detectado · detectado / en_adenda / descartado |
| `adenda_id` | `bigint` | FK contrato_adendas |
| `redaccion_sugerida` | `text` | FASE POSTERIOR (IA) |

#### M12_IA

**`ia_procesos`** — Catalogo de procesos donde la IA sugiere. Reglas: nunca guarda sola; siempre existe camino manual; datos sensibles nunca salen (v4 idea 4)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `codigo` | `varchar(60)` | única · obligatorio · arbol_desde_archivos / comprobante_sin_xml / notas_tesoreria / enlace_sugerido / posible_adicional / rendicion_foto / datos_contrato / partida_sugerida / licitacion_bases |
| `nombre` | `varchar(160)` | obligatorio |
| `esta_activo` | `boolean` | obligatorio · def. false · Se activa o desactiva por configuracion |
| `envia_datos_externos` | `boolean` | obligatorio · def. true |
| `autorizado_por` | `bigint` | FK users · Gerencia autoriza que datos pueden enviarse |
| `autorizado_at` | `timestamptz` |  |

**`ia_sugerencias`** — Auditoria de cada sugerencia. Permite medir cuanto acierta cada proceso. No procesa adjuntos marcados es_sensible

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · SUG-AAAA-NNNNNN |
| `proceso_id` | `bigint` | obligatorio · FK ia_procesos |
| `origen_adjunto_id` | `bigint` | obligatorio · FK adjuntos · Archivo o foto de origen, siempre adjunto |
| `propuesta` | `jsonb` | obligatorio |
| `seguridad_por_campo` | `jsonb` |  |
| `explicacion` | `varchar(300)` | obligatorio · Por que lo propuso, en una linea |
| `estado` | `varchar(15)` | obligatorio · def. por_revisar · por_revisar / resuelta |
| `accion` | `varchar(15)` | aceptada / corregida / rechazada |
| `correccion` | `jsonb` |  |
| `resuelta_por` | `bigint` | FK users |
| `resuelta_at` | `timestamptz` |  |
| `resultado_type` | `varchar(120)` |  |
| `resultado_id` | `bigint` |  |

**`ia_aprendizajes`** — Lo que una persona corrige una vez no se vuelve a preguntar (ej: es la misma partida)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `proceso_id` | `bigint` | obligatorio · FK ia_procesos |
| `clave_origen` | `varchar(300)` | obligatorio |
| `valor_destino` | `varchar(300)` | obligatorio |
| `veces` | `smallint` | obligatorio · def. 1 |
| `ultima_sugerencia_id` | `bigint` | FK ia_sugerencias |

#### M13_Alertas

**`alerta_tipos`** — Catalogo unico de alertas y vencimientos (v4 parte 7): hitos, cartas fianza, servicios con vigencia, partidas excedidas, embarque en riesgo, tareo sin cerrar, certificaciones, habilitacion de campo, etc.

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `codigo` | `varchar(60)` | única · obligatorio |
| `nombre` | `varchar(160)` | obligatorio |
| `es_obligatoria` | `boolean` | obligatorio · def. false · Se puede adelantar, no apagar |
| `anticipacion_dias` | `varchar(40)` | Por defecto, ej: 60,30,15 |
| `destinatarios` | `varchar(200)` | Roles por defecto |
| `es_activa` | `boolean` | obligatorio · def. true |

**`alerta_preferencias`** — Cada persona ajusta anticipacion y canal dentro de lo que su rol permite

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `user_id` | `bigint` | obligatorio · FK users |
| `alerta_tipo_id` | `bigint` | obligatorio · FK alerta_tipos |
| `anticipacion_dias` | `varchar(40)` |  |
| `canal` | `varchar(20)` | obligatorio · def. app · app / correo |
| `es_activa` | `boolean` | obligatorio · def. true |

**`alertas_emitidas`** — Registro de alertas enviadas. La clave unica hace idempotentes los jobs programados

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `alerta_tipo_id` | `bigint` | obligatorio · FK alerta_tipos |
| `entidad_type` | `varchar(120)` | obligatorio |
| `entidad_id` | `bigint` | obligatorio |
| `clave` | `varchar(200)` | única · obligatorio · IDEMPOTENCIA: tipo + entidad + umbral + fecha. Evita enviar dos veces la misma alerta |
| `user_id` | `bigint` | obligatorio · FK users |
| `emitida_at` | `timestamptz` | obligatorio · def. now() |
| `leida_at` | `timestamptz` |  |

#### M10_Seguridad

**`puestos`** — El puesto es el cargo del organigrama; el rol es un paquete de permisos y una persona puede tener varios (v4 glosario)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `nombre` | `varchar(120)` | obligatorio |
| `unidad_organizativa_id` | `bigint` | FK unidades_organizativas |
| `es_activo` | `boolean` | obligatorio · def. true |

**`restricciones_campo`** — Restricciones por CAMPO, no por modulo: auxiliares abren un proyecto pero no ven costos ni margenes; sueldos solo RRHH, Gerencia y contabilidad; detalle medico solo RRHH. Va en el nucleo desde el inicio

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `rol_id` | `bigint` | obligatorio · Tabla roles de spatie/laravel-permission |
| `recurso` | `varchar(80)` | obligatorio · proyecto / orden / contrato_laboral / habilitacion / almacen |
| `campo` | `varchar(80)` | obligatorio · costos / margen / remuneracion / detalle_medico / precio_compra |
| `tipo` | `varchar(15)` | obligatorio · def. oculto · oculto / solo_lectura |

**`cruces_funciones`** — Separacion de funciones: que la misma persona no pida y apruebe. Si se asignan roles que se cruzan, alerta a Gerencia General

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `rol_a_id` | `bigint` | obligatorio |
| `rol_b_id` | `bigint` | obligatorio |
| `control_compensatorio` | `varchar(300)` | obligatorio |
| `aprobado_por` | `bigint` | FK users |

#### ERP_02_Compras

**`tipos_compra`** — Doce tipos iniciales configurables (v4 flujo 2): bien_proyecto, importacion, servicio_puntual, subcontrato, servicio_vigencia, garantia_financiera, viaticos_caja_chica, bien_empresa (FASE POSTERIOR), alquiler_equipo, honorarios, software_licencias, combustible_peajes. El texto v4 dice ocho pero lista doce

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `codigo` | `varchar(40)` | única · obligatorio |
| `nombre` | `varchar(120)` | obligatorio |
| `ejemplo` | `varchar(200)` |  |
| `requiere_recepcion` | `boolean` | obligatorio · def. false |
| `requiere_conformidad` | `boolean` | obligatorio · def. false |
| `controla_stock` | `boolean` | obligatorio · def. false |
| `requiere_embarque` | `boolean` | obligatorio · def. false |
| `genera_valorizacion` | `boolean` | obligatorio · def. false |
| `tiene_vigencia` | `boolean` | obligatorio · def. false |
| `requiere_rendicion` | `boolean` | obligatorio · def. false |
| `es_activo` | `boolean` | obligatorio · def. true |
| `orden` | `smallint` | obligatorio · def. 0 |

#### ERP_14_Presupuesto_y_Partidas

**`presupuestos`** — Presupuesto con analisis de precios unitarios. Una version aprobada no se edita. Al ganar, el MISMO presupuesto pasa al proyecto (no se copia). La lista de insumos debe sumar exactamente el costo directo

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | obligatorio · PRE-AAAA-NNNN |
| `version` | `smallint` | obligatorio · def. 1 |
| `oportunidad_id` | `bigint` | FK oportunidades |
| `proyecto_id` | `bigint` | FK proyectos |
| `nombre` | `varchar(200)` | obligatorio |
| `moneda_id` | `bigint` | obligatorio · FK monedas |
| `usa_apu` | `boolean` | obligatorio · def. true · false = precio por item (suministro simple). POR VALIDAR en que contratos se usa APU completo |
| `gastos_generales_pct` | `decimal(5,2)` | obligatorio · def. 0 |
| `utilidad_pct` | `decimal(5,2)` | obligatorio · def. 0 |
| `igv_pct` | `decimal(5,2)` | obligatorio · def. 18 |
| `costo_directo` | `decimal(14,2)` | obligatorio · def. 0 |
| `gastos_generales` | `decimal(14,2)` | obligatorio · def. 0 |
| `utilidad` | `decimal(14,2)` | obligatorio · def. 0 |
| `igv` | `decimal(14,2)` | obligatorio · def. 0 |
| `total` | `decimal(14,2)` | obligatorio · def. 0 |
| `estado_id` | `bigint` | obligatorio · FK estados |
| `aprobado_por` | `bigint` | FK users |
| `aprobado_at` | `timestamptz` |  |

**`presupuesto_titulos`** — Titulos del presupuesto (01 CAMARAS, 02 CABLEADO Y POSTES...)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `presupuesto_id` | `bigint` | obligatorio · FK presupuestos |
| `padre_id` | `bigint` | FK presupuesto_titulos |
| `codigo` | `varchar(30)` | obligatorio |
| `nombre` | `varchar(200)` | obligatorio |
| `orden` | `smallint` | obligatorio · def. 0 |

**`presupuesto_partidas`** — Partidas del presupuesto con metrado y costo unitario

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `presupuesto_id` | `bigint` | obligatorio · FK presupuestos |
| `titulo_id` | `bigint` | FK presupuesto_titulos |
| `codigo` | `varchar(30)` | obligatorio |
| `descripcion` | `varchar(300)` | obligatorio |
| `unidad_medida_id` | `bigint` | obligatorio · FK unidades_medida |
| `metrado` | `decimal(12,3)` | obligatorio |
| `costo_unitario` | `decimal(14,4)` | obligatorio · Con APU sale de apu_insumos |
| `parcial` | `decimal(14,2)` | obligatorio · metrado x costo_unitario |
| `orden` | `smallint` | obligatorio · def. 0 |

**`insumos`** — Todo lo que cuesta. S10 trae catalogos genericos: migrar solo lo que Techquk usa (v4 migracion)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `codigo` | `varchar(30)` | única · obligatorio |
| `nombre` | `varchar(200)` | obligatorio |
| `tipo` | `varchar(15)` | obligatorio · material / mano_obra / equipo / subcontrato |
| `unidad_medida_id` | `bigint` | obligatorio · FK unidades_medida |
| `precio_referencia` | `decimal(14,4)` |  |
| `producto_id` | `bigint` | FK productos |
| `es_activo` | `boolean` | obligatorio · def. true |

**`apu_insumos`** — Analisis de precios unitarios: la receta de insumos de una unidad de partida

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `presupuesto_partida_id` | `bigint` | obligatorio · FK presupuesto_partidas |
| `insumo_id` | `bigint` | obligatorio · FK insumos |
| `tipo_cantidad` | `varchar(15)` | obligatorio · cantidad / rendimiento |
| `cantidad` | `decimal(12,5)` | obligatorio · Ej: cable 1.05 m por metro; tecnico 0.05 h por metro (20 m/h) |
| `desperdicio_pct` | `decimal(5,2)` | obligatorio · def. 0 |
| `precio` | `decimal(14,4)` | obligatorio |
| `parcial` | `decimal(14,4)` | obligatorio |

**`partida_arboles`** — Version del arbol de partidas de control. Al aprobar: crea PC-99, pasa el proyecto a en ejecucion, habilita el selector de imputacion

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `proyecto_id` | `bigint` | obligatorio · FK proyectos |
| `version` | `smallint` | obligatorio · def. 1 |
| `presupuesto_id` | `bigint` | FK presupuestos |
| `estado_id` | `bigint` | obligatorio · FK estados |
| `costo_presupuestado` | `decimal(14,2)` | obligatorio · La suma de las hojas debe ser igual a este monto |
| `aprobado_por` | `bigint` | FK users · Jefatura de Proyectos |
| `aprobado_at` | `timestamptz` |  |

**`partidas_control`** — Nodo del arbol contra el que se carga cada costo real. Profundidad libre; lo disenan Robert y el especialista del proyecto. Una partida con costos no se elimina ni se mueve sin dejar rastro

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `proyecto_id` | `bigint` | obligatorio · FK proyectos |
| `padre_id` | `bigint` | FK partidas_control |
| `arbol_alta_id` | `bigint` | obligatorio · FK partida_arboles · Version del arbol donde nace |
| `codigo` | `varchar(30)` | obligatorio · PC-02.01 |
| `nombre` | `varchar(200)` | obligatorio |
| `es_hoja` | `boolean` | obligatorio · def. true |
| `responsable_user_id` | `bigint` | obligatorio · FK users · Una persona por partida |
| `origen_titulo_id` | `bigint` | FK presupuesto_titulos |
| `origen_partida_id` | `bigint` | FK presupuesto_partidas |
| `es_gastos_generales` | `boolean` | obligatorio · def. false · PC-04 |
| `es_no_presupuestado` | `boolean` | obligatorio · def. false · PC-99: la crea el sistema al aprobar el arbol. No se borra |
| `es_activa` | `boolean` | obligatorio · def. true |

**`partida_presupuestos`** — Presupuestado de cada partida en cada version del arbol (las partidas con costos se conservan entre versiones). [S] diseno propuesto

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `partida_id` | `bigint` | obligatorio · FK partidas_control |
| `arbol_id` | `bigint` | obligatorio · FK partida_arboles |
| `monto` | `decimal(14,2)` | obligatorio · Solo en hojas; los padres suman |

**`formulas_polinomicas`** — Regla de reajuste de precios de un contrato de obra. Se define con el presupuesto y se aplica en cada valorizacion

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `presupuesto_id` | `bigint` | única · obligatorio · FK presupuestos |
| `estado_id` | `bigint` | obligatorio · FK estados |

**`formula_monomios`** — Monomios de la formula polinomica

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `formula_id` | `bigint` | obligatorio · FK formulas_polinomicas |
| `simbolo` | `varchar(10)` | obligatorio |
| `descripcion` | `varchar(120)` | obligatorio |
| `coeficiente` | `decimal(6,5)` | obligatorio · Los coeficientes suman exactamente 1 |
| `indice_codigo` | `varchar(20)` | obligatorio |
| `indice_base` | `decimal(12,4)` | obligatorio |

**`indices_precios`** — Indices mensuales para el reajuste. Carga manual mensual; automatizable FASE POSTERIOR. No se valoriza un periodo sin sus indices

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `indice_codigo` | `varchar(20)` | obligatorio |
| `periodo` | `date` | obligatorio |
| `valor` | `decimal(12,4)` | obligatorio |
| `fuente` | `varchar(60)` | obligatorio · def. oficial |

#### ERP_15_Obra_Publica

**`adelantos`** — Dinero recibido o entregado antes del avance, que se descuenta despues (v4 flujo 10)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · ADL-AAAA-NNNN |
| `contrato_id` | `bigint` | FK contratos |
| `subcontrato_id` | `bigint` | FK subcontratos |
| `direccion` | `varchar(10)` | obligatorio · recibido (del cliente) / entregado (al subcontratista) |
| `tipo` | `varchar(15)` | obligatorio · directo / materiales |
| `monto` | `decimal(14,2)` | obligatorio |
| `porcentaje` | `decimal(5,2)` |  |
| `moneda_id` | `bigint` | obligatorio · FK monedas |
| `garantia_id` | `bigint` | FK cartas_fianza |
| `monto_amortizado` | `decimal(14,2)` | obligatorio · def. 0 · Nunca supera el monto del adelanto |
| `estado_id` | `bigint` | obligatorio · FK estados |
| `fecha` | `date` | obligatorio |

**`valorizacion_lineas`** — Detalle del avance valorizado por partida

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `valorizacion_id` | `bigint` | obligatorio · FK valorizaciones |
| `presupuesto_partida_id` | `bigint` | obligatorio · FK presupuesto_partidas |
| `metrado_periodo` | `decimal(12,3)` | obligatorio |
| `metrado_acumulado` | `decimal(12,3)` | obligatorio |
| `precio_contractual` | `decimal(14,4)` | obligatorio |
| `monto` | `decimal(14,2)` | obligatorio · Metrado ejecutado x precio contractual |

**`liquidaciones_contrato`** — Cierre economico hacia la entidad o el subcontratista. No se presenta con valorizaciones pendientes. Al consentirse habilita devolucion de garantias y cierre del proyecto

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · LIQ-contrato |
| `contrato_id` | `bigint` | FK contratos |
| `subcontrato_id` | `bigint` | FK subcontratos |
| `estado_id` | `bigint` | obligatorio · FK estados |
| `presentada_at` | `date` |  |
| `plazo_pronunciamiento_at` | `date` |  |
| `consentida_at` | `date` |  |

**`liquidacion_conceptos`** — Conceptos de la liquidacion

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `liquidacion_id` | `bigint` | obligatorio · FK liquidaciones_contrato |
| `concepto` | `varchar(20)` | obligatorio · contratado / adicionales / reducciones / reajustes / penalidades / adelantos / retenciones / cobrado |
| `monto` | `decimal(14,2)` | obligatorio |

**`consorcios`** — Agrupacion con otras empresas para un contrato. Valorizaciones y costos se reparten segun el modo

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · CSR-AAAA-NNN |
| `contrato_id` | `bigint` | obligatorio · FK contratos |
| `modo` | `varchar(15)` | obligatorio · participacion (solo Techquk) / total (todo el contrato con porcentajes) |
| `operador_party_id` | `bigint` | obligatorio · FK parties · Quien factura a la entidad |
| `estado_id` | `bigint` | obligatorio · FK estados |

**`consorcio_integrantes`** — Integrantes y participacion

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `consorcio_id` | `bigint` | obligatorio · FK consorcios |
| `party_id` | `bigint` | obligatorio · FK parties |
| `porcentaje` | `decimal(5,2)` | obligatorio · Los porcentajes suman exactamente 100 |
| `es_techquk` | `boolean` | obligatorio · def. false |

**`subcontratos`** — Un tipo de compra con alcance, valorizaciones, adelantos, retencion y liquidacion. Al aprobar: comprometido en las partidas del alcance

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · SC-AAAA-NNN |
| `proyecto_id` | `bigint` | obligatorio · FK proyectos |
| `party_id` | `bigint` | obligatorio · FK parties · El subcontratista |
| `orden_compra_id` | `bigint` | FK ordenes_compra |
| `monto` | `decimal(14,2)` | obligatorio |
| `moneda_id` | `bigint` | obligatorio · FK monedas |
| `retencion_pct` | `decimal(5,2)` | obligatorio · def. 0 · % de garantia sobre cada valorizacion |
| `estado_id` | `bigint` | obligatorio · FK estados |

**`subcontrato_partidas`** — Alcance del subcontrato asignado a partidas de control

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `subcontrato_id` | `bigint` | obligatorio · FK subcontratos |
| `partida_control_id` | `bigint` | obligatorio · FK partidas_control |
| `monto` | `decimal(14,2)` | obligatorio |

**`garantia_renovaciones`** — Historial de renovaciones de una garantia (v4 flujo 1)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `carta_fianza_id` | `bigint` | obligatorio · FK cartas_fianza |
| `vence_anterior` | `date` | obligatorio |
| `vence_nuevo` | `date` | obligatorio |
| `comision` | `decimal(14,2)` | obligatorio · def. 0 · Toda garantia registra su costo, aunque sea cero |
| `orden_costo_id` | `bigint` | FK ordenes_compra |
| `renovada_at` | `date` | obligatorio |

#### ERP_16_Tesoreria_y_Gastos

**`pagos_programados`** — Tarjetas del tablero de pagos: Tesoreria las ubica por vencimiento y las arrastra entre columnas; el sistema suma y muestra la caja restante

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `cuenta_pagar_id` | `bigint` | obligatorio · FK cuentas_pagar |
| `fecha_programada` | `date` | obligatorio |
| `monto` | `decimal(14,2)` | obligatorio |
| `moneda_id` | `bigint` | obligatorio · FK monedas |
| `orden_tarjeta` | `smallint` | obligatorio · def. 0 |
| `estado` | `varchar(15)` | obligatorio · def. programado · programado / pagado / postergado |

**`notas_tesoreria`** — Captura de las notas en papel de Tesoreria por foto. La IA sugiere, Tesoreria confirma. POR VALIDAR con una hoja real

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `fecha` | `date` | obligatorio |
| `adjunto_id` | `bigint` | obligatorio · FK adjuntos · Foto de la hoja en papel |
| `estado` | `varchar(15)` | obligatorio · def. por_revisar · por_revisar / revisada |
| `sugerencia_ia_id` | `bigint` | FK ia_sugerencias |
| `revisada_por` | `bigint` | FK users |
| `revisada_at` | `timestamptz` |  |

**`notas_tesoreria_lineas`** — Lineas de la hoja de notas, enlazadas a la cuenta que corresponda

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `nota_id` | `bigint` | obligatorio · FK notas_tesoreria |
| `tipo` | `varchar(10)` | obligatorio · cobro / pago |
| `party_id` | `bigint` | FK parties |
| `descripcion` | `varchar(300)` | obligatorio |
| `monto` | `decimal(14,2)` | obligatorio |
| `moneda_id` | `bigint` | obligatorio · FK monedas |
| `fecha_prevista` | `date` |  |
| `cuenta_pagar_id` | `bigint` | FK cuentas_pagar |
| `cuenta_cobrar_id` | `bigint` | FK cuentas_cobrar |

**`flujo_caja_escenarios`** — Escenarios del flujo de caja (lo cobrado menos lo pagado, semana a semana)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `nombre` | `varchar(120)` | obligatorio |
| `descripcion` | `varchar(300)` |  |
| `es_base` | `boolean` | obligatorio · def. false |
| `caja_minima` | `decimal(14,2)` | Alerta si la caja proyectada baja de este monto |

**`flujo_caja_ajustes`** — Movimientos hipoteticos de un escenario

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `escenario_id` | `bigint` | obligatorio · FK flujo_caja_escenarios |
| `fecha` | `date` | obligatorio |
| `concepto` | `varchar(200)` | obligatorio |
| `monto` | `decimal(14,2)` | obligatorio · Positivo ingreso, negativo egreso |

**`servicios_vigencia`** — SCTR, polizas, alquileres y licencias con vencimiento. El SCTR vigente por proyecto es requisito para tarear en obra

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `party_id` | `bigint` | obligatorio · FK parties · Proveedor |
| `tipo` | `varchar(20)` | obligatorio · sctr / poliza / alquiler / licencia / otro |
| `descripcion` | `varchar(300)` | obligatorio |
| `proyecto_id` | `bigint` | FK proyectos |
| `centro_costo_id` | `bigint` | FK centros_costo |
| `vigente_desde` | `date` | obligatorio |
| `vence_el` | `date` | obligatorio · ALERTA 30 dias antes |
| `monto` | `decimal(14,2)` |  |
| `moneda_id` | `bigint` | obligatorio · FK monedas |
| `orden_servicio_id` | `bigint` | FK ordenes_compra |
| `responsable_user_id` | `bigint` | obligatorio · FK users |
| `estado_id` | `bigint` | obligatorio · FK estados |

**`rendiciones_viaticos`** — Entrega de fondos de campo y su rendicion con comprobantes (v4 flujo 7)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · REN-AAAA-NNNN |
| `colaborador_id` | `bigint` | obligatorio · FK colaboradores |
| `proyecto_id` | `bigint` | FK proyectos |
| `fecha_entrega` | `date` | obligatorio |
| `monto_entregado` | `decimal(14,2)` | obligatorio |
| `moneda_id` | `bigint` | obligatorio · FK monedas |
| `monto_rendido` | `decimal(14,2)` | obligatorio · def. 0 |
| `saldo_devolver` | `decimal(14,2)` | obligatorio · def. 0 |
| `orden_fondo_id` | `bigint` | FK ordenes_compra |
| `estado_id` | `bigint` | obligatorio · FK estados |
| `aprobada_por` | `bigint` | FK users · Jefatura del tecnico |
| `aprobada_at` | `timestamptz` |  |

**`rendicion_lineas`** — Gastos rendidos. Al aprobar la rendicion se ejecuta en la partida

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `rendicion_id` | `bigint` | obligatorio · FK rendiciones_viaticos |
| `fecha` | `date` | obligatorio |
| `concepto` | `varchar(200)` | obligatorio |
| `monto` | `decimal(14,2)` | obligatorio |
| `comprobante_tipo` | `varchar(10)` |  |
| `comprobante_serie` | `varchar(10)` |  |
| `comprobante_numero` | `varchar(20)` |  |
| `adjunto_id` | `bigint` | FK adjuntos · Foto de la boleta |
| `partida_control_id` | `bigint` | FK partidas_control |
| `centro_costo_id` | `bigint` | FK centros_costo |
| `sugerencia_ia_id` | `bigint` | FK ia_sugerencias |

#### ERP_17_Tareo_y_Habilitacion

**`categorias_costo_hora`** — Tabla de categorias de costo hora vigentes a la fecha. El costo real viene de la planilla; la diferencia estandar vs real se calcula al cerrarla

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `codigo` | `varchar(20)` | única · obligatorio |
| `nombre` | `varchar(120)` | obligatorio |
| `costo_hora` | `decimal(10,2)` | obligatorio · Costo estandar |
| `vigente_desde` | `date` | obligatorio |
| `vigente_hasta` | `date` |  |

**`partes_semanales`** — Parte de horas semanal (tareo de obra y horas de desarrollo en un solo documento). Al cerrar: comprometido y ejecutado en cada partida; la semana ya no se edita

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · HOR-AAAA-SS-persona |
| `colaborador_id` | `bigint` | obligatorio · FK colaboradores |
| `anio` | `smallint` | obligatorio |
| `semana` | `smallint` | obligatorio |
| `estado_id` | `bigint` | obligatorio · FK estados |
| `enviado_por` | `bigint` | FK users · Jefe de cuadrilla o desarrollador |
| `enviado_at` | `timestamptz` |  |
| `cerrado_por` | `bigint` | FK users · Jefatura de Proyectos o de Desarrollo |
| `cerrado_at` | `timestamptz` |  |

**`parte_lineas`** — Horas por dia y por imputacion. No se registran horas de obra sin SCTR vigente para el proyecto ni sin habilitacion de campo

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `parte_id` | `bigint` | obligatorio · FK partes_semanales |
| `fecha` | `date` | obligatorio |
| `horas` | `decimal(4,2)` | obligatorio · Mas de 12 horas en un dia requiere motivo |
| `proyecto_id` | `bigint` | FK proyectos |
| `partida_control_id` | `bigint` | FK partidas_control |
| `centro_costo_id` | `bigint` | FK centros_costo |
| `tipo_trabajo_id` | `bigint` | obligatorio · FK tipos_trabajo |
| `descripcion` | `varchar(300)` |  |
| `costo_hora` | `decimal(10,2)` | Congelado al cerrar, de la categoria vigente |
| `es_facturable` | `boolean` | obligatorio · def. false |
| `motivo_exceso_id` | `bigint` | FK motivos |

**`requisitos_habilitacion`** — Lo que alguien necesita vigente para trabajar en obra

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `codigo` | `varchar(30)` | única · obligatorio · examen_ocupacional / sctr / induccion / epp |
| `nombre` | `varchar(120)` | obligatorio |
| `es_por_proyecto` | `boolean` | obligatorio · def. false · SCTR es por proyecto |
| `dias_alerta` | `smallint` | obligatorio · def. 15 |
| `es_activo` | `boolean` | obligatorio · def. true |

**`colaborador_habilitaciones`** — Datos de salud: sensibles segun la ley de proteccion de datos personales

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `colaborador_id` | `bigint` | obligatorio · FK colaboradores |
| `requisito_id` | `bigint` | obligatorio · FK requisitos_habilitacion |
| `proyecto_id` | `bigint` | FK proyectos |
| `vigente_desde` | `date` | obligatorio |
| `vence_el` | `date` | obligatorio · Alerta 15 dias antes; bloqueo el dia del vencimiento |
| `resultado_apto` | `boolean` | UNICO dato visible fuera de RRHH. El detalle medico solo lo ve RRHH |
| `adjunto_id` | `bigint` | FK adjuntos · Si es examen medico, el adjunto va con es_sensible = true |
| `autorizada_sin_requisito` | `boolean` | obligatorio · def. false |
| `autorizada_por` | `bigint` | FK users · Gerencia de Operaciones |
| `motivo_id` | `bigint` | FK motivos |

**`asistencias`** — Lo ocurrido; insumo de la planilla. Las ausencias futuras y bloqueos de agenda siguen en disponibilidad

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `colaborador_id` | `bigint` | obligatorio · FK colaboradores |
| `fecha` | `date` | obligatorio |
| `tipo` | `varchar(20)` | obligatorio · asistio / falta / tardanza / licencia / vacaciones / descanso_medico |
| `horas` | `decimal(4,2)` |  |
| `fuente` | `varchar(15)` | obligatorio · def. manual · tareo / manual / importada |

#### ERP_18_RRHH_y_Planilla

**`demandas_personal`** — Demanda de personal a 90 dias: brecha entre proyectos en cartera y personal disponible

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `proyecto_id` | `bigint` | FK proyectos |
| `competencia_id` | `bigint` | FK competencias |
| `puesto_id` | `bigint` | FK puestos |
| `cantidad` | `smallint` | obligatorio · def. 1 |
| `fecha_desde` | `date` | obligatorio |
| `fecha_hasta` | `date` | obligatorio |
| `estado` | `varchar(15)` | obligatorio · def. abierta · abierta / cubierta / cancelada |
| `calculada_at` | `timestamptz` |  |

**`candidatos`** — Banco de candidatos (v4 flujo 9)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `party_id` | `bigint` | obligatorio · FK parties |
| `fuente` | `varchar(60)` |  |
| `puesto_id` | `bigint` | FK puestos |
| `estado` | `varchar(20)` | obligatorio · def. en_banco · en_banco / en_proceso / contratado / descartado |
| `observaciones` | `text` |  |

**`procesos_seleccion`** — Proceso de seleccion de un perfil

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `demanda_id` | `bigint` | FK demandas_personal |
| `puesto_id` | `bigint` | obligatorio · FK puestos |
| `estado_id` | `bigint` | obligatorio · FK estados |
| `fecha_inicio` | `date` | obligatorio |
| `fecha_cierre` | `date` |  |
| `responsable_user_id` | `bigint` | obligatorio · FK users |

**`proceso_candidatos`** — Candidatos dentro de un proceso

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `proceso_id` | `bigint` | obligatorio · FK procesos_seleccion |
| `candidato_id` | `bigint` | obligatorio · FK candidatos |
| `etapa` | `varchar(30)` | obligatorio |
| `resultado` | `varchar(20)` |  |
| `colaborador_id` | `bigint` | FK colaboradores · Si fue contratado |

**`salidas_personal`** — Salida de personal: lista de traspasos y revocacion de accesos. La liquidacion va en planilla con su asiento

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `colaborador_id` | `bigint` | obligatorio · FK colaboradores |
| `fecha_inicio` | `date` | obligatorio |
| `fecha_cese_prevista` | `date` |  |
| `motivo` | `varchar(120)` |  |
| `estado_id` | `bigint` | obligatorio · FK estados |
| `liquidacion_planilla_id` | `bigint` | FK planillas |

**`salida_traspasos`** — Items del traspaso al salir una persona

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `salida_id` | `bigint` | obligatorio · FK salidas_personal |
| `tipo` | `varchar(20)` | obligatorio · asignaciones / cartera / conocimiento / accesos / equipos |
| `descripcion` | `varchar(300)` | obligatorio |
| `responsable_user_id` | `bigint` | obligatorio · FK users |
| `estado` | `varchar(15)` | obligatorio · def. pendiente |
| `completado_at` | `timestamptz` |  |

**`planillas`** — Calculo mensual de remuneraciones DENTRO del ERP (decision v4). Al cerrar: boletas, archivo PLAME, asientos y diferencia real contra estandar. Una planilla cerrada se corrige con una planilla de ajuste. No se cierra con tareo sin cerrar

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · PLA-AAAA-MM |
| `periodo` | `date` | obligatorio |
| `tipo` | `varchar(15)` | obligatorio · def. mensual · mensual / ajuste / gratificacion / cts |
| `estado_id` | `bigint` | obligatorio · FK estados |
| `calculada_at` | `timestamptz` |  |
| `cerrada_por` | `bigint` | FK users · RRHH |
| `cerrada_at` | `timestamptz` |  |
| `visto_bueno_contador_por` | `bigint` | FK users |
| `visto_bueno_at` | `timestamptz` |  |
| `archivo_plame_adjunto_id` | `bigint` | FK adjuntos |

**`planilla_conceptos`** — Tasas, topes y conceptos como tablas configurables con vigencia. Las reglas vigentes las valida el contador

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `codigo` | `varchar(20)` | obligatorio |
| `nombre` | `varchar(120)` | obligatorio |
| `tipo` | `varchar(20)` | obligatorio · ingreso / descuento / aporte_empleador |
| `base` | `varchar(30)` |  |
| `tasa` | `decimal(8,5)` |  |
| `tope` | `decimal(14,2)` |  |
| `vigente_desde` | `date` | obligatorio |
| `vigente_hasta` | `date` |  |
| `es_activo` | `boolean` | obligatorio · def. true |

**`planilla_boletas`** — Boleta por persona y periodo. DATO SENSIBLE: sueldos solo RRHH, Gerencia y contabilidad

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `planilla_id` | `bigint` | obligatorio · FK planillas |
| `colaborador_id` | `bigint` | obligatorio · FK colaboradores |
| `contrato_laboral_id` | `bigint` | obligatorio · FK contratos_laborales |
| `dias_trabajados` | `smallint` | obligatorio |
| `total_ingresos` | `decimal(14,2)` | obligatorio |
| `total_descuentos` | `decimal(14,2)` | obligatorio |
| `total_aportes` | `decimal(14,2)` | obligatorio |
| `neto` | `decimal(14,2)` | obligatorio |
| `adjunto_id` | `bigint` | FK adjuntos · Boleta de pago |

**`planilla_boleta_lineas`** — Conceptos aplicados en cada boleta

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `boleta_id` | `bigint` | obligatorio · FK planilla_boletas |
| `concepto_id` | `bigint` | obligatorio · FK planilla_conceptos |
| `base` | `decimal(14,2)` |  |
| `monto` | `decimal(14,2)` | obligatorio |

**`planilla_diferencias_costo`** — Diferencia entre costo estandar y costo real, por proyecto, al cerrar la planilla

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `planilla_id` | `bigint` | obligatorio · FK planillas |
| `proyecto_id` | `bigint` | obligatorio · FK proyectos |
| `partida_control_id` | `bigint` | FK partidas_control |
| `costo_estandar` | `decimal(14,2)` | obligatorio |
| `costo_real` | `decimal(14,2)` | obligatorio |
| `diferencia` | `decimal(14,2)` | obligatorio |
| `se_reparte` | `boolean` | POR VALIDAR con Gerencia: repartir a partidas o dejar como gasto |

**`planilla_saldos`** — Saldos iniciales de la migracion (vacaciones, CTS acumulada). Validar con el contador antes del primer calculo

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `colaborador_id` | `bigint` | obligatorio · FK colaboradores |
| `tipo` | `varchar(20)` | obligatorio · vacaciones / cts / gratificacion |
| `dias` | `decimal(6,2)` |  |
| `saldo` | `decimal(14,2)` |  |
| `a_fecha` | `date` | obligatorio |

#### ERP_13_Integracion_contable

**`plantillas_contables`** — La regla que dice en que cuentas se registra cada tipo de operacion. La define el contador. Una operacion sin plantilla no genera asiento: queda en fallidos

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `tipo_operacion` | `varchar(60)` | única · obligatorio |
| `nombre` | `varchar(160)` | obligatorio |
| `vigente_desde` | `date` | obligatorio |
| `es_activa` | `boolean` | obligatorio · def. true |

**`plantilla_contable_lineas`** — Lineas de una plantilla

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `plantilla_id` | `bigint` | obligatorio · FK plantillas_contables |
| `lado` | `varchar(5)` | obligatorio · debe / haber |
| `cuenta` | `varchar(20)` | obligatorio |
| `base` | `varchar(20)` | obligatorio · subtotal / igv / total / detraccion / retencion |
| `usa_centro_costo` | `boolean` | obligatorio · def. false |
| `orden` | `smallint` | obligatorio · def. 0 |

**`asientos`** — Registro contable de una operacion, armado por plantilla. Reemplaza a asientos_generados (que mezclaba cabecera y lineas)

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · AS-AAAA-MM-NNNN |
| `origen_type` | `varchar(120)` | obligatorio |
| `origen_id` | `bigint` | obligatorio |
| `periodo` | `date` | obligatorio |
| `plantilla_id` | `bigint` | FK plantillas_contables |
| `estado` | `varchar(15)` | obligatorio · def. generado · generado / exportado / fallido |
| `motivo_fallo` | `varchar(300)` |  |
| `exportacion_id` | `bigint` | FK exportaciones_contables |

**`asiento_lineas`** — Debe y haber siempre suman lo mismo dentro de un asiento

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `asiento_id` | `bigint` | obligatorio · FK asientos |
| `cuenta` | `varchar(20)` | obligatorio |
| `debe` | `decimal(14,2)` | obligatorio · def. 0 |
| `haber` | `decimal(14,2)` | obligatorio · def. 0 |
| `proyecto_id` | `bigint` | FK proyectos |
| `centro_costo_id` | `bigint` | FK centros_costo |
| `glosa` | `varchar(255)` |  |

**`exportaciones_contables`** — Lote exportado al programa del contador. Si una operacion no tiene cuenta configurada queda fallida con su motivo; el resto del lote se exporta. Columnas exactas POR CONFIRMAR con el contador

| Columna | Tipo | Detalle |
|---|---|---|
| `id` | `bigint` | PK |
| `numero` | `varchar(30)` | única · obligatorio · EXP-NNNN |
| `periodo` | `date` | obligatorio |
| `adjunto_id` | `bigint` | FK adjuntos · Excel de formato fijo |
| `estado` | `varchar(15)` | obligatorio · def. generada |
| `formato_version` | `varchar(20)` |  |
| `total_asientos` | `integer` | obligatorio · def. 0 |
| `total_fallidos` | `integer` | obligatorio · def. 0 |
| `generada_por` | `bigint` | FK users |
| `generada_at` | `timestamptz` |  |

