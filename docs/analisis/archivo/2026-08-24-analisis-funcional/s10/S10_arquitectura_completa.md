# Arquitectura de la base de datos S10

**Sistema analizado:** ERP S10 en producción en Techquk S.A.C.
**Foto:** respaldo del 17 de agosto de 2026, 15:15
**Fuente:** catálogo del sistema de SQL Server, extraído en cuatro consultas
**Fecha del documento:** agosto 2026

---

## Cómo leer este documento

Está escrito para que se entienda sin ser técnico. Cada sección empieza explicando **qué es** el
elemento antes de mostrar los números.

Las afirmaciones llevan una marca:

| Marca | Significado |
|---|---|
| `[V]` | **Verificado** — sale directamente de los datos extraídos |
| `[S]` | **Supuesto** — interpretación razonable, no comprobada |
| `[P]` | **Pendiente** — hay que preguntarlo o consultarlo aparte |

**Los archivos que acompañan a este documento:**

| Archivo | Qué contiene |
|---|---|
| `s10_catalogo_tablas.csv` | Las 1,916 tablas con su dominio, filas, columnas y relaciones |
| `s10_diccionario_columnas.csv` | Las 32,626 columnas con su tipo, longitud y si admite vacío |
| `s10_relaciones.csv` | Las 1,571 relaciones entre tablas |

---

## 1. Qué es una base de datos, en dos párrafos

Una base de datos es un archivador. Cada **tabla** es un cajón que guarda fichas de un mismo
tipo: un cajón para clientes, otro para facturas, otro para productos.

Cada ficha es una **fila**, y cada dato de esa ficha es una **columna**. Si la tabla de
clientes tiene las columnas RUC, razón social y dirección, entonces cada cliente es una fila
con esos tres datos.

Lo que convierte un montón de cajones en un sistema son las **relaciones**: la ficha de una
factura no repite el nombre del cliente, sino que apunta a su ficha en el cajón de clientes. Si
el cliente cambia de dirección, se corrige en un solo lugar.

Y alrededor de las tablas hay tres cosas más: los **índices**, que son como el índice de un
libro y hacen que buscar sea rápido; los **procedimientos**, que son instrucciones guardadas
dentro de la propia base; y las **vistas**, que son consultas preparadas de antemano.

---

## 2. El tamaño del sistema

`[V]` Estas son las cifras exactas de la instalación analizada:

| Elemento | Cantidad |
|---|---:|
| Tablas | **1,916** |
| Columnas en total | **32,626** |
| Relaciones entre tablas | **1,571** |
| Índices | **2,324** |
| Procedimientos almacenados | **9,152** |
| Vistas | **1,356** |
| Funciones | **919** |
| Disparadores | **15** |
| **Total de objetos programables** | **11,441** |

### La primera sorpresa: la mayoría de las tablas está vacía

`[V]` De las 1,916 tablas, **519 tienen al menos una fila** y **1,397 están completamente
vacías**. Es el **73%**.

`[S]` Eso no es un defecto. Es cómo funciona el software empaquetado: se instala el esquema
completo y la licencia decide qué pantallas se habilitan, no qué tablas existen.

`[V]` **La evidencia que lo respalda** está en la propia base. Hay un registro llamado
`EsquemaMD5` que guarda una huella digital de la estructura completa. Esa huella solo tiene
sentido si todas las instalaciones comparten el mismo esquema — si cada cliente tuviera solo
sus tablas, cada uno tendría una huella distinta y la verificación no serviría.

`[V]` Se confirma con lo que hay dentro: existen 34 tablas de gestión inmobiliaria —venta de
departamentos, prototipos, cocheras— todas vacías. Techquk no vende departamentos. Están ahí
porque vienen con el producto.

### El promedio de columnas

`[V]` 32,626 columnas entre 1,916 tablas dan un promedio de **17 columnas por tabla**. Pero el
promedio esconde una dispersión enorme:

| Tabla | Columnas | Filas |
|---|---:|---:|
| `FlujoCajaDocumento` | **534** | 0 |
| `TmpNRO` | 392 | 31 |
| `TmpNRO_Detalle` | 240 | 0 |
| `RDocumento` (facturas de compra) | **190** | 1,340 |
| `Proyecto` | **173** | 234 |
| `GuiaAlmacen` | 101 | 151 |

