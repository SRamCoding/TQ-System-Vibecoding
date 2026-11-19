# Documento 05 — Plan de construcción técnica

**Programa:** Ordenamiento Organizacional y Sistemas de Gestión — Techquk S.A.C.
**Versión:** 2.1 · Agosto 2026 · Complementa el documento 01

---

## Registro de cambios

| Versión | Fecha | Cambios |
|---|---|---|
| 1.0 | Ago 2026 | Plan inicial: fase de núcleo, 6 antipatrones, 5 decisiones |
| 2.0 | Ago 2026 | D5 resuelta · importaciones sube de fase · tres módulos nuevos |
| **2.1** | **Ago 2026** | **Estimaciones con el equipo real · el riesgo cambia de forma** |

### Qué cambió en la versión 2.0

`[V]` Tras revisar el organigrama del documento 03:

| Cambio | Motivo |
|---|---|
| **D5 resuelta:** Techquk importa directamente | `[V]` 80 operaciones en 2024, USD 452.3 mil CIF |
| **Importaciones sube de F4 a F3** | `[V]` Ahí está el riesgo: penalidad de S/ 250,498.54 |
| **Nueva F3 de contratos** | `[V]` Rol con indicador "penalidades meta cero" |
| **Tres módulos nuevos** | Contratos, Suscripciones, Desarrollo de software |
| **Dos decisiones nuevas: D6 y D7** | Gestión del desarrollo · medición de disponibilidad |
| El horizonte se extiende | 18 módulos en lugar de 15 |
| **Estimaciones recalculadas con el equipo real** | Tres desarrolladores con experiencia previa en el stack |
| **El riesgo cambia de forma** | Ya no es falta de gente sino concentración funcional |
**Insumo principal:** análisis de la base de datos de un ERP en producción (`S10_firstSteps.md`)

---

## 1. Qué aporta este documento

El documento 01 define **qué construir** y **en qué orden de negocio**. Este define **cómo
construirlo** y, sobre todo, **qué construir primero para no tener que rehacerlo**.

La diferencia entre ambos no es de detalle sino de naturaleza: el 01 se escribió desde el
negocio, este se escribe desde la evidencia de un ERP real de veintiséis años que se desarmó
tabla por tabla. Los patrones que aparecen aquí no salen de un libro — se observaron
funcionando, con sus aciertos y sus consecuencias medibles.

**Lo que no cambia del documento 01:** los cinco principios de diseño, el alcance funcional de
los diecinueve módulos, la decisión de construir en lugar de comprar, la de no desarrollar
contabilidad ni planilla, y el orden general CRM → Proyectos → Abastecimiento → Finanzas.

**Lo que sí cambia:** se inserta una fase previa, se ajusta el horizonte temporal a algo
defendible, y se fija por escrito un conjunto de decisiones técnicas que hoy están implícitas.

---

## 2. La corrección de fondo: falta el núcleo

### El problema

El plan actual arranca la construcción con el CRM. Es correcto desde el negocio —es lo de
menor riesgo y mayor visibilidad— pero implica que las decisiones estructurales se toman
mientras se construye la primera pantalla.

Cuatro mecanismos atraviesan **todos** los módulos del sistema:

| Mecanismo | Lo usan |
|---|---|
| Identidad de personas y organizaciones | CRM-01, CRM-02, CRM-03, ERP-01, ERP-02, ERP-06, ERP-07 |
| Autorización por monto y ámbito | CRM-03, ERP-02, ERP-03, ERP-06 |
| Estados de documento y transiciones | CRM-02, CRM-03, ERP-01, ERP-02, ERP-05, ERP-06 |
| Auditoría y trazabilidad | los diecinueve módulos |

Si se construyen dentro del CRM, quedan con forma de CRM. Cuando llegue Abastecimiento en la
fase 3, habrá que retrofitearlos — y retrofitear un mecanismo transversal con seis meses de
datos encima es exactamente el tipo de trabajo que hace que un proyecto se atrase el doble de
lo previsto.

### La propuesta

**Insertar una Fase 1 de núcleo, de tres a cuatro semanas, antes del CRM.** El resto del plan
se corre un mes y las fases siguientes se aceleran, porque cada módulo nuevo consume el núcleo
en lugar de inventarlo.

Es el mismo argumento que sostiene la Fase 0: no se automatiza lo que no está definido. Aquí:
**no se construye un módulo sobre mecanismos que todavía no existen.**

---

