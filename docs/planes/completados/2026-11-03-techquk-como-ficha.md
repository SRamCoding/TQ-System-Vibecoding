# Plan: Techquk como ficha (1.15)

- **Estado:** terminado
- **Módulo(s):** Nucleo
- **Fase:** F1
- **Creado:** 2026-11-03 · **Actualizado:** 2026-11-03

## Objetivo

Los datos de Techquk que imprime un documento (razón social, nombre comercial, RUC, dirección fiscal, teléfono,
correo, sitio web y redes, logo) salen de **un solo lugar**: `EmpresaService::datos()`, leído de la ficha que eligió
la 1.13. El logo es un adjunto de esa ficha. La ficha elegida no se puede desactivar ni borrar sin elegir otra antes,
y el paso del asistente muestra cómo la verán los documentos y qué les falta.

## Contexto que leí

- `docs/planes/mapa-etapas.md`, fila 1.15: «la 1.13 ya la elige; queda que los documentos la usen y el logo, como
  adjunto de la ficha».
- `docs/planes/completados/2026-10-30-asistente-configuracion.md`, D2 y bitácora: parámetro `empresa.ficha` con el RUC,
  `ValidadorFichaEmpresa`, `EmpresaService::ficha()`. Anotado para la 1.15: «si se desactiva la ficha elegida, el
  resumen dice “Sin elegir” y nada avisa». El logo «se enchufa como un adjunto de la ficha».
- Plan 1.4o (redes de las fichas: `PartyRedSocial`, catálogo `RedSocial` con «Sitio web»).
- Plan 1.8 (adjuntos): `tipos_adjunto` con `documento_type`, extensiones y tamaño propios; la ficha ya tiene su sección
  «Archivos» (`ArchivosDeRegistro` en `PartyResource`). Precedente: el tipo «Ícono de aplicación» (plan 1.4z, D9).
- `Party`: `razon_social`, `nombre_comercial`, `numero_documento`, `direccion_fiscal`, `ubigeo`, `telefono`, `email`.
- **Hoy no hay ningún documento impreso** ni librería de PDF: solo existe el módulo Nucleo; cotizaciones llegan en la
  etapa 2. El v4 no fija el encabezado de los documentos.
- `docs/decisiones/pendientes.md`: nada bloquea. No toca el organigrama (en espera desde 2026-11-03).

## Decisiones (aprobadas el 2026-11-03)

- **D1. Un punto de entrada para los documentos.** `EmpresaService::datos(): ?DatosEmpresa`, un objeto
  de solo lectura en `Nucleo/Parties/` con razón social, nombre comercial, RUC, dirección fiscal con su distrito,
  provincia y departamento, teléfono, correo, sitio web, redes (nombre, ícono, enlace, en el orden del catálogo) y el
  logo (el adjunto, o null). `null` si no hay ficha elegida. Los documentos de la etapa 2 lo piden aquí y nunca leen
  la party directamente: así, cambiar de ficha o de fuente no toca ningún documento. Las cuentas bancarias **no**
  van ahí: cada documento elige la suya (factura, cotización) cuando exista.
- **D2. El logo, un tipo de adjunto «Logo».** Tipo `logo` para fichas de Personas y empresas
  (`documento_type` de `Party`), solo PNG, JPG o WEBP (SVG no: puede llevar código), hasta 2 MB, sembrado en
  `CatalogosSeeder`. Sirve para cualquier ficha (el logo de un cliente también vale), y el de Techquk es el vigente
  más reciente de su ficha: subir otro lo reemplaza con el mecanismo de siempre. Quién lo sube: quien edita Personas
  y empresas (`parties.editar`), como cualquier archivo de la ficha.
- **D3. La ficha elegida no se desactiva ni se borra.** `PartiesService::desactivar()` y `borrar()`
  la rechazan con «Es la ficha de Techquk: elige otra en Configuración inicial antes de desactivarla». Cierra lo
  anotado en la 1.13 (que el resumen pase a «Sin elegir» sin aviso). La alternativa, avisar en Salud del sistema,
  deja una ventana en que los documentos salen sin datos.