`[S]` Una tabla de 190 columnas para las facturas de compra indica que ahí se acumuló mucho a
lo largo de los años: campos de impuestos, de detracción, de percepción, de facturación
electrónica, de contabilización. Cada regulación nueva agregó columnas en lugar de tablas.

---

## 3. Cómo está organizada: los dominios

`[S]` **Advertencia importante sobre esta sección.** La base **no está dividida** en módulos.
Las 1,916 tablas viven todas juntas en el mismo espacio, sin ninguna separación física. Si
abres el explorador de objetos, ves una sola lista alfabética.

La agrupación que sigue la construimos nosotros, clasificando cada tabla por su nombre. Es
inferencia, no un dato del sistema. **Cubre el 79.5% de las tablas**; el resto no encaja
limpiamente en ninguna categoría.

### El reparto

`[V]` Resultado de clasificar las 1,916 tablas:

| Dominio | Tablas | Con datos | Vacías | Filas | Columnas |
|---|---:|---:|---:|---:|---:|
| **Núcleo y maestros** | 261 | 88 | 173 | 135,262 | 3,110 |
| **Presupuestos** | 117 | 48 | 69 | 300,517 | 1,722 |
| **Proyectos y producción** | 206 | 56 | 150 | 621,890 | 3,238 |
| **Logística y compras** | 61 | 31 | 30 | 5,958 | 1,002 |
| **Almacén e inventario** | 63 | 26 | 37 | 4,001 | 1,064 |
| **Contabilidad** | 76 | 32 | 44 | 95,398 | 1,238 |
| **Tesorería y finanzas** | 75 | 11 | 64 | 116 | 1,263 |
| **Facturación y ventas** | 38 | 10 | 28 | 10,686 | 704 |
| **Planillas y personal** | 103 | 15 | 88 | 2,823 | 1,575 |
| **Asistencia y tareo** | 60 | 13 | 47 | 351 | 940 |
| **Subcontratos** | 35 | 17 | 18 | 46,635 | 478 |
| **Activos y equipos** | 21 | 1 | 20 | 7 | 325 |
| **Inmobiliaria** | 34 | 0 | 34 | 0 | 531 |
| **Temporales y respaldo** | 307 | 51 | 256 | 72,442 | 8,860 |
| **Metadatos del propio sistema** | 66 | 44 | 22 | 103,616 | 889 |
| *Sin clasificar* | 393 | 76 | 317 | 1,718 | 5,688 |

### Tres lecturas de esa tabla

**`[V]` El módulo más grande no es de negocio.** "Temporales y respaldo" tiene 307 tablas y
8,860 columnas — más columnas que cualquier módulo real. Son tablas de trabajo que el sistema
usa para armar reportes y luego descarta.

**`[V]` Inmobiliaria está completamente vacía.** 34 tablas, 531 columnas, cero filas. Es la
prueba más limpia de que el esquema se instala completo.

**`[V]` Tesorería casi no se usa.** 75 tablas y apenas 116 filas en total. Techquk tiene el
módulo bancario instalado pero prácticamente no lo opera.

### Las tablas más pesadas de cada dominio

`[V]` Las cinco con más filas de toda la base:

| Tabla | Filas | Qué es |
|---|---:|---|
| `UsoRecurso` | 546,965 | Consumo de recursos por actividad. `[S]` Tabla calculada, no capturada |
| `ClasificacionTipoDetalle` | 131,871 | Detalle de agrupaciones de costo |
| `ActualizacionDetalle` | 63,831 | Bitácora interna del proceso de actualización del producto |
| `Cubso` | 57,610 | Catálogo del Estado peruano |
| `ConfiguracionVsflex` | 51,127 | Configuración visual de las grillas de pantalla |

`[S]` **Solo una de esas cinco es información de negocio.** Las otras cuatro son datos
derivados, bitácoras del fabricante o configuración de interfaz.

---

## 4. Las relaciones: cómo se conectan las tablas

`[V]` Hay **1,571 relaciones** declaradas entre tablas. Cada una dice "esta columna de esta
tabla apunta a esa otra tabla".

### Las tablas centrales

`[V]` Estas son las diez tablas a las que más apunta el resto del sistema:

| Tabla | Veces referenciada | Qué guarda |
|---|---:|---|
| **`Identificador`** | **129** | Personas y organizaciones |
| `Proyecto` | 101 | Los proyectos |
| `Insumo` | 88 | Materiales y recursos |
| `Partida` | 51 | Partidas de obra |
| `EsquemaPlanilla` | 44 | Estructura de planillas |
| `Concepto` | 41 | Conceptos de remuneración |
| `SubpresupuestoDetalle` | 39 | Detalle de presupuestos |
| `Moneda` | 38 | Monedas |
| `Subpresupuesto` | 28 | Subpresupuestos |
| `Presupuesto` | 24 | Presupuestos |

