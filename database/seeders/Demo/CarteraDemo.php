<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * Los datos inventados de la demo (ficha t0-datos-demo, MV-001 a MV-004). Solo datos: el recorrido está en DemoSeeder.
 * Montos en soles. Ninguna empresa, persona ni proyecto real.
 */
final class CarteraDemo
{
    /**
     * Las cuentas: clave => [razón social, base de RUC de 10 dígitos, pública, nivel de gobierno, rubro, origen,
     * decisor [nombres, apellidos, cargo]].
     *
     * @return array<string, array{0: string, 1: string, 2: bool, 3: ?string, 4: string, 5: string, 6: array{0: string, 1: string, 2: string}}>
     */
    public static function cuentas(): array
    {
        return [
            'mpvn' => ['Municipalidad Provincial de Valle Norte', '2069910001', true, 'LOCAL', 'O', 'CONVOCATORIA_PUBLICA', ['Julio César', 'Paredes Linares', 'Gerente de Seguridad Ciudadana']],
            'ugel' => ['Unidad de Gestión Educativa Local Valle Norte', '2069910002', true, 'REGIONAL', 'P', 'CONVOCATORIA_PUBLICA', ['Marleny', 'Saavedra Ruiz', 'Jefa de Administración']],
            'laureles' => ['Municipalidad Distrital de Los Laureles', '2069910003', true, 'LOCAL', 'O', 'CONVOCATORIA_PUBLICA', ['Hernán', 'Castañeda Rojas', 'Gerente Municipal']],
            'hospital' => ['Hospital Regional de Valle Norte', '2069910004', true, 'REGIONAL', 'Q', 'CONVOCATORIA_PUBLICA', ['Gladys', 'Montenegro Tapia', 'Jefa de la Oficina de Estadística e Informática']],
            'gore' => ['Gobierno Regional de Valle Norte', '2069910005', true, 'REGIONAL', 'O', 'CONVOCATORIA_PUBLICA', ['Ricardo', 'Alvarado Chumpitaz', 'Subgerente de Tecnologías de la Información']],
            'pampa_alta' => ['Municipalidad Distrital de La Pampa Alta', '2069910006', true, 'LOCAL', 'O', 'VISITA_FRIO', ['Elmer', 'Vásquez Llontop', 'Gerente de Desarrollo Urbano']],
            'universidad' => ['Universidad Nacional del Valle Norte', '2069910007', true, 'NACIONAL', 'P', 'FERIA', ['Patricia', 'Gonzales Bances', 'Directora de Tecnologías de la Información']],
            'pehvn' => ['Proyecto Especial Hidráulico Valle Norte', '2069910008', true, 'REGIONAL', 'E', 'REFERENCIA', ['Óscar', 'Zapata Inga', 'Director de Operaciones']],
            'agronorte' => ['Agroindustrial Norteña S.A.C.', '2069920001', false, null, 'A', 'REFERENCIA', ['Lucía', 'Benavides Arca', 'Gerente de Administración y Finanzas']],
            'clinica' => ['Clínica del Norte S.A.C.', '2069920002', false, null, 'Q', 'SITIO_WEB', ['Fernando', 'Ríos Mendoza', 'Gerente General']],
            'pampa_verde' => ['Agrícola Pampa Verde S.A.C.', '2069920003', false, null, 'A', 'REFERENCIA', ['Carmen', 'Delgado Uceda', 'Jefa de Riego y Operaciones']],
            'minera' => ['Minera Cerro Azul S.A.A.', '2069920004', false, null, 'B', 'CONVOCATORIA_PRIVADA', ['Javier', 'Huamán Torres', 'Superintendente de Seguridad Patrimonial']],
            'canasta' => ['Supermercados La Canasta S.A.C.', '2069920005', false, null, 'G', 'SITIO_WEB', ['Andrés', 'Peralta Quiroz', 'Jefe de Prevención de Pérdidas']],
            'ruta_andina' => ['Transportes Ruta Andina S.A.C.', '2069920006', false, null, 'H', 'VISITA_FRIO', ['Silvia', 'Cabrera Núñez', 'Gerente de Operaciones']],
            'andes' => ['Colegio Los Andes del Norte S.A.C.', '2069920007', false, null, 'P', 'REFERENCIA', ['Miguel', 'Ortiz Salcedo', 'Director Administrativo']],
            'mirador' => ['Hotel Mirador del Valle S.A.C.', '2069920008', false, null, 'I', 'FERIA', ['Rocío', 'Farfán Espinoza', 'Gerente de Hotel']],
        ];
    }

