<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Acciones\Estados\AccionTransicion;
use App\Modules\Nucleo\Excepciones\DefinicionDocumentoInvalida;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;

/**
 * Las máquinas de estados que declaran los módulos, los efectos que pueden usar, las revisiones antes de anular que
 * un módulo pone a un documento ajeno y las listas de motivos que no son de una transición (plan 1.5, D1, D3, D4 y
 * D9b). Es código, como RegistroAcciones: el sincronizador lo copia a
 * la base. Una definición que rompe las reglas del núcleo no se registra.
 */
final class RegistroDocumentos
{
    private const CODIGO = '/^[a-z][a-z0-9_]*$/';

    /** @var array<string, DefinicionDocumento> por clave */
    private array $definiciones = [];

    /** @var list<class-string<FuenteDeMotivos>> */
    private array $fuentes = [];

    public function __construct(private readonly RegistroAcciones $acciones) {}

    /** @var array<string, class-string<Efecto|EfectoExterno>> */
    private array $efectos = [];

    /** @var array<string, list<class-string<RevisionAntesDeAnular>>> por clave del documento */
    private array $revisiones = [];

    /** @var array<string, string> código => nombre */
    private array $familias = [];

    /** @var array<string, list<MotivoInicial>> por código de familia */
    private array $motivosIniciales = [];

    public function registrar(DefinicionDocumento $definicion): void
    {
        if (isset($this->definiciones[$definicion->clave()])) {
            throw DefinicionDocumentoInvalida::de($definicion->clave(), 'ya hay una definición con esa clave.');
        }

        $this->validar($definicion);
        $this->definiciones[$definicion->clave()] = $definicion;

        // Cada transición de persona se puede ejecutar sin la interfaz (regla 7, D9).
        foreach ($definicion->transiciones() as $transicion) {
            if ($definicion->permiso($transicion) !== null && ! $transicion->accionPropia) {
                $this->acciones->registrar(new AccionTransicion($definicion, $transicion));
            }
        }
    }

    /**
     * @param  class-string<Efecto|EfectoExterno>  $clase
     */
    public function efecto(string $clave, string $clase): void
    {
        if (isset($this->efectos[$clave]) && $this->efectos[$clave] !== $clase) {
            throw DefinicionDocumentoInvalida::de($clave, 'ya hay otro efecto con esa clave.');
        }

        if (! is_subclass_of($clase, Efecto::class) && ! is_subclass_of($clase, EfectoExterno::class)) {
            throw DefinicionDocumentoInvalida::de($clave, "{$clase} no implementa Efecto ni EfectoExterno.");
        }

        $this->efectos[$clave] = $clase;
    }

    /**
     * Un módulo impide anular un documento de otro mientras haya uno suyo vivo que dependa de él (D4): Almacén
     * registra la suya para `compras.orden` sin que Compras conozca Almacén. La clave se revisa al sincronizar.
     *
     * @param  class-string<RevisionAntesDeAnular>  $clase
     */
    public function revisionAntesDeAnular(string $claveDocumento, string $clase): void
    {
        if (! is_subclass_of($clase, RevisionAntesDeAnular::class)) {
            throw DefinicionDocumentoInvalida::de($claveDocumento, "{$clase} no implementa RevisionAntesDeAnular.");
        }

        if (! in_array($clase, $this->revisiones[$claveDocumento] ?? [], true)) {
            $this->revisiones[$claveDocumento][] = $clase;
        }
    }

    /**
     * Un módulo que guarda motivos en sus tablas suma sus «Otro» a la consulta de MotivosService (D10, agregado 2).
     *
     * @param  class-string<FuenteDeMotivos>  $clase
     */
    public function fuenteDeMotivos(string $clase): void
    {
        if (! is_subclass_of($clase, FuenteDeMotivos::class)) {
            throw DefinicionDocumentoInvalida::de($clase, 'no implementa FuenteDeMotivos.');
        }

        if (! in_array($clase, $this->fuentes, true)) {
            $this->fuentes[] = $clase;
        }
    }

    /**
     * @return list<class-string<FuenteDeMotivos>>
     */
    public function fuentesDeMotivos(): array
    {
        return $this->fuentes;
    }

    /**
     * Lista de motivos que no sale de una transición (la pérdida de una oportunidad, D9b), con los motivos con que nace
     * (plan 2.6, D3).
     *
     * @param  list<MotivoInicial>  $iniciales
     */
    public function familiaDeMotivos(string $codigo, string $nombre, array $iniciales = []): void
    {
        $this->familias[$codigo] = $nombre;
        $this->motivosIniciales[$codigo] = $iniciales;
    }

    /** @return list<MotivoInicial> */
    public function motivosIniciales(string $familia): array
    {
        return $this->motivosIniciales[$familia] ?? [];
    }

    /**
     * @return array<string, DefinicionDocumento>
     */
    public function definiciones(): array
    {
        return $this->definiciones;
    }