**Cómo leer esto.** Que 129 tablas apunten a `Identificador` significa que **es el centro del
sistema**. Cada vez que algo necesita registrar una persona u organización —un cliente, un
proveedor, un banco, un trabajador, un aprobador— apunta a esa tabla.

`[V]` Ese es el hallazgo más valioso del análisis, y está desarrollado en el documento de
resultados: no existen tablas separadas de clientes y proveedores. Existe una sola de personas,
y el papel que cada una cumple se define aparte.

### Las tablas más dependientes

`[V]` Del otro lado, estas son las que más dependen de otras:

| Tabla | Relaciones salientes |
|---|---:|
| `DescuentoPersonal` | 11 |
| `EmpresaParametroContable` | 11 |
| `ProyectoPartida` | 11 |
| `AsientoContable` | 10 |
| `RDocumento` | 9 |

`[S]` Una tabla con once relaciones salientes es un punto de cruce: para entender una fila hay
que consultar once tablas más. Son las más costosas de reportar y las más frágiles ante
cambios.

### Qué pasa al borrar

`[V]` De las 1,571 relaciones, **1,545 están configuradas como `NO_ACTION`** y solo **26 como
`CASCADE`**.

**Qué significa.** `NO_ACTION` es la opción conservadora: si intentas borrar un cliente que
tiene facturas, la base lo impide. `CASCADE` es la opción peligrosa: borra el cliente y se
llevan sus facturas por delante.

`[S]` Que el 98% sea conservador indica un diseño cuidadoso. Es la configuración correcta para
un sistema contable, donde nada debería desaparecer sin dejar rastro.

---

## 5. Los índices: por dónde se consulta

**Qué es un índice.** Igual que el índice alfabético al final de un libro: en vez de leer todas
las páginas para encontrar un tema, vas al índice y saltas directo. Sin índices, buscar una
factura entre un millón implica revisarlas todas.

`[V]` Cifras de la instalación:

| Concepto | Cantidad |
|---|---:|
| Índices totales | 2,324 |
| De ellos, únicos | 1,726 |
| De ellos, agrupados *(clustered)* | 1,197 |
| Tablas con al menos un índice | 1,649 |

`[V]` **267 tablas no tienen ningún índice.**

`[S]` Que 1,726 de 2,324 sean únicos —el 74%— dice algo bueno del diseño: la mayoría de los
índices no está solo para acelerar, sino para **impedir duplicados**. Es una decisión de
integridad, no de rendimiento.

---

## 6. Las llaves primarias: cómo se identifica cada fila

**Qué es una llave primaria.** El dato que identifica una fila sin ambigüedad, como el DNI
identifica a una persona. Sin ella, dos filas idénticas son indistinguibles.

### El problema encontrado

`[V]` **338 tablas no tienen llave primaria.** De esas, **67 tienen datos adentro**.

`[S]` Una tabla con datos y sin llave primaria es un riesgo: si por error se duplica una fila,
no hay forma automática de detectarlo ni de borrar solo una de las dos.

### Las llaves compuestas

`[V]` De las 1,578 tablas que sí tienen llave, así se reparte cuántas columnas necesita cada
una:

| Columnas en la llave | Tablas |
|---:|---:|
| 1 | 1,015 |
| 2 | 224 |
| 3 | 151 |
| 4 | 82 |
| 5 | 52 |
| 6 | 15 |
| 7 | 12 |
| 8 | 15 |
| 9 | 10 |
| 10 | 1 |
| **11** | **1** |

`[V]` La más extrema es `TareoActividadObreroHora`, que necesita **once columnas** para
identificar una fila:

```
CodProyecto, CodOT, CodTareo, CodTarea, CodPresupuesto,
CodSubpresupuesto, Item, CodSubpartida, CodSubActividad,
CodObrero, Correlativo
```

**Por qué ocurre esto.** S10 usa como identificador el código que escribe el usuario, no un
número interno. Y como cada nivel arrastra los códigos de todos sus padres, la llave se va
alargando: el proyecto, dentro de él la orden de trabajo, dentro de ella el tareo, dentro de él
la tarea, y así.