## 3. Los cuatro patrones del núcleo

Los cuatro se observaron funcionando en el ERP analizado. Se incluye la evidencia porque un
patrón sostenido en un caso real se defiende mejor que uno sostenido en una opinión.

---

### 3.1 Party — una entidad para personas y organizaciones

**Evidencia observada.** En el ERP analizado no existen tablas de clientes ni de proveedores.
Existe una sola, con 129 relaciones apuntando a ella —la más referenciada de una base de 1,916
tablas— y **veinticinco nombres de columna distintos** que representan otros tantos papeles:
proveedor, banco, obrero, supervisor, revisor, beneficiario, cobrador, quien aprueba, quien
reporta. Incluso la tabla de usuarios del sistema cuelga de ahí.

**El retorno, medido.** Su catálogo de roles pasó de 9 en 1999 a 13 en 2021 **sin que se creara
una sola tabla nueva**. Cuando en 2015 apareció la figura del practicante, insertaron una fila.

**Por qué aplica a Techquk.** Una misma organización puede ser cliente en un proyecto, proveedor
en otro y aliado tecnológico en un tercero. Con tablas separadas se duplica; con una sola, no.

**Implementación mínima:**

```php
Schema::create('parties', function (Blueprint $table) {
    $table->id();
    $table->string('razon_social');
    $table->foreignId('tipo_documento_id')->constrained();   // RUC, DNI, CE, pasaporte
    $table->string('numero_documento');
    $table->string('nombre_comercial')->nullable();
    $table->foreignId('ubigeo_id')->nullable()->constrained();
    $table->string('direccion')->nullable();
    $table->timestamps();
    $table->unique(['tipo_documento_id', 'numero_documento']);
});

Schema::create('party_roles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('party_id')->constrained()->cascadeOnDelete();
    $table->string('rol');            // cliente, proveedor, aliado, entidad_contratante
    $table->date('desde')->nullable();
    $table->date('hasta')->nullable();
    $table->timestamps();
    $table->unique(['party_id', 'rol']);
});
```

`desde` y `hasta` son un agregado sobre lo observado: el ERP analizado no registra desde cuándo
alguien es proveedor, y ese dato importa para auditoría.

**La advertencia que viene con el patrón.** Casi ninguna consulta va directo a `parties`:
siempre pasa por `party_roles`. Con volumen alto y consultas mal escritas se nota. Se resuelve
con índices y, si hace falta, vistas por rol.

Pero conviene ver la proporción: **el rendimiento es un problema que se resuelve; la
duplicación de datos es un problema de integridad que solo se administra.**

---

### 3.2 Autorización parametrizable en cuatro dimensiones

**Evidencia observada.** El ERP analizado no programa sus límites de aprobación: los guarda en
una tabla que cruza proyecto, centro de compra, moneda y rango de monto, más dos parámetros de
comportamiento —cuántas firmas se requieren y si el orden importa— y tres a nivel de cada
aprobador: si su firma es obligatoria, en qué turno le toca y qué nivel jerárquico tiene.

Con eso se expresa *"las compras en dólares del proyecto X sobre 50 mil requieren dos firmas en
orden, y la del gerente es indispensable"* **sin escribir una línea de código**.

**La lección más valiosa vino de su falla, no de su diseño.** En la instalación analizada, de
las nueve órdenes de compra emitidas **ninguna pasó por un control capaz de detenerla**. La
única regla con firma obligatoria aplicaba a un proyecto que aún no había comprado nada, y dos
proyectos compraron sin ninguna regla configurada. Nada lo señaló.

**Implementación mínima:**

```php
Schema::create('reglas_aprobacion', function (Blueprint $table) {
    $table->id();
    $table->string('documento');                       // cotizacion, orden_compra, requerimiento
    $table->foreignId('proyecto_id')->nullable()->constrained();
    $table->foreignId('area_id')->nullable()->constrained();
    $table->char('moneda', 3)->default('PEN');
    $table->decimal('monto_desde', 14, 2)->default(0);
    $table->decimal('monto_hasta', 14, 2)->nullable();
    $table->unsignedTinyInteger('firmas_requeridas')->default(1);
    $table->boolean('secuencial')->default(false);
    $table->boolean('activa')->default(true);
    $table->timestamps();
});

Schema::create('regla_aprobadores', function (Blueprint $table) {
    $table->id();
    $table->foreignId('regla_aprobacion_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->boolean('obligatorio')->default(false);
    $table->unsignedTinyInteger('orden')->default(0);
    $table->timestamps();
});
```

