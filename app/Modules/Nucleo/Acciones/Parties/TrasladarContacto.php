<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * La persona cambió de institución: cierra su vínculo aquí y abre uno nuevo en la otra party, enlazado (D1). La
 * pantalla precarga nombres, teléfono y correo, que se pueden cambiar; el cargo nuevo es obligatorio. Sin `desde`,
 * el día siguiente al cierre. Devuelve el id del vínculo nuevo.
 */
final class TrasladarContacto extends AccionContacto
{
    protected function operacion(): string
    {
        return 'trasladar-contacto';
    }

    public function nombre(): string
    {
        return 'Trasladar a otra institución';
    }

    public function campos(): array
    {
        $datos = array_map(
            fn (Campo $campo): Campo => $campo->nombre === 'cargo' ? Campo::texto('cargo', 'Cargo nuevo', maximo: 120) : $campo,
            self::camposDeDatos(),
        );

        return [
            $this->campoContacto(),
            Campo::entero('nueva_party_id', 'Nueva institución')->conReglas('exists:parties,id'),
            Campo::fecha('hasta', 'Último día aquí (si sigue abierto)')->opcional(),
            ...$datos,
            Campo::booleano('es_principal', 'Contacto principal allá')->opcional(),
            $this->campoVersion(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->contactos->trasladar(
            $datos['contacto_id'],
            $datos['nueva_party_id'],
            $datos['hasta'] ?? null,
            self::datos([...$datos, 'hasta' => null], (bool) ($datos['es_principal'] ?? false)),
            $datos['version'],
        );
    }
}
