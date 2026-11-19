<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

/**
 * Una transición en la definición de un documento (plan 1.5, D1, D5, D7 y D9). Con varios orígenes se guarda una
 * fila por origen, con el mismo código y la misma lista de motivos. Las del sistema no tienen permiso y solo las
 * pide código del módulo; las que requieren aprobación las ejecuta el mecanismo de aprobaciones (1.7). Las de persona
 * tienen una AccionTransicion registrada sola, salvo las de acción propia: necesitan más datos (registrar una adenda)
 * y su módulo registra una acción que llama al servicio de transiciones.
 */
final readonly class TransicionDefinida
{
    /** @var list<string> */
    public array $desde;

    /**
     * @param  string|list<string>  $desde
     * @param  list<string>  $efectos  claves de RegistroDocumentos, en el orden en que se aplican
     * @param  list<class-string<CondicionTransicion>>  $condiciones  se revisan en este orden, después del permiso
     */
    public function __construct(
        public string $codigo,
        public string $nombre,
        string|array $desde,
        public string $hacia,
        public bool $requiereMotivo = false,
        public bool $requiereAprobacion = false,
        public bool $esDelSistema = false,
        public array $efectos = [],
        public array $condiciones = [],
        public bool $accionPropia = false,
    ) {
        $this->desde = is_string($desde) ? [$desde] : $desde;
    }
}
