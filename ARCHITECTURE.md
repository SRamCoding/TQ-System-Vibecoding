# Arquitectura

Monolito modular sobre Laravel + Filament + PostgreSQL 16. Una aplicación, una base, un despliegue,
en un servidor Linux propio de Techquk. La modularidad está en el código y se verifica con pruebas.
Razones: `docs/decisiones/0001-monolito-modular.md`.

## Módulos

Cada módulo es dueño de sus tablas (su `TableGroup` en `docs/diseno/techquk_modelo.dbml`).
La etapa indica cuándo se construye (`docs/planes/mapa-etapas.md`).

| Carpeta (`app/Modules/…`) | Contenido | Etapa |
|---|---|---|
| `Nucleo` | M1–M13: parties y roles, aprobaciones, estados, auditoría, catálogos, numeración, adjuntos, notificaciones, parámetros, seguridad (puestos, restricciones por campo, cruces de funciones), **imputación** (centros de costo, motivos, imputaciones), **IA** (procesos, sugerencias), **alertas**; además el **registro de acciones** y las **plantillas** (ADR 0022) | 1 |
| `Crm/Cuentas` · `Crm/Oportunidades` · `Crm/Cotizaciones` · `Crm/Licitaciones` · `Crm/Actividades` · `Crm/Catalogo` | La cara comercial | 2 |
| `Erp/Contratos` | Contratos, hitos, garantías y renovaciones, penalidades, adendas, conformidades | 2 |
| `Erp/Proyectos` | Proyectos, valorizaciones | 2 |
| `Erp/Presupuestos` | Presupuestos con APU, insumos, árbol de partidas de control, fórmula polinómica, índices | 2 |
| `Erp/Compras` | Requerimientos, cuadro comparativo, órdenes, tipos de compra | 2 |
| `Erp/Almacen` | Almacenes (central y de obra), stock, movimientos, series, guías | 2 |
| `Erp/Personal` | Colaboradores, competencias, asignación, categorías de costo hora | 2 |
| `Erp/Tareo` | Partes semanales, habilitación de campo, asistencias | 2 |
| `Erp/ObraPublica` | Adelantos, valorización por línea, liquidación, consorcios, subcontratos | 2 y 3 |
| `Erp/Facturacion` | Comprobantes por XML, cuentas por cobrar, cobranzas | 3 |
| `Erp/Tesoreria` | Cuentas por pagar, tablero de pagos, notas por foto, flujo de caja, servicios con vigencia, rendiciones | 3 |
| `Erp/Contabilidad` | Plantillas contables, asientos, exportaciones a Excel | 3 |
| `Erp/RRHH` | Demanda a 90 días, selección, salida, planilla | 4 |
| `Erp/Importaciones` | Embarques, gastos, prorrateo | 5 |
| `Erp/Servicios` · `Erp/Suscripciones` | Postventa: tickets, garantías por serie, suscripciones | 5 |
| `Erp/Analitica` | Tableros por rol (solo lectura) | 5 |

### Correspondencia con el modelo de datos

El DBML conserva los códigos de grupo del análisis. Si una tabla cae en un módulo distinto de su grupo, manda esta tabla.

