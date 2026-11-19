<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Las marcas que deciden retenciones y percepciones, con su propio permiso (D7). */
final class EditarTributariosParty extends AccionParty
{
    protected function operacion(): string
    {
        return 'editar-tributarios';
    }

    public function nombre(): string
    {
        return 'Editar datos tributarios';
    }

    public function campos(): array
    {
        return [
            $this->campoParty(),
            Campo::booleano('es_agente_retencion', 'Es agente de retención'),
            Campo::booleano('es_agente_percepcion', 'Es agente de percepción'),
            Campo::booleano('es_buen_contribuyente', 'Es buen contribuyente'),
            $this->campoVersion(),
        ];
    }

    public function permiso(): string
    {
        return 'parties.editar-tributarios';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $id = $datos['party_id'];
        $version = $datos['version'];
        unset($datos['party_id'], $datos['version']);

        $this->parties->editarTributarios($id, array_map(fn (mixed $valor): bool => (bool) $valor, $datos), $version);

        return null;
    }
}
