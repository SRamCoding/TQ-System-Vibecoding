<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

/**
 * Lo elegido en la portada de proyectos (plan 2.61, D4): el texto del buscador y las opciones marcadas de cada grupo.
 * Dentro de un grupo, cualquiera de las marcadas; entre grupos, todas.
 */
final readonly class FiltrosPortada
{
    public const array ORDENES = ['recientes' => 'Más recientes', 'fin' => 'Fin más cercano', 'nombre' => 'Por nombre'];

    /**
     * @param  list<string>  $estados  códigos de estado
     * @param  list<int>  $jefes
     * @param  list<string>  $salud  claves de `RegistroFiltrosSalud`
     */
    public function __construct(
        public string $texto = '',
        public array $estados = [],
        public array $jefes = [],
        public array $salud = [],
        public bool $mios = false,
        public string $orden = 'recientes',
    ) {}
}