    /** Los jefes de proyecto inventados: [nombres, apellidos, DNI, correo]. */
    public static function jefes(): array
    {
        return [
            'rosa' => ['Rosa', 'Quispe Herrera', '00000011', 'rosa.quispe@techquk.test'],
            'diego' => ['Diego', 'Salazar Bravo', '00000012', 'diego.salazar@techquk.test'],
            'andrea' => ['Andrea', 'Vílchez Campos', '00000013', 'andrea.vilchez@techquk.test'],
        ];
    }

    /**
     * Los nueve proyectos (MV-001), del plan 2026-11-16-datos-desarrollo-cartera. Partidas: [código, descripción,
     * unidad, metrado, precio unitario, título]. Avance: fracción comprometida para los que siguen en ejecución.
     * PC-99: [motivo, comentario, monto] de gastos no presupuestados.
     *
     * @return list<array<string, mixed>>
     */
    public static function proyectos(): array
    {
        return [
            [
                'clave' => 'P1', 'nombre' => 'Videovigilancia y centro de monitoreo de Valle Norte', 'cuenta' => 'mpvn', 'linea' => 'VIDEOVIGILANCIA',
                'gg' => '10', 'ut' => '8', 'ganada' => '2024-02-12', 'inicio' => '2024-03-04', 'fin' => '2024-10-31', 'estado' => 'cerrado', 'jefe' => 'rosa',
                'pc99' => [['imprevisto_obra', 'Reubicación de seis postes por interferencia con la red eléctrica', '38500.00']],
                'partidas' => [
                    ['01.01', 'Suministro e instalación de cámara PTZ 4K', 'NIU', '36', '14850.00', 'CÁMARAS Y POSTES'],
                    ['01.02', 'Suministro e instalación de cámara bullet 4 MP', 'NIU', '80', '2480.00', 'CÁMARAS Y POSTES'],
                    ['01.03', 'Poste metálico de 9 m con cimentación', 'NIU', '52', '4650.00', 'CÁMARAS Y POSTES'],
                    ['02.01', 'Enlace inalámbrico punto a punto', 'NIU', '30', '6900.00', 'ENLACES Y CABLEADO'],
                    ['02.02', 'Tendido de fibra óptica monomodo', 'MTR', '8600', '11.40', 'ENLACES Y CABLEADO'],
                    ['03.01', 'Videowall de 3 x 3 pantallas', 'GLB', '1', '120000.00', 'CENTRO DE MONITOREO'],
                    ['03.02', 'Servidores de grabación y videoanalítica', 'NIU', '4', '58600.00', 'CENTRO DE MONITOREO'],
                    ['03.03', 'Mobiliario y climatización del centro de monitoreo', 'GLB', '1', '84500.00', 'CENTRO DE MONITOREO'],
                    ['04.01', 'Canalización, buzones y obra civil', 'GLB', '1', '80301.51', 'OBRAS CIVILES'],
                ],
            ],
            [
                'clave' => 'P2', 'nombre' => 'Cableado estructurado de la I.E. N.° 10245 Los Algarrobos', 'cuenta' => 'ugel', 'linea' => 'REDES',
                'gg' => '10', 'ut' => '8', 'ganada' => '2024-05-20', 'inicio' => '2024-06-10', 'fin' => '2024-09-08', 'estado' => 'cerrado', 'jefe' => 'diego',
                'partidas' => [
                    ['01.01', 'Punto de red Cat 6 certificado', 'NIU', '220', '245.00', 'CABLEADO ESTRUCTURADO'],
                    ['01.02', 'Gabinete de piso de 42 RU', 'NIU', '3', '5850.00', 'CABLEADO ESTRUCTURADO'],
                    ['02.01', 'Switch de acceso de 48 puertos PoE', 'NIU', '5', '8400.00', 'EQUIPOS ACTIVOS'],
                    ['02.02', 'Punto de acceso inalámbrico', 'NIU', '24', '1350.00', 'EQUIPOS ACTIVOS'],
                    ['02.03', 'Configuración, certificación y pruebas', 'GLB', '1', '6500.00', 'EQUIPOS ACTIVOS'],
                ],
            ],
            [
                'clave' => 'P3', 'nombre' => 'Suministro de 60 computadoras para la Municipalidad de Los Laureles', 'cuenta' => 'laureles', 'linea' => 'REDES',
                'gg' => '5', 'ut' => '6', 'ganada' => '2024-09-02', 'inicio' => '2024-09-16', 'fin' => '2024-10-31', 'estado' => 'cerrado', 'jefe' => 'andrea',
                'partidas' => [
                    ['01.01', 'Computadora de escritorio con monitor de 24"', 'NIU', '60', '1480.00', 'EQUIPOS DE CÓMPUTO'],
                    ['01.02', 'Impresora multifuncional láser', 'NIU', '8', '1890.00', 'EQUIPOS DE CÓMPUTO'],
                    ['01.03', 'Entrega, instalación y acta', 'GLB', '1', '2650.00', 'EQUIPOS DE CÓMPUTO'],
                ],
            ],
            [
                'clave' => 'P4', 'nombre' => 'Sala de videoconferencia del directorio de Agroindustrial Norteña', 'cuenta' => 'agronorte', 'linea' => 'REDES',
                'gg' => '10', 'ut' => '35', 'ganada' => '2025-01-28', 'inicio' => '2025-02-10', 'fin' => '2025-03-21', 'estado' => 'cerrado', 'jefe' => 'diego',
                'partidas' => [
                    ['01.01', 'Pantalla interactiva de 86"', 'NIU', '1', '21400.00', 'SALA DE DIRECTORIO'],
                    ['01.02', 'Sistema de videoconferencia con cámara y barra de audio', 'NIU', '1', '18900.00', 'SALA DE DIRECTORIO'],
                    ['01.03', 'Audio de sala con micrófonos de techo', 'GLB', '1', '14600.00', 'SALA DE DIRECTORIO'],
                    ['01.04', 'Montaje, cableado y capacitación', 'GLB', '1', '8100.00', 'SALA DE DIRECTORIO'],
                ],
            ],
            [
                'clave' => 'P5', 'nombre' => 'Mantenimiento de CCTV de la Clínica del Norte (12 meses)', 'cuenta' => 'clinica', 'linea' => 'VIDEOVIGILANCIA',
                'gg' => '10', 'ut' => '35', 'ganada' => '2025-12-15', 'inicio' => '2026-01-02', 'fin' => '2026-12-31', 'estado' => 'en_ejecucion', 'jefe' => 'rosa', 'avance' => '0.85',
                'partidas' => [
                    ['01.01', 'Mantenimiento preventivo trimestral de CCTV', 'ZZ', '4', '6200.00', 'MANTENIMIENTO'],
                    ['01.02', 'Bolsa de atención correctiva', 'HUR', '120', '68.00', 'MANTENIMIENTO'],
                    ['01.03', 'Repuestos en consignación', 'GLB', '1', '5040.00', 'MANTENIMIENTO'],
                ],
            ],
            [
                'clave' => 'P6', 'nombre' => 'Sistema de información hospitalaria, software y servidor del Hospital Regional de Valle Norte', 'cuenta' => 'hospital', 'linea' => 'SOFTWARE',
                'gg' => '10', 'ut' => '8', 'ganada' => '2025-08-18', 'inicio' => '2025-09-15', 'fin' => '2026-12-15', 'estado' => 'en_ejecucion', 'jefe' => 'andrea', 'avance' => '0.78',
                'pc99' => [['precio_mayor', 'Alza del precio del almacenamiento SAN entre la oferta y la compra', '18450.00'], ['cambio_cliente', 'El hospital pidió 12 estaciones más para emergencia', '35400.00']],
                'partidas' => [
                    ['01.01', 'Licencias del sistema de información hospitalaria', 'GLB', '1', '612000.00', 'SISTEMA DE INFORMACIÓN'],
                    ['01.02', 'Implementación, parametrización y migración de datos', 'HUR', '4200', '95.00', 'SISTEMA DE INFORMACIÓN'],
                    ['01.03', 'Capacitación al personal asistencial', 'GLB', '1', '48000.00', 'SISTEMA DE INFORMACIÓN'],
                    ['02.01', 'Servidor de rack con virtualización', 'NIU', '4', '86500.00', 'SERVIDORES Y ALMACENAMIENTO'],
                    ['02.02', 'Almacenamiento SAN', 'NIU', '1', '245000.00', 'SERVIDORES Y ALMACENAMIENTO'],
                    ['03.01', 'Computadoras para consultorios y estaciones de enfermería', 'NIU', '180', '2950.00', 'OTROS ACTIVOS'],
                    ['03.02', 'Red de datos de los servicios', 'GLB', '1', '103971.08', 'OTROS ACTIVOS'],
                ],
            ],
            [
                'clave' => 'P7', 'nombre' => 'Telemetría de pozos de riego con plataforma web', 'cuenta' => 'pampa_verde', 'linea' => 'SOFTWARE',
                'gg' => '10', 'ut' => '35', 'ganada' => '2025-04-07', 'inicio' => '2025-04-21', 'fin' => '2025-08-29', 'estado' => 'cerrado', 'jefe' => 'diego',
                'pc99' => [['cambio_cliente', 'Dos estaciones adicionales en los pozos nuevos del fundo', '9800.00']],
                'partidas' => [
                    ['01.01', 'Estación de telemetría con sensor de nivel y caudal', 'NIU', '14', '4350.00', 'EQUIPAMIENTO DE CAMPO'],
                    ['01.02', 'Gateway LoRaWAN con energía solar', 'NIU', '3', '6800.00', 'EQUIPAMIENTO DE CAMPO'],
                    ['02.01', 'Desarrollo de la plataforma de monitoreo', 'HUR', '520', '85.00', 'PLATAFORMA WEB'],
                    ['02.02', 'Servidor en la nube por 12 meses', 'MON', '12', '420.00', 'PLATAFORMA WEB'],
                    ['02.03', 'Capacitación y transferencia', 'GLB', '1', '2520.00', 'PLATAFORMA WEB'],
                ],
            ],
            [
                'clave' => 'P8', 'nombre' => 'Red de datos y enlaces de la sede del Gobierno Regional de Valle Norte', 'cuenta' => 'gore', 'linea' => 'REDES',
                'gg' => '10', 'ut' => '8', 'ganada' => '2026-05-11', 'inicio' => '2026-06-01', 'fin' => '2027-01-27', 'estado' => 'en_ejecucion', 'jefe' => 'rosa', 'avance' => '0.55',
                'partidas' => [
                    ['01.01', 'Punto de red Cat 6A certificado', 'NIU', '640', '265.00', 'RED DE DATOS'],
                    ['01.02', 'Switch de distribución de 48 puertos', 'NIU', '12', '12800.00', 'RED DE DATOS'],
                    ['02.01', 'Enlace inalámbrico a sede descentralizada', 'NIU', '9', '24600.00', 'ENLACES INALÁMBRICOS'],
                    ['03.01', 'Gabinetes, UPS y climatización', 'GLB', '1', '160000.00', 'CENTRO DE DATOS'],
                    ['03.02', 'Firewall de siguiente generación', 'NIU', '2', '58400.00', 'CENTRO DE DATOS'],
                    ['03.03', 'Instalación, certificación y pruebas', 'GLB', '1', '26600.00', 'CENTRO DE DATOS'],
                ],
            ],
            [
                'clave' => 'P9', 'nombre' => 'Historia clínica electrónica, servidores y equipamiento del Hospital Regional de Valle Norte', 'cuenta' => 'hospital', 'linea' => 'SOFTWARE',
                'gg' => '10', 'ut' => '8', 'ganada' => '2026-10-26', 'inicio' => '2026-11-23', 'fin' => '2027-06-30', 'estado' => 'en_preparacion', 'jefe' => 'andrea',
                'partidas' => [
                    ['01.01', 'Módulo de historia clínica electrónica', 'GLB', '1', '486000.00', 'HISTORIA CLÍNICA ELECTRÓNICA'],
                    ['01.02', 'Integración con laboratorio e imágenes', 'HUR', '2600', '95.00', 'HISTORIA CLÍNICA ELECTRÓNICA'],
                    ['02.01', 'Servidor de contingencia y respaldo', 'NIU', '2', '92000.00', 'SERVIDORES'],
                    ['03.01', 'Tabletas para visita médica', 'NIU', '120', '1850.00', 'EQUIPAMIENTO'],
                    ['03.02', 'Impresoras de pulseras y etiquetas', 'NIU', '40', '2600.00', 'EQUIPAMIENTO'],
                    ['03.03', 'Puesta en marcha y soporte del primer mes', 'GLB', '1', '49314.05', 'EQUIPAMIENTO'],
                ],
            ],
        ];
    }