`[S]` **La consecuencia práctica.** Cualquier consulta que quiera unir esa tabla con otra
necesita comparar once columnas. Es incómodo de escribir, lento de ejecutar y frágil: si
mañana se agrega un nivel más a la jerarquía, hay que tocar decenas de tablas a la vez.

> **Esta es la primera lección de diseño del análisis.** Un identificador interno automático,
> con el código de negocio como columna aparte, evita todo esto por completo.

---

## 7. Los tipos de dato

**Qué es un tipo de dato.** La clase de información que admite una columna: texto, número,
fecha, sí/no. Elegir mal el tipo tiene consecuencias que aparecen años después.

`[V]` Reparto de las 32,626 columnas:

| Tipo | Columnas | Qué guarda |
|---|---:|---|
| `varchar` | 12,957 | Texto de largo variable |
| `numeric` | 6,913 | Números con decimales |
| `uniqueidentifier` | 3,313 | Identificadores únicos de 36 caracteres |
| `datetime` | 2,414 | Fecha y hora |
| `bit` | 2,376 | Sí o no |
| `int` | 1,141 | Números enteros |
| `tinyint` | 881 | Enteros muy pequeños |
| `smallint` | 779 | Enteros pequeños |
| `float` | 489 | Números con decimales aproximados |
| `decimal` | 442 | Números con decimales exactos |
| `nvarchar` | 317 | Texto con soporte internacional |
| `smalldatetime` | 209 | Fecha y hora, menos precisa |
| `char` | 207 | Texto de largo fijo |
| `text` | 48 | Texto largo — **tipo obsoleto** |
| `image` | 41 | Contenido binario — **tipo obsoleto** |
| `money` | 17 | Importes monetarios |

### Tres observaciones

`[V]` **Los tipos obsoletos siguen presentes.** `text` e `image` fueron descontinuados por
Microsoft en 2005 y aún hay 89 columnas usándolos. Ya no admiten varias operaciones normales, y
tarde o temprano dejarán de funcionar.

`[V]` **`char` aparece en 207 columnas, repartidas en 131 tablas.**

> **Corrección a una afirmación anterior.** En documentos previos se dijo que `char` estaba en
> 521 tablas. Ese número era en realidad la cantidad de columnas llamadas `Descripcion`. La
> cifra correcta es 131 tablas — bastante menos grave de lo que se había señalado.

**Por qué `char` importa.** Rellena con espacios en blanco hasta completar el tamaño. Si una
columna es `char(50)` y guardas "Cemento", quedan 43 espacios pegados. Al consultar, hay que
limpiarlos cada vez o las comparaciones fallan sin avisar.

`[V]` **`float` en 489 columnas.** `[S]` Es un tipo aproximado: no guarda el número exacto sino
el más cercano que puede representar. Para dinero eso produce diferencias de centavos que
después nadie sabe explicar. Para importes debería usarse `decimal` o `numeric`, que sí son
exactos.

---

## 8. La auditoría: quién hizo qué

**Qué es.** Columnas que registran quién creó cada fila y cuándo, quién la modificó por última
vez y cuándo. Sin ellas es imposible responder "¿quién cambió este precio?".

`[V]` Cobertura en la instalación:

| Columna | Tablas que la tienen | % de 1,916 |
|---|---:|---:|
| `CreacionFecha` | 627 | 33% |
| `CreacionUsuario` | 617 | 32% |
| `ActualizacionFecha` | 603 | 31% |
| `ActualizacionUsuario` | 590 | 31% |
| `AuditorUsuario` | 249 | 13% |
| `AuditorFecha` | 248 | 13% |
| `AuditorOK` | 231 | 12% |

### Las tres capas

`[S]` Lo que estos números revelan es un diseño de auditoría en tres niveles:

**Capa 1 — creación.** Quién insertó la fila. Presente en un tercio de las tablas.

**Capa 2 — modificación.** Quién la tocó por última vez. Casi la misma cobertura.

**Capa 3 — visado.** `AuditorUsuario`, `AuditorFecha` y `AuditorOK` en unas 250 tablas. Esta no
es rastro técnico: es un **visto bueno de revisión** incrustado en la fila. Alguien captura,
otro valida.

`[S]` Que solo el 13% de las tablas tenga esa tercera capa sugiere que se aplicó
selectivamente, a los documentos que requieren doble control.

### La brecha

`[V]` **Dos tercios de las tablas no registran quién creó sus filas.**