La resolución de qué regla aplica, con la regla específica ganándole a la general:

```php
public function reglaPara(string $documento, Model $doc): ?ReglaAprobacion
{
    return ReglaAprobacion::where('documento', $documento)
        ->where('activa', true)
        ->where('moneda', $doc->moneda)
        ->where('monto_desde', '<=', $doc->total)
        ->where(fn($q) => $q->whereNull('monto_hasta')
                            ->orWhere('monto_hasta', '>=', $doc->total))
        ->where(fn($q) => $q->whereNull('proyecto_id')
                            ->orWhere('proyecto_id', $doc->proyecto_id))
        ->orderByRaw('proyecto_id IS NULL')
        ->first();
}
```

**Dos cosas que el ERP analizado no tiene y que aquí son obligatorias:**

Una **pantalla de reglas vigentes en lenguaje claro**. En el caso observado hizo falta cruzar
tres tablas para descubrir que el único control real correspondía a un proyecto sin compras.

Una **alerta cuando un ámbito opera sin regla configurada**. Dos proyectos compraron sin
política de aprobación y nada lo advirtió.

---

### 3.3 Catálogo de tipos de transacción con banderas de comportamiento

**Evidencia observada.** El ERP analizado maneja **55 tipos de movimiento de almacén** —ingreso
por compra, por orden de compra, por inventario, por transformación, por préstamo, por
fabricación; egreso por venta, por consumo, por transferencia, por merma, por devolución— con
un solo formulario. Cada tipo trae media docena de campos booleanos que le dicen al sistema
cómo comportarse.

**El detalle que muestra hasta dónde llega.** "Egreso a partida de control" existe en tres
versiones: con pedido previo, sin pedido, y con descarga directa sin pasar por stock. La
empresa usa dos de las tres. El sistema no impone un nivel de rigor — lo ofrece y deja que la
operación elija.

**Implementación mínima:**

```php
Schema::create('tipos_movimiento', function (Blueprint $table) {
    $table->string('codigo', 4)->primary();
    $table->string('descripcion');
    $table->enum('sentido', ['ingreso', 'egreso']);
    $table->boolean('afecta_stock')->default(true);
    $table->boolean('afecta_costo')->default(true);
    $table->boolean('requiere_documento')->default(false);
    $table->boolean('requiere_orden_previa')->default(false);
    $table->boolean('requiere_serie')->default(false);
    $table->boolean('permite_costo_cero')->default(false);
    $table->boolean('activo')->default(true);
    $table->unsignedSmallInteger('orden')->default(0);   // ver 3.4
});
```

`requiere_serie` es un agregado específico de Techquk: cámaras, switches y equipos de red se
controlan por número de serie, y esa bandera decide si el formulario lo exige.

---

### 3.4 Estados como catálogo, con orden separado del código

**Evidencia observada.** En el ERP analizado, el catálogo de estados de orden de compra tiene
"Por Aprobar" con el código `06`, después de "Anulado" que es el `05`. El orden lógico del
proceso no coincide con la numeración porque ese estado se agregó años después y le asignaron
el siguiente número libre.

**La regla:** el código identifica, la columna `orden` presenta. Cuestan una columna y evitan
un desorden permanente.

**Y un caso peor observado.** El catálogo de estados de línea de pedido tiene cinco valores que
cubren solo el ciclo de aprobación — la entrega no está contemplada. El sistema registra que un
pedido se atendió en un booleano suelto, sin fecha ni usuario. Un reporte de pendientes armado
sobre el estado muestra nueve; armado sobre el booleano muestra cero. Ambos correctos según su
fuente.

**Implementación:**

```php
Schema::create('estados_documento', function (Blueprint $table) {
    $table->id();
    $table->string('documento');              // oportunidad, cotizacion, orden_compra
    $table->string('codigo', 4);
    $table->string('descripcion');
    $table->unsignedSmallInteger('orden');
    $table->boolean('es_inicial')->default(false);
    $table->boolean('es_final')->default(false);
    $table->timestamps();
    $table->unique(['documento', 'codigo']);
});

Schema::create('transiciones_estado', function (Blueprint $table) {
    $table->id();
    $table->foreignId('desde_estado_id')->constrained('estados_documento');
    $table->foreignId('hacia_estado_id')->constrained('estados_documento');
    $table->boolean('requiere_motivo')->default(false);
    $table->string('permiso')->nullable();
    $table->timestamps();
});
```