| Grupo del DBML | Módulo |
|---|---|
| `M1` a `M13` (incluido `M10_Seguridad`) | `Nucleo` |
| `CRM_01` a `CRM_07` | `Crm/*` (uno por grupo) |
| `ERP_01_Proyectos` | `Erp/Proyectos` |
| `ERP_02_Compras` | `Erp/Compras` |
| `ERP_03_Importaciones` | `Erp/Importaciones` |
| `ERP_04_Almacen` | `Erp/Almacen` |
| `ERP_05_Servicios_y_postventa` | `Erp/Servicios` |
| `ERP_06_Facturacion` | `Erp/Facturacion` |
| `ERP_07_Personal` | `Erp/Personal` |
| `ERP_09_Analitica` | `Erp/Analitica` |
| `ERP_10_Contratos` | `Erp/Contratos` |
| `ERP_11_Suscripciones` | `Erp/Suscripciones` |
| `ERP_12_Desarrollo_de_software` | **Fuera del MVP** (D6: solo las horas viven en el ERP, y van en `Erp/Tareo`) |
| `ERP_13_Integracion_contable` | `Erp/Contabilidad`, salvo `cuentas_pagar` y `pagos`, que son de `Erp/Tesoreria` |
| `ERP_14_Presupuesto_y_Partidas` | `Erp/Presupuestos` |
| `ERP_15_Obra_Publica` | `Erp/ObraPublica` |
| `ERP_16_Tesoreria_y_Gastos` | `Erp/Tesoreria` |
| `ERP_17_Tareo_y_Habilitacion` | `Erp/Tareo` |
| `ERP_18_RRHH_y_Planilla` | `Erp/RRHH` |
| `ZZ_Obsoletas_v4` | **No se crean** |

## Estructura interna de un módulo

```
app/Modules/Erp/Compras/
├── ComprasServiceProvider.php   # rutas, eventos, políticas, acciones, recursos de Filament
├── Models/        # Eloquent. Solo este módulo los escribe
├── Services/      # API pública del módulo. Lo único que otros módulos invocan
├── Acciones/      # acciones registradas: una clase por acción de usuario (ver abajo)
├── Efectos/       # una clase por efecto de una transición de estado
├── Events/  Listeners/  Jobs/  Policies/  Enums/
├── Filament/      # Resources, Pages, Widgets: solo presentación
├── Console/       # comandos de artisan del módulo (si los tiene)
└── database/{migrations,factories,seeders,data}/   # data: archivos públicos que siembra (con su fuente)
tests/Modules/Erp/Compras/       # pruebas del módulo, incluidos sus escenarios de aceptación
```

## Reglas de dependencia

1. **Cualquier módulo puede usar `Nucleo`**: sus modelos para relaciones y lectura, sus servicios y traits.
2. **Entre módulos de negocio, solo lo que cada uno expone:** sus `Services/`, sus `Events/` y los registros de
   extensión que declare públicos (desarrollador, 2026-11-07, plan 2.21). Hoy: Cuentas, `Actividad/` y `Pestanas/`;
   Oportunidades, `Requisitos/`, `Impedimentos/`, `Secciones/` y `Pestanas/`; Proyectos, `Pestanas/` (plan 2.10,
   D3: Presupuestos suma a la ficha del proyecto las pestañas Presupuesto y Partidas de control) y `Resumen/` (plan
   2.61, D6: cada módulo suma su tarjeta al Resumen de la ficha; hoy Presupuestos, con el dinero y lo que necesita
   atención). Son los puntos donde otro módulo se
   enchufa (un aporte, una pestaña, un requisito) sin que el dueño lo conozca. La lista vive en `tests/Arch`
   (`HARNESS_PUBLICAS`) y cambiarla es cambiar esta regla. Las constantes que otro módulo necesita (un permiso) van en
   el servicio, no en el provider. Fuera de su `Models/`, un módulo no importa modelos de otro. Dentro de `Models/`,
   solo para declarar relaciones (leer sí, escribir no).
3. **Lo transaccional va por servicio; lo reactivo, por evento** (con `ShouldDispatchAfterCommit`).
4. **`Analitica` solo lee.**
5. **Integraciones externas detrás de una interfaz:** validación SUNAT, padrón RUC, servicio de IA,
   exportación contable, tipo de cambio, convocatorias del OECE (ADR 0018). La implementación concreta se registra en el contenedor;
   las pruebas usan una versión falsa.

Las reglas 1, 2 y 4 y los nombres con mayúsculas exactas se verifican en `tests/Arch/`.

