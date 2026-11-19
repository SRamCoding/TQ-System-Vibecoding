# Documento 02-A · Anexo — Flujogramas de los procesos de Recursos Humanos

**Programa:** Ordenamiento Organizacional y Sistemas de Gestión — Techquk S.A.C.
**Versión:** 2.0 · Agosto 2026 · Anexo del documento 02-A v1.1

> Los tres diagramas responden preguntas distintas y conviene no mezclarlos.
> El **F1** muestra el recorrido completo; el **F2** muestra la única regla que puede detener una asignación; el **F3** muestra de dónde viene y a dónde va la información.

---

## Registro de cambios

| Versión | Fecha | Cambios |
|---|---|---|
| 1.0 | Ago 2026 | Tres flujogramas: ciclo de vida, asignación e interfaces |
| **2.0** | **Ago 2026** | **F2 incorpora la imputación de horas · F3 explicita la frontera con planilla** |

`[S]` Ambos cambios acompañan los del documento 02-A v2.0 y no alteran el diseño propuesto.

---

## F1 · Ciclo de vida: de la previsión a la salida

```mermaid
flowchart TD
    START(["Proyecto en cartera o adjudicado"]) --> RH01

    RH01["<b>RH-01</b><br/>Planificación de la demanda<br/><i>90 días de anticipación</i>"]
    RH01 --> DEC1{"¿La planta actual<br/>cubre la necesidad?"}

    DEC1 -->|Sí| RH04
    DEC1 -->|No| RH02["<b>RH-02</b><br/>Selección y banco de candidatos"]

    RH02 --> DEC2{"¿Hay candidato<br/>en el banco?"}
    DEC2 -->|Sí| RH03
    DEC2 -->|No| CONV["Convocatoria externa<br/>y precalificación técnica"]
    CONV --> RH03

    RH03["<b>RH-03</b><br/>Contratación e incorporación"]
    RH03 --> RH06["<b>RH-06</b><br/>Contrato emitido y registrado<br/><i>con vigencia y modalidad</i>"]
    RH06 --> RH04

    RH04["<b>RH-04</b><br/>Asignación<br/>persona · proyecto · rol · dedicación"]
    RH04 --> GATE{{"Validaciones<br/>obligatorias"}}

    GATE --> V1{"¿Dedicación agregada<br/>menor o igual a 100 por ciento?"}
    V1 -->|No| ALERTA["Alerta de sobreasignación<br/>a la gerencia de línea"]
    ALERTA --> DECIDE["La gerencia de línea<br/>decide la prioridad"]
    DECIDE --> RH04

    V1 -->|Sí| V2{"¿Habilitación de campo<br/>vigente? · RH-08"}
    V2 -->|No| BLOQUEO["Asignación bloqueada<br/>hasta regularizar o autorizar"]
    BLOQUEO --> RH08
    V2 -->|Sí| EJEC(["Persona asignada<br/>y trabajando en el proyecto"])

    RH08["<b>RH-08</b><br/>Examen ocupacional · seguro ·<br/>inducción · equipo de protección"]
    RH08 --> V2

    EJEC --> RH05["<b>RH-05</b><br/>Competencias y certificaciones<br/><i>se actualizan con la experiencia</i>"]
    RH05 --> RH07["<b>RH-07</b><br/>Brecha de competencia<br/>y plan de desarrollo"]
    RH07 -.->|"Retroalimenta la capacidad<br/>disponible"| RH01

    EJEC --> FIN{"¿Termina el vínculo?"}
    FIN -->|No| EJEC
    FIN -->|Sí| RH09["<b>RH-09</b><br/>Desvinculación:<br/>liberar asignaciones · revocar accesos ·<br/>documentar conocimiento · traspasar cartera"]
    RH09 --> END(["La empresa conserva<br/>el conocimiento y la cartera"])

    classDef central fill:#1f4e79,stroke:#0d2c47,color:#ffffff
    classDef cumplim fill:#7d5ba6,stroke:#4e3269,color:#ffffff
    classDef control fill:#c0392b,stroke:#7b241c,color:#ffffff
    classDef terminal fill:#e8eef4,stroke:#1f4e79,color:#0d2c47

    class RH01,RH02,RH03,RH04,RH05,RH09 central
    class RH06,RH07,RH08 cumplim
    class ALERTA,BLOQUEO control
    class START,EJEC,END terminal
```

**Cómo leerlo.** Azul: los procesos que habilitan la ejecución. Morado: cumplimiento y desarrollo. Rojo: los dos puntos donde el sistema **detiene** algo en vez de solo registrarlo.

> **Los dos rombos rojos son el aporte real del ERP.** Todo lo demás lo puede hacer una hoja de cálculo con disciplina. Bloquear una asignación por sobreasignación o por habilitación vencida, no.

---

## F2 · Detalle de RH-04: la asignación y sus controles