La tabla de transiciones es la que convierte el catálogo en una máquina de estados de verdad:
declara qué saltos están permitidos y quién puede hacerlos. Ahí vive la regla del documento 01
de que ninguna oportunidad se cierra como perdida sin motivo tipificado.

---

## 4. Los seis antipatrones prohibidos

Cada uno se observó causando daño en el ERP analizado. Conviene que estén por escrito antes de
la primera migración, porque después de doscientas tablas ya no se corrigen.

| # | Prohibido | Qué se observó | La regla |
|---|---|---|---|
| **A1** | Códigos de negocio como llave primaria | Relaciones que necesitan **ocho columnas** para apuntar a un registro | Llave artificial siempre. El código de negocio es atributo único, no llave |
| **A2** | Tipos de dato de largo fijo para texto | 131 tablas con `char`: todo viene con espacios sobrantes y las comparaciones fallan sin avisar | `varchar` o `text` de PostgreSQL. Nunca `char` |
| **A3** | Archivos binarios dentro de la base | Una tabla de 280 MB con 508 registros — 565 KB por fila | Adjuntos en `Storage`; en la tabla solo la ruta |
| **A4** | Campos de auditoría opcionales | Tres tablas con fecha de creación completamente en nulo | `NOT NULL DEFAULT now()`. Si puede quedar vacío, quedará vacío |
| **A5** | Lógica de negocio en la base de datos | 9,159 procedimientos almacenados: el negocio vive en el motor, no en la aplicación | Lógica en PHP. La base valida integridad, no reglas de negocio |
| **A6** | Dos convenciones de nombres conviviendo | Códigos de usuario y GUID mezclados sin criterio; `CodEmpresa` y `CodIdentificadorEmpresa` para lo mismo | Una convención, escrita, aplicada desde la primera tabla |

**El antipatrón que los une todos.** En el ERP analizado se encontraron **seis casos** de
controles declarados y nunca alimentados: columnas de trazabilidad en nulo, un campo de firma
obligatoria apagado, tres estados de entrega que existen y jamás se usan, un padrón tributario
sin actualizar desde 2015.

> **El software ofrecía más estructura de la que la operación alimentaba.**

Un control a medias es peor que ninguno, porque da la falsa sensación de que algo está bajo
vigilancia. La regla derivada: **si un dato importa, se hace obligatorio en la base. Si no
importa lo suficiente para eso, probablemente no debería existir.**

---

## 5. Fases revisadas

### 5.1 Qué cambia respecto del documento 01

| Cambio | Motivo |
|---|---|
| Se inserta **F1 — Núcleo** de 3–4 semanas | Los cuatro mecanismos transversales antes del primer módulo |
| El margen por proyecto se adelanta a F3 | Es el dolor número uno declarado en el documento 00, y en el plan actual aparece recién en el mes siete |
| Se agrega criterio de salida técnico por fase | Un módulo "terminado" sin pruebas ni documentación no está terminado |
| El horizonte pasa de 10–12 a **14–18 meses** a dedicación parcial | Ver 5.3 |

### 5.2 Secuencia propuesta v2.0

| Fase | Contenido | Semanas | Criterio de salida |
|---|---|---:|---|
| **F0** | Levantamiento y diseño organizacional | 2–3 | Mapa de procesos, organigrama y matriz RACI aprobados |
| **F1** | **Núcleo:** los diez mecanismos | 5–6 | Mecanismos con pruebas y un módulo de demostración |
| **F2** | Cuentas · Catálogo comercial · Personal | 4–5 | Cartera y catálogo cargados; asignaciones operando |
| **F3** | **Contratos ★** · Proyectos · margen simplificado | 6–8 | **Alerta de penalidad diaria funcionando** · margen estimado vs. real |
| **F4** | Oportunidades · Cotizaciones · Licitaciones | 6–8 | 100% de cotizaciones nuevas emitidas desde el sistema |
| **F5** | Compras · **Importaciones ★** · Almacén | 8–10 | Costo puesto en almacén reflejado en el margen · alerta de embarque vs. hito |
| **F6** | Facturación y cobranzas | 5–6 | Ciclo cerrado de contrato a cobro sin recaptura |
| **F7** | Servicios · **Suscripciones ★** | 5–6 | SLA medido · renovaciones alertadas |
| **F8** | **Horas de desarrollo ★** · Analítica | 4–5 | Margen de proyectos híbridos completo |