- **D4. El paso del asistente muestra el encabezado.** Debajo de «Cambiar la ficha de Techquk», una
  vista previa con lo que tomarían los documentos (logo, nombres, RUC, dirección, contacto, redes) y la lista de lo
  que falta («Sin logo», «Sin teléfono»). Es la misma vista parcial que usarán los documentos impresos
  (`nucleo::empresa.encabezado`), así que no queda código sin uso. Sin logo ni datos opcionales el paso igual puede
  marcarse como listo: solo la ficha elegida es obligatoria.
- **D5. Lo que no cambia.** El nombre «TechQuk» del panel y su logo animado son la marca del sistema, no datos legales:
  siguen como están. El parámetro sigue guardando el RUC (1.13).

## Pasos

- [x] 1. `DatosEmpresa` y `EmpresaService::datos()` (D1), con el logo (D2): tipo de adjunto en `CatalogosSeeder`.
      Pruebas: sin ficha → null; con ficha → cada campo; redes en orden; logo vigente más reciente; ficha desactivada
      a mano en la base → null.
- [x] 2. Rechazo al desactivar o borrar la ficha elegida (D3), en el servicio. Pruebas en `PartiesService`.
- [x] 3. Vista `nucleo::empresa.encabezado` y la vista previa con faltantes en el paso del asistente (D4). Prueba del
      componente; recorrido en el navegador (claro y oscuro, panel deslizante).
- [x] 4. Documentos: `EmpresaService` y comentarios al día; mapa (1.15 ✅); nota en la etapa 2 de que cotizaciones toman
      el encabezado de aquí y eligen la librería de PDF.
- [x] 5. Revisión de `revisor-arquitectura` y `composer check` en verde.

## Preparado para lo diferido

- **Documentos impresos (etapa 2):** piden `EmpresaService::datos()` e incluyen `nucleo::empresa.encabezado`; la librería
  de PDF se elige con el primero, sin cambiar esta parte.
- **Cuenta bancaria en facturas y cotizaciones:** con `PartiesService::cuentaPrincipal()` o `cuentasDePago()` sobre
  `EmpresaService::datos()->partyId` (otro módulo no usa el modelo `Party`).
- **Logos de clientes y proveedores:** el mismo tipo «Logo» ya sirve para cualquier ficha.

## Criterio de aceptación

- `tests/Modules/Nucleo/EmpresaServiceTest.php`: datos completos, faltantes, logo, sin ficha.
- `tests/Modules/Nucleo/PartiesServiceTest.php` (o el que corresponda): no se desactiva ni borra la ficha elegida.
- `tests/Modules/Nucleo/ConfiguracionInicialTest.php`: el paso muestra el encabezado y lo que falta.
- En pantalla: en el paso «Datos de la empresa» se ve cómo saldrán los documentos; subir un logo en la ficha lo muestra.

## Bitácora

- 2026-11-03 — Rama `techquk-como-ficha` desde `validacion-por-formularios` (igual que `main`). Plan escrito.
- 2026-11-03 — El desarrollador aprobó D1–D5, una por una, todas con la opción recomendada: ventanilla única sin
  cuentas bancarias; «Logo» para cualquier ficha (PNG, JPG o WEBP, hasta 2 MB, cuenta el último); se impide
  desactivar o borrar la ficha elegida; vista previa con faltantes, y el paso queda listo con solo la ficha; la barra
  del sistema no cambia.
- 2026-11-03 — Paso 1. `DatosEmpresa` (en `Nucleo/Parties/`) y `EmpresaService::datos()`. Las redes salen de
  `RedesSocialesService::redesQueVe()`, que ahora acepta `$quienMira` nulo: solo las públicas, lo que imprime un
  documento. El sitio web es la red con ícono `sitio_web` y va aparte. La dirección lleva `Ubigeo::etiqueta()` solo si
  hay dirección. `faltantes()` lista logo, dirección, teléfono, correo y sitio web (el nombre comercial no: hay
  empresas sin él). Tipo de adjunto `logo` (`EmpresaService::TIPO_LOGO`) para `party`, `png,jpg,jpeg,webp`, 2 MB, en
  `CatalogosSeeder`; el SVG ya está prohibido en todo adjunto. Pruebas: `EmpresaServiceTest`.
