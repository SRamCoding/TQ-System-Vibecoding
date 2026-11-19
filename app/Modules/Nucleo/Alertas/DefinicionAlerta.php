<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Alertas;

use InvalidArgumentException;

/**
 * Un tipo de alerta del catálogo único (plan 1.10, D1): un aviso que el sistema manda solo, por una revisión diaria o por
 * un fallo. Lo declara su módulo en RegistroAlertas, con sus textos de fábrica y sus datos: los marcadores
 * (`{cantidad}`) que el sistema llena siempre con el valor real y que un texto editado no puede quitar (D5). Lo
 * editable (roles, textos, anticipación, si está activa) vive en `alerta_tipos`.
 */
final readonly class DefinicionAlerta
{
    /**
     * @param  array<string, string>  $datos  marcador => qué es («cantidad» => «Cantidad de cruces»)
     * @param  list<string>  $rolesPorDefecto  nombres de rol que la reciben al instalarse (D2)
     */
    public function __construct(
        /** «<modulo>.<alerta>»: también es la clave de su aviso en RegistroAvisos. */
        public string $clave,
        public string $nombre,
        /** Para qué sirve, en la pantalla de Configuración → Alertas. */
        public string $descripcion,
        /** El grupo de avisos (RegistroAvisos) con cuya preferencia de correo viaja. */
        public string $grupo,
        public string $icono,
        public string $asunto,
        public string $texto,
        public array $datos,
        public array $rolesPorDefecto,
        /** Obligatoria: no se apaga y llega por correo aunque la persona lo haya apagado (D3). */
        public bool $obligatoria = false,
        /** Solo la campana: para la del correo que no salió, que por correo también fallaría (D3). */
        public bool $soloCampana = false,
        /** Días antes del vencimiento por defecto, «60,30,15»; nulo: no avisa con anticipación (D6). */
        public ?string $anticipacion = null,
        /**
         * Plan 2.2 (D6): a quién llega siempre, por ser una persona puntual que se pasa al emitir («al responsable de la
         * partida»). Con él, la alerta puede no tener roles. Nulo: llega solo por sus roles, y necesita al menos uno.
         */
        public ?string $destinatarioPropio = null,
    ) {
        if ($rolesPorDefecto === [] && $destinatarioPropio === null) {
            throw new InvalidArgumentException("La alerta «{$clave}» necesita al menos un rol por defecto.");
        }

        foreach (array_keys($datos) as $marcador) {
            if (preg_match('/^[a-z][a-z_]{0,30}$/', $marcador) !== 1) {
                throw new InvalidArgumentException("El dato «{$marcador}» de la alerta «{$clave}» va en minúsculas y guion bajo.");
            }
        }

        $faltan = TextoAlerta::faltantes($asunto, $texto, array_keys($datos));
        $sobran = TextoAlerta::desconocidos($asunto, $texto, array_keys($datos));

        if ($faltan !== [] || $sobran !== []) {
            throw new InvalidArgumentException("Los textos de fábrica de «{$clave}» no cuadran con sus datos.");
        }

        if ($anticipacion !== null && ! TextoAlerta::anticipacionValida($anticipacion)) {
            throw new InvalidArgumentException("La anticipación de «{$clave}» va como «60,30,15».");
        }
    }

    /** Si llega siempre a una persona puntual (plan 2.2, D6): entonces sus roles son opcionales. */
    public function conDestinatarioPropio(): bool
    {
        return $this->destinatarioPropio !== null;
    }
}
