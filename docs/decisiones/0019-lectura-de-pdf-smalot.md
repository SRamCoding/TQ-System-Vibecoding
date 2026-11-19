# 0019 — Lectura del texto de un PDF con smalot/pdfparser (LGPL-3.0)

- **Estado:** aceptada
- **Fecha:** 2026-10-28
- **Decide:** desarrollador

## Contexto

El mecanismo de IA (plan 1.11, D6) verifica la evidencia de cada campo sugerido: el texto citado debe estar en esa
página del PDF. Para eso hay que leer el texto por página de un PDF desde PHP, en el job, sin binarios en el servidor
(`pdftotext` y similares exigirían instalar programas en producción).

`smalot/pdfparser` es PHP puro y es la librería más usada para esto. Verificado el 2026-10-28: última versión v2.13.1
(publicada el 2026-10-13), requiere PHP 7.1 o más y las extensiones `zlib`, `iconv` y `mbstring` (ya instaladas).
**Licencia LGPL-3.0**, copyleft débil: sus obligaciones aparecen al distribuir el programa a terceros o al modificar la
librería.

## Decisión

Se agrega `smalot/pdfparser` (`^2.13`) detrás de la interfaz `TextoDeAdjunto`. La licencia es aceptable porque el
sistema es de uso interno de Techquk (no se distribuye a terceros) y la librería se usa vía Composer sin modificarla.

## Consecuencias

- **Nunca** se copia la librería al repositorio ni se parcha su código. Un arreglo va en nuestra implementación de
  `TextoDeAdjunto` o se cambia de librería.
- Si algún día el sistema se distribuye a terceros (venderlo, entregarlo a un cliente), esta decisión se revisa.
- Nadie fuera de `TextoDeAdjunto` usa la librería: cambiarla toca un solo archivo.
- Las pruebas de la comparación de citas usan PDF generados para la prueba, nunca documentos reales.

## Alternativas descartadas

- **`pdftotext` (poppler) u otro binario:** exige instalar programas en el servidor y llamarlos por consola.
- **Un servicio externo de OCR o extracción:** saca el documento de la empresa; la IA ya lo hace solo con autorización
  de Gerencia (plan 1.11, D2).
- **Dejar solo la interfaz hasta el primer proceso que lea PDF:** ofrecido; el desarrollador prefirió agregarla ahora.