### 5.3 Los tres cambios de orden, argumentados

`[V]` **Contratos entra en F3, antes que el CRM.** El riesgo económico documentado es una
penalidad de S/ 250,498.54, superior al monto íntegro de cinco de los nueve contratos. Un
sistema que alerta sobre vencimientos contractuales rinde desde el primer mes, mucho antes que
uno que ordena el embudo comercial.

`[V]` **Importaciones sube y va con compras.** Con 80 operaciones al año —una cada cuatro días
hábiles— y siendo la entrega tardía del equipo la principal causa de incumplimiento de plazo, no
puede quedar en una fase posterior a proyectos.

`[S]` **Catálogo comercial y Personal suben a F2.** El grafo de dependencias del documento 10 los
requiere para cotizaciones, compras, almacén y asignación de proyectos.

### 5.4 El equipo real y lo que cambia en el horizonte

`[V]` **La composición del equipo.** Tres desarrolladores con experiencia previa en un ERP
construido sobre el mismo stack —Laravel, Filament, PostgreSQL— más un analista funcional sin
experiencia previa en desarrollo de ERP. La Jefatura de Desarrollo está vacante.

Eso cambia las estimaciones en dos sentidos opuestos, y conviene separarlos.

#### Lo que acelera

`[S]` **No hay curva de aprendizaje del stack.** El tiempo que normalmente se pierde decidiendo
cómo se estructura un proyecto Filament, cómo se organizan los recursos y cómo se manejan los
permisos ya está resuelto.

`[S]` **Y hay experiencia de dominio, no solo de herramienta.** Haber construido un ERP antes
significa conocer los problemas que aparecen en el mes seis: la desnormalización que se
justifica, los estados que hay que versionar, el reporte que nadie previó. Eso vale más que la
familiaridad con el framework.

`[S]` Estimo entre **15% y 20% menos tiempo** que un equipo sin ese antecedente.

#### Lo que no acelera

`[S]` **Tres desarrolladores no rinden el triple que uno.** Hay coordinación, revisión mutua y
decisiones que hay que tomar en conjunto. La proporción realista es **2.2 a 2.5 veces**, no 3.

`[S]` **Y el núcleo casi no se puede repartir.** Los diez mecanismos son decisiones de diseño
entrelazadas: partirlos entre tres personas produce tres criterios distintos. Conviene que lo
construyan una o dos personas, con el resto revisando.

`[V]` **El cuello de botella no es el desarrollo, es la especificación.** Dieciocho módulos, 78
pantallas y 61 widgets tienen que ser especificados y validados por **una sola persona**. Tres
desarrolladores esperando definiciones van más rápido que uno, pero no más rápido de lo que
llegan las definiciones.

#### Las estimaciones revisadas

**Escenario 1 — contabilidad y planilla externas (recomendado):**

| Dedicación del equipo | Horizonte |
|---|---|
| Tres desarrolladores a dedicación completa | **6–8 meses** |
| Tres desarrolladores al 50% | **11–15 meses** |
| Dos a dedicación completa, uno al 50% | **7–9 meses** |
| Un desarrollador a dedicación completa | 9–12 meses |
| Un desarrollador a dedicación parcial | 16–21 meses |

**Escenario 2 — contabilidad y planilla propias:**

| Dedicación del equipo | Horizonte |
|---|---|
| Tres desarrolladores a dedicación completa | **10–13 meses** |
| Tres desarrolladores al 50% | **18–24 meses** |
| Un desarrollador a dedicación completa | 15–19 meses |

`[S]` **Comparado con la versión anterior**, que asumía un desarrollador sin experiencia previa
en el stack, el horizonte del escenario 1 baja de 11–14 meses a **6–8 meses** con el equipo
completo dedicado.

#### Cómo se reparte el trabajo

`[S]` El grafo de dependencias del documento 10 permite paralelizar de forma natural:

| Fase | Cómo se reparte |
|---|---|
| **F0** Levantamiento | El analista al frente; los desarrolladores participan en las sesiones técnicas |
| **F1** Núcleo | **Una o dos personas.** No se reparte bien; el resto revisa y prepara F2 |
| **F2** Cuentas · Catálogo · Personal | **Tres frentes en paralelo**, uno por persona |
| **F3** Contratos · Proyectos | Dos personas; la tercera adelanta el catálogo comercial |
| **F4** CRM comercial | Tres frentes: oportunidades, cotizaciones, licitaciones |
| **F5** Compras · Importaciones · Almacén | Tres frentes, con el enlace de costeo como punto de encuentro |
| **F6–F8** | Se reparte por módulo |