`[S]` Muchas de esas son tablas temporales o catálogos de fábrica donde no hace falta. Pero
la asimetría vale la pena mirarla con cuidado, porque hay un sesgo conocido: los registros
creados por caminos irregulares —cargas masivas, correcciones manuales, scripts de
emergencia— son justamente los que más interesaría rastrear.

---

## 9. Los nombres de columna: qué revelan

`[V]` Las columnas más repetidas de toda la base:

| Nombre | Tablas donde aparece |
|---|---:|
| `CreacionFecha` | 627 |
| `CreacionUsuario` | 617 |
| `ActualizacionFecha` | 603 |
| **`CodProyecto`** | **603** |
| `ActualizacionUsuario` | 590 |
| `Descripcion` | 521 |
| `TieneNota` | 347 |
| `Nivel` | 343 |
| **`CodIdentificador`** | **342** |
| `CodInsumo` | 279 |
| `Estado` | 257 |
| `Maquina` | 239 |

### Lo que dice esta lista

`[V]` **`CodProyecto` aparece en 603 tablas** — casi tantas como las columnas de auditoría.

`[S]` **El proyecto es la unidad que organiza toda la información del sistema.** Casi cualquier
dato pertenece a un proyecto. Es la decisión estructural más importante del diseño de S10, y la
que explica las llaves primarias de once columnas.

`[V]` **`CodIdentificador` en 342 tablas** confirma lo que ya vimos: las personas y
organizaciones están en todas partes.

`[V]` **`TieneNota` en 347 tablas.** `[S]` Es una bandera que indica si esa fila tiene un
comentario adjunto. Que esté en tantas tablas revela que el sistema permite anotar casi
cualquier cosa.

`[V]` **`Maquina` en 239 tablas.** `[S]` Registra desde qué computadora se hizo la operación —
un nivel de rastreo poco común, y bastante útil.

---

## 10. Los procedimientos: dónde vive la lógica

**Qué es un procedimiento almacenado.** Un conjunto de instrucciones guardadas dentro de la
propia base de datos. En vez de que el programa haga los cálculos, se los pide a la base.

`[V]` La instalación tiene **9,152 procedimientos, 1,356 vistas y 919 funciones**.

`[S]` **Nueve mil procedimientos significan que el negocio vive en la base de datos, no en la
aplicación.** Es una decisión arquitectónica de peso: cambiar una regla de negocio implica
modificar la base, no el programa.

### El reparto por módulo

`[V]` Los procedimientos siguen el patrón `S10_NN_Entidad_Accion`, donde `NN` es el número de
módulo. Agrupándolos:

| Prefijo | Procedimientos | `[S]` Módulo probable |
|---|---:|---|
| `S10_00` | 1,162 | Núcleo y maestros |
| `S10_15` | 1,050 | Planillas |
| `S10_06` | 774 | Evaluación de proveedores / logística |
| `S10_14` | 680 | Contabilidad |
| `S10_09` | 486 | Inmobiliaria / tesorería |
| `S10_12` | 375 | Facturación electrónica |
| `S10_01` | 336 | Presupuestos |
| `S10_03` | 332 | Almacenes |
| `S10_02` | 234 | Logística y compras |
| `S10_16` | 204 | Asistencia |
| `S10_10` | 92 | Agrupamiento de documentos |
| `S10_08` | 21 | — |
| `S10_17` | 21 | Portal de proveedores |
| `S10_11` | 10 | — |
| `S10_05` | 5 | — |
| `S10_04` | 2 | — |
| *(sin prefijo)* | 3,368 | Procedimientos de otra convención |

### Tres observaciones sobre esta tabla

`[V]` **Faltan los prefijos `07` y `13`.** `[S]` Pueden ser módulos que Techquk no licenció, o
que S10 descontinuó.

`[V]` **Cuatro prefijos tienen menos de 25 procedimientos.** `[S]` Son restos: módulos casi
vacíos de los que quedó el rastro.

`[V]` **3,368 procedimientos no siguen la convención** — más de un tercio del total. `[S]`
Probablemente sean anteriores a que se adoptara el patrón de nombres, o pertenezcan a
subsistemas con otra historia.

> **Nota importante.** Los nombres de módulo de esa tabla son **inferencia nuestra**. La base no
> guarda en ninguna parte un catálogo que diga qué es cada número. Lo buscamos en cuatro tablas
> candidatas —`Modulo`, `Aplicacion`, `ModuloRecurso` y `ESQUEMA_BASEDATOS_J_MOD`— y ninguna lo
> contiene.

