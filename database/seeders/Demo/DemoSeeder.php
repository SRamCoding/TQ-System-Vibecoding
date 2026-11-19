<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Crm\Cotizaciones\Services\DatosCotizacion;
use App\Modules\Crm\Cotizaciones\Services\DatosLinea;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\DatosCuenta;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\DatosDelProyectoAlGanar;
use App\Modules\Crm\Oportunidades\Services\DatosOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\AjustesPropuesta;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Presupuestos\Services\DatosPresupuesto;
use App\Modules\Erp\Presupuestos\Services\GastosDemoService;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Organigrama\DatosUnidad;
use App\Modules\Nucleo\Parties\DatosContacto;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\ContactosPartyService;
use App\Modules\Nucleo\Services\OrganigramaService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Los datos de demostración (ficha t0-datos-demo): cuentas, embudo, cotizaciones y los nueve proyectos con presupuesto,
 * árbol y gastos, recorridos por los servicios de cada módulo y cada paso en su fecha (la fecha del sistema se mueve
 * con `setTestNow` y vuelve al terminar). Solo en local. Si la cartera ya está, no hace nada.
 */
final class DemoSeeder extends Seeder
{
    private User $dev;

    private User $gerente;

    /** @var array<string, array{cuenta: int, party: int, decisor: int}> */
    private array $cuentas = [];

    /** @var array<string, User> */
    private array $jefes = [];

    private int $pen;

