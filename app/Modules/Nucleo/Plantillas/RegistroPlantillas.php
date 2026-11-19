<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Plantillas;

use App\Modules\Nucleo\Excepciones\DefinicionPlantillaInvalida;
use App\Modules\Nucleo\Excepciones\PlantillaRechazada;

/**
 * Los tipos de plantilla que registran los módulos (ADR 0022; plan 2.11), como RegistroDocumentos con las máquinas
 * de estados. Es código: la base solo guarda la clave en `plantillas.tipo`. Un tipo que rompe las reglas no se
 * registra. Los permisos de cada tipo los declara su módulo (`RegistroPermisos`), no este registro.
 */
final class RegistroPlantillas
{
    /** `<area>.<modulo>.<cosa>`: como un permiso (ADR 0021), en minúsculas y hasta 80 (`plantillas.tipo`). */
    private const CLAVE = '/^[a-z][a-z0-9_-]*(\.[a-z][a-z0-9_-]*){2,}$/';

    private const PERMISO = '/^[a-z][a-z0-9_-]*(\.[a-z][a-z0-9_-]*)+$/';

    /** @var array<string, DefinicionPlantilla> por clave */
    private array $tipos = [];

    public function registrar(DefinicionPlantilla $tipo): void
    {
        $clave = $tipo->clave();

        if (preg_match(self::CLAVE, $clave) !== 1 || strlen($clave) > 80) {
            throw DefinicionPlantillaInvalida::de($clave, 'la clave va como «<area>.<modulo>.<cosa>», en minúsculas y hasta 80 caracteres.');
        }

        if (isset($this->tipos[$clave])) {
            throw DefinicionPlantillaInvalida::de($clave, 'ya hay un tipo con esa clave.');
        }

        if (trim($tipo->nombre()) === '') {
            throw DefinicionPlantillaInvalida::de($clave, 'le falta el nombre que ven las personas.');
        }

        foreach (['usar' => $tipo->permisoUsar(), 'publicar' => $tipo->permisoPublicar()] as $para => $permiso) {
            if (preg_match(self::PERMISO, $permiso) !== 1) {
                throw DefinicionPlantillaInvalida::de($clave, "el permiso para {$para} («{$permiso}») no tiene forma de permiso.");
            }
        }

        if ($tipo->formatoActual() < 1) {
            throw DefinicionPlantillaInvalida::de($clave, 'el formato empieza en 1.');
        }

        $this->tipos[$clave] = $tipo;
    }

    public function existe(string $clave): bool
    {
        return isset($this->tipos[$clave]);
    }

    /** @throws PlantillaRechazada si el tipo no está registrado (una plantilla vieja de un módulo retirado) */
    public function obtener(string $clave): DefinicionPlantilla
    {
        return $this->tipos[$clave] ?? throw PlantillaRechazada::tipoDesconocido($clave);
    }

    /**
     * @return list<DefinicionPlantilla> en orden de registro
     */
    public function todos(): array
    {
        return array_values($this->tipos);
    }
}