`[S]` **La fase 2 es la que mejor aprovecha el equipo:** tres módulos que no dependen entre sí y
que todo lo demás necesita.

#### La condición que sostiene estas estimaciones

`[P]` **Que la dedicación sea real.** El organigrama muestra que estos tres desarrolladores son
también quienes entregan software a clientes. Los proyectos P6 y P9 del Hospital Regional
Docente Las Mercedes incluyen frente de software.

`[S]` Un equipo "al 100%" que en la práctica dedica el 40% porque hay entregas de por medio
produce el horizonte de la fila del 50%, no el de la primera. **Conviene que Gerencia autorice
un porcentaje explícito y que se mida**, en lugar de asumir dedicación completa y descubrir el
desvío en el mes cinco.

### 5.5 El margen adelantado

En el plan original, el margen real por proyecto llega en F3 —mes siete o después— porque
depende del módulo de compras.

**Propuesta:** en F3 se habilita una carga manual de costos por proyecto. No es elegante, pero
permite que Gerencia vea margen estimado contra real desde el mes cuatro. Cuando F4 entregue
las compras, esa carga manual se reemplaza por el dato automático sin cambiar la pantalla que
Gerencia ya conoce.

`[S]` El costo de esa pantalla intermedia es de días. El beneficio es que el patrocinador ve
resultado en el indicador que más le importa, seis meses antes.

---

## 6. Decisiones que hay que tomar antes de la primera línea de código

Ninguna es técnicamente difícil. Todas son caras de cambiar después.

### D1 — ¿Multiempresa o empresa única?

Techquk es un integrador de tecnología. Es plausible que en algún momento quiera **vender este
ERP** a clientes del mismo rubro.

Si esa posibilidad existe, el aislamiento por inquilino se diseña ahora. Si se descarta, se
construye para una sola empresa y se ahorra complejidad.

`[Decisión para Gerencia]` La respuesta cambia el esquema completo, no un módulo.

### D2 — ¿Qué organiza la información?

En el ERP analizado, el código de proyecto aparece en **604 tablas**. Es la decisión estructural
más importante de su diseño y la pagaron en columnas repetidas.

Para Techquk la pregunta equivalente: ¿la información se organiza por proyecto, por cliente o
por unidad de negocio? La respuesta condiciona permisos, reportes y aislamiento de datos.

`[S]` Dado el modelo de negocio —proyectos de integración con canal público y privado— el
proyecto parece la unidad correcta. Pero conviene decidirlo explícitamente y no por omisión.

### D3 — El catálogo CUBSO

Techquk vende al Estado y está inscrita en el RNP. El **Catálogo Único de Bienes, Servicios y
Obras** es de uso obligatorio en contratación pública.

Es el único elemento del ERP analizado que aplica a Techquk tal cual: son 57,610 códigos
basados en el estándar UNSPSC de Naciones Unidas.

**Recomendación:** cargarlo en F1 como catálogo del núcleo, y que el catálogo comercial (CRM-06)
referencie a él. Así toda cotización y toda orden nacen ya clasificadas para licitación.

`[P]` Verificar la versión vigente con el organismo regulador. La instalación analizada tiene
una carga de 2018 sin refrescar.

### D4 — Facturación electrónica

El documento 01 la lista como integración de fase posterior. Conviene adelantarla como
**decisión**, aunque la construcción venga después.

La ficha pública de Techquk indica que emite desde sus propios sistemas desde abril de 2019.
Eso significa que ya existe infraestructura funcionando, y determina si el ERP emite o solo
alimenta.

`[P]` Punto ya listado en el documento 00. Se repite aquí porque condiciona el diseño de ERP-06.

### D5 — Repositorio y continuidad

El documento 01 ya lo señala. Se refuerza: el repositorio a nombre de la empresa, no de una
persona, **antes** del primer commit. Mover un repositorio con historial es incómodo; empezar
en el lugar correcto no cuesta nada.

---

## 7. El riesgo real del proyecto

El documento 00 lista cinco riesgos. Cuatro están bien mitigados. El quinto cambió de forma al
conocer el equipo.

### 7.1 El riesgo ya no es la falta de gente

