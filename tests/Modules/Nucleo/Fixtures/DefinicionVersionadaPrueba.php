<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use Illuminate\Database\Eloquent\Model;

/** La máquina de estados mínima del documento versionado de prueba: un borrador, numerado `DVP-AAAA-NNNN`. */
final class DefinicionVersionadaPrueba extends DefinicionDocumento
{
    public function __construct(private bool $comparten = true) {}

    public function modelo(): string
    {
        return DocumentoVersionadoPrueba::class;
    }

    public function clave(): string
    {
        return 'nucleo.prueba-versionada';
    }

    public function nombre(): string
    {
        return 'documento versionado de prueba';
    }

    public function numeracion(): FormatoNumeracion
    {
        return new FormatoNumeracion('DVP');
    }

    public function versionesCompartenNumero(): bool
    {
        return $this->comparten;
    }

    public function identificador(Model $documento): string
    {
        return $documento->getAttribute('numero').' v'.$documento->getAttribute('numero_version');
    }

    public function estados(): array
    {
        return [new EstadoDefinido('borrador', 'Borrador', TonoEstado::Neutro, esInicial: true, esEditable: true)];
    }

    public function transiciones(): array
    {
        return [];
    }
}
