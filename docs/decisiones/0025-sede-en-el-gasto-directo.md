# 0025 — El gasto directo lleva su sede, y el núcleo pide su saldo por un enchufe

- **Estado:** aceptada (2026-11-16, por el desarrollador: «sí, todas tus propuestas me parecen correctas»)
- **Fecha:** 2026-11-16
- **Decide:** desarrollador
- **Toca:** la imputación del núcleo (`Nucleo/Imputacion`, `ImputacionService`, el selector de imputación de la 2.2), y
  por ella todo documento con costo de las áreas 1, 3 y 4. Lo llena `Erp/Presupuestos` (plan 2.13).

## Contexto

Plan 2.13: la empresa presupuesta lo que no es de ningún proyecto **por sede y por mes** (D2), y ese presupuesto
controla el gasto directo como el árbol controla un proyecto: saldo antes de pedir, comprometido, ejecutado, exceso con
motivo y aprobación.

Hoy una línea de gasto directo se imputa solo a un centro de costo (`imputaciones.centro_costo_id`, destino
`CentroDeCosto`, plan 1.9). No sabe de qué sede es, así que nada puede controlarla por sede.

Además, el selector de imputación vive en el núcleo, y el núcleo no puede usar nada de un módulo de negocio
(`tests/Arch`: «el núcleo no depende de ningún módulo de negocio»). El saldo de una sede lo sabe `Erp/Presupuestos`.

## Decisión

1. **El gasto directo guarda su sede, siempre** (plan 2.13, D3). Una imputación a centro de costo lleva además su
   alcance: una **sede de Techquk** (las de su ficha en Personas y empresas, `party_sedes`) o **«Empresa»**, para lo
   que es de todas (un seguro anual). Uno de los dos, obligatorio, garantizado en la base con un `CHECK`; nunca los
   dos ni ninguno. El centro de costo se queda como está: lo siguen usando los asientos, pero no se presupuesta. La
   imputación a una partida de proyecto no cambia: no lleva sede.
2. **El núcleo pide el saldo por un enchufe** (como `VentaDelProyecto` en Proyectos): una interfaz del núcleo,
   «saldo del gasto directo de un alcance en un mes», con presupuestado, comprometido, ejecutado y disponible.
   `Erp/Presupuestos` la implementa y la registra en su proveedor. El núcleo nunca sabe quién respondió.
3. **Sin nadie enchufado, no hay control** (antes de la 2.13, o si se apagara): el selector pide la sede igual, pero
   no muestra saldo ni bloquea. **Con la 2.13 enchufada y sin presupuesto en esa sede y mes**, el gasto no se carga a
   nada: el selector ofrece crear ahí mismo el presupuesto genérico del mes, en borrador, que se aprueba como
   cualquier presupuesto de la empresa (plan 2.13, D4). Esas dos respuestas se distinguen en la interfaz («no hay
   control» frente a «no hay presupuesto»), no se confunden con un saldo cero.
4. **Lo que no cambia para quien imputa:** los documentos con costo siguen llamando a `ImputacionService` con su
   elección del selector; la sede viaja dentro de la elección. Ningún módulo calcula el saldo por su cuenta.

## Consecuencias

- **Núcleo:** la columna y el `CHECK` en `imputaciones` (las migraciones aún se editan, ADR 0010), la sede o
  «Empresa» en `CentroDeCosto` y en el selector, el enchufe con su versión «sin control» por defecto y sus pruebas de
  contrato, como las de C1.
- **Lo ya imputado en desarrollo** no tiene sede: no hay datos reales, así que la migración se edita y las pruebas y
  semillas pasan a elegirla.
- **Área 1 (2.13):** implementa el enchufe y el presupuesto genérico del mes.
- **Áreas 3 y 4:** nada nuevo que programar; sus formularios con gasto directo ya usan el selector común, que pasa a
  pedir la sede. Se les avisa al repartir.
- La regla 3 de AGENTS.md («toda línea de costo tiene destino») sigue igual; el destino del gasto directo ahora es
  centro de costo más sede o «Empresa».

## Alternativas descartadas

- **Que Presupuestos lea las imputaciones y calcule por su cuenta, sin que el gasto sepa su sede:** no hay de dónde
  sacar la sede de un gasto de oficina.
- **Que el núcleo llame directo a `Erp/Presupuestos`:** rompe la regla de que el núcleo no depende de los módulos.
- **«Empresa» como sede falsa en `party_sedes`:** mezclaría una dirección inventada con las sedes reales de la ficha.
