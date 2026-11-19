<?php

declare(strict_types=1);

/*
 * Feriados nacionales del Perú que siembra FeriadosSeeder (plan 1.4d, D2), por año. Carga un año solo si todavía no tiene
 * ningún feriado: después, lo que se borre o cambie en la pantalla no vuelve. Jueves y Viernes Santo se calculan desde el Domingo de Resurrección.
 *
 * POR CONFIRMAR (docs/decisiones/pendientes.md): Contabilidad o RRHH revisan esta lista con gob.pe antes de producción
 * y dicen qué días no laborables da la empresa. Los nombres son los oficiales.
 *
 * Cada fila: [mes, día, nombre].
 */
return [
    'anios' => [2026, 2027],
    'fijos' => [
        [1, 1, 'Año Nuevo'],
        [5, 1, 'Día del Trabajo'],
        [6, 7, 'Batalla de Arica y Día de la Bandera'],
        [6, 29, 'San Pedro y San Pablo'],
        [7, 23, 'Día de la Fuerza Aérea del Perú'],
        [7, 28, 'Fiestas Patrias'],
        [7, 29, 'Fiestas Patrias'],
        [8, 6, 'Batalla de Junín'],
        [8, 30, 'Santa Rosa de Lima'],
        [10, 8, 'Combate de Angamos'],
        [11, 1, 'Día de Todos los Santos'],
        [12, 8, 'Inmaculada Concepción'],
        [12, 9, 'Batalla de Ayacucho'],
        [12, 25, 'Navidad'],
    ],
];