### Lo único que sí está declarado

`[V]` La tabla `Aplicacion` sí guarda un catálogo, pero de **aplicaciones satélite**, no de
módulos del ERP:

| Código | Aplicación | Tipo |
|---|---|---|
| 01 | Facturación Electrónica | Portal web |
| 02 | Portal del Colaborador | Portal web |
| 03 | Aprobaciones Móvil | App móvil |
| 04 | Pedidos Móvil | App móvil |
| 05 | Tareo Móvil | App móvil |
| 06 | Gestión de Proyectos | Portal web |
| 07 | Tareo Móvil en Línea | App móvil |
| 08 | Portal de Asistencia | Portal web |
| 09 | Tablero Móvil | App móvil |
| 10 | GP Móvil | App móvil |

`[V]` Todas se crearon el 5 de junio de 2021, en el mismo lote.

---

## 11. La ficha técnica de esta instalación

`[V]` La tabla `InformacionBaseDatos` guarda la identidad de la instalación:

| Clave | Valor | Qué significa |
|---|---|---|
| `EsquemaMD5` | `9ADC02B7E91CE0388578C8979DC5F6BC` | Huella digital de la estructura |
| `EsquemaFecha` | 30 mayo 2016 | Fecha del esquema base |
| `EsquemaLoad_UltimoScript` | `R192_075_06_S10_06_Pedido_Adicionar (NC).sql` | Último parche aplicado |
| `EsquemaLoad_Fecha` | 30 marzo 2023 | Cuándo se aplicó |
| `ActualizaFecha` | 14 agosto 2026 | Última actualización de datos |
| `S10LibEsquema` | 8 julio 2021 | Versión de la biblioteca de esquema |
| `Ionic.Zip` | 18 mayo 2016 | Componente de compresión registrado |

### Dos cosas que vale la pena señalar

`[V]` **El esquema lleva tres años y medio sin parches.** El último es de marzo de 2023 y
estamos en agosto de 2026.

`[P]` Puede ser normal —si esa es la última revisión estable— o puede significar que hay
actualizaciones pendientes. Es una pregunta razonable para el proveedor.

`[V]` **El nombre del último script confirma la numeración de módulos:**
`R192_075_06_S10_06_Pedido_Adicionar`. El `06` aparece dos veces, y `Pedido` es una entidad de
compras. `[S]` Eso refuerza que `S10_06` es el módulo de logística o abastecimiento, no de
evaluación de proveedores como se había supuesto.

---

## 12. Los seis mecanismos que hacen funcionar el sistema

`[S]` Más allá del inventario, estas son las decisiones de diseño que explican cómo opera la
base. Todas se verificaron durante el análisis.

### 12.1 Una sola tabla para todas las personas

No existen tablas de clientes ni de proveedores. Existe `Identificador`, con 1,638 filas y 129
tablas apuntando a ella. El papel que cumple cada entidad —cliente, proveedor, banco, obrero—
se asigna en una tabla aparte.

`[V]` **El retorno medido:** el catálogo de roles pasó de 9 en 1999 a 13 en 2021 sin que se
creara una sola tabla nueva.

### 12.2 Las reglas de aprobación viven como datos

Los límites de autorización no están programados. Están en una tabla que cruza proyecto, centro
de compra, moneda y rango de monto. Cambiar una política es editar una fila.

### 12.3 Los tipos de transacción llevan sus propias reglas

El almacén maneja 55 tipos de movimiento con un solo formulario. Cada tipo trae banderas que le
dicen al sistema cómo comportarse: si afecta el stock, si exige documento, si requiere orden
previa.

### 12.4 Los estados son catálogo, no texto libre

Cada tipo de documento tiene su lista de estados posibles, con el código separado del orden de
presentación.

### 12.5 Los documentos se encadenan línea a línea

Entre pedido, requisitoria, orden de compra y guía de almacén hay tablas puente que guardan la
correspondencia **a nivel de ítem**, no de documento. Permite responder de qué solicitud salió
cada tornillo.

### 12.6 Casi nada se borra

`[V]` El 98% de las relaciones están configuradas para impedir borrados en cascada. Los
documentos se anulan cambiando de estado; los catálogos se desactivan con una bandera.

---

## 13. Los seis problemas encontrados

