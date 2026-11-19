<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Imputacion;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Imputacion\EleccionImputacion;
use App\Modules\Nucleo\Imputacion\FuenteDeDestinos;
use App\Modules\Nucleo\Imputacion\OpcionDestino;
use App\Modules\Nucleo\Imputacion\SaldoConLinea;
use App\Modules\Nucleo\Imputacion\TipoImputacion;
use App\Modules\Nucleo\Models\CentroCosto;
use App\Modules\Nucleo\Services\TipoCambioService;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;

/**
 * El selector de imputación (plan 2.2, D1 a D3 y D7; v4, parte 1): un solo componente para las líneas de todos los
 * documentos con costo. Da el control de cada campo de `EleccionImputacion::campos()`, para pasarlo en `$controles` de
 * `AccionFilament::desde()`:
 *
 *     AccionFilament::desde('compras.agregar-linea', controles: SelectorImputacion::controles());
 *
 * Solo presentación: el saldo en vivo es un aviso; la regla vive en `FuenteDeDestinos`, `ImputacionService` y
 * `ExcesoDePartidaService`, que el servicio del documento llama al guardar. Lee el monto, la moneda y la fecha de la línea
 * de sus campos del mismo formulario. Siempre ofrece también lo que la línea ya tiene, aunque ya no esté activo.
 */
final class SelectorImputacion
{
    private const string P = EleccionImputacion::PREFIJO;

    /**
     * @param  ?FuenteDeDestinos  $fuente  la del contenedor si no se da (la página de modo desarrollo da la suya)
     * @return array<string, Closure(): Field>
     */
    public static function controles(?FuenteDeDestinos $fuente = null, string $monto = 'monto', string $moneda = 'moneda', string $fecha = 'fecha'): array
    {
        $f = fn (): FuenteDeDestinos => $fuente ?? app(FuenteDeDestinos::class);
        $p = self::P;
        $es = fn (TipoImputacion ...$tipos): Closure => fn (Get $get): bool => in_array(self::tipo($get), $tipos, true);
        $noPresupuestado = fn (Get $get): bool => self::tipo($get) === TipoImputacion::NoPresupuestado;
        $excede = function (Get $get) use ($f, $monto, $moneda, $fecha): bool {
            $saldo = self::saldo($f(), $get, $monto, $moneda, $fecha);

            return self::tipo($get) === TipoImputacion::Partida && $saldo instanceof SaldoConLinea && $saldo->excede();
        };

        return [
            "{$p}tipo" => fn (): Field => Radio::make("{$p}tipo")->label('Imputar a')->required()->live()
                ->options(collect(TipoImputacion::cases())->mapWithKeys(fn (TipoImputacion $t): array => [$t->value => $t->etiqueta()])->all())
                // Plan 2.2, D7: la PC-99 se ve, pero apagada si la fuente no la admite (encendida desde el plan 2.6).
                ->disableOptionWhen(fn (string $value): bool => $value === TipoImputacion::NoPresupuestado->value && ! $f()->admiteGastoConProyecto())
                ->descriptions($f()->admiteGastoConProyecto() ? [] : [TipoImputacion::NoPresupuestado->value => 'Llega con el gasto no presupuestado.'])
                // El motivo de una lista no sirve en la otra.
                ->afterStateUpdated(function (Set $set) use ($p): void {
                    $set("{$p}partida_id", null);
                    $set("{$p}motivo_id", null);
                }),

            "{$p}proyecto_id" => fn (): Field => Select::make("{$p}proyecto_id")->label('Proyecto')->searchable()->live()
                ->visible($es(TipoImputacion::Partida, TipoImputacion::NoPresupuestado))
                ->required($es(TipoImputacion::Partida, TipoImputacion::NoPresupuestado))
                ->options(fn (mixed $state): array => self::conLoGuardado(self::opciones($f()->proyectos()), $state, fn (int $id): ?string => $f()->etiquetaProyecto($id)))
                ->getSearchResultsUsing(fn (string $search): array => self::opciones($f()->proyectos($search)))
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? $f()->etiquetaProyecto((int) $value) : null)
                ->afterStateUpdated(fn (Set $set) => $set("{$p}partida_id", null)),

            "{$p}partida_id" => fn (): Field => Select::make("{$p}partida_id")->label('Partida de control')->searchable()->live()
                ->visible(fn (Get $get): bool => self::tipo($get) === TipoImputacion::Partida && is_numeric($get("{$p}proyecto_id")))
                ->required($es(TipoImputacion::Partida))
                ->options(fn (Get $get, mixed $state): array => self::conLoGuardado(self::opciones($f()->partidas((int) $get("{$p}proyecto_id"))), $state, fn (int $id): ?string => $f()->etiquetaPartida($id)))
                ->getSearchResultsUsing(fn (Get $get, string $search): array => self::opciones($f()->partidas((int) $get("{$p}proyecto_id"), $search)))
                ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? $f()->etiquetaPartida((int) $value) : null)
                ->helperText(function (Get $get) use ($f, $monto, $moneda, $fecha): ?string {
                    $texto = self::textoDelSaldo(self::saldo($f(), $get, $monto, $moneda, $fecha));
                    // D3: sin el tipo de cambio del día no se adivina un valor; se muestra el saldo sin la línea y se avisa.
                    $sinCambio = $texto !== null && self::enSoles((string) $get($monto), (string) ($get($moneda) ?: 'PEN'), $get($fecha)) === null;

                    return $sinCambio ? "Falta el tipo de cambio del día: el saldo no incluye esta línea. {$texto}" : $texto;
                }),