- 2026-11-03 — Paso 2. `PartiesService::desactivar()` y `borrar()` rechazan la ficha elegida
  (`PartyRechazada::esLaFichaDeLaEmpresa`), con la fila de la party bloqueada y el parámetro leído de la base
  (`EmpresaService::esLaFicha()` → `ParametrosService::textoReciente()`: `texto()` guarda lo leído en la petición).
  Del otro lado, `ValidadorFichaEmpresa::rechazo()` lee la party con bloqueo compartido: corre dentro de la transacción
  de `ParametrosService`. Prueba de concurrencia en `tests/Concurrencia` (modo `ficha`): sin el bloqueo compartido
  falló 3 de 3 (quedaban elegida e inactiva); con él, pasa.
- 2026-11-03 — Paso 3. `nucleo::empresa.encabezado` es papel: blanco también en modo oscuro, con la tinta nueva de
  `ColoresMarca` (`TINTA`, `TINTA_SUAVE`, AA sobre blanco) y el azul noche en el nombre; sin variables de Filament, que
  un PDF no tiene. El logo va incrustado como `data:` (`EmpresaService::imagenDelLogo()`, en caché por id de adjunto,
  como los íconos subidos). En el asistente, la vista previa va entre el encabezado del panel y la ficha, solo en el
  paso de la empresa con la ficha elegida, y no en otra pantalla abierta dentro del panel. Se vuelve a dibujar con
  `ficha-party-cambiada` (que ahora también avisa `VerParty` al editar) y `archivos-cambiados` (nuevo, de
  `ArchivosDeRegistro`). Revisado con el Chromium de Playwright sobre la vista real (dibujada dentro de una
  transacción que se deshace, con datos inventados): claro, oscuro y angosto. En angosto el logo tomaba media hoja:
  ahora el texto baja debajo del logo cuando no le quedan 14rem. La base de desarrollo no tiene todavía el tipo «Logo»:
  lo crea `db:seed`, como en el despliegue.
- 2026-11-03 — Paso 4. Mapa (1.15 ✅ y nota de la etapa 2 sobre documentos impresos), `interfaz.md` («Encabezado de
  los documentos») y `codigo.md` (los datos de Techquk solo por `EmpresaService::datos()`).
- 2026-11-03 — Revisión de `revisor-arquitectura`: sin nada grave en el código (sin cruces de módulos, ciclos ni
  deadlocks; repasó el caso de cambiar a otra ficha mientras se desactiva la anterior: rechazo conservador, nunca una
  elegida inactiva). Atendido: (1) la nota de la etapa 2 mandaba a tomar la cuenta bancaria de `ficha()`, un `Party`
  que otro módulo no usa: ahora `PartiesService::cuentaPrincipal()`/`cuentasDePago()` con `datos()->partyId`;
  (2) la regla pasó también a `codigo.md`; (3) pruebas que faltaban: ficha desactivada por fuera → null, y que
  `ArchivosDeRegistro` y `VerParty` emiten sus eventos (`assertDispatched`); (4) `esLaFicha()` exige jurídica, como el
  validador; (5) el logo ya no va a la caché: hasta 2 MB eran casi 3 MB por fila en la caché de la base, y leerlo del
  disco es barato (el tope de 2 MB se mantiene, D2); (6) frase de `interfaz.md` corregida.
- 2026-11-03 — Pedido nuevo del desarrollador, aparte: la primera vez que entra el administrador, el asistente en una
  ventana al centro, paso a paso. Va como tarea 1.13b en otra rama, encima de esta, después de cerrar la 1.15.