Las costuras concretas de la etapa 2 (qué servicio y qué evento ofrece cada área a las demás) están en el
[ADR 0020](docs/decisiones/0020-costuras-etapa-2.md).

## Tres mecanismos que todo módulo usa

**Documentos con estados y efectos.** Los estados y transiciones de cada tipo de documento son datos
(M3): el módulo los declara en una definición, el núcleo los copia a sus tablas y desde pantalla se editan
nombre, color y motivos. Cada transición declara quién puede ejecutarla y qué efectos produce; cada efecto
es una clase en `Efectos/` (por ejemplo, `RecepcionConfirmadaSubeStock`). El estado solo cambia por el
servicio de transiciones del núcleo, y un estado no editable lo protege el modelo. Cambiar un flujo es
cambiar la definición o un efecto, no reescribir el módulo. Detalle: `docs/convenciones/codigo.md`.

**Imputación.** Toda línea de costo crea o actualiza su fila en `imputaciones` vía `ImputacionService`
del núcleo, con sus tres montos (comprometido, ejecutado, pagado). Ningún módulo calcula saldos de
partida por su cuenta.

**Registro de acciones.** Cada acción de usuario («registrar recepción», «aprobar orden») es una clase
en `Acciones/` con nombre, campos tipados, permiso requerido y un método que llama al servicio. La usan
Filament, las pruebas y, en el futuro, la API móvil y el agente de voz. Toda acción se puede ejecutar
y probar sin interfaz.

Y uno que usa el módulo que lo necesita: **plantillas** (ADR 0022). El núcleo guarda, versiona, da permisos y pone la
pantalla común (`Nucleo/Plantillas`, `PlantillasService`, el panel `nucleo::filament.plantillas.panel`); cada módulo
registra sus tipos (`DefinicionPlantilla`) y dice qué se captura de su documento y cómo se aplica, siempre copiando a
una propuesta editable que confirma su propia acción. El núcleo nunca interpreta el contenido de un tipo. Detalle:
`docs/convenciones/codigo.md`, «Plantillas».

## Interfaz

Una sola aplicación con un solo modo (plan 1.4f). El inicio muestra una caja por cada módulo que la persona
tiene permitido; en escritorio abre su módulo en otra pestaña, en el celular en la misma. En escritorio, cada
persona puede prender una barra lateral con esos mismos módulos, menos los que oculte; en el celular no hay barra y
se vuelve al inicio. Cada módulo solo declara su `CajaModulo` en su provider: quién ve qué lo decide
`NavegacionService` del núcleo, y la preferencia nunca da acceso. Si cada rol tendrá además su tablero de inicio
está pendiente (`docs/decisiones/pendientes.md`). Las preferencias de cada persona (tema, letra, barra, saludo…)
las declara cada módulo en `RegistroPreferencias` del núcleo y se ajustan en «Mis preferencias» (plan 1.4g); solo
cambian la presentación. Pantallas especiales con componentes propios de Livewire: árbol
de partidas, tablero de pagos, grilla de tareo. Detalle de pantallas en `docs/convenciones/interfaz.md`.

## Componentes en ejecución

- **Web:** Laravel + Filament detrás de un proxy inverso, en Docker sobre Linux. Adaptable al celular
  e instalable como aplicación web.
- **Cola:** driver de base de datos; los jobs fallidos quedan en `failed_jobs` y avisan al responsable.
- **Programador:** `schedule:run` cada minuto (alertas, recálculos, respaldos).
- **PostgreSQL 16:** única base.
- **Archivos:** disco de Laravel; en la base solo la ruta. Respaldo a S3.
- **API móvil:** no existe todavía. Se agrega cuando haga falta, sobre los mismos servicios y acciones
  (`docs/decisiones/0007-api-movil-preparada.md`).

## Migración desde S10

Fuera del alcance del MVP. Cuando llegue: extracción a una zona intermedia y carga a través de los
servicios de cada módulo, para que pase por las mismas validaciones. El corte es por proyecto.