    /** La definición del documento por su alias del morph map. */
    public function definicionDe(string $tipo): ?DefinicionDocumento
    {
        foreach ($this->definiciones as $definicion) {
            if ($definicion->tipo() === $tipo) {
                return $definicion;
            }
        }

        return null;
    }

    /**
     * @return class-string<Efecto|EfectoExterno>|null
     */
    public function claseDeEfecto(string $clave): ?string
    {
        return $this->efectos[$clave] ?? null;
    }

    /**
     * Las de la definición y las que registraron otros módulos.
     *
     * @return list<class-string<RevisionAntesDeAnular>>
     */
    public function revisionesAntesDeAnular(DefinicionDocumento $definicion): array
    {
        return array_values(array_unique([...$definicion->revisionesAntesDeAnular(), ...$this->revisiones[$definicion->clave()] ?? []]));
    }

    /**
     * Claves de documento que recibieron una revisión de otro módulo.
     *
     * @return list<string>
     */
    public function documentosConRevisiones(): array
    {
        return array_keys($this->revisiones);
    }

    /**
     * @return array<string, string>
     */
    public function familias(): array
    {
        return $this->familias;
    }

    private function validar(DefinicionDocumento $definicion): void
    {
        $falla = fn (string $problema): DefinicionDocumentoInvalida => DefinicionDocumentoInvalida::de($definicion->clave(), $problema);
        $estados = [];

        foreach ($definicion->estados() as $estado) {
            if (! preg_match(self::CODIGO, $estado->codigo)) {
                throw $falla("el código de estado «{$estado->codigo}» debe ir en minúsculas y con guiones bajos.");
            }

            if (isset($estados[$estado->codigo])) {
                throw $falla("el estado «{$estado->codigo}» está dos veces.");
            }

            if ($estado->esCancelacion && (! $estado->esFinal || $estado->esEditable)) {
                throw $falla("el estado de anulación «{$estado->codigo}» debe ser final y no editable.");
            }

            $estados[$estado->codigo] = $estado;
        }

        foreach ($definicion->revisionesAntesDeAnular() as $revision) {
            if (! is_subclass_of($revision, RevisionAntesDeAnular::class)) {
                throw $falla("{$revision} no implementa RevisionAntesDeAnular.");
            }
        }

        $iniciales = array_filter($estados, fn (EstadoDefinido $estado): bool => $estado->esInicial);

        if (count($iniciales) !== 1) {
            throw $falla('debe tener un solo estado inicial y tiene '.count($iniciales).'.');
        }

        $saltos = [];

        foreach ($definicion->transiciones() as $transicion) {
            if (! preg_match(self::CODIGO, $transicion->codigo)) {
                throw $falla("el código de transición «{$transicion->codigo}» debe ir en minúsculas y con guiones bajos.");
            }

            $hacia = $estados[$transicion->hacia] ?? throw $falla("la transición «{$transicion->codigo}» va a «{$transicion->hacia}», que no está entre sus estados.");

            if ($transicion->desde === []) {
                throw $falla("la transición «{$transicion->codigo}» no tiene estado de origen.");
            }

            foreach ($transicion->condiciones as $condicion) {
                if (! is_subclass_of($condicion, CondicionTransicion::class)) {
                    throw $falla("la condición {$condicion} de «{$transicion->codigo}» no implementa CondicionTransicion.");
                }
            }

            if ($transicion->accionPropia && ($transicion->requiereAprobacion || $transicion->esDelSistema)) {
                throw $falla("«{$transicion->codigo}» no la ejecuta una persona: no lleva acción propia.");
            }

            if ($transicion->requiereAprobacion && $transicion->esDelSistema) {
                throw $falla("«{$transicion->codigo}» requiere aprobación: ya la ejecuta el mecanismo de aprobaciones como transición del sistema, no la marques también como del sistema.");
            }

            if ($hacia->esCancelacion && ! $transicion->requiereMotivo) {
                throw $falla("«{$transicion->codigo}» anula el documento y no pide motivo: anular siempre exige motivo.");
            }

            foreach ($transicion->desde as $codigoDesde) {
                $desde = $estados[$codigoDesde] ?? throw $falla("la transición «{$transicion->codigo}» sale de «{$codigoDesde}», que no está entre sus estados.");

                if (isset($saltos[$codigoDesde][$transicion->codigo])) {
                    throw $falla("la transición «{$transicion->codigo}» sale dos veces de «{$codigoDesde}».");
                }

                if ($transicion->requiereAprobacion && $desde->esEditable) {
                    throw $falla("«{$transicion->codigo}» requiere aprobación y sale de «{$codigoDesde}», que es editable: lo que se firma no puede cambiar mientras se firma.");
                }

                $saltos[$codigoDesde][$transicion->codigo] = true;
            }
        }
    }
}