    /**
     * El embudo abierto y las perdidas (MV-004). Etapa: prospecto, calificada, propuesta_enviada, negociacion o
     * perdida. Cotización: borrador, por_aprobar, emitida, aprobada_emitida (pasó por la firma del gerente) o
     * rechazada. Líneas: [descripción, cantidad, precio, costo]. Perdida: [motivo, comentario].
     *
     * @return list<array<string, mixed>>
     */
    public static function embudo(): array
    {
        return [
            ['nombre' => 'Ampliación de cámaras en el mercado modelo', 'cuenta' => 'pampa_alta', 'linea' => 'VIDEOVIGILANCIA', 'monto' => '185000.00', 'creada' => '2026-10-28', 'cierre' => '2027-02-15', 'etapa' => 'prospecto'],
            ['nombre' => 'Red inalámbrica del campus universitario', 'cuenta' => 'universidad', 'linea' => 'REDES', 'monto' => '420000.00', 'creada' => '2026-11-04', 'cierre' => '2027-04-30', 'etapa' => 'prospecto'],
            ['nombre' => 'Control de acceso y CCTV de almacenes', 'cuenta' => 'canasta', 'linea' => 'VIDEOVIGILANCIA', 'monto' => '96500.00', 'creada' => '2026-11-10', 'cierre' => '2027-01-20', 'etapa' => 'prospecto'],
            ['nombre' => 'Sala de servidores de la sede central', 'cuenta' => 'ruta_andina', 'linea' => 'DATACENTER', 'monto' => '138000.00', 'creada' => '2026-11-14', 'cierre' => '2027-03-10', 'etapa' => 'prospecto'],

            ['nombre' => 'Telemetría de canales de riego', 'cuenta' => 'pehvn', 'linea' => 'SOFTWARE', 'monto' => '640000.00', 'creada' => '2026-09-22', 'cierre' => '2027-03-31', 'etapa' => 'calificada'],
            ['nombre' => 'Renovación de equipos de los laboratorios de cómputo', 'cuenta' => 'andes', 'linea' => 'REDES', 'monto' => '78000.00', 'creada' => '2026-10-06', 'cierre' => '2026-12-20', 'etapa' => 'calificada', 'cotizacion' => 'borrador', 'lineas' => [
                ['Computadora de escritorio con monitor de 22"', '30', '2150.00', '1540.00'],
                ['Switch de 24 puertos', '2', '1850.00', '1290.00'],
                ['Instalación y configuración', '1', '4200.00', '2600.00'],
            ]],
            ['nombre' => 'Wifi para huéspedes y red de habitaciones', 'cuenta' => 'mirador', 'linea' => 'REDES', 'monto' => '64000.00', 'creada' => '2026-10-15', 'cierre' => '2027-01-15', 'etapa' => 'calificada'],
            ['nombre' => 'Monitoreo de flota con GPS y plataforma', 'cuenta' => 'ruta_andina', 'linea' => 'SOFTWARE', 'monto' => '112000.00', 'creada' => '2026-10-20', 'cierre' => '2027-02-28', 'etapa' => 'calificada'],
            ['nombre' => 'Ampliación del centro de monitoreo de Valle Norte', 'cuenta' => 'mpvn', 'linea' => 'VIDEOVIGILANCIA', 'monto' => '356000.00', 'creada' => '2026-09-30', 'cierre' => '2027-01-31', 'etapa' => 'calificada', 'cotizacion' => 'por_aprobar', 'lineas' => [
                ['Cámara PTZ 4K con instalación', '12', '16200.00', '14900.00'],
                ['Puesto de operador del centro de monitoreo', '4', '18500.00', '17100.00'],
                ['Ampliación del almacenamiento de grabación', '1', '62000.00', '57300.00'],
            ]],

            ['nombre' => 'CCTV perimetral de la planta concentradora', 'cuenta' => 'minera', 'linea' => 'VIDEOVIGILANCIA', 'monto' => '512000.00', 'creada' => '2026-08-18', 'cierre' => '2026-12-15', 'etapa' => 'propuesta_enviada', 'cotizacion' => 'emitida', 'lineas' => [
                ['Cámara térmica de largo alcance', '8', '38500.00', '26900.00'],
                ['Cámara bullet 8 MP antivandálica', '40', '2650.00', '1830.00'],
                ['Torre de 15 m con pararrayos', '8', '9800.00', '6900.00'],
                ['Enlaces, energía e instalación', '1', '63000.00', '44000.00'],
            ]],
            ['nombre' => 'Mantenimiento de la red de datos 2027', 'cuenta' => 'clinica', 'linea' => 'REDES', 'monto' => '48000.00', 'creada' => '2026-10-02', 'cierre' => '2026-12-31', 'etapa' => 'propuesta_enviada', 'cotizacion' => 'emitida', 'lineas' => [
                ['Mantenimiento preventivo semestral', '2', '9800.00', '6600.00'],
                ['Bolsa de horas de soporte', '200', '120.00', '78.00'],
            ]],
            ['nombre' => 'Sistema de citas en línea', 'cuenta' => 'hospital', 'linea' => 'SOFTWARE', 'monto' => '168000.00', 'creada' => '2026-09-08', 'cierre' => '2026-12-31', 'etapa' => 'propuesta_enviada', 'cotizacion' => 'emitida', 'lineas' => [
                ['Desarrollo e implementación del sistema de citas', '1', '128000.00', '92000.00'],
                ['Quioscos de autoatención', '4', '9800.00', '7100.00'],
            ]],
            ['nombre' => 'Videovigilancia de tiendas, segunda fase', 'cuenta' => 'canasta', 'linea' => 'VIDEOVIGILANCIA', 'monto' => '142000.00', 'creada' => '2026-09-15', 'cierre' => '2026-12-10', 'etapa' => 'propuesta_enviada', 'cotizacion' => 'emitida', 'lineas' => [
                ['Kit de cámaras por tienda (16 cámaras y NVR)', '6', '19800.00', '13700.00'],
                ['Monitoreo centralizado', '1', '23000.00', '15800.00'],
            ]],

            ['nombre' => 'Data center modular de la unidad minera', 'cuenta' => 'minera', 'linea' => 'DATACENTER', 'monto' => '890000.00', 'creada' => '2026-07-06', 'cierre' => '2026-11-30', 'etapa' => 'negociacion', 'cotizacion' => 'emitida', 'lineas' => [
                ['Data center modular de 8 racks', '1', '640000.00', '448000.00'],
                ['Sistema contra incendios con agente limpio', '1', '128000.00', '89000.00'],
                ['Instalación, pruebas y capacitación', '1', '122000.00', '84000.00'],
            ]],
            ['nombre' => 'Red de fibra óptica entre sedes regionales', 'cuenta' => 'gore', 'linea' => 'REDES', 'monto' => '1260000.00', 'creada' => '2026-08-03', 'cierre' => '2026-12-20', 'etapa' => 'negociacion', 'cotizacion' => 'aprobada_emitida', 'lineas' => [
                ['Tendido de fibra óptica monomodo de 48 hilos', '62000', '14.80', '13.60'],
                ['Nodo de distribución con equipos activos', '6', '58000.00', '53200.00'],
            ]],
            ['nombre' => 'Plataforma de trazabilidad de lotes', 'cuenta' => 'agronorte', 'linea' => 'SOFTWARE', 'monto' => '215000.00', 'creada' => '2026-08-25', 'cierre' => '2026-12-05', 'etapa' => 'negociacion', 'cotizacion' => 'emitida', 'lineas' => [
                ['Desarrollo de la plataforma de trazabilidad', '1', '154000.00', '106000.00'],
                ['Lectores y etiquetas para la línea de empaque', '1', '61000.00', '43500.00'],
            ]],

            ['nombre' => 'Cámaras corporales para serenazgo', 'cuenta' => 'laureles', 'linea' => 'VIDEOVIGILANCIA', 'monto' => '86000.00', 'creada' => '2026-06-10', 'cierre' => '2026-09-30', 'etapa' => 'perdida', 'cotizacion' => 'rechazada', 'perdida' => ['precio', 'Otra empresa ofertó 18 % menos'], 'lineas' => [
                ['Cámara corporal con estación de carga', '40', '1650.00', '1150.00'],
                ['Software de gestión de evidencias', '1', '20000.00', '14000.00'],
            ]],
            ['nombre' => 'Cableado del nuevo pabellón de ingeniería', 'cuenta' => 'universidad', 'linea' => 'REDES', 'monto' => '230000.00', 'creada' => '2026-05-12', 'cierre' => '2026-08-31', 'etapa' => 'perdida', 'perdida' => ['presupuesto_cancelado', 'La universidad recortó la obra del pabellón para 2027']],
            ['nombre' => 'Pantallas informativas del terminal', 'cuenta' => 'ruta_andina', 'linea' => 'REDES', 'monto' => '58000.00', 'creada' => '2026-06-20', 'cierre' => '2026-09-15', 'etapa' => 'perdida', 'perdida' => ['no_respondio', 'Tres seguimientos sin respuesta']],
        ];
    }
}
