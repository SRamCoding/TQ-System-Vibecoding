<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * Lo elegido en la vista «Tarjetas» (plan 2.21d, D11): el buscador y las opciones marcadas de cada grupo. Dentro de un
 * grupo, cualquiera de las marcadas; entre grupos, todas.
 */
final readonly class FiltrosOportunidades
{
    /** Atención con dato hoy. «Sin próxima acción» espera a CRM-05 y «Buena pro cerca» a su fecha (D14). */
    public const array ATENCION = ['atascadas' => 'Atascadas (pasaron su plazo)', 'cierre-vencido' => 'Cierre estimado vencido'];

    /** Observación 7.1: privada o licitación (pública o privada por convocatoria). */
    public const array TIPOS = ['privada' => 'Privada', 'licitacion_publica' => 'Licitación pública', 'licitacion_privada' => 'Licitación privada (convocatoria)'];

    /** Observación 7.1: «Por urgencia» cuenta el próximo plazo de la licitación como un cierre. */
    public const array ORDENES = ['monto' => 'Mayor monto', 'cierre' => 'Por urgencia (cierre o plazo)', 'dias' => 'Más días en la etapa', 'recientes' => 'Más recientes'];

    /**
     * @param  list<string>  $atencion
     * @param  list<string>  $etapas  códigos de estado
     * @param  list<int>  $ejecutivos
     * @param  list<int>  $lineas  líneas de solución
     * @param  list<string>  $clientes  `privado` o `nivel:<id>` (nivel de gobierno), de la cuenta
     * @param  list<string>  $tipos  claves de TIPOS
     * @param  list<string>  $origenes  códigos del canal del lead (observación 9.3)
     */
    public function __construct(
        public string $texto = '',
        public array $atencion = [],
        public array $etapas = [],
        public array $ejecutivos = [],
        public array $lineas = [],
        public array $clientes = [],
        public string $orden = 'monto',
        public array $tipos = [],
        public array $origenes = [],
    ) {}
}