            // En la PC-99, opcional: el centro de referencia del gasto (plan 2.1, D6).
            "{$p}centro_costo_id" => fn (): Field => Select::make("{$p}centro_costo_id")
                ->label(fn (Get $get): string => self::tipo($get) === TipoImputacion::NoPresupuestado ? 'Centro de costo de referencia' : 'Centro de costo')
                ->visible($es(TipoImputacion::CentroDeCosto, TipoImputacion::NoPresupuestado))
                ->required($es(TipoImputacion::CentroDeCosto))
                ->options(fn (mixed $state): array => CentroCosto::query()
                    ->where(fn ($query) => $query->where('es_activo', true)->when(is_numeric($state), fn ($q) => $q->orWhere('id', (int) $state)))
                    ->orderBy('nombre')->pluck('nombre', 'id')->all()),

            // Con una partida, visibles siempre: dos líneas que solo juntas exceden también lo necesitan, y eso lo dice
            // la revisión al enviar. Obligatorios solo si esta línea sola ya excede; sin exceso, el envío los limpia. En
            // la PC-99, siempre obligatorios y de la lista del gasto no presupuestado (plan 2.6, D3).
            "{$p}motivo_id" => fn (): Field => Select::make("{$p}motivo_id")
                ->label(fn (Get $get): string => $noPresupuestado($get) ? 'Motivo del gasto no presupuestado' : 'Motivo del exceso')
                ->visible($es(TipoImputacion::Partida, TipoImputacion::NoPresupuestado))
                ->required(fn (Get $get): bool => $noPresupuestado($get) || $excede($get))
                ->helperText(fn (Get $get): ?string => $noPresupuestado($get) ? null : 'Solo si la partida se excede con esta línea o con las demás del documento.')
                ->options(fn (Get $get): array => self::opciones($noPresupuestado($get) ? $f()->motivosNoPresupuestados() : $f()->motivosDeExceso())),

            "{$p}comentario" => fn (): Field => Textarea::make("{$p}comentario")
                ->label(fn (Get $get): string => $noPresupuestado($get) ? '¿Por qué no estaba en el presupuesto?' : '¿Por qué se excede?')->rows(2)->maxLength(500)
                ->visible($es(TipoImputacion::Partida, TipoImputacion::NoPresupuestado))
                ->required(fn (Get $get): bool => $noPresupuestado($get) || $excede($get)),
        ];
    }

    /** El saldo de la partida elegida con esta línea, o el texto de por qué no se puede calcular. */
    private static function saldo(FuenteDeDestinos $fuente, Get $get, string $monto, string $moneda, string $fecha): SaldoConLinea|string|null
    {
        $partida = $get(self::P.'partida_id');

        if (self::tipo($get) !== TipoImputacion::Partida || ! is_numeric($partida)) {
            return null;
        }

        $enSoles = self::enSoles((string) $get($monto), (string) ($get($moneda) ?: 'PEN'), $get($fecha));

        try {
            return $fuente->saldoConLineas((int) $partida, $enSoles ?? '0');
        } catch (ErrorDeNegocio $e) {
            return $e->getMessage();
        }
    }

    /** El monto de la línea en soles, con el tipo de cambio de venta de su fecha (como `destinar()`); null si falta. */
    private static function enSoles(string $monto, string $moneda, mixed $fecha): ?string
    {
        if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', trim($monto)) !== 1) {
            return '0';
        }

        try {
            $dia = $fecha instanceof CarbonImmutable ? $fecha : CarbonImmutable::parse((string) ($fecha ?: 'today'), 'America/Lima');

            return bcmul(trim($monto), (string) app(TipoCambioService::class)->del($moneda, $dia)->venta, 2);
        } catch (Throwable) {
            return null;
        }
    }

    private static function textoDelSaldo(SaldoConLinea|string|null $saldo): ?string
    {
        if (! $saldo instanceof SaldoConLinea) {
            return $saldo;
        }

        $soles = fn (string $monto): string => 'S/ '.number_format((float) $monto, 2);
        $base = "Presupuestado {$soles($saldo->presupuestado)} · Comprometido con esta línea {$soles($saldo->comprometidoConLineas())}";

        if ($saldo->excede()) {
            return "{$base} · Excede en {$soles($saldo->excedeEn())}. Se puede continuar, pero la línea se marca y exige motivo y aprobación.";
        }

        $porcentaje = $saldo->porcentajeDespues();
        $alLimite = $porcentaje !== null && bccomp($porcentaje, '90', 2) >= 0 ? " La partida queda al {$porcentaje} %." : '';

        return "{$base} · Disponible {$soles($saldo->disponibleDespues())}.{$alLimite}";
    }

    private static function tipo(Get $get): ?TipoImputacion
    {
        $tipo = $get(self::P.'tipo');

        return $tipo instanceof TipoImputacion ? $tipo : TipoImputacion::tryFrom((string) $tipo);
    }

    /**
     * @param  list<OpcionDestino>  $opciones
     * @return array<int, string>
     */
    private static function opciones(array $opciones): array
    {
        return collect($opciones)->mapWithKeys(fn (OpcionDestino $o): array => [$o->id => $o->etiqueta])->all();
    }

    /**
     * Lo que la línea ya tiene, aunque hoy no se pueda elegir (AGENTS.md: si no, Filament no deja guardar).
     *
     * @param  array<int, string>  $opciones
     * @param  Closure(int): ?string  $etiqueta
     * @return array<int, string>
     */
    private static function conLoGuardado(array $opciones, mixed $state, Closure $etiqueta): array
    {
        if (is_numeric($state) && ! array_key_exists((int) $state, $opciones)) {
            $texto = $etiqueta((int) $state);

            if ($texto !== null) {
                $opciones[(int) $state] = $texto;
            }
        }

        return $opciones;
    }
}