```mermaid
flowchart LR
    A["Jefe de Proyecto<br/>solicita persona"] --> B["Coordinación de Recursos<br/>propone asignación"]
    B --> C{"Persona con rol<br/>y competencia<br/>requerida"}
    C -->|No cumple| B
    C -->|Cumple| D{"Suma de dedicación<br/>en el periodo"}

    D -->|"Mayor a 100 por ciento"| E["Conflicto de capacidad"]
    E --> F["Gerencia de línea<br/>prioriza proyectos"]
    F --> B

    D -->|"100 por ciento o menos"| G{"Trabajo en campo"}
    G -->|No| J
    G -->|Sí| H{"Examen ocupacional,<br/>seguro e inducción<br/>vigentes"}

    H -->|No| I["Bloqueo · se deriva a RH-08"]
    I --> H
    H -->|Sí| J["Asignación confirmada<br/>con vigencia desde–hasta"]

    J --> K["Registro en ERP-07"]
    K --> L["Liberación automática<br/>al cierre del proyecto"]
    K --> M["<b>La persona imputa horas</b><br/>semana a semana · ERP-12"]
    M --> N["Costo real al proyecto<br/>proyecto_costos"]

    classDef ok fill:#1f4e79,stroke:#0d2c47,color:#ffffff
    classDef stop fill:#c0392b,stroke:#7b241c,color:#ffffff
    classDef nuevo fill:#0F6E56,stroke:#04342C,color:#ffffff
    class E,I stop
    class J,K,L ok
    class M,N nuevo
```

> **Lo que este diagrama define para el desarrollo:** la asignación no es un `INSERT` simple. Es una transacción con tres validaciones previas —competencia, capacidad y habilitación— y una fecha de fin que dispara la liberación. Modelarla como un campo `proyecto_actual` en la tabla de personas hace imposible todo lo anterior.

> `[V]` **Las dos cajas verdes son el agregado de la v2.0.** La asignación **planifica** —esta
> persona al 50% entre marzo y junio— pero las horas imputadas **registran lo real**. Si alguien
> asignado al 50% dedicó el 80% porque el proyecto se complicó, el margen calculado sobre la
> asignación está mal.
>
> `[V]` Importa especialmente porque el **55.3% del portafolio son contratos híbridos**, con
> frente de hardware y de software. Sin horas imputadas, ese segundo frente no tiene costo.
>
> `[S]` **Quien imputa es la persona que trabajó**, desde su propia pantalla. Recursos Humanos
> administra la asignación, no captura horas ajenas.

---

## F3 · Interfaces: qué entra, qué sale y con quién

```mermaid
flowchart LR
    subgraph FUERA_IZQ["Origen de la demanda"]
        CRM["CRM-02<br/>Oportunidades avanzadas"]
        PROY["ERP-01<br/>Proyectos adjudicados"]
    end

    subgraph RRHH["Recursos Humanos · ERP-07"]
        P1["RH-01 Planificación"]
        P2["RH-02 Selección"]
        P4["RH-04 Asignación"]
        P5["RH-05 Competencias"]
        P6["RH-06 Contratos"]
        P8["RH-08 Seguridad"]
    end

    subgraph FUERA_DER["Destino de la información"]
        PLAN["<b>Sistema de planillas</b><br/>fuera del ERP · D9"]
        LEGAL["Asistencia Legal<br/>revisión de cláusulas"]
        SIG["SIG<br/>audita, no ejecuta"]
        LICI["CRM-04<br/>acreditación de personal clave"]
        SEG["ERP-08<br/>usuarios y accesos"]
        COSTO["ERP-01<br/>costo real del proyecto"]
    end

    CRM --> P1
    PROY --> P1
    P1 --> P2
    P1 --> P4
    P2 --> P6
    P6 --> P4
    P5 --> P4
    P8 --> P4

    P6 -->|"novedades del mes"| PLAN
    PLAN -.->|"<b>costo hora</b>"| P4
    P4 --> COSTO
    P6 -.-> LEGAL
    P8 -.-> SIG
    P5 -.-> LICI
    P6 --> SEG

    classDef rh fill:#1f4e79,stroke:#0d2c47,color:#ffffff
    classDef ext fill:#e8eef4,stroke:#7a8b99,color:#0d2c47
    classDef fuera fill:#FFF4E6,stroke:#E8890C,color:#7B4A00
    class P1,P2,P4,P5,P6,P8 rh
    class LEGAL,SIG,LICI,SEG,COSTO ext
    class PLAN fuera
```

**Línea continua:** el dato pasa y es necesario para continuar. **Línea punteada:** el dato se informa o se consulta, sin detener el proceso.

> **La flecha que más importa es `P5 → CRM-04`.** Es la que convierte a Recursos Humanos en condición de ingreso a una licitación: sin certificación vigente acreditable, la propuesta no se presenta. Es también el argumento más fácil de defender ante Gerencia, porque tiene efecto directo sobre el ingreso.

> `[V]` **La caja naranja es la frontera con planilla, agregada en la v2.0.** El cálculo de
> remuneraciones **no vive en el ERP**: en S10 son 1,050 procedimientos, el segundo módulo más
> grande del sistema, y su costo real es el mantenimiento normativo permanente.
>
> **Dos flechas la cruzan, y van en sentidos opuestos.** RH-06 **entrega** las novedades del mes
> —altas, bajas, cambios de modalidad, ausencias—. Y el sistema de planillas **devuelve** el
> costo hora, sin el cual las horas imputadas son un número sin valor económico.
>
> `[P]` Esa devolución depende de la decisión D9. En su forma más simple es un campo actualizado
> a mano por periodo; en su forma completa, la integración I5 del documento 10.

---

## Nota de uso

Los tres diagramas están en Mermaid, igual que los del documento 01, por lo que se renderizan directamente en MkDocs Material sin plugin adicional.

**Los flujogramas son parte de la hipótesis de diseño**, no de la operación verificada. Sirven bien como material de taller: se proyectan, se pide a los participantes que señalen dónde el recorrido real difiere, y las diferencias señaladas son el hallazgo del taller.

---

*Fin del anexo de flujogramas del documento 02-A v2.0. La versión 1.0 está archivada en
`v1_historico/`.*