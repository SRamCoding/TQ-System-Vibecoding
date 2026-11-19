<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo\Piezas;

/**
 * Las piezas de cada tema (plan 1.4e2). Las cuatro estaciones (5 fondos, 20 motivos, 6 acompañantes) y las fechas especiales
 * (menos piezas: duran pocos días). Un tema sin piezas sigue con sus variantes de `RegistroAmbientaciones`.
 */
final class PiezasDeLaFecha
{
    public static function registrarEn(RegistroPiezasDibujo $registro): void
    {
        $tema = 'primavera';
        $vista = function (string $nombre) use (&$tema): string {
            return 'nucleo::filament.ambientacion.piezas.'.str_replace('_', '-', $tema).".{$nombre}";
        };
        $fondo = fn (string $clave, string $nombre, array $etiquetas = []): PiezaDibujo => new PiezaDibujo(TipoPiezaDibujo::Fondo, $clave, $nombre, $vista("fondo-{$clave}"), $etiquetas);
        $motivo = fn (string $clave, string $nombre, array $etiquetas, array $requiere = []): PiezaDibujo => new PiezaDibujo(TipoPiezaDibujo::Motivo, $clave, $nombre, $vista("motivo-{$clave}"), $etiquetas, $requiere);
        $acompanante = fn (string $clave, string $nombre, array $requiere = []): PiezaDibujo => new PiezaDibujo(TipoPiezaDibujo::Acompanante, $clave, $nombre, $vista("acompanante-{$clave}"), requiere: $requiere);
        // Las que sirven a varias fechas (la sala, el confeti…): un solo dibujo en `piezas/comunes`.
        $fondoComun = fn (string $clave, string $nombre): PiezaDibujo => new PiezaDibujo(TipoPiezaDibujo::Fondo, $clave, $nombre, "nucleo::filament.ambientacion.piezas.comunes.fondo-{$clave}");
        $acompananteComun = fn (string $clave, string $nombre): PiezaDibujo => new PiezaDibujo(TipoPiezaDibujo::Acompanante, $clave, $nombre, "nucleo::filament.ambientacion.piezas.comunes.acompanante-{$clave}");

        $registro->registrar('primavera',
            $fondo('colinas', 'Colinas'),
            $fondo('jardin', 'Jardín', ['cerca']),
            $fondo('campo', 'Campo'),
            $fondo('laguna', 'Laguna', ['agua']),
            $fondo('sendero', 'Sendero'),
            $motivo('flor', 'Flor', ['flor']),
            $motivo('tulipanes', 'Tulipanes', ['flor']),
            $motivo('arbol-florido', 'Árbol florido', ['arbol', 'flor']),
            $motivo('brotes', 'Brotes', ['planta']),
            $motivo('margaritas', 'Margaritas', ['flor']),
            $motivo('rosal', 'Rosal', ['flor']),
            $motivo('maceta', 'Maceta', ['flor']),
            $motivo('lirios', 'Lirios', ['flor']),
            $motivo('campanillas', 'Campanillas', ['flor']),
            $motivo('dientes-de-leon', 'Dientes de león', ['flor']),
            $motivo('cerezo', 'Cerezo', ['arbol', 'flor']),
            $motivo('nido', 'Nido', ['arbol']),
            $motivo('regadera', 'Regadera', ['planta']),
            $motivo('cesta', 'Cesta de flores', ['flor']),
            $motivo('arco-flores', 'Arco de flores', ['flor']),
            $motivo('colmena', 'Colmena', ['flor', 'colmena']),
            $motivo('girasoles', 'Girasoles', ['flor']),
            $motivo('lavanda', 'Lavanda', ['flor']),
            $motivo('fresas', 'Fresas', ['planta']),
            $motivo('hortensias', 'Hortensias', ['flor']),
            $acompanante('abeja', 'Abeja', ['flor']),
            $acompanante('mariposa', 'Mariposa'),
            $acompanante('pajaros', 'Pájaros'),
            $acompanante('mariquita', 'Mariquita', ['flor']),
            $acompanante('colibri', 'Colibrí', ['flor']),
            $acompanante('caracol', 'Caracol'),
        );

        // Verano (diciembre a marzo): la costa norte.
        $tema = 'verano';
        $registro->registrar('verano',
            $fondo('playa', 'Playa', ['arena', 'mar', 'agua']),
            $fondo('dunas', 'Dunas', ['arena']),
            $fondo('piscina', 'Piscina', ['agua', 'piscina']),
            $fondo('malecon', 'Malecón', ['mar', 'agua']),
            $fondo('canaveral', 'Cañaveral', ['campo']),
            $motivo('sombrilla', 'Sombrilla', ['playa']),
            $motivo('castillo-arena', 'Castillo de arena', ['playa'], ['arena']),
            $motivo('helado', 'Helado', ['comida']),
            $motivo('sandia', 'Sandía', ['comida']),
            $motivo('palmera', 'Palmera', ['arbol']),
            $motivo('tabla-surf', 'Tabla de surf', ['playa'], ['arena', 'mar']),
            $motivo('balde-pala', 'Balde y pala', ['playa'], ['arena']),
            $motivo('caballito-totora', 'Caballito de totora', ['mar'], ['mar']),
            $motivo('lentes', 'Lentes de sol', ['playa']),
            $motivo('sombrero', 'Sombrero de paja', ['playa']),
            $motivo('limonada', 'Limonada', ['comida']),
            $motivo('mango', 'Mangos', ['comida']),
            $motivo('barquito', 'Barquito de vela', ['mar'], ['mar']),
            $motivo('pelota', 'Pelota de playa', ['playa']),
            $motivo('flotador', 'Flotador', ['agua'], ['piscina']),
            $motivo('cometa', 'Cometa', ['aire']),
            $motivo('sandalias', 'Sandalias', ['playa'], ['arena']),
            $motivo('conchas', 'Conchas', ['playa'], ['arena']),
            $motivo('raspadilla', 'Raspadilla', ['comida']),
            $motivo('coco', 'Coco', ['comida']),
            $acompanante('gaviotas', 'Gaviotas', ['mar']),
            $acompanante('pelicano', 'Pelícano', ['mar']),
            $acompanante('cangrejo', 'Cangrejo', ['arena']),
            $acompanante('lagartija', 'Lagartija', ['arena']),
            $acompanante('libelula', 'Libélula'),
            $acompanante('mariposa', 'Mariposa'),
        );

        // Otoño (marzo a junio): hojas, cosecha y viento. Sin calabazas (se asocian a Halloween).
        $tema = 'otono';
        $registro->registrar('otono',
            $fondo('bosque', 'Bosque', ['arbol']),
            $fondo('parque', 'Parque', ['parque']),
            $fondo('colinas', 'Colinas doradas'),
            $fondo('huerto', 'Huerto', ['campo']),
            $fondo('camino-hojas', 'Camino de hojas'),
            $motivo('arbol-dorado', 'Árbol dorado', ['arbol']),
            $motivo('arbol-rojo', 'Árbol rojizo', ['arbol']),
            $motivo('monton-hojas', 'Montón de hojas', ['hojas']),
            $motivo('bellotas', 'Bellotas', ['arbol']),
            $motivo('hongos', 'Hongos', ['planta']),
            $motivo('paraguas', 'Paraguas', ['lluvia']),
            $motivo('botas', 'Botas de lluvia', ['lluvia']),
            $motivo('bufanda', 'Bufanda', ['abrigo']),
            $motivo('te', 'Taza de té', ['bebida']),
            $motivo('manzanas', 'Manzanas', ['fruta']),
            $motivo('uvas', 'Uvas', ['fruta']),
            $motivo('granadas', 'Granadas', ['fruta']),
            $motivo('libro', 'Libro', ['lectura']),
            $motivo('banca', 'Banca del parque', ['parque']),
            $motivo('farol', 'Farol', ['parque']),
            $motivo('rastrillo', 'Rastrillo', ['hojas']),
            $motivo('canasta-frutas', 'Canasta de frutas', ['fruta']),
            $motivo('remolino-hojas', 'Remolino de hojas', ['hojas']),
            $motivo('molinillo', 'Molinillo de viento', ['viento']),
            $motivo('choclos', 'Choclos', ['cosecha']),
            $acompanante('hojas-viento', 'Hojas al viento'),
            $acompanante('golondrinas', 'Golondrinas'),
            $acompanante('buho', 'Búho', ['arbol']),
            $acompanante('gorrion', 'Gorrión'),
            $acompanante('oruga', 'Oruga'),
            $acompanante('gato', 'Gato', ['parque']),
        );

        // Invierno (junio a setiembre): el de la costa, garúa y neblina, con un toque de sierra.
        $tema = 'invierno';
        $registro->registrar('invierno',
            $fondo('colinas-neblina', 'Colinas con neblina'),
            $fondo('ciudad', 'Ciudad'),
            $fondo('playa-invierno', 'Playa de invierno', ['mar']),
            $fondo('plaza', 'Plaza'),
            $fondo('sierra', 'Sierra', ['sierra']),
            $motivo('chullo', 'Chullo', ['abrigo']),
            $motivo('chocolate', 'Chocolate caliente', ['bebida']),
            $motivo('emoliente', 'Emoliente', ['bebida']),
            $motivo('guantes', 'Guantes', ['abrigo']),
            $motivo('poncho', 'Poncho', ['abrigo']),
            $motivo('frazada', 'Frazada', ['abrigo']),
            $motivo('tetera', 'Tetera', ['bebida']),
            $motivo('sopa', 'Sopa', ['comida']),
            $motivo('impermeable', 'Impermeable', ['lluvia']),
            $motivo('lampara', 'Lámpara', ['casa']),
            $motivo('tejido', 'Tejido', ['casa']),
            $motivo('arbol-sin-hojas', 'Árbol sin hojas', ['arbol']),
            $motivo('pino', 'Pino', ['arbol']),
            $motivo('ventana', 'Ventana empañada', ['casa']),
            $motivo('bolsa-agua-caliente', 'Bolsa de agua caliente', ['casa']),
            $motivo('charco', 'Charco', ['lluvia']),
            $motivo('chompa', 'Chompa', ['abrigo']),
            $motivo('termo', 'Termo', ['bebida']),
            $motivo('cafe', 'Café', ['bebida']),
            $motivo('medias', 'Medias de lana', ['abrigo']),
            $acompanante('gato-ovillo', 'Gato con ovillo'),
            $acompanante('perro-chompa', 'Perro con chompa'),
            $acompanante('palomas', 'Palomas'),
            $acompanante('pinguino', 'Pingüino de Humboldt', ['mar']),
            $acompanante('llama', 'Llama', ['sierra']),
            $acompanante('caracol', 'Caracol'),
        );

        // Fechas especiales: menos piezas que una estación (duran pocos días). Las religiosas, en silueta y sin rostros.
        $tema = 'navidad';
        $registro->registrar('navidad',
            $fondo('pueblo', 'Pueblito de Belén', ['exterior']),
            $fondoComun('sala', 'Sala'),
            $motivo('nacimiento', 'Nacimiento', ['fe']),
            $motivo('estrella-belen', 'Estrella de Belén', ['fe'], ['exterior']),
            $motivo('arbol-navidad', 'Árbol de Navidad', ['adorno']),
            $motivo('campanas', 'Campanas', ['adorno']),
            $motivo('corona-adviento', 'Corona de Adviento', ['fe']),
            $motivo('regalos', 'Regalos', ['adorno']),
            $motivo('paneton', 'Panetón con chocolate', ['comida']),
            $motivo('angel', 'Ángel', ['fe']),
            $acompanante('pastores', 'Pastores', ['exterior']),
            $acompanante('reyes-magos', 'Reyes Magos', ['exterior']),
            $acompanante('palomas', 'Palomas'),
            $acompananteComun('estrellitas', 'Estrellitas'),
        );

        // Semana Santa: sobria; sin confeti ni fiesta.
        $tema = 'semana_santa';
        $registro->registrar('semana_santa',
            $fondo('colinas-jerusalen', 'Colinas de Jerusalén'),
            $fondo('huerto-olivos', 'Huerto de olivos'),
            $motivo('cruz-lirios', 'Cruz con lirios', ['fe']),
            $motivo('palmas', 'Palmas de Ramos', ['fe']),
            $motivo('biblia', 'Biblia abierta', ['fe']),
            $motivo('sepulcro-vacio', 'Sepulcro vacío', ['fe']),
            $motivo('amanecer', 'Amanecer de la Resurrección', ['fe']),
            $acompanante('paloma', 'Paloma'),
        );

        $tema = 'anio_nuevo';
        $registro->registrar('anio_nuevo',
            $fondo('ciudad-fiesta', 'Ciudad en fiesta'),
            $fondoComun('sala', 'Sala'),
            $motivo('fuegos', 'Fuegos artificiales', ['fiesta']),
            $motivo('reloj', 'Medianoche', ['tiempo']),
            $motivo('calendario', 'Calendario nuevo', ['tiempo']),
            $motivo('copas', 'Brindis', ['fiesta']),
            $motivo('uvas', 'Las doce uvas', ['comida']),
            $acompananteComun('confeti', 'Confeti'),
            $acompananteComun('serpentinas', 'Serpentinas'),
            $acompananteComun('estrellitas', 'Estrellitas'),
        );

        $tema = 'fiestas_patrias';
        $registro->registrar('fiestas_patrias',
            $fondo('andes', 'Los Andes'),
            $fondo('banderines-patrios', 'Plaza con banderines'),
            $motivo('bandera', 'Bandera del Perú', ['patria']),
            $motivo('escarapela', 'Escarapela', ['patria']),
            $motivo('cantuta', 'Cantuta', ['flor']),
            $motivo('vicuna', 'Vicuña', ['animal']),
            $motivo('gallito-rocas', 'Gallito de las rocas', ['animal']),
            $motivo('sol-radiante', 'Sol radiante', ['patria']),
            $acompananteComun('serpentinas', 'Serpentinas'),
            $acompananteComun('estrellitas', 'Estrellitas'),
        );

        $tema = 'dia_madre';
        $registro->registrar('dia_madre',
            $fondo('jardin-flores', 'Jardín con flores'),
            $fondoComun('sala', 'Sala'),
            $motivo('ramo', 'Ramo de flores', ['flor']),
            $motivo('tarjeta', 'Tarjeta', ['carino']),
            $motivo('corazon', 'Corazón', ['carino']),
            $motivo('tulipanes', 'Tulipanes', ['flor']),
            $acompananteComun('corazoncitos', 'Corazoncitos'),
            $acompananteComun('estrellitas', 'Estrellitas'),
        );

        $tema = 'dia_padre';
        $registro->registrar('dia_padre',
            $fondo('taller', 'Taller'),
            $fondoComun('sala', 'Sala'),
            $motivo('corbata', 'Corbata', ['regalo']),
            $motivo('herramientas', 'Herramientas', ['taller']),
            $motivo('taza', 'Taza de café', ['regalo']),
            $motivo('medalla', 'Medalla', ['regalo']),
            $acompananteComun('estrellitas', 'Estrellitas'),
            $acompananteComun('confeti', 'Confeti'),
        );

        $tema = 'cumpleanos';
        $registro->registrar('cumpleanos',
            $fondoComun('sala', 'Sala'),
            $fondoComun('banderines', 'Patio con banderines'),
            $motivo('torta', 'Torta', ['fiesta']),
            $motivo('globos', 'Globos', ['fiesta']),
            $motivo('regalos', 'Regalos', ['fiesta']),
            $acompananteComun('confeti', 'Confeti'),
            $acompananteComun('serpentinas', 'Serpentinas'),
            $acompananteComun('estrellitas', 'Estrellitas'),
        );

        $tema = 'aniversario';
        $registro->registrar('aniversario',
            $fondo('oficina', 'Oficina'),
            $fondoComun('banderines', 'Patio con banderines'),
            $motivo('logo', 'La T de Techquk', ['empresa']),
            $motivo('trofeo', 'Trofeo', ['logro']),
            $motivo('engranajes', 'Engranajes', ['empresa']),
            $motivo('cohete', 'Cohete', ['logro']),
            $acompananteComun('confeti', 'Confeti'),
            $acompananteComun('estrellitas', 'Estrellitas'),
            $acompananteComun('serpentinas', 'Serpentinas'),
        );
    }
}
