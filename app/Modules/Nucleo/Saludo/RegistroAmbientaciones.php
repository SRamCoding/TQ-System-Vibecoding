<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

/**
 * Las variantes de cada fecha y temporada (plan 1.4b, D8). Cada año toca otra: el índice es el año más un desfase
 * propio del tema, para que no cambien todas al mismo ritmo. Es la misma para todos ese año. Agregar una variante es
 * agregar su vista en `resources/views/filament/ambientacion/` y una línea aquí. Hoy hay cinco por tema: cada una vuelve
 * cada cinco años.
 */
final class RegistroAmbientaciones
{
    /** @var array<string, list<VarianteAmbientacion>> */
    private array $variantes = [];

    public function __construct()
    {
        // primavera
        $this->registrar(new VarianteAmbientacion('primavera', 'flor', 'Flor que se abre', ['#F2A7C3', '#F6D365', '#8CC084'], 'nucleo::filament.ambientacion.primavera-flor'));
        $this->registrar(new VarianteAmbientacion('primavera', 'brotes', 'Brotes', ['#C9E4A6', '#8CC084', '#5E9E57'], 'nucleo::filament.ambientacion.primavera-brotes'));
        $this->registrar(new VarianteAmbientacion('primavera', 'mariposa', 'Mariposa', ['#B9A7E8', '#F2C14E', '#A6DAE5'], 'nucleo::filament.ambientacion.primavera-mariposa'));
        $this->registrar(new VarianteAmbientacion('primavera', 'lluvia', 'Lluvia de primavera', ['#A6DAE5', '#8CC084', '#F2A7C3'], 'nucleo::filament.ambientacion.primavera-lluvia'));
        $this->registrar(new VarianteAmbientacion('primavera', 'abeja', 'Abeja', ['#F2C14E', '#F2A7C3', '#8CC084'], 'nucleo::filament.ambientacion.primavera-abeja'));

        // verano
        $this->registrar(new VarianteAmbientacion('verano', 'sol', 'Sol', ['#F6C343', '#F2A541', '#E8414F'], 'nucleo::filament.ambientacion.verano-sol'));
        $this->registrar(new VarianteAmbientacion('verano', 'olas', 'Olas', ['#4FA8C8', '#A6DAE5', '#F6C343'], 'nucleo::filament.ambientacion.verano-olas'));
        $this->registrar(new VarianteAmbientacion('verano', 'sombrilla', 'Sombrilla', ['#E8414F', '#F2D8A0', '#4FA8C8'], 'nucleo::filament.ambientacion.verano-sombrilla'));
        $this->registrar(new VarianteAmbientacion('verano', 'helado', 'Helado', ['#F2A7C3', '#A6DAE5', '#E0A96D'], 'nucleo::filament.ambientacion.verano-helado'));
        $this->registrar(new VarianteAmbientacion('verano', 'lentes', 'Lentes de sol', ['#F6C343', '#3D4772', '#A6DAE5'], 'nucleo::filament.ambientacion.verano-lentes'));

        // otono
        $this->registrar(new VarianteAmbientacion('otono', 'hojas', 'Hojas que caen', ['#D9861F', '#C0392B', '#E0A96D'], 'nucleo::filament.ambientacion.otono-hojas'));
        $this->registrar(new VarianteAmbientacion('otono', 'arbol', 'Árbol de otoño', ['#E0A96D', '#D9861F', '#8B5E3C'], 'nucleo::filament.ambientacion.otono-arbol'));
        $this->registrar(new VarianteAmbientacion('otono', 'paraguas', 'Paraguas', ['#D9861F', '#6FB7C9', '#3D4772'], 'nucleo::filament.ambientacion.otono-paraguas'));
        $this->registrar(new VarianteAmbientacion('otono', 'viento', 'Viento', ['#A6B4C8', '#D9861F', '#E0A96D'], 'nucleo::filament.ambientacion.otono-viento'));
        $this->registrar(new VarianteAmbientacion('otono', 'bellota', 'Bellota', ['#8B5E3C', '#C48A4E', '#E0A96D'], 'nucleo::filament.ambientacion.otono-bellota'));

        // invierno
        $this->registrar(new VarianteAmbientacion('invierno', 'garua', 'Garúa', ['#A6B4C8', '#C9D3E0', '#6FB7C9'], 'nucleo::filament.ambientacion.invierno-garua'));
        $this->registrar(new VarianteAmbientacion('invierno', 'chullo', 'Chullo', ['#C0392B', '#F2C14E', '#3D4772'], 'nucleo::filament.ambientacion.invierno-chullo'));
        $this->registrar(new VarianteAmbientacion('invierno', 'chocolate', 'Chocolate caliente', ['#CF92BB', '#6B4528', '#C9D3E0'], 'nucleo::filament.ambientacion.invierno-chocolate'));
        $this->registrar(new VarianteAmbientacion('invierno', 'copo', 'Copo de nieve', ['#6FB7C9', '#A6DAE5', '#C9D3E0'], 'nucleo::filament.ambientacion.invierno-copo'));
        $this->registrar(new VarianteAmbientacion('invierno', 'nevados', 'Nevados', ['#7F8C9D', '#A6B4C8', '#C9D3E0'], 'nucleo::filament.ambientacion.invierno-montanas'));

        // anio_nuevo
        $this->registrar(new VarianteAmbientacion('anio_nuevo', 'fuegos', 'Fuegos artificiales', ['#F2C14E', '#E06C9F', '#A6DAE5'], 'nucleo::filament.ambientacion.anio-nuevo-fuegos'));
        $this->registrar(new VarianteAmbientacion('anio_nuevo', 'reloj', 'Medianoche', ['#3D4772', '#D9A83A', '#F2C14E'], 'nucleo::filament.ambientacion.anio-nuevo-reloj'));
        $this->registrar(new VarianteAmbientacion('anio_nuevo', 'copas', 'Brindis', ['#F6D365', '#D9A83A', '#E06C9F'], 'nucleo::filament.ambientacion.anio-nuevo-copas'));
        $this->registrar(new VarianteAmbientacion('anio_nuevo', 'calendario', 'Calendario nuevo', ['#C0392B', '#3D4772', '#F6F0F0'], 'nucleo::filament.ambientacion.anio-nuevo-calendario'));
        $this->registrar(new VarianteAmbientacion('anio_nuevo', 'destellos', 'Destellos', ['#3D4772', '#F2C14E', '#F6F0F0'], 'nucleo::filament.ambientacion.anio-nuevo-destellos'));

        // semana_santa
        $this->registrar(new VarianteAmbientacion('semana_santa', 'cruz', 'Cruz y luz', ['#7B5EA7', '#F2C14E', '#F6F0F0'], 'nucleo::filament.ambientacion.semana-santa-cruz'));
        $this->registrar(new VarianteAmbientacion('semana_santa', 'ramos', 'Domingo de Ramos', ['#5E9E57', '#8CC084', '#7B5EA7'], 'nucleo::filament.ambientacion.semana-santa-ramos'));
        $this->registrar(new VarianteAmbientacion('semana_santa', 'sepulcro', 'Sepulcro vacío', ['#9C9388', '#F6D365', '#8CC084'], 'nucleo::filament.ambientacion.semana-santa-sepulcro'));
        $this->registrar(new VarianteAmbientacion('semana_santa', 'amanecer', 'Amanecer de Pascua', ['#F6D365', '#F2C14E', '#8CC084'], 'nucleo::filament.ambientacion.semana-santa-amanecer'));
        $this->registrar(new VarianteAmbientacion('semana_santa', 'lirio', 'Lirio de Pascua', ['#F6F0F0', '#D8CFE8', '#F2C14E'], 'nucleo::filament.ambientacion.semana-santa-lirio'));

        // dia_madre
        $this->registrar(new VarianteAmbientacion('dia_madre', 'corazon', 'Corazón', ['#E06C9F', '#F2A7C3', '#F6F0F0'], 'nucleo::filament.ambientacion.dia-madre-corazon'));
        $this->registrar(new VarianteAmbientacion('dia_madre', 'ramo', 'Ramo de flores', ['#E06C9F', '#B9A7E8', '#F2C14E'], 'nucleo::filament.ambientacion.dia-madre-ramo'));
        $this->registrar(new VarianteAmbientacion('dia_madre', 'tulipanes', 'Tulipanes', ['#E06C9F', '#F2A7C3', '#C0392B'], 'nucleo::filament.ambientacion.dia-madre-tulipanes'));
        $this->registrar(new VarianteAmbientacion('dia_madre', 'carta', 'Carta', ['#B9A7E8', '#E06C9F', '#ECE6F5'], 'nucleo::filament.ambientacion.dia-madre-carta'));
        $this->registrar(new VarianteAmbientacion('dia_madre', 'rosa', 'Rosa', ['#C0392B', '#8CC084', '#F2A7C3'], 'nucleo::filament.ambientacion.dia-madre-rosa'));

        // dia_padre
        $this->registrar(new VarianteAmbientacion('dia_padre', 'corbata', 'Corbata', ['#2B5D8C', '#3A78B5', '#A6B4C8'], 'nucleo::filament.ambientacion.dia-padre-corbata'));
        $this->registrar(new VarianteAmbientacion('dia_padre', 'taza', 'Café', ['#3A78B5', '#8B5E3C', '#A6B4C8'], 'nucleo::filament.ambientacion.dia-padre-taza'));
        $this->registrar(new VarianteAmbientacion('dia_padre', 'herramientas', 'Herramientas', ['#7F8C9D', '#3A78B5', '#8B5E3C'], 'nucleo::filament.ambientacion.dia-padre-herramientas'));
        $this->registrar(new VarianteAmbientacion('dia_padre', 'medalla', 'Medalla', ['#3A78B5', '#F2C14E', '#D9A83A'], 'nucleo::filament.ambientacion.dia-padre-medalla'));
        $this->registrar(new VarianteAmbientacion('dia_padre', 'trofeo', 'Trofeo', ['#F2C14E', '#3A78B5', '#D9A83A'], 'nucleo::filament.ambientacion.dia-padre-trofeo'));

        // fiestas_patrias
        $this->registrar(new VarianteAmbientacion('fiestas_patrias', 'bandera', 'Bandera del Perú', ['#D91023', '#FFFFFF', '#D91023'], 'nucleo::filament.ambientacion.fiestas-patrias-bandera'));
        $this->registrar(new VarianteAmbientacion('fiestas_patrias', 'escarapela', 'Escarapela', ['#D91023', '#FFFFFF', '#D91023'], 'nucleo::filament.ambientacion.fiestas-patrias-escarapela'));
        $this->registrar(new VarianteAmbientacion('fiestas_patrias', 'andes', 'Andes', ['#5E9E57', '#D91023', '#F2C14E'], 'nucleo::filament.ambientacion.fiestas-patrias-montanas'));
        $this->registrar(new VarianteAmbientacion('fiestas_patrias', 'cantuta', 'Cantuta', ['#D91023', '#E8414F', '#5E9E57'], 'nucleo::filament.ambientacion.fiestas-patrias-cantuta'));
        $this->registrar(new VarianteAmbientacion('fiestas_patrias', 'fuegos', 'Fuegos rojiblancos', ['#D91023', '#FFFFFF', '#3D4772'], 'nucleo::filament.ambientacion.fiestas-patrias-fuegos'));

        // navidad
        $this->registrar(new VarianteAmbientacion('navidad', 'estrella', 'Estrella y luces', ['#C0392B', '#F2C14E', '#2E7D4F'], 'nucleo::filament.ambientacion.navidad-estrella'));
        $this->registrar(new VarianteAmbientacion('navidad', 'arbol', 'Árbol de Navidad', ['#2E7D4F', '#C0392B', '#F2C14E'], 'nucleo::filament.ambientacion.navidad-arbol'));
        $this->registrar(new VarianteAmbientacion('navidad', 'pesebre', 'Pesebre', ['#3D4772', '#F2C14E', '#B08968'], 'nucleo::filament.ambientacion.navidad-pesebre'));
        $this->registrar(new VarianteAmbientacion('navidad', 'campana', 'Campana', ['#F2C14E', '#C0392B', '#2E7D4F'], 'nucleo::filament.ambientacion.navidad-campana'));
        $this->registrar(new VarianteAmbientacion('navidad', 'regalo', 'Regalo', ['#C0392B', '#2E7D4F', '#F2C14E'], 'nucleo::filament.ambientacion.navidad-regalo'));

        // cumpleanos
        $this->registrar(new VarianteAmbientacion('cumpleanos', 'torta', 'Torta', ['#F2A7C3', '#E06C9F', '#F2C14E'], 'nucleo::filament.ambientacion.cumpleanos-pastel'));
        $this->registrar(new VarianteAmbientacion('cumpleanos', 'globos', 'Globos', ['#E06C9F', '#A6DAE5', '#F2C14E'], 'nucleo::filament.ambientacion.cumpleanos-globos'));
        $this->registrar(new VarianteAmbientacion('cumpleanos', 'confeti', 'Confeti', ['#B9A7E8', '#E06C9F', '#F2C14E'], 'nucleo::filament.ambientacion.cumpleanos-confeti'));
        $this->registrar(new VarianteAmbientacion('cumpleanos', 'regalo', 'Regalo', ['#A6DAE5', '#E06C9F', '#B9A7E8'], 'nucleo::filament.ambientacion.cumpleanos-regalo'));
        $this->registrar(new VarianteAmbientacion('cumpleanos', 'gorro', 'Gorro de fiesta', ['#B9A7E8', '#F2C14E', '#E06C9F'], 'nucleo::filament.ambientacion.cumpleanos-gorro'));

        // aniversario
        $this->registrar(new VarianteAmbientacion('aniversario', 'copa', 'Copa', ['#3D4772', '#A6DAE5', '#F6F0F0'], 'nucleo::filament.ambientacion.aniversario-trofeo'));
        $this->registrar(new VarianteAmbientacion('aniversario', 'insignia', 'Insignia', ['#3D4772', '#F2C14E', '#CF92BB'], 'nucleo::filament.ambientacion.aniversario-insignia'));
        $this->registrar(new VarianteAmbientacion('aniversario', 'engranajes', 'Engranajes', ['#3D4772', '#A6DAE5', '#F6F0F0'], 'nucleo::filament.ambientacion.aniversario-engranajes'));
        $this->registrar(new VarianteAmbientacion('aniversario', 'circuito', 'Circuito', ['#A6DAE5', '#3D4772', '#CF92BB'], 'nucleo::filament.ambientacion.aniversario-circuito'));
        $this->registrar(new VarianteAmbientacion('aniversario', 'cohete', 'Cohete', ['#CF92BB', '#3D4772', '#F2C14E'], 'nucleo::filament.ambientacion.aniversario-cohete'));
    }

    public function registrar(VarianteAmbientacion $variante): void
    {
        $this->variantes[$variante->tema][] = $variante;
    }

    /**
     * @return list<VarianteAmbientacion>
     */
    public function de(string $tema): array
    {
        return $this->variantes[$tema] ?? [];
    }

    /** @return list<string> los temas con variantes, en el orden en que se registraron */
    public function temas(): array
    {
        return array_keys($this->variantes);
    }

    /** La variante de ese tema con esa clave, o nula si no existe. */
    public function buscar(string $tema, string $clave): ?VarianteAmbientacion
    {
        foreach ($this->de($tema) as $variante) {
            if ($variante->clave === $clave) {
                return $variante;
            }
        }

        return null;
    }

    /** La que toca ese año, o nula si el tema todavía no tiene variantes. */
    public function delAnio(string $tema, int $anio): ?VarianteAmbientacion
    {
        $variantes = $this->de($tema);
        $cantidad = count($variantes);

        if ($cantidad === 0) {
            return null;
        }

        return $variantes[($anio + crc32($tema)) % $cantidad];
    }
}
