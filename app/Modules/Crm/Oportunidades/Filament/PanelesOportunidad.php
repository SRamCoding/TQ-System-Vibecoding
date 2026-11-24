<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Resources\Parties\SelectoresParty;
use App\Modules\Nucleo\Filament\SelectorCatalogo;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\RevisionDeFoto;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

/**
 * Los paneles deslizantes de Oportunidades (plan 2.21, D11.4, D11.6 y D11.7): Nueva oportunidad, Avanzar y las dos
 * salidas de Cerrar (ganar y perder). Los usan la lista, el tablero, la ficha y la cuenta, así que viven en un solo
 * lugar. Solo presentación: cada uno es una acción registrada dibujada por AccionFilament. Avanzar y Cerrar toman la
 * versión de la oportunidad al abrirse (`fillForm`), nunca al guardar.
 */
final class PanelesOportunidad
{
    /** La transición que avanza desde cada etapa abierta (D1); la última se cierra, no se avanza. */
    public const array AVANCE_DESDE = ['prospecto' => 'calificar', 'calificada' => 'enviar_propuesta', 'propuesta_enviada' => 'negociar'];

    /** Desde dónde se puede perder (D4). */
    public const array ABIERTAS = ['prospecto', 'calificada', 'propuesta_enviada', 'negociacion'];

    /** Días desde los que una fecha de cierre real pide confirmación, sin bloquear (D3). */
    public const int DIAS_CONFIRMAR_CIERRE = 90;

