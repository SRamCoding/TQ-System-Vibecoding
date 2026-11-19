<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Una configuración de alerta que las reglas no permiten (plan 1.10, D2, D5 y D6). No se guarda nada. */
final class AlertaRechazada extends ErrorDeNegocio
{
    public static function sinRoles(): self
    {
        return new self('Toda alerta llega al menos a un rol: elige uno.');
    }

    public static function rolNoExiste(): self
    {
        return new self('Uno de los roles elegidos ya no existe: vuelve a abrir la alerta.');
    }

    public static function obligatoria(string $alerta): self
    {
        return new self("«{$alerta}» es obligatoria: no se apaga.");
    }

    public static function faltaDato(string $dato): self
    {
        return new self("Falta el dato «{$dato}»: insértalo con su botón, debajo del texto.");
    }

    /** @param list<string> $validos */
    public static function datoDesconocido(string $marcador, array $validos): self
    {
        return new self("«{{$marcador}}» no es un dato de esta alerta. Puedes usar: ".implode(', ', array_map(fn (string $d): string => "{{$d}}", $validos)).'.');
    }

    public static function sinAnticipacion(): self
    {
        return new self('Esta alerta no avisa con anticipación: deja vacíos los días.');
    }

    public static function anticipacionInvalida(): self
    {
        return new self('Los días de anticipación van como «60,30,15»: números enteros separados por comas, sin repetir.');
    }
}