`[V]` **Tres desarrolladores con experiencia en el mismo stack** es un punto de partida mucho
mejor que el que asumía la versión anterior de este documento. El conocimiento técnico está
repartido: si uno se va, los otros dos entienden el modelo.

### 7.2 El riesgo real es la concentración funcional

`[V]` **El conocimiento del negocio está en una sola persona.** El analista funcional es quien
levanta los procesos, escribe las especificaciones, valida con las áreas y decide qué significa
cada campo. Los desarrolladores construyen lo que esa persona define.

`[S]` Si esa persona no está disponible, los tres desarrolladores no se detienen por falta de
capacidad técnica — se detienen por falta de definiciones.

`[V]` **Y el volumen es considerable:** diecinueve módulos, 78 pantallas y 61 widgets que
especificar y validar.

#### Mitigación, en orden de efectividad

**Especificar por adelantado, no por demanda.** Que las definiciones de una fase estén cerradas
antes de que empiece su construcción. Si el analista especifica mientras los desarrolladores
construyen lo mismo, el cuello de botella se vuelve permanente.

**Que un desarrollador acompañe el levantamiento de su módulo.** No para especificar, sino para
entender el porqué. Es la forma más barata de que el conocimiento funcional deje de estar en una
sola cabeza.

**Las pruebas automatizadas como documentación.** Son la única que no se desactualiza, porque
cuando miente, falla. Criterio de salida por fase: cobertura sobre las reglas de negocio, no
sobre el CRUD.

**El documento de decisiones.** Un archivo donde cada decisión de arquitectura quede registrada
con su fecha y su motivo. Cuesta diez minutos por decisión y es lo que permite que alguien nuevo
entienda **por qué** las cosas son como son, no solo cómo son.

### 7.3 El segundo riesgo: la dedicación que no es real

`[V]` **Los tres desarrolladores son también quienes entregan software a clientes.** Los
proyectos P6 y P9 del Hospital Regional Docente Las Mercedes incluyen frente de software, y el
55.3% del portafolio son contratos híbridos.

`[S]` Un equipo autorizado "al 100%" que en la práctica dedica el 40% porque hay entregas de por
medio produce el horizonte del 50%, no el del 100%. Y el desvío se descubre en el mes cinco,
cuando ya es tarde para replanificar.

**Mitigación:** que Gerencia autorice un **porcentaje explícito** de dedicación y que se mida
con las horas imputadas. `[S]` Es exactamente lo que el módulo ERP-12 va a permitir hacer con
los proyectos de cliente — conviene aplicarlo también al proyecto interno.

### 7.4 El tercer riesgo: la vacante de jefatura

`[V]` La Jefatura de Desarrollo está vacante, y el organigrama la marca como prioridad 1.

`[S]` Sin ella, las decisiones de arquitectura recaen en el analista funcional o se toman por
consenso entre los tres desarrolladores. Ninguna de las dos es mala, pero conviene que sea
explícito quién decide cuando hay desacuerdo — o esas decisiones se postergan.

`[S]` **Mientras la vacante siga abierta**, la recomendación es nombrar un responsable técnico
del proyecto entre los tres desarrolladores, con el mandato explícito de resolver empates.

---

## 8. Criterios de salida técnicos por fase

El documento 01 define criterios de salida funcionales. Faltan los técnicos. Una fase no está
cerrada sin las cinco condiciones:

| # | Condición |
|---|---|
| 1 | Migraciones que reconstruyen el esquema desde cero sin intervención manual |
| 2 | Pruebas automatizadas sobre las reglas de negocio de la fase, no solo sobre el CRUD |
| 3 | Documentación de los modelos y sus relaciones, publicada en el sitio de documentación |
| 4 | Datos de prueba (`seeders`) que permitan levantar un entorno demostrable en minutos |
| 5 | Acta de aceptación firmada por el usuario clave del área |

`[S]` La condición 4 parece menor y no lo es: es lo que permite que Gerencia vea el sistema
funcionando en cualquier momento, y lo que hace posible que otra persona levante el proyecto
sin ayuda.

---

## 9. Cómo se mide que el plan funcionó

Los indicadores del documento 01 miden adopción. Faltan dos que miden si la **construcción**
fue sana:

| Indicador | Meta | Por qué |
|---|---|---|
| Tablas sin llave primaria | **0** | En el ERP analizado, 67 tablas con datos no la tienen |
| Columnas de auditoría en nulo | **0** | Se observaron tres tablas con la fecha de creación completamente vacía |
| Reglas de aprobación sin ámbito cubierto | **0** | Dos proyectos compraron sin política configurada y nada lo advirtió |
| Tiempo de levantar el entorno desde cero | **< 15 min** | Prueba de que la documentación y los seeders funcionan |
| Cobertura de pruebas sobre reglas de negocio | **> 70%** | Es la documentación que no se desactualiza |

