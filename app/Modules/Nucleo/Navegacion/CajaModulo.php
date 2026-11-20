<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use InvalidArgumentException;

/**
 * Una caja del inicio, y su ítem de la barra lateral (plan 1.4f). La registra el provider de cada módulo, con las
 * preguntas que responde y la animación de su ícono (plan 1.4q).
 */
final readonly class CajaModulo
{
    public function __construct(
        /** Minúsculas, cifras y guiones: también marca la caja y su ítem de la barra (`data-modulo`) y se guarda en `modulos_ocultos`. */
        public string $clave,
        public string $nombre,
        public string $url,
        /** Obligatorio a propósito: una caja visible para todos se declara con null, no por olvido. */
        public ?string $permiso,
        /** Sección del inicio en que aparece (Núcleo, CRM o ERP), con su color. */
        public AreaModulo $area,
        public string $icono = 'heroicon-o-squares-2x2',
        /** Sin preguntas, el tooltip de la caja (plan 1.4f); con preguntas, ya no se muestra (plan 1.4q, D1). */
        public ?string $descripcion = null,
        /**
         * Las preguntas que responde el módulo, literales, en el orden en que aparecen al dejar el mouse sobre la caja
         * (plan 1.4q, D5). Sin preguntas, la caja se comporta como antes.
         *
         * @var list<string>
         */
        public array $preguntas = [],
        /** Con qué termina el ícono después de las preguntas (D6); sin una propia, el pulso (D7). */
        public AnimacionCaja $animacion = AnimacionCaja::Pulso,
        /** Solo las cajas de proceso del inicio (MV-016): la franja en que van. */
        public ?Franja $franja = null,
    ) {
        // Hasta 40: es lo que cabe en `modulos_ocultos.clave_modulo` (plan 1.4f, D2).
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $clave) !== 1) {
            throw new InvalidArgumentException("La clave de la caja [{$clave}] debe llevar solo minúsculas, cifras y guiones, hasta 40.");
        }

        foreach ($preguntas as $pregunta) {
            if (trim($pregunta) === '') {
                throw new InvalidArgumentException("La caja [{$clave}] tiene una pregunta vacía.");
            }
        }
    }

    /** La misma caja con otro nombre: el que le da su proceso (Proceso::cajas). */
    public function conNombre(?string $nombre): self
    {
        return $nombre === null ? $this : new self($this->clave, $nombre, $this->url, $this->permiso, $this->area, $this->icono, $this->descripcion, $this->preguntas, $this->animacion, $this->franja);
    }
}