    public function run(): void
    {
        if (! app()->environment('local') || Oportunidad::query()->where('nombre', CarteraDemo::proyectos()[0]['nombre'])->exists()) {
            return;
        }

        $this->dev = User::query()->where('es_sistema', false)->whereDoesntHave('roles', fn ($q) => $q->where('name', SeguridadService::ROL_GERENCIA_GENERAL))->orderBy('id')->firstOrFail();
        $this->gerente = User::query()->whereHas('roles', fn ($q) => $q->where('name', SeguridadService::ROL_GERENCIA_GENERAL))->where('es_activo', true)->firstOrFail();
        $this->pen = (int) DB::table('monedas')->where('codigo', 'PEN')->value('id');
        auth()->setUser($this->dev);

        try {
            $this->en('2024-01-02', function (): void {
                $this->aprobacionPorMargen();
                $this->jefesDeProyecto();
            });

            foreach (CarteraDemo::proyectos() as $p) {
                $this->command?->info("Demo: {$p['clave']} · {$p['nombre']}");
                $this->proyecto($p);
            }

            foreach (CarteraDemo::embudo() as $o) {
                $this->command?->info("Demo: oportunidad · {$o['nombre']}");
                $this->oportunidadAbierta($o);
            }
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    /** El recorrido de un proyecto de la cartera, de la cuenta al cierre. */
    private function proyecto(array $p): void
    {
        $ganada = CarbonImmutable::parse($p['ganada']);
        $publica = CarteraDemo::cuentas()[$p['cuenta']][2];
        $partidas = $p['partidas'];
        $cd = array_reduce($partidas, fn (string $s, array $x): string => bcadd($s, bcmul($x[3], $x[4], 2), 2), '0');

        // 35 días antes: la cuenta, la oportunidad y su calificación.
        $o = $this->en($ganada->subDays(35), function () use ($p, $cd): Oportunidad {
            $o = $this->nuevaOportunidad($p['cuenta'], $p['nombre'], $p['linea'], bcmul($cd, '1.4', 2), CarbonImmutable::parse($p['ganada']), true);
            $this->avanzar($o, 'calificar');

            return $o->refresh();
        });

        // 21 días antes: el presupuesto de la oportunidad.
        $presupuesto = $this->en($ganada->subDays(21), function () use ($p, $o, $partidas): Presupuesto {
            $armado = app(ArmadoPresupuestoService::class);
            $pre = $armado->crear(new DatosPresupuesto($p['nombre'], $this->pen, $p['gg'], $p['ut'], usaApu: false, oportunidadId: $o->id), $this->dev);
            $titulos = [];

            foreach ($partidas as [$codigo, $descripcion, $unidad, $metrado, $precio, $titulo]) {
                $codTitulo = substr($codigo, 0, 2);
                $titulos[$codTitulo] ??= $armado->agregarTitulo($pre->id, null, $codTitulo, $titulo)->id;
                $armado->agregarPartida($pre->id, $titulos[$codTitulo], $codigo, $descripcion, $this->unidad($unidad), $metrado, $precio);
            }

            return $pre->refresh();
        });

        // 14 días antes: la cotización enlazada al presupuesto; bajo el mínimo, la firma el gerente. Se emite.
        $cot = $this->en($ganada->subDays(14), function () use ($o, $presupuesto): Cotizacion {
            $c = app(CotizacionesService::class)->crear($o->id, new DatosCotizacion(contactoId: $this->cuentas[$this->claveDeCuenta($o)]['decisor'], diasValidez: 30), $this->dev);
            $c = app(CotizacionesService::class)->enlazarPresupuesto($c->id, $c->refresh()->version, $presupuesto->id, '0', $this->dev);

            return $this->emitir($c->refresh());
        });

        $this->en($ganada->subDays(13), fn () => $this->avanzar($o->refresh(), 'enviar_propuesta'));
        $this->en($ganada->subDays(5), fn () => $this->avanzar($o->refresh(), 'negociar'));

        // El día que se ganó: se acepta la cotización y se gana la oportunidad.
        $this->en($ganada, function () use ($o, $cot, $publica, $p): void {
            $cot->refresh();
            app(CotizacionesService::class)->aceptar($cot->id, $cot->version, CarbonImmutable::parse($p['ganada']), $publica ? TipoRespaldo::BuenaPro : TipoRespaldo::OrdenServicio, $publica ? 'Buena pro del procedimiento '.$p['clave'] : 'OS-'.substr($p['ganada'], 0, 4).'-0'.substr($p['clave'], 1), null, null, $this->dev);
            $o->refresh();
            // MV-006: al ganar nace el proyecto con lo cotizado como su presupuesto «PRY-… · PRE-01 v1».
            app(OportunidadesService::class)->ganar($o->id, $o->version, CarbonImmutable::parse($p['ganada']), null, null, $this->dev, new DatosDelProyectoAlGanar(
                $this->jefes[$p['jefe']]->id, CarbonImmutable::parse($p['inicio']), CarbonImmutable::parse($p['fin']),
            ));
        });
        $proyectoId = (int) app(ProyectosService::class)->deOportunidad($o->id);

        // Unos días después, en algunos: la versión 2 del presupuesto con lo que cambió desde la oferta (MV-008).
        if (isset($p['v2'])) {
            $this->en($ganada->addDays(5), function () use ($p, $proyectoId): void {
                $armado = app(ArmadoPresupuestoService::class);
                $v1 = Presupuesto::query()->where('proyecto_id', $proyectoId)->where('es_vigente', true)->firstOrFail();
                $v2 = $armado->nuevaVersion($v1->id, $this->dev);

                foreach ($p['v2'] as $codigo => [$metrado, $precio]) {
                    $partida = PartidaDePresupuesto::query()->where('presupuesto_id', $v2->id)->where('codigo', $codigo)->firstOrFail();
                    $armado->editarPartida($partida->id, $partida->descripcion, $partida->unidad_medida_id, $metrado, $precio, $partida->version);
                }

                $armado->traerAlProyecto($v2->id, $proyectoId, $v2->refresh()->version, $this->dev);
            });
        }

        // Una semana después: el árbol desde el presupuesto vigente, y su aprobación.
        $this->en($ganada->addDays(7), function () use ($p, $proyectoId): void {
            $jefe = $this->jefes[$p['jefe']];
            $arbol = app(ArbolPartidasService::class)->crearDesdePropuesta(
                app(ConsultaArbol::class)->propuestaDesdePresupuesto($proyectoId),
                AjustesPropuesta::desdeCampos([], [], [], $jefe->id),
                $this->dev,
            );

            if ($p['estado'] !== 'en_preparacion') {
                $arbol->refresh();
                app(TransicionesService::class)->transicionar($arbol, 'aprobar', $arbol->version, $this->dev);
            }
        });

        if ($p['estado'] === 'en_preparacion') {
            return;
        }

        $this->gastos($p, $proyectoId);

        if ($p['estado'] === 'cerrado') {
            $this->en($p['fin'], function () use ($proyectoId): void {
                $proyecto = Proyecto::query()->findOrFail($proyectoId);
                app(TransicionesService::class)->transicionar($proyecto, 'cerrar', $proyecto->version, $this->dev, null, 'Acta de conformidad firmada.');
            });
        }
    }

    /**
     * Los gastos de demostración (MV-003): cada hoja en dos gastos (60 % y 40 %). Los cerrados, ejecutados y pagados
     * enteros con una variación pequeña sobre lo presupuestado; los que siguen, por su avance. Más los de la PC-99.
     */
    private function gastos(array $p, int $proyectoId): void
    {
        $inicio = CarbonImmutable::parse($p['inicio']);
        $cerrado = $p['estado'] === 'cerrado';
        $fin = $cerrado ? CarbonImmutable::parse($p['fin']) : CarbonImmutable::parse('2026-11-18');
        $dias = max(1, (int) $inicio->diffInDays($fin));
        $variaciones = $cerrado ? ['0.97', '1.00', '0.95', '0.99', '0.93', '1.00', '0.96', '0.98', '0.94'] : ['1.15', '1.00', '0.85', '1.20', '0.70', '1.10', '0.90'];
        $proveedores = ['Distribuidora Tecnológica del Norte S.A.C.', 'Importaciones Andinas de Seguridad S.A.C.', 'Redes y Fibra del Pacífico S.A.C.', 'Servicios Técnicos Valle Norte E.I.R.L.', 'Soluciones Informáticas Integrales S.A.C.'];
        $hojas = PartidaControl::query()->where('proyecto_id', $proyectoId)->where('es_no_presupuestado', false)->where('es_hoja', true)->orderBy('codigo')->get();
        $servicio = app(GastosDemoService::class);

        foreach ($hojas as $i => $hoja) {
            $presupuestado = (string) DB::table('partida_presupuestos')->where('partida_id', $hoja->id)->value('monto');

            if (bccomp($presupuestado, '0', 2) <= 0) {
                continue;
            }

            $fraccion = $cerrado ? $variaciones[$i % count($variaciones)] : bcmul($p['avance'], $variaciones[$i % count($variaciones)], 4);
            $fraccion = bccomp($fraccion, '1', 4) > 0 ? '1' : $fraccion;
            $total = self::r2(bcmul($presupuestado, $fraccion, 4));
            $primero = self::r2(bcmul($total, '0.6', 4));

            foreach ([[$primero, '0.25', 'Compra'], [bcsub($total, $primero, 2), '0.70', 'Saldo de compra']] as $j => [$monto, $momento, $prefijo]) {
                $fecha = $inicio->addDays((int) round($dias * (float) $momento));
                [$ejecutado, $pagado] = $cerrado ? [$monto, $monto] : ($j === 0 ? [$monto, $monto] : [self::r2(bcmul($monto, '0.6', 4)), self::r2(bcmul($monto, '0.3', 4))]);
                $this->en($fecha, fn () => $servicio->registrar($hoja->id, "{$prefijo}: {$hoja->nombre}", $proveedores[($i + $j) % count($proveedores)], $fecha, $monto, $ejecutado, $pagado));
            }
        }

        $pc99 = PartidaControl::query()->where('proyecto_id', $proyectoId)->where('es_no_presupuestado', true)->first();

        foreach ($p['pc99'] ?? [] as $k => [$motivo, $comentario, $monto]) {
            $fecha = $inicio->addDays((int) round($dias * (0.5 + 0.15 * $k)));
            $motivoId = (int) DB::table('motivos as m')->join('tipos_motivo as t', 't.id', '=', 'm.tipo_motivo_id')->where('t.codigo', 'erp.presupuestos.gasto_no_presupuestado')->where('m.codigo', $motivo)->value('m.id');
            $this->en($fecha, fn () => $servicio->registrar($pc99->id, $comentario, $proveedores[$k % count($proveedores)], $fecha, $monto, $cerrado ? $monto : self::r2(bcmul($monto, '0.5', 4)), $cerrado ? $monto : '0', $motivoId, $comentario));
        }
    }

    /** Una oportunidad del embudo abierto o perdida (MV-004), con su cotización si la tiene. */
    private function oportunidadAbierta(array $d): void
    {
        $creada = CarbonImmutable::parse($d['creada']);
        $califica = $d['etapa'] !== 'prospecto';

        $o = $this->en($creada, fn (): Oportunidad => $this->nuevaOportunidad($d['cuenta'], $d['nombre'], $d['linea'], $d['monto'], CarbonImmutable::parse($d['cierre']), $califica));

        if ($califica && $d['etapa'] !== 'perdida' || isset($d['cotizacion'])) {
            $this->en($creada->addDays(6), fn () => $this->avanzar($o->refresh(), 'calificar'));
        }

        $cot = null;

        if (isset($d['cotizacion'])) {
            $cot = $this->en($creada->addDays(12), function () use ($o, $d): Cotizacion {
                $c = app(CotizacionesService::class)->crear($o->id, new DatosCotizacion(contactoId: $this->cuentas[$d['cuenta']]['decisor'], diasValidez: 60), $this->dev);

                foreach ($d['lineas'] as [$descripcion, $cantidad, $precio, $costo]) {
                    app(CotizacionesService::class)->agregarLinea($c->id, $c->refresh()->version, new DatosLinea($cantidad, $precio, $costo, descripcion: $descripcion, unidadMedidaId: $this->unidad('NIU')), $this->dev);
                }

                $c->refresh();

                return match ($d['cotizacion']) {
                    'borrador' => $c,
                    'por_aprobar' => app(CotizacionesService::class)->solicitarAprobacion($c->id, $c->version, 'Margen bajo por la competencia en el procedimiento.', $this->dev),
                    default => $this->emitir($c),
                };
            });
        }

        if (in_array($d['etapa'], ['propuesta_enviada', 'negociacion'], true) || ($d['etapa'] === 'perdida' && $cot !== null)) {
            $this->en($creada->addDays(13), fn () => $this->avanzar($o->refresh(), 'enviar_propuesta'));
        }

        if ($d['etapa'] === 'negociacion') {
            $this->en($creada->addDays(25), fn () => $this->avanzar($o->refresh(), 'negociar'));
        }

        if ($d['etapa'] === 'perdida') {
            $cuando = CarbonImmutable::parse($d['cierre']);
            $this->en($cuando, function () use ($o, $d, $cot): void {
                if ($cot !== null) {
                    $cot->refresh();
                    app(TransicionesService::class)->transicionar($cot, DefinicionCotizacion::RECHAZAR, $cot->version, $this->dev, new MotivoElegido($this->motivo('crm.cotizaciones.cotizacion.rechazar', $d['perdida'][0])), $d['perdida'][1]);
                }

                $o->refresh();
                app(OportunidadesService::class)->perder($o->id, $o->version, new MotivoElegido($this->motivo('crm.oportunidades.oportunidad.perder', $d['perdida'][0])), GanadorConocido::NoSeSabe, null, null, null, false, $d['perdida'][1], $this->dev);
            });
        }
    }

    private function nuevaOportunidad(string $cuenta, string $nombre, string $linea, string $monto, CarbonImmutable $cierre, bool $calificada): Oportunidad
    {
        $c = $this->cuenta($cuenta);
        $r = app(OportunidadesService::class)->crear($c['cuenta'], new DatosOportunidad(
            $nombre,
            null,
            $monto,
            $this->pen,
            $cierre,
            (int) DB::table('lineas_solucion')->where('codigo', $linea)->value('id'),
            ejecutivoUserId: $this->dev->id,
            necesidadConfirmada: $calificada,
            presupuestoConfirmado: $calificada,
            decisorContactoId: $calificada ? $c['decisor'] : null,
        ), [], $this->dev);

        return Oportunidad::query()->findOrFail($r->id);
    }

    private function avanzar(Oportunidad $o, string $codigo): void
    {
        app(OportunidadesService::class)->avanzar($o->id, $o->version, $codigo, null, null, null, [], null, $this->dev);
    }

    /** Emite; si el margen está bajo el mínimo, primero pide la aprobación y la firma el gerente. */
    private function emitir(Cotizacion $c): Cotizacion
    {
        $servicio = app(CotizacionesService::class);

        if (bccomp((string) $c->margen_porcentaje, (string) ($c->margen_minimo ?? '22'), 2) < 0) {
            $c = $servicio->solicitarAprobacion($c->id, $c->version, 'Margen de obra pública: se pide la firma de gerencia.', $this->dev);
            app(AprobacionesService::class)->firmar('cotizacion', $c->id, DefinicionCotizacion::APROBAR, $this->gerente);
            $c->refresh();
        }

        return $servicio->emitir($c->id, $c->version, null, $this->dev)->refresh();
    }

    /** La cuenta (la crea la primera vez) con su contacto decisor. */
    private function cuenta(string $clave): array
    {
        if (isset($this->cuentas[$clave])) {
            return $this->cuentas[$clave];
        }

        [$razon, $base, $publica, $nivel, $rubro, $origen, [$nombres, $apellidos, $cargo]] = CarteraDemo::cuentas()[$clave];
        $creada = app(CuentasService::class)->crear(new DatosCuenta(
            $base.DocumentoIdentidad::digitoVerificadorRuc($base),
            $this->dev->id,
            (int) DB::table('origenes_comerciales')->where('codigo', $origen)->value('id'),
            $publica,
            $nivel === null ? null : (int) DB::table('niveles_gobierno')->where('codigo', $nivel)->value('id'),
            razonSocial: $razon,
            rubroId: (int) DB::table('rubros')->where('codigo', $rubro)->value('id'),
        ));
        $partyId = $creada->cuenta->partyId;
        $correo = mb_strtolower(explode(' ', $nombres)[0].'.'.explode(' ', $apellidos)[0]).'@cliente-demo.test';
        $decisor = app(ContactosPartyService::class)->agregar($partyId, new DatosContacto($nombres, $apellidos, $cargo, telefono: '9'.substr($base, 2, 8), email: self::sinTildes($correo), esDecisor: true, esPrincipal: true));

        return $this->cuentas[$clave] = ['cuenta' => $creada->cuenta->id, 'party' => $partyId, 'decisor' => $decisor];
    }

    private function claveDeCuenta(Oportunidad $o): string
    {
        foreach ($this->cuentas as $clave => $c) {
            if ($c['cuenta'] === $o->cuenta_id) {
                return $clave;
            }
        }

        throw new \LogicException('Cuenta desconocida.');
    }

    /** La unidad, el puesto del gerente y la regla que firma las cotizaciones bajo el margen mínimo (plan viejo, D5). */
    private function aprobacionPorMargen(): void
    {
        $organigrama = app(OrganigramaService::class);
        $unidad = $organigrama->crearUnidad(new DatosUnidad('GG', 'Gerencia General', TipoUnidadOrganizativa::Gerencia));
        $puesto = $organigrama->crearPuesto($unidad, 'Gerente general');
        $organigrama->designarJefatura($unidad, $puesto, (int) DB::table('unidades_organizativas')->where('id', $unidad)->value('version'));
        app(SeguridadService::class)->cambiarPuesto($this->gerente->id, $puesto, $this->gerente->refresh()->version, $this->dev);
        app(AprobacionesService::class)->crearRegla(new DatosRegla('Cotización bajo el margen mínimo', 'cotizacion', DefinicionCotizacion::APROBAR, null, null, null, ["unidad:{$unidad}"], CondicionRegla::MargenBajoElMinimo));
    }

    private function jefesDeProyecto(): void
    {
        foreach (CarteraDemo::jefes() as $clave => [$nombres, $apellidos, $dni, $correo]) {
            $persona = Party::factory()->create(['numero_documento' => $dni, 'nombres' => $nombres, 'apellidos' => $apellidos, 'razon_social' => "{$apellidos} {$nombres}"]);
            $this->jefes[$clave] = User::factory()->create(['party_id' => $persona->id, 'name' => "{$nombres} {$apellidos}", 'email' => $correo, 'es_activo' => true]);
        }
    }

    /**
     * Corre el paso con la fecha del sistema en ese día (10:00 de Lima), con el tipo de cambio del día.
     *
     * @template T
     *
     * @param  callable(): T  $paso
     * @return T
     */
    private function en(CarbonImmutable|string $dia, callable $paso): mixed
    {
        $momento = CarbonImmutable::parse(is_string($dia) ? $dia : $dia->toDateString(), 'America/Lima')->setTime(10, 0);
        Carbon::setTestNow($momento);
        CarbonImmutable::setTestNow($momento);
        $tipos = app(TipoCambioService::class);

        if (! $tipos->hayDe($momento->startOfDay())) {
            $tipos->registrar('USD', $momento->startOfDay(), '3.7400', '3.7500', OrigenTipoCambio::Manual);
        }

        return $paso();
    }

    private function unidad(string $codigo): int
    {
        return (int) DB::table('unidades_medida')->where('codigo', $codigo)->value('id');
    }

    private function motivo(string $familia, string $codigo): int
    {
        return (int) DB::table('motivos as m')->join('tipos_motivo as t', 't.id', '=', 'm.tipo_motivo_id')->where('t.codigo', $familia)->where('m.codigo', $codigo)->value('m.id');
    }

    private static function r2(string $n): string
    {
        return bcadd($n, '0.005', 2);
    }

    private static function sinTildes(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
    }
}