Las tres primeras se verifican con una consulta. Conviene correrlas al cierre de cada fase.

---

## 10. Lo que este plan deliberadamente no resuelve

`[S]` **No sustituye la Fase 0.** Todo lo anterior es estructura. Qué campos lleva una
cotización de Techquk, qué estados tiene realmente un proyecto de videovigilancia y quién
aprueba qué — eso sale del levantamiento, no de este documento.

`[S]` **No convierte el análisis del ERP ajeno en requisitos.** Ese sistema es de una empresa
constructora. Sus presupuestos de obra, análisis de precios unitarios y metrados no aplican.
Lo aprovechable son los patrones estructurales y los errores documentados, no el dominio.

`[V]` **Y hay un límite del método que conviene tener presente.** Todo lo observado en aquella
base describe **cómo se registró** cada operación, no necesariamente cómo ocurrió. Los códigos
de movimiento y de estado registran qué opción eligió el usuario. En una implementación nueva,
donde la gente todavía aprende el sistema, esa distancia puede ser considerable.

Aplicado a Techquk: el sistema que se construya va a registrar lo que la gente decida
registrar. Los controles del núcleo —firmas obligatorias, campos no nulos, transiciones
validadas— existen precisamente para reducir esa distancia.

---

## 11. Resumen de lo solicitado a Gerencia

El documento 00 pide dos decisiones. Este agrega cinco, todas previas a la construcción:

| # | Decisión | Impacto si no se toma |
|---|---|---|
| **D1** | ¿Multiempresa o empresa única? | El esquema completo se diseña sobre un supuesto |
| **D2** | ¿Qué organiza la información: contrato, proyecto o cliente? | Permisos y reportes quedan indefinidos |
| **D3** | ¿Se incorpora el catálogo CUBSO en el núcleo? | Las cotizaciones no nacen clasificadas para licitación |
| **D4** | ¿El ERP emite comprobantes o alimenta al emisor actual? | ERP-06 se diseña sin destino claro |
| ~~**D5**~~ | ~~¿Importación directa o por terceros?~~ | **`[V]` RESUELTA: directa** |
| **D6** ★ | ¿El desarrollo se gestiona en el ERP o en herramienta aparte? | ERP-12 se diseña sin alcance |
| **D7** ★ | ¿La disponibilidad de servicios se mide sola o a mano? | ERP-11 no puede medir su indicador |
| **D8** | ¿Cuál de los tres escenarios de dedicación se autoriza? | Se compromete un plazo que la capacidad no sostiene |

`[S]` **D2 cambió de forma.** En la v1.0 preguntaba si el proyecto organizaba la información.
Con el módulo de contratos, la pregunta es si el eje es el contrato o el proyecto — y la
respuesta probable es que sean dos niveles: el contrato gobierna plazos y penalidades, el
proyecto gobierna ejecución y costo.

---

## 12. La idea que resume el plan

Los tres patrones más valiosos que se observaron en veintiséis años de un ERP real son
variaciones de la misma decisión:

> **Poner en tablas lo que la tentación sería programar en código.**

Una tabla de personas con roles asignables, en vez de una tabla por cada rol. Una fila por
regla de aprobación, en vez de un `if` por cada límite de monto. Un catálogo de tipos de
movimiento con banderas, en vez de un formulario por cada variante.

En los tres casos el beneficio es el mismo: **agregar una variante nueva es insertar una fila,
no reprogramar y publicar.**

Y en los tres casos viene con la misma contra, que también se observó: **cada cosa que se
parametriza es una cosa que alguien puede configurar mal.** Por eso el núcleo incluye, además
de los mecanismos, las pantallas que muestran su estado y las alertas cuando quedan sin
configurar.

La flexibilidad sin visibilidad se convierte en reglas fantasma que nadie recuerda haber
puesto. Eso fue exactamente lo que ocurrió en el sistema analizado, y es lo único de su
experiencia que Techquk puede evitar por completo desde el primer día.

---

*Fin del documento 05. Complementa a `01-Plan-ERP-CRM.md`. La evidencia de cada patrón y
antipatrón está en `S10_firstSteps.md`.*