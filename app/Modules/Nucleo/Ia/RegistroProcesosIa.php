<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Los procesos de IA que declaran los módulos desde su provider (plan 1.11, D1). Hoy ninguno: el mecanismo llega sin
 * procesos activos y cada uno se registra con su etapa.
 */
final class RegistroProcesosIa
{
    /** @var array<string, DefinicionProcesoIa> */
    private array $procesos = [];

    public function registrar(DefinicionProcesoIa $proceso): void
    {
        if (isset($this->procesos[$proceso->clave])) {
            throw new InvalidArgumentException("El proceso de IA «{$proceso->clave}» ya está registrado.");
        }

        $this->procesos[$proceso->clave] = $proceso;
    }

    public function proceso(string $clave): DefinicionProcesoIa
    {
        return $this->procesos[$clave] ?? throw new InvalidArgumentException("El proceso de IA «{$clave}» no está registrado.");
    }

    /** @return array<string, DefinicionProcesoIa> */
    public function procesos(): array
    {
        return $this->procesos;
    }

    /**
     * Que lo que el proceso declara enviar pueda salir (D2): orígenes del morph map, ningún campo sensible (ADR 0016).
     * Se revisa al sincronizar y antes de cada llamada: al registrar, el morph map puede no estar listo todavía.
     */
    public function revisar(DefinicionProcesoIa $proceso): void
    {
        foreach ($proceso->origenes as $origen) {
            if (Relation::getMorphedModel($origen) === null) {
                throw new InvalidArgumentException("El origen «{$origen}» del proceso de IA «{$proceso->clave}» no está en el morph map.");
            }
        }

        foreach ($proceso->produce as $tipo) {
            if (Relation::getMorphedModel($tipo) === null) {
                throw new InvalidArgumentException("El registro «{$tipo}» que produce el proceso de IA «{$proceso->clave}» no está en el morph map.");
            }
        }

        foreach ($proceso->camposEnviados as $origen => $campos) {
            /** @var class-string<Model> $clase */
            $clase = Relation::getMorphedModel($origen);
            $modelo = new $clase;
            $sensibles = $modelo instanceof DeclaraCamposSensibles ? array_keys($modelo->camposSensibles()) : [];

            $columnas = once(fn (): array => Schema::getColumnListing($modelo->getTable()));

            foreach ($campos as $campo) {
                if (in_array($campo, $sensibles, true) || in_array($campo, $modelo->getHidden(), true)) {
                    throw new InvalidArgumentException("El proceso de IA «{$proceso->clave}» no puede enviar «{$origen}.{$campo}»: es un campo sensible.");
                }

                // Una relación o un accessor arrastraría otro registro entero (revisión de arquitectura).
                if (! in_array($campo, $columnas, true)) {
                    throw new InvalidArgumentException("El proceso de IA «{$proceso->clave}» no puede enviar «{$origen}.{$campo}»: no es una columna del registro.");
                }
            }
        }
    }
}
