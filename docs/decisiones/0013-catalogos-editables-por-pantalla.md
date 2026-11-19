# 0013 — Catálogos y parámetros editables desde la pantalla

- **Estado:** aceptada
- **Fecha:** 2026-10-10
- **Decide:** desarrollador

## Contexto

La tarea 1.2 dejó los catálogos de M5 en solo lectura (D7 de su plan: «crear y editar quedan fuera hasta
que un flujo de la etapa 2 lo pida») y declaró que un elemento no se borra, se desactiva (D4). Al cerrarla,
el desarrollador pidió poder agregar, editar y borrar desde Configuración. Además, los seeders actualizaban
monedas, tipos de documento y la descripción de los parámetros en cada despliegue, y la actualización de
ubigeos desactivaba todo lo que no estuviera en el padrón del INEI: con edición por pantalla, ambas cosas
deshacían lo que una persona cambió. Plan: `docs/planes/completados/2026-10-10-nucleo-edicion-catalogos.md`.

## Decisión

Los nueve catálogos se crean, editan, desactivan, activan y borran desde la pantalla, y los parámetros
editan valor y descripción, siempre por acciones registradas con bloqueo optimista y bitácora:

- **Borrar** solo lo que nunca se usó: la llave foránea lo impide y se sugiere desactivar (ajusta D4).
- **Código inmutable:** se fija al crear; el sistema busca por código.
- **Campos que mueven dinero** (`tipos_documento.signo` y `afecta_cuenta_corriente`, `monedas.decimales`)
  con permiso aparte, `catalogos.editar-sensibles`. Crear, desactivar y borrar monedas y tipos de documento
  también lo exigen: el sistema usa esos elementos por dentro. El resto, `catalogos.editar`.
- **La moneda local** (parámetro `general.moneda_local`) no se borra ni se desactiva.
- **Ubigeos con `origen`** (`inei` | `manual`): la actualización del INEI no desactiva los manuales ni
  reactiva lo desactivado a mano; un manual que el INEI publica pasa a `inei`.
- **Los seeders solo crean lo que falta**, también en las listas oficiales; de un parámetro existente no
  tocan valor ni descripción.
- **Parámetros:** crear y borrar siguen en código (`RegistroParametros`): uno que ningún código lee no
  hace nada y borrar uno que se lee rompe.
- **Forma:** cinco clases de acción genéricas (más la de campos de dinero) parametrizadas por `Catalogo`,
  registradas como una instancia por catálogo y operación: `nucleo.<catalogo>.<operacion>`
  (`nucleo.formas-pago.borrar`). Los campos de cada catálogo se definen en el enum `Catalogo`.

## Consecuencias

- Mantener los catálogos es trabajo de Administración (matriz del v4, parte 7: Configuración, E), no de un
  despliegue. Corregir una lista oficial (SUNAT, ISO) también se hace en la pantalla: el seeder ya no la
  devuelve a su valor.
- `catalogos.editar-sensibles` se otorga junto con `catalogos.editar`: por sí solo permite crear, desactivar y
  borrar monedas y tipos de documento.
- Un elemento oficial borrado (nunca usado) vuelve con el siguiente despliegue: el seeder crea lo que falta.
  Para retirarlo de verdad, se desactiva.
- Un selector de catálogo ofrece los activos y, marcado «(desactivado)», el que el registro ya tenía: editar
  otro campo no obliga a cambiarlo.
- Una columna nueva en un catálogo debe declararse en `Catalogo::campos()`; `CatalogoDefinicionTest` falla
  si no.
- La primera instalación anota en la bitácora los ~1 900 ubigeos (unos segundos, una vez).
- Si el INEI retira un código y después lo vuelve a publicar, ya no se reactiva solo: no se distingue de uno
  desactivado a mano. Lo activa una persona.
- Un elemento que otro módulo referencie (etapa 2 en adelante) queda protegido por su llave foránea sin
  código adicional.

## Alternativas descartadas

- **Solo desactivar, nunca borrar (D4 original):** deja basura de lo creado por error y nunca usado.
- **Una acción por catálogo y operación (~45 clases):** código repetido sin ganancia; la definición en el
  enum da campos tipados por catálogo igual.
- **Código editable mientras no se use:** el sistema y los seeders buscan por código; cambiarlo rompe
  referencias en código aunque la base no tenga filas que lo usen.
- **Mismo permiso para todo:** un error en el signo de un documento invierte saldos de cuenta corriente.
- **No crear ubigeos a mano:** obliga a esperar al INEI para un distrito que el negocio ya necesita.