    /**
     * D11.4: Nueva oportunidad, con la cuenta fija si viene de la cuenta. Origen y ejecutivo, por defecto los de la
     * cuenta (D5, D7). La calificación y el decisor se completan al avanzar.
     *
     * @param  Closure(mixed): void  $alTerminar
     */
    public static function nueva(?int $cuentaFija, Closure $alTerminar): Action
    {
        $local = Moneda::query()->where('codigo', app(ParametrosService::class)->monedaLocal())->value('id');

        return AccionFilament::desde(
            'crm.oportunidades.crear',
            $cuentaFija === null ? null : fn (): array => ['cuenta_id' => $cuentaFija],
            $alTerminar,
            ocultos: fn (): array => ['descripcion', 'necesidad_confirmada', 'presupuesto_confirmado', 'decisor_contacto_id'],
            controles: [
                'cuenta_id' => fn (): Field => SelectoresOportunidad::cuenta()->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        foreach (self::porDefectoDeLaCuenta(is_numeric($state) ? (int) $state : null) as $campo => $valor) {
                            $set($campo, $valor);
                        }
                    }),
                'ejecutivo_user_id' => fn (): Field => SelectoresOportunidad::ejecutivo()
                    ->helperText('Por defecto, el ejecutivo de la cuenta.'),
                // Observación 9.5 (7): ¿ya existe? Aviso ámbar bajo el nombre (avisa, no impide).
                'nombre' => fn (): Field => TextInput::make('nombre')->live(onBlur: true)
                    ->belowContent(fn (Get $get): ?HtmlString => self::avisoDeParecidas($get)),
                'linea_solucion_id' => fn (): Field => SelectorCatalogo::de(Catalogo::LineasSolucion, 'linea_solucion_id')->live(),
            ],
        )
            ->label('Nueva oportunidad')->icon('heroicon-o-plus')
            ->slideOver()
            ->modalDescription(fn (): string => 'Etapa inicial: '.EtapaAbierta::Prospecto->etiqueta().' · '.app(PortadaOportunidades::class)->probabilidad(EtapaAbierta::Prospecto).' %')
            ->fillForm(fn (): array => ['moneda_id' => $local, ...self::porDefectoDeLaCuenta($cuentaFija)]);
    }

    /**
     * D11.6: Avanzar desde una etapa, con lo que exige la siguiente (acumulado), la calificación editable ahí mismo y
     * lo que cambia. `$id` recibe lo que Filament inyecta (`$record` en la ficha, `$arguments` en el tablero).
     *
     * @param  Closure(mixed): void  $alTerminar
     */
    public static function avanzar(string $desde, Closure $id, Closure $alTerminar): Action
    {
        $codigo = self::AVANCE_DESDE[$desde];
        $destino = EtapaAbierta::from($desde)->siguiente() ?? EtapaAbierta::Negociacion;
        $oportunidadId = fn (Action $action): int => (int) $action->evaluate($id);

        return AccionFilament::desde(
            DefinicionOportunidad::CLAVE.".{$codigo}",
            fn (Action $action): array => ['oportunidad_id' => $oportunidadId($action)],
            $alTerminar,
            controles: [
                // Plan 2.21d, D8: cada requisito propio se cumple ahí mismo; lo que pide aparece al marcarlo.
                'necesidad_confirmada' => fn (): Field => Toggle::make('necesidad_confirmada')->label('Necesidad confirmada')->live(),
                'necesidad_detalle' => fn (): Field => TextInput::make('necesidad_detalle')->label('¿Cómo se confirmó?')->maxLength(500)
                    ->placeholder('Reunión, oficio, correo…')
                    ->visible(fn (Get $get): bool => (bool) $get('necesidad_confirmada'))
                    ->required(fn (Get $get): bool => (bool) $get('necesidad_confirmada') && ! (bool) $get('necesidad_ya')),
                'presupuesto_confirmado' => fn (): Field => Toggle::make('presupuesto_confirmado')->label('Presupuesto confirmado')->live(),
                'certificacion_presupuestal' => fn (): Field => FileUpload::make('certificacion_presupuestal')->label('Certificación presupuestal o PIA')
                    ->storeFiles(false)->maxSize(RevisionDeFoto::TOPE_MB * 1024)
                    ->visible(fn (Get $get): bool => (bool) $get('presupuesto_confirmado') && ! (bool) $get('presupuesto_ya'))
                    ->required(fn (Get $get): bool => (bool) $get('presupuesto_confirmado') && ! (bool) $get('presupuesto_ya')),
                'decisor_contacto_id' => fn (): Field => SelectoresOportunidad::decisor(conRegistrar: true),
            ],
            deApoyo: [
                fn (): Field => Hidden::make('party_id'),
                // Lo que ya estaba cumplido al abrir: no se vuelve a pedir el cómo ni la certificación.
                fn (): Field => Hidden::make('necesidad_ya'),
                fn (): Field => Hidden::make('presupuesto_ya'),
            ],
        )
            ->name(lcfirst(str_replace('_', '', ucwords($codigo, '_'))))
            ->label('Avanzar etapa')->icon('heroicon-o-chevron-double-right')
            ->slideOver()
            ->modalHeading("Avanzar a «{$destino->etiqueta()}»")
            ->modalSubmitActionLabel("Pasar a «{$destino->etiqueta()}»")
            ->modalContent(fn (Action $action): View => self::vistaAvanzar($oportunidadId($action), $destino))
            ->fillForm(function (Action $action) use ($oportunidadId): array {
                $oportunidad = Oportunidad::query()->findOrFail($oportunidadId($action));

                return [
                    'version' => $oportunidad->version,
                    'necesidad_confirmada' => $oportunidad->necesidad_confirmada,
                    'necesidad_detalle' => $oportunidad->necesidad_detalle,
                    'necesidad_ya' => $oportunidad->necesidad_confirmada,
                    'presupuesto_confirmado' => $oportunidad->presupuesto_confirmado,
                    'presupuesto_ya' => $oportunidad->presupuesto_confirmado,
                    'decisor_contacto_id' => $oportunidad->decisor_contacto_id,
                    'party_id' => app(CuentasService::class)->resumen($oportunidad->cuenta_id)->partyId,
                ];
            });
    }

    /**
     * D11.6 y D3: la salida «ganada» de Cerrar. Fecha de cierre real (hoy por defecto, nunca futura) y monto final; con
     * más de 90 días, pide confirmarla sin impedirla.
     *
     * @param  Closure(mixed): void  $alTerminar
     */
    public static function ganar(Closure $id, Closure $alTerminar): Action
    {
        $oportunidadId = fn (Action $action): int => (int) $action->evaluate($id);
        $antigua = fn (Get $get): bool => filled($get('fecha_cierre_real'))
            && CarbonImmutable::parse((string) $get('fecha_cierre_real'))->lt(CarbonImmutable::parse(Calendario::hoy())->subDays(self::DIAS_CONFIRMAR_CIERRE));

        return AccionFilament::desde(
            DefinicionOportunidad::CLAVE.'.ganar',
            fn (Action $action): array => ['oportunidad_id' => $oportunidadId($action)],
            $alTerminar,
            controles: [
                'fecha_cierre_real' => fn (): Field => DatePicker::make('fecha_cierre_real')->format('Y-m-d')->live()
                    ->maxDate(fn (): string => Calendario::hoy()),
                // MV-006: el proyecto nace al ganar, con su jefe y sus fechas.
                'jefe_proyecto_user_id' => fn (): Field => Select::make('jefe_proyecto_user_id')->searchable()
                    ->options(fn (): array => User::query()->where('es_activo', true)->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all())
                    ->helperText('Nace el proyecto «en preparación» con lo cotizado como su presupuesto.'),
                'fecha_inicio' => fn (): Field => DatePicker::make('fecha_inicio')->format('Y-m-d'),
                'fecha_fin_prevista' => fn (): Field => DatePicker::make('fecha_fin_prevista')->format('Y-m-d'),
            ],
            deApoyo: [fn (): Field => Toggle::make('confirmo_fecha_antigua')
                ->label('La fecha de cierre tiene más de '.self::DIAS_CONFIRMAR_CIERRE.' días: confirmo que es correcta')
                ->visible($antigua)->accepted($antigua)],
        )
            ->name('ganar')
            ->label('Ganada')->icon('heroicon-o-hand-thumb-up')->color('success')
            ->slideOver()
            ->modalHeading('Cerrar como ganada')
            ->modalDescription('La cuenta pasa a cliente desde la fecha de cierre, si no lo era antes.')
            ->fillForm(function (Action $action) use ($oportunidadId): array {
                $oportunidad = Oportunidad::query()->findOrFail($oportunidadId($action));

                $inicio = CarbonImmutable::parse(Calendario::hoy())->addDays(7);

                return [
                    'version' => $oportunidad->version, 'fecha_cierre_real' => Calendario::hoy(), 'monto_final' => $oportunidad->monto_estimado,
                    'fecha_inicio' => $inicio->toDateString(), 'fecha_fin_prevista' => $inicio->addDays(90)->toDateString(),
                ];
            });
    }

    /**
     * D11.6 y D4: la salida «perdida» de Cerrar. Motivo de su lista (pieza común) y «¿Lo ganó otra empresa?»; con «Sí»,
     * la empresa (Personas y empresas activas) y su precio con moneda, o «precio desconocido».
     *
     * @param  Closure(mixed): void  $alTerminar
     */
    public static function perder(Closure $id, Closure $alTerminar): Action
    {
        $clave = DefinicionOportunidad::CLAVE.'.perder';
        $oportunidadId = fn (Action $action): int => (int) $action->evaluate($id);
        $si = fn (Get $get): bool => $get('ganador_conocido') === GanadorConocido::Si->value;
        $conPrecio = fn (Get $get): bool => $si($get) && ! $get('precio_desconocido');

        return AccionFilament::desde(
            $clave,
            fn (Action $action): array => ['oportunidad_id' => $oportunidadId($action)],
            $alTerminar,
            controles: [
                'ganador_conocido' => fn (): Field => Select::make('ganador_conocido')->live()
                    ->options(array_combine(
                        array_map(fn (GanadorConocido $g): string => $g->value, GanadorConocido::cases()),
                        array_map(fn (GanadorConocido $g): string => $g->etiqueta(), GanadorConocido::cases()),
                    )),
                'ganador_party_id' => fn (): Field => SelectoresParty::party('ganador_party_id')->visible($si)
                    ->helperText('Una ficha de Personas y empresas; recibe el tipo «Competidor».'),
                // Observación 9.5 (3): «a qué precio aproximado», con cuánto menos que nosotros.
                'precio' => fn (): Field => TextInput::make('precio')->label('A qué precio (aprox.)')->numeric()->visible($conPrecio)->live(onBlur: true)
                    ->helperText(function (Get $get, mixed $state): ?string {
                        $nuestro = is_numeric($get('oportunidad_id')) ? Oportunidad::query()->whereKey((int) $get('oportunidad_id'))->value('monto_estimado') : null;

                        if (! is_numeric($state) || $nuestro === null || (float) $nuestro <= 0) {
                            return 'Lo que se sabe, aunque no esté confirmado.';
                        }

                        $diferencia = ((float) $nuestro - (float) $state) * 100 / (float) $nuestro;

                        return $diferencia >= 0
                            ? number_format($diferencia, 1).' % menos que nosotros (ofertamos '.self::dinero((string) $nuestro, 'S/').')'
                            : number_format(-$diferencia, 1).' % más que nosotros (ofertamos '.self::dinero((string) $nuestro, 'S/').')';
                    }),
                'moneda_id' => fn (): Field => AccionFilament::campo($clave, 'moneda_id')->visible($conPrecio),
                'precio_desconocido' => fn (): Field => Toggle::make('precio_desconocido')->live()->visible($si),
            ],
        )
            ->name('perder')
            ->label('Perdida')->icon('heroicon-o-hand-thumb-down')->color('danger')
            ->slideOver()
            ->modalHeading('Cerrar como perdida')
            ->fillForm(fn (Action $action): array => ['version' => Oportunidad::query()->findOrFail($oportunidadId($action))->version]);
    }

    /** Observación 9.5 (7): el aviso ámbar con las oportunidades parecidas, «Ver esa» y «Es otra, seguir». */
    private static function avisoDeParecidas(Get $get): ?HtmlString
    {
        $cuenta = $get('cuenta_id');
        $linea = $get('linea_solucion_id');
        $parecidas = app(PortadaOportunidades::class)->parecidas(is_numeric($cuenta) ? (int) $cuenta : null, (string) $get('nombre'), is_numeric($linea) ? (int) $linea : null);

        if ($parecidas === []) {
            return null;
        }

        $filas = implode('', array_map(fn (array $p): string => '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:4px">«'.e($p['nombre']).'» · '.e($p['etapa']).' · la lleva '.e($p['responsable'])
            .' <span style="opacity:.7">('.e($p['por']).')</span> <a href="'.e(Resources\Oportunidades\Pages\VerOportunidad::getUrl(['record' => $p['id']])).'" target="_blank" style="font-weight:600;text-decoration:underline">Ver esa</a></div>', array_slice($parecidas, 0, 3)));

        return new HtmlString('<div x-data="{ fuera: false }" x-show="! fuera" style="border:1px solid color-mix(in oklab, var(--warning-500) 45%, transparent);background:color-mix(in oklab, var(--warning-500) 13%, var(--tq-superficie, transparent));border-radius:10px;padding:8px 12px;font-size:13px">'
            .'<strong>¿Ya existe?</strong>'.$filas
            .'<div style="margin-top:6px"><button type="button" x-on:click="fuera = true" style="font-weight:600;text-decoration:underline">Es otra, seguir</button></div></div>');
    }

    /** «S/ 24,715.10»: solo para mostrar, con texto decimal (nunca float). */
    public static function dinero(string $monto, ?string $simbolo): string
    {
        $redondeado = bcadd($monto, '0', 2);
        $negativo = str_starts_with($redondeado, '-');
        [$enteros, $decimales] = explode('.', ltrim($redondeado, '-'));
        $conMiles = strrev(implode(',', str_split(strrev($enteros), 3)));

        return trim(($simbolo ?? '').' '.($negativo ? '-' : '').$conMiles.'.'.$decimales);
    }

    /**
     * Origen (con su detalle de «Otro», plan 2.0, D5) y ejecutivo de la cuenta, para llenar Nueva oportunidad.
     *
     * @return array<string, int|string|null>
     */
    private static function porDefectoDeLaCuenta(?int $cuentaId): array
    {
        if ($cuentaId === null) {
            return [];
        }

        $cuenta = app(CuentasService::class)->resumen($cuentaId);

        return ['cuenta_id' => $cuenta->id, 'origen_comercial_id' => $cuenta->origenComercialId, 'origen_detalle' => $cuenta->origenDetalle, 'ejecutivo_user_id' => $cuenta->ejecutivoUserId];
    }

    /** «Esta etapa exige» (cumplido y faltante, de RequisitosDeEtapa) y «Qué cambia al avanzar». */
    private static function vistaAvanzar(int $oportunidadId, EtapaAbierta $destino): View
    {
        $oportunidad = Oportunidad::query()->with(['estado', 'moneda'])->findOrFail($oportunidadId);
        $portada = app(PortadaOportunidades::class);
        $actual = EtapaAbierta::from($oportunidad->estado->codigo);
        $antes = $portada->probabilidad($actual);
        $despues = $portada->probabilidad($destino);
        $ponderado = fn (int $p): string => self::dinero(bcdiv(bcmul((string) $oportunidad->monto_estimado, (string) $p, 4), '100', 2), $oportunidad->moneda->simbolo);

        return view('oportunidades::paneles.avanzar', [
            'oportunidadId' => $oportunidad->id,
            'requisitos' => app(RequisitosDeEtapa::class)->revisar($oportunidad->id, $destino->value),
            'cambios' => [
                ['que' => 'Etapa', 'antes' => $actual->etiqueta(), 'despues' => $destino->etiqueta()],
                ['que' => 'Probabilidad', 'antes' => "{$antes} %", 'despues' => "{$despues} %"],
                ['que' => 'Ponderado', 'antes' => $ponderado($antes), 'despues' => $ponderado($despues)],
            ],
        ]);
    }
}
