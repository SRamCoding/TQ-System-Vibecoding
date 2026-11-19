# v4 · Flujo 10 · Los contratos de obra pública: adelantos,

> Extracto de la parte 4 de la especificación v4. Fichas de sus documentos: `parte-6-fichas-documentos.md`. Escenarios de prueba: `parte-8-criterios-aceptacion.md`.

```text
Flujo 10 · Los contratos de obra pública: adelantos,
reajustes, valorizaciones, liquidación, consorcios y
moneda
Al revisar la propuesta contra lo que sabemos de los contratos de Techquk aparecieron huecos que se
concentran en un mismo lugar: las obras públicas formales. Tú mismo registraste una carta fianza
para una obra pública en Chiclayo, así que no es un caso raro. Casi todos esos huecos son el mismo
mecanismo visto desde dos lados: lo que la entidad le hace a Techquk (darle un adelanto, retenerle
una garantía, valorizar su avance, reajustar precios, liquidar el contrato), Techquk se lo hace a su
subcontratista. Se construye una vez y sirve en las dos direcciones. AJUSTE SET 2026
Este flujo sigue otro caso, porque la Plaza de Armas es un suministro con instalación de 30 días y no
tiene nada de esto: la Red de fibra del Hospital Regional, CT-2026-030, una obra de S/ 890,000 a
180 días con adelanto directo, adelanto de materiales, fórmula polinómica y un subcontrato de obras
civiles. Los datos son ficticios.
|  1  |  Se registran las condiciones del contrato de obra
|  Administración de Contratos
|  Además de hitos y penalidad, el contrato de obra trae: los adelantos que la entidad otorga (con
|  sus garantías), la fórmula polinómica de reajuste (con sus monomios y coeficientes, que vienen
|  del presupuesto), la modalidad de garantía (carta fianza o retención de cada pago), la moneda, y
|  si se ejecuta en consorcio. Todo se configura por contrato, porque cada uno tiene sus bases y la
|  ley cambia.
|  Adelantos · CT-2026-030 · Red de fibra Hospital Regional  |  + Registrar adelanto
|  Adelanto  |  Monto  |  % del contrato Garantía  |  Recibido  |  Amortizado  |  Por amortizar
|  Directo  |  89,000.00  |  10 % GAR-2026-041 · vence 15 mar  |  12 set  |  35,600.00  |  53,400.00
|  Materiales  |  133,500.00  |  15 % GAR-2026-042 · vence 15 mar  |  20 set  |  0.00  |  133,500.00
|  En cada valorización se descuenta la parte proporcional: el adelanto directo se amortiza en la misma proporción en que
|  avanza el contrato; el de materiales, según lo que la valorización consume de los insumos adelantados. La garantía se
|  puede reducir a medida que se amortiza.
Adelantos. Entran a la caja cuando se reciben y salen de cada valorización hasta amortizarse. Las cuentas por cobrar
muestran el neto real.
|  Fórmula polinómica · PRE-2026-040 · Red de fibra  |  aprobada con el presupuesto
|  Monomio  |  Coef.  |  Índice base (jun 2026)  |  Índice del mes (set 2026)  |  FACTOR DE REAJUSTE ·
|  SET 2026
|  Mano de obra  |  0.320  |  612.40  |  621.10
|  Cable de fibra óptica  |  0.285  |  398.20  |  411.60
|  1.0148
|  Σ coef × (índice mes ÷
|  Equipos electrónicos  |  0.190  |  512.00  |  509.80  |  índice base)
|  Índice general de precios  |  0.205  |  184.30  |  186.90
|  SOBRE LA VALORIZACIÓN
|  Suma  |  1.000  |  3
|  142,300.00 × 0.0148 =
|  2,106.04
|  Los índices oficiales se cargan
|  cada mes. Las deducciones por
|  adelantos que exige la norma se
|  aplican según lo configurado por
|  contrato.
Fórmula polinómica. Los monomios y coeficientes vienen del presupuesto; el sistema solo aplica la fórmula con los índices del
mes. Es cálculo, no criterio.
|  2  |  Se valoriza el avance de cada periodo
|  Jefatura de Proyectos · Administración de Contratos
|  Cada mes la valorización se arma sola desde el avance registrado por partida: metrado ejecutado
|  por precio contractual, más el reajuste con los índices del mes, menos la amortización de los
|  adelantos, menos la retención si la hay, menos penalidades del periodo. La persona revisa y
|  aprueba; al aprobarse habilita el comprobante por el neto (flujo 4).
|  Valorización 3 · set 2026 · CT-2026-030  |  Por aprobar  |  Aprobar y habilitar factura
|  Concepto  |  Monto
|  Avance del periodo (metrado × precio contractual)  |  142,300.00
|  Reajuste por fórmula polinómica (factor 1.0148)  |  2,106.04
|  Amortización del adelanto directo (10 %)  |  −14,230.00
|  Amortización del adelanto de materiales (según consumo)  |  −21,400.00
|  Retención de garantía (si aplica en lugar de fianza)  |  0.00
|  Penalidad aplicada este periodo  |  0.00
|  Neto a facturar (sin IGV)  |  108,776.04
|  La valorización se arma sola desde el avance registrado por partida; la persona revisa y aprueba. Al aprobarse, habilita el comprobante
|  en el flujo 4 por el neto.
Valorización con reajuste y amortización. Es el documento que junta casi todos los huecos en una sola cuenta.
|  Qué queda registrado · valorización 3 aprobada por un neto de S/ 108,776.04 · adelanto directo: amortizado
|  49,830.00, por amortizar 39,170.00 · reajuste acumulado 2,106.04 registrado · comprobante habilitado en la
|  bandeja por facturar
|  3  |  Se ejecuta con subcontratistas
|  Jefatura de Proyectos · Compras
|  El subcontrato es un tipo de compra con vida propia: su alcance se asigna a partidas de control,
|  el subcontratista presenta valorizaciones según su avance, Techquk le amortiza los adelantos
|  que le dio, le retiene un porcentaje de garantía y al final lo liquida. Cada valorización aprobada
|  pasa a ejecutado en las partidas del alcance y a cuentas por pagar por el neto.
|  Subcontrato SC-2026-007 · Obras civiles Norte
|  En ejecución  |  + Valorización del subcontratista
|  E.I.R.L.
|  Alcance por partida  |  Valorizaciones  |  Adelantos  |  Retención  |  Liquidación  |  Documentos
|  Val.  |  Periodo  |  Avance  |  Monto  |  Amortización adelanto  |  Retención 5 %  |  Neto a pagar Estado
|  1  |  ago  |  35 %  |  24,500.00  |  −4,900.00  |  −1,225.00  |  18,375.00  |  pagada
|  2  |  set  |  40 %  |  28,000.00  |  −5,600.00  |  −1,400.00  |  21,000.00  |  por aprobar
|  Es el mismo mecanismo que el contrato con la entidad, en la dirección contraria: valorizaciones, adelantos amortizados, retención y
|  liquidación. Cada valorización aprobada pasa a ejecutado en las partidas del alcance.
Subcontrato. Lo que la entidad le hace a Techquk, Techquk se lo hace a su subcontratista. Se construye una vez y sirve en las
dos direcciones.
|  4  |  Se maneja el consorcio, la retención y la moneda cuando aplican
|  Administración de Contratos · Tesorería
|  Si el contrato es en consorcio, el ERP controla la participación de Techquk: su parte del ingreso,
|  sus costos y sus aportes. Si la garantía es por retención, las cuentas por cobrar separan lo
|  cobrable de lo retenido. Si hay moneda extranjera, todo documento guarda moneda, tipo de
|  cambio, fecha y fuente, y la diferencia de cambio genera su asiento.
|  Consorcio Vial Norte · CT-2025-044  |  Techquk 40 %
|  Integrante  |  % Rol  |  LO QUE CONTROLA EL ERP EN ESTE MODO
|  Techquk S.A.C.  |  40 % socio · ejecuta el frente de  |  La parte de Techquk del ingreso (40 % de cada
|  comunicaciones  |  valorización), sus costos, sus aportes al consorcio y el
|  reparto de resultados.
|  Constructora Norte  |  60 % operador · factura a la entidad
|  S.A.
|  FACTURACIÓN
|  MODO DE REGISTRO  |  Si el consorcio factura con su propio RUC, Techquk le
|  Solo la participación de Techquk  |  factura al consorcio su participación; el comprobante se
|  Todo el contrato con porcentajes  |  sube igual que cualquier otro.
Consorcios. Como el sistema es de una sola empresa, controla la participación de Techquk; el modo «todo el contrato» existe
para cuando Techquk es el operador.
|  Garantía por retención · CT-2026-035  |  Moneda y tipo de cambio · OC-2026-0151
|  Modalidad  |  Retenido a la fecha  |  Moneda  |  Monto original
|  Retención del 10 % de cada  |  S/ 18,400.00  |  USD  |  USD 6,000.00
|  pago  |  TC al registrar  |  Equivalente
|  Se devuelve  |  3.740 · fuente oficial · 18 set  |  S/ 22,440.00
|  al consentimiento de la
|  liquidación  |  Al pagar con TC 3.752, la diferencia de cambio (S/
|  72.00) genera su propio asiento. Todo documento con
|  Las cuentas por cobrar separan lo cobrable de lo  |  montos guarda moneda, TC, fecha y fuente.
|  retenido, y lo retenido tiene su propia alerta al cierre.
Retención en lugar de fianza, y moneda extranjera. Ambas se configuran por contrato o por documento; nada va escrito en el
código.
|  5  |  Se liquida el contrato con la entidad
|  Administración de Contratos
|  Distinta del cierre interno del proyecto. La liquidación mira hacia la entidad: monto contratado,
|  más adicionales, menos reducciones, más reajustes, menos penalidades, menos adelantos ya
|  descontados, menos lo cobrado. Se arma sola, tiene sus estados y sus plazos con alertas, y al
|  consentirse habilita la devolución de las garantías.
|  Liquidación del contrato ·  |  plazo para presentar: 45 días desde la
|  Borrador  |  Presentar a la entidad
|  CT-2026-030  |  conformidad · vence 14 nov
|  Concepto  |  Monto
|  Monto contratado  |  890,000.00
|  Adicionales aprobados  |  +38,600.00
|  Reducciones  |  −12,000.00
|  Reajustes acumulados  |  +9,840.00
|  Penalidades  |  −4,120.00
|  Adelantos recibidos  |  −222,500.00
|  Adelantos amortizados en valorizaciones  |  +222,500.00
|  Cobrado hasta la fecha (valorizaciones 1 a 7)  |  −871,300.00
|  Saldo a favor de Techquk  |  51,020.00
|  Borrador  |  Presentada Observada  |  Aprobada  |  Consentida  |  Al consentirse, se habilita la devolución de la garantía de
|  fiel cumplimiento y el proyecto puede cerrarse (flujo 6).
Liquidación del contrato hacia la entidad. Se arma sola con lo que ya está en el sistema; distinta del cierre interno del
proyecto.
|  6  |  Si Techquk arrienda equipos
|  Operaciones · Tesorería
|  Era una de las tipologías del portafolio sin verificar. El modelo la soporta desde ya (equipos
|  propios con serie y depreciación, contrato de arrendamiento con cobro periódico, actas de
|  entrega y devolución), pero se construye solo si la lista real de contratos lo confirma.
|  FASE POSTERIOR
|  Equipos propios y arrendamientos  |  fase posterior
|  Equipo  |  Serie  |  Estado  |  Con cliente  |  Arrendamiento  |  Próximo cobro
|  Cámara térmica 3MP  |  TH…0221  |  arrendada  |  EPSEL S.A.  |  AR-2026-003 · mensual 900.00  |  1 oct
|  Dron de inspección  |  DJ…9910  |  en almacén  |  —  |  —  |  —
|  Si se confirma que Techquk arrienda equipos: registro de equipos propios con serie y depreciación, contrato de arrendamiento con cobro
|  periódico, entrega y devolución con acta.
Arrendamiento de equipos. Diseñado para que el modelo lo soporte; se construye solo si la lista real de contratos lo confirma.
|  CUANDO LAS COSAS NO SALEN COMO SE ESPERA
|  Cambio de coeficientes o de índices después de una valorización aprobada. Se
|  recalcula en la siguiente como reajuste acumulado; la aprobada no se toca.
|  Adelanto de materiales que no se consume al ritmo previsto. La amortización sigue el
|  consumo real; el saldo por amortizar queda visible y su garantía no se reduce hasta
|  amortizarse.
|  Valorización observada por la entidad. Vuelve a borrador con la observación registrada; el
|  comprobante no se habilita hasta la aprobación.
|  Subcontratista que no presenta valorización. El avance de sus partidas queda como no
|  valorizado y alerta a la Jefatura de Proyectos.
|  Techquk como operador del consorcio. Se usa el modo «todo el contrato con porcentajes»;
|  los socios aparecen como cuentas por pagar o cobrar según el reparto.
|  POR VALIDAR Deducciones de reajuste por adelantos, cuándo aplica la retención en
|  lugar de fianza, y plazos de liquidación. Deben validarse con quien administra los contratos
|  y con el reglamento vigente de la Ley 32069. Son configuración por contrato.
|  PARTE 5
```
