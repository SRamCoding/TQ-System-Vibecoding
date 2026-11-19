<?php

declare(strict_types=1);

/*
 * Las 21 secciones de la CIIU Rev. 4 que siembra CatalogosSeeder como «Rubros» (plan 2.20a, N3; fuente en
 * `rubros-ciiu.fuente.json`). Son los códigos del sistema del catálogo, que es cerrado: no se crean ni se borran
 * desde la pantalla, solo se edita el nombre.
 *
 * Cada fila: letra => [nombre abreviado (el que se siembra y se muestra), nombre oficial completo].
 */
return [
    'A' => ['Agricultura, ganadería, silvicultura y pesca', 'Agricultura, ganadería, silvicultura y pesca'],
    'B' => ['Minas y canteras', 'Explotación de minas y canteras'],
    'C' => ['Industrias manufactureras', 'Industrias manufactureras'],
    'D' => ['Electricidad, gas y aire acondicionado', 'Suministro de electricidad, gas, vapor y aire acondicionado'],
    'E' => ['Agua, alcantarillado y gestión de desechos', 'Suministro de agua; evacuación de aguas residuales, gestión de desechos y descontaminación'],
    'F' => ['Construcción', 'Construcción'],
    'G' => ['Comercio al por mayor y al por menor', 'Comercio al por mayor y al por menor; reparación de vehículos automotores y motocicletas'],
    'H' => ['Transporte y almacenamiento', 'Transporte y almacenamiento'],
    'I' => ['Alojamiento y servicio de comidas', 'Actividades de alojamiento y de servicio de comidas'],
    'J' => ['Información y comunicaciones', 'Información y comunicaciones'],
    'K' => ['Finanzas y seguros', 'Actividades financieras y de seguros'],
    'L' => ['Inmobiliarias', 'Actividades inmobiliarias'],
    'M' => ['Actividades profesionales, científicas y técnicas', 'Actividades profesionales, científicas y técnicas'],
    'N' => ['Servicios administrativos y de apoyo', 'Actividades de servicios administrativos y de apoyo'],
    'O' => ['Administración pública y defensa', 'Administración pública y defensa; planes de seguridad social de afiliación obligatoria'],
    'P' => ['Enseñanza', 'Enseñanza'],
    'Q' => ['Salud y asistencia social', 'Actividades de atención de la salud humana y de asistencia social'],
    'R' => ['Arte, entretenimiento y recreación', 'Actividades artísticas, de entretenimiento y recreativas'],
    'S' => ['Otras actividades de servicios', 'Otras actividades de servicios'],
    'T' => ['Hogares como empleadores', 'Actividades de los hogares como empleadores; actividades no diferenciadas de los hogares como productores de bienes y servicios para uso propio'],
    'U' => ['Organizaciones extraterritoriales', 'Actividades de organizaciones y órganos extraterritoriales'],
];