`[S]` Documentados porque son los que conviene no repetir al diseñar un sistema nuevo.

| # | Problema | Evidencia |
|---|---|---|
| **1** | Códigos de negocio como llave primaria | `[V]` Una llave de 11 columnas; 15 tablas con llaves de 8 |
| **2** | Tablas con datos y sin llave primaria | `[V]` 67 tablas |
| **3** | Tipos de dato obsoletos | `[V]` 89 columnas `text` e `image`, descontinuados desde 2005 |
| **4** | `float` para importes | `[V]` 489 columnas de un tipo aproximado |
| **5** | Archivos binarios dentro de la base | `[V]` La tabla `Notero` pesa 280 MB con 508 filas |
| **6** | Controles declarados y no alimentados | `[V]` Seis casos documentados en el informe de resultados |

### El problema número seis, ampliado

`[S]` Es el más interesante porque no es técnico sino de operación. Durante el análisis se
encontraron seis mecanismos de control que existen en la estructura y **nadie alimenta**:

Tres tablas con la fecha de creación completamente vacía. Dos columnas de trazabilidad en la
tabla puente de compras, en nulo en las trece líneas registradas. Un campo de firma obligatoria
apagado en las dieciséis aprobaciones. Tres estados de entrega de pedido que existen y jamás se
usaron. Un padrón tributario sin actualizar desde 2015. Un catálogo del Estado sin refrescar
desde 2018.

> **El software ofrece más estructura de la que la operación alimenta.**

`[S]` La lección: un control a medias es peor que ninguno, porque da la falsa sensación de que
algo está bajo vigilancia.

---

## 14. Qué queda fuera de este documento

**No incluye el código de los procedimientos.** Documentar la lógica interna de 9,152
procedimientos de un producto comercial excede el alcance de un análisis de base de datos. Lo
que sí está es el inventario: qué operaciones existen y a qué módulo pertenecen.

**La agrupación por dominios es inferencia.** La base no la trae. Cubre el 79.5% de las tablas;
393 no encajan limpiamente en ninguna categoría.

`[P]` **No se pudo determinar el catálogo oficial de módulos.** Se buscó en cuatro tablas
candidatas y ninguna lo contiene. `[S]` Probablemente S10 no lo guarde en la base: los módulos
serían una decisión de licencia y de instalación, no un dato de la aplicación. La fuente
correcta es el contrato o el menú del sistema instalado.

`[P]` **No se pudo confirmar del todo si el esquema es completo.** El argumento del `EsquemaMD5`
lo hace muy probable, pero la confirmación definitiva viene del proveedor.

---

## 15. Resumen en una página

| Pregunta | Respuesta |
|---|---|
| ¿Cuántas tablas? | 1,916, de las cuales 519 tienen datos |
| ¿Cuántas columnas? | 32,626, un promedio de 17 por tabla |
| ¿Cuántas relaciones? | 1,571, el 98% configuradas de forma conservadora |
| ¿Cuántos índices? | 2,324, de los cuales 1,726 impiden duplicados |
| ¿Cuánta lógica hay en la base? | 9,152 procedimientos, 1,356 vistas, 919 funciones |
| ¿Está dividida en módulos? | No. Todas las tablas en un solo espacio |
| ¿Cuál es la tabla central? | `Identificador`, con 129 tablas apuntando a ella |
| ¿Qué organiza la información? | El proyecto: `CodProyecto` está en 603 tablas |
| ¿Está el esquema completo? | `[S]` Muy probablemente sí, por el argumento del `EsquemaMD5` |
| ¿Qué antigüedad tiene? | Esquema de 2016, último parche de 2023 |

---

## 16. Lo que este análisis aporta al proyecto de Techquk

**Cuatro patrones de diseño verificados** que conviene replicar: la tabla única de personas con
roles, las reglas de autorización como datos, los tipos de transacción con banderas de
comportamiento, y las tablas puente con trazabilidad línea a línea.

**Seis antipatrones documentados** con evidencia medible, que conviene evitar.

**Y una advertencia sobre el método.** Todo lo observado describe **cómo se registró** cada
operación, no necesariamente cómo ocurrió. Los códigos de movimiento y de estado registran qué
opción eligió el usuario. En una implementación de siete semanas, donde la gente todavía
aprende el sistema, esa distancia puede ser considerable.

---

*Los archivos de datos que acompañan este documento permiten consultar cualquier tabla, columna
o relación específica sin volver a la base.*
