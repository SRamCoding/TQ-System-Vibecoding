<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Actividades\Models\Actividad;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Models\Lead;
use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Crm\Catalogo\Models\FamiliaProducto;
use App\Modules\Crm\Catalogo\Services\PreciosCatalogoService;
use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Crm\Cotizaciones\Services\DatosCotizacion;
use App\Modules\Crm\Cotizaciones\Services\DatosLinea;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\DatosCuenta;
use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Enums\TipoProcedimiento;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\DatosDelProyectoAlGanar;
use App\Modules\Crm\Oportunidades\Services\DatosOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\AjustesPropuesta;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Presupuestos\Services\DatosPresupuesto;
use App\Modules\Erp\Presupuestos\Services\FormulaPolinomicaService;
use App\Modules\Erp\Presupuestos\Services\GastosDemoService;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Organigrama\DatosUnidad;
use App\Modules\Nucleo\Parties\DatosContacto;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\ContactosPartyService;
use App\Modules\Nucleo\Services\OrganigramaService;
use App\Modules\Nucleo\Services\PartiesService;
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
            $this->indicesUnificados();
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

            $this->command?->info('Demo: licitaciones');
            $this->licitaciones();

            $this->command?->info('Demo: catálogo');
            $this->en('2026-06-01', fn () => $this->catalogo());

            $this->command?->info('Demo: campañas y leads');
            $this->en('2026-11-19', fn () => $this->campanias());

            $this->command?->info('Demo: agenda');
            $this->en('2026-11-19', fn () => $this->actividades());
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

            if (in_array($p['clave'], ['P6', 'P8', 'P9'], true)) {
                $this->formulaDe($pre->id, $p['linea']);
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

        $this->contratoDe($p, $proyectoId);

        if ($p['estado'] === 'en_preparacion') {
            return;
        }

        $this->gastos($p, $proyectoId);

        if ($p['estado'] === 'cerrado') {
            $this->en($p['fin'], function () use ($proyectoId): void {
                $proyecto = Proyecto::query()->findOrFail($proyectoId);
                app(TransicionesService::class)->transicionar($proyecto, 'cerrar', $proyecto->version, $this->dev, null, 'Acta de conformidad firmada.');
                app(ContratosService::class)->culminar((int) Contrato::query()->where('proyecto_id', $proyectoId)->value('id'));
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

    /** Las licitaciones de la demo (ficha t1-licitaciones), enlazadas a la cartera y al embudo. */
    private function licitaciones(): void
    {
        $competidores = [];

        foreach (CarteraDemo::competidores() as $clave => [$razon, $base]) {
            $competidores[$clave] = $this->en('2023-11-01', fn (): int => app(PartiesService::class)->registrar(
                new DatosParty(Catalogo::RUC, $base.DocumentoIdentidad::digitoVerificadorRuc($base), razonSocial: $razon), [OportunidadesService::ROL_COMPETIDOR],
            )->id);
        }

        $servicio = app(LicitacionesService::class);
        $offsets = [0, 6, 11, 14, 19, 26];

        foreach (CarteraDemo::licitaciones() as $d) {
            $convocada = CarbonImmutable::parse($d['convocada']);
            $oportunidad = isset($d['oportunidad']) ? Oportunidad::query()->where('nombre', $d['oportunidad'])->firstOrFail() : null;
            $resultado = $oportunidad?->fecha_cierre_real !== null ? CarbonImmutable::parse($oportunidad->fecha_cierre_real->toDateString()) : $convocada->addDays(26);
            $fechas = array_map(fn (int $dias): CarbonImmutable => $convocada->addDays($dias), $offsets);
            $fechas[5] = $resultado->max($fechas[4]->addDay());
            $cronograma = array_map(fn (string $etapa, CarbonImmutable $f): array => [$etapa, $f->toDateString()], LicitacionesService::ETAPAS, $fechas);
            $cuenta = $this->cuenta($d['cuenta']);

            $l = $this->en($convocada, fn (): Licitacion => $servicio->registrar(
                $cuenta['cuenta'], $d['nomenclatura'], TipoProcedimiento::from($d['tipo']), $d['objeto'] ?? $d['nombre'] ?? (string) $oportunidad?->nombre,
                (int) DB::table('lineas_solucion')->where('codigo', $d['linea'])->value('id'), $d['vr'], $convocada, null, $cronograma, $this->dev,
            ));

            $estado = EstadoLicitacion::from($d['estado']);
            $cerrada = in_array($estado, [EstadoLicitacion::BuenaPro, EstadoLicitacion::Perdida, EstadoLicitacion::Desierta], true);
            $nuestra = collect($d['participantes'] ?? [])->first(fn (array $p): bool => $p[0] === 'techquk');
            $l->update([
                'estado' => $estado,
                'oportunidad_id' => $oportunidad?->id,
                'motivo_no_participa' => $d['motivo'] ?? null,
                'monto_ofertado' => $nuestra[1] ?? $d['ofertado'] ?? null,
                'fecha_presentacion' => in_array($estado, [EstadoLicitacion::Presentada], true) || $cerrada ? $fechas[4]->toDateString() : null,
                'fecha_resultado' => $cerrada ? $fechas[5]->toDateString() : null,
            ]);

            $hoy = CarbonImmutable::parse('2026-11-19');
            $l->hitos()->get()->each(fn ($h) => $h->update(['es_cumplido' => $cerrada || $h->fecha->lte($hoy)]));

            if (in_array($estado, [EstadoLicitacion::Presentada, EstadoLicitacion::BuenaPro, EstadoLicitacion::Perdida], true)) {
                foreach (LicitacionesService::EXPEDIENTE_SUGERIDO as $i => $nombre) {
                    $l->documentos()->create(['nombre' => $nombre, 'origen' => 'ia', 'es_confirmado' => true, 'es_listo' => true, 'orden' => $i + 1]);
                }
            }

            foreach ($d['documentos'] ?? [] as $i => [$nombre, $listo]) {
                $l->documentos()->create(['nombre' => $nombre, 'origen' => 'manual', 'es_confirmado' => true, 'es_listo' => $listo, 'orden' => $i + 1,
                    'nota' => $listo ? null : 'Falta', 'vence' => str_contains($nombre, 'Certificados') ? '2026-12-20' : null]);
            }

            if ($d['ia'] ?? false) {
                $servicio->sugerirExpediente($l);
            }

            foreach ($d['consultas'] ?? [] as $i => [$pregunta, $respuesta]) {
                $l->consultas()->create(['pregunta' => $pregunta, 'respuesta' => $respuesta, 'fecha' => $fechas[1]->toDateString()]);
            }

            foreach ($d['participantes'] ?? [] as [$quien, $oferta, $puntaje, $puesto]) {
                $l->participantes()->create([
                    'nombre' => $quien === 'techquk' ? 'Techquk S.A.C.' : CarteraDemo::competidores()[$quien][0],
                    'party_id' => $quien === 'techquk' ? null : $competidores[$quien],
                    'es_techquk' => $quien === 'techquk', 'oferta' => $oferta, 'puntaje' => $puntaje, 'puesto' => $puesto, 'es_ganador' => $puesto === 1,
                ]);
            }
        }
    }

    /**
     * La agenda de la demo (ficha t1-agenda): gestiones hechas en cada oportunidad abierta y su próxima acción (dos
     * vencidas, dos para hoy, las demás en los próximos días; dos oportunidades quedan sin próxima acción).
     */
    private function actividades(): void
    {
        $pendiente = ['prospecto' => ['llamada', 'Llamar para calificar la necesidad'], 'calificada' => ['correo', 'Enviar la propuesta'],
            'propuesta_enviada' => ['llamada', 'Seguimiento de la propuesta'], 'negociacion' => ['reunion', 'Reunión de negociación']];
        $dias = [-3, -1, 0, null, 0, 1, 2, null, 4, 6, 8, 9, 12, 14, 15, 20];
        $horas = ['09:30', '11:00', '10:00', '15:30', '16:00', '09:00', '12:00'];
        $abiertas = Oportunidad::query()->with('estado')->whereHas('estado', fn ($q) => $q->whereIn('codigo', array_keys($pendiente)))->orderBy('id')->get();
        $hoy = CarbonImmutable::parse('2026-11-19', 'America/Lima');

        foreach ($abiertas as $i => $o) {
            $creada = CarbonImmutable::parse($o->created_at)->setTimezone('America/Lima')->startOfDay();
            $decisor = $o->decisor_contacto_id === null ? 'el cliente' : (string) DB::table('party_contactos')->where('id', $o->decisor_contacto_id)->value('nombres');
            $hechas = [[2, 'llamada', "Primera llamada con {$decisor}", 'Confirmó el interés y pidió una visita.']];

            if ($o->estado->codigo !== 'prospecto') {
                $hechas[] = [9, 'visita', 'Visita para revisar el alcance', 'Revisamos el alcance en sitio; piden detalle de plazos y garantía.'];
            }

            if (in_array($o->estado->codigo, ['propuesta_enviada', 'negociacion'], true)) {
                $hechas[] = [14, 'correo', 'Envío de la propuesta', 'Propuesta enviada con la cotización emitida.'];
            }

            foreach ($hechas as [$d, $tipo, $asunto, $resultado]) {
                $fecha = $creada->addDays($d)->setTime(10, 30);

                if ($fecha->lt($hoy)) {
                    Actividad::query()->create(['tipo' => $tipo, 'cuenta_id' => $o->cuenta_id, 'oportunidad_id' => $o->id, 'asunto' => $asunto, 'fecha' => $fecha,
                        'resultado' => $resultado, 'es_realizada' => true, 'responsable_user_id' => $this->dev->id]);
                }
            }

            $d = $dias[$i % count($dias)];

            if ($d !== null) {
                [$tipo, $asunto] = $pendiente[$o->estado->codigo];
                [$h, $m] = explode(':', $horas[$i % count($horas)]);
                Actividad::query()->create(['tipo' => $tipo, 'cuenta_id' => $o->cuenta_id, 'oportunidad_id' => $o->id, 'asunto' => $asunto,
                    'fecha' => $hoy->addDays($d)->setTime((int) $h, (int) $m), 'es_realizada' => false, 'responsable_user_id' => $this->dev->id]);
            }
        }

        foreach (Oportunidad::query()->whereHas('estado', fn ($q) => $q->where('codigo', 'ganada'))->get() as $o) {
            Actividad::query()->create(['tipo' => 'reunion', 'cuenta_id' => $o->cuenta_id, 'oportunidad_id' => $o->id, 'asunto' => 'Reunión de inicio con el cliente',
                'fecha' => CarbonImmutable::parse($o->fecha_cierre_real->toDateString(), 'America/Lima')->addDays(6)->setTime(11, 0),
                'resultado' => 'Presentamos al jefe de proyecto y acordamos el cronograma.', 'es_realizada' => true, 'responsable_user_id' => $this->dev->id]);
        }
    }

    /** El catálogo de la demo (ficha t1-catalogo): familias, códigos del Estado e ítems con su historia de precios. */
    private function catalogo(): void
    {
        $familias = [];

        foreach (CarteraDemo::familias() as $codigo => [$nombre, $padre]) {
            $familias[$codigo] = FamiliaProducto::query()->create(['codigo' => $codigo, 'nombre' => $nombre, 'parent_id' => $padre === null ? null : $familias[$padre]])->id;
        }

        $cubso = [];

        foreach (CarteraDemo::cubso() as [$codigo, $descripcion]) {
            $cubso[] = DB::table('cubso')->insertGetId(['codigo' => $codigo, 'descripcion' => $descripcion, 'version_catalogo' => '2026', 'es_activo' => true, 'created_by' => $this->dev->id, 'created_at' => now()]);
        }

        $precios = app(PreciosCatalogoService::class);
        $lineas = ['VV' => 'VIDEOVIGILANCIA', 'RD' => 'REDES', 'CO' => 'DATACENTER', 'SV' => 'SOFTWARE'];

        foreach (CarteraDemo::productos() as [$codigo, $descripcion, $familia, $unidad, $marca, $modelo, $c, $precio, $costo, $margen, $servicio, $serie, $anterior, $activo]) {
            $p = $precios->crear([
                'codigo' => $codigo, 'descripcion' => $descripcion, 'unidad_medida_id' => $this->unidad($unidad), 'familia_producto_id' => $familias[$familia],
                'linea_solucion_id' => (int) DB::table('lineas_solucion')->where('codigo', $lineas[substr($familia, 0, 2)])->value('id'),
                'marca' => $marca, 'modelo' => $modelo, 'cubso_id' => $c === null ? null : $cubso[$c], 'es_servicio' => $servicio, 'requiere_serie' => $serie,
            ]);

            if ($anterior !== null) {
                $precios->cambiarPrecio($p->id, $anterior, null, $margen, CarbonImmutable::parse('2026-01-05'));
            }

            if ($precio !== null) {
                $precios->cambiarPrecio($p->id, $precio, $costo, $margen, CarbonImmutable::parse($anterior === null ? '2026-03-02' : '2026-09-01'), $anterior === null ? 'manual' : 'carga');
            }

            if (! $activo) {
                $precios->descontinuar($p->id, true);
            }
        }
    }

    /** Campañas y leads de la demo (ficha t1-campanias). */
    private function campanias(): void
    {
        $origen = fn (string $c): int => (int) DB::table('origenes_comerciales')->where('codigo', $c)->value('id');
        $feria = Campania::query()->create(['nombre' => 'Feria Tecnológica del Norte 2026', 'tipo' => 'feria', 'desde' => '2026-09-24', 'hasta' => '2026-09-26', 'presupuesto' => '8500.00',
            'objetivo' => 'Mostrar videovigilancia con analítica a municipios y empresas agroindustriales; meta: 20 contactos y 4 oportunidades.', 'es_activa' => false]);
        $web = Campania::query()->create(['nombre' => 'Formulario de contacto del sitio web', 'tipo' => 'web', 'desde' => '2026-01-01', 'presupuesto' => null, 'objetivo' => 'Atender en menos de 3 días a quien escribe por la web.', 'es_activa' => true]);
        $referidos = Campania::query()->create(['nombre' => 'Referidos de clientes 2026', 'tipo' => 'referidos', 'desde' => '2026-03-01', 'hasta' => '2026-12-31', 'presupuesto' => '3000.00',
            'objetivo' => 'Que los clientes actuales nos presenten a otros; un vale por cada oportunidad calificada.', 'es_activa' => true]);
        $mirador = DB::table('cuentas')->join('parties', 'parties.id', '=', 'cuentas.party_id')->where('parties.razon_social', 'Hotel Mirador del Valle S.A.C.')->value('parties.numero_documento');
        $ganadaClinica = Oportunidad::query()->where('nombre', 'Mantenimiento de la red de datos 2027')->first();
        $ahora = CarbonImmutable::parse('2026-11-19 10:00', 'America/Lima');

        // [campaña, origen, nombre, cargo, organización, ruc, correo, mensaje, días atrás, estado, motivo]
        $leads = [
            [$feria, 'FERIA', 'Pedro Mendoza', 'Jefe del Área de Obras', 'Municipalidad Distrital de La Pampa Alta', null, 'pmendoza@pampaalta-demo.test', 'Quiere cámaras con analítica para el mercado y la plaza.', 54, 'contactado', null],
            [$feria, 'FERIA', 'Ana Salazar', 'Administradora', 'Clínica San Rafael del Norte S.A.C.', null, 'asalazar@csr-demo.test', 'CCTV para dos sedes y control de acceso.', 54, 'nuevo', null],
            [$feria, 'FERIA', 'Jorge Llontop', 'Gerente de Planta', 'Agroexportadora Valle Fértil S.A.C.', null, 'jllontop@vallefertil-demo.test', 'Monitoreo de cámaras frigoríficas y perímetro.', 55, 'convertido', null],
            [$feria, 'FERIA', 'María Cáceres', 'Estudiante', null, null, 'mcaceres@correo-demo.test', 'Pidió información para una tesis.', 55, 'descartado', 'Solo pedía información'],
            [$feria, 'FERIA', 'Luis Bances', 'Subgerente de Logística', 'Hotel Mirador del Valle S.A.C.', $mirador, 'lbances@mirador-demo.test', 'Ampliar el wifi a la zona de piscina.', 53, 'contactado', null],
            [$web, 'SITIO_WEB', 'Rocío Paredes', 'Dueña', 'Minimarket Doña Rocío', null, 'rparedes@correo-demo.test', 'Cuatro cámaras para su tienda.', 20, 'descartado', 'No es mercado para Techquk'],
            [$web, 'SITIO_WEB', 'Fernando Ríos', 'Gerente General', 'Clínica del Norte S.A.C.', null, 'frios@clinicanorte-demo.test', 'Renovar el contrato de mantenimiento de la red.', 50, 'convertido', null],
            [$web, 'SITIO_WEB', 'Hugo Tello', 'Jefe de Sistemas', 'Colegio Los Andes del Norte S.A.C.', null, 'htello@andes-demo.test', 'Cotizar switches para un pabellón nuevo.', 6, 'nuevo', null],
            [$web, 'SITIO_WEB', 'Karina Vela', 'Coordinadora', 'Instituto Superior Tecnológico Valle Norte', null, 'kvela@istvn-demo.test', 'Laboratorio de redes para la carrera de computación.', 2, 'nuevo', null],
            [$referidos, 'REFERENCIA', 'Óscar Zapata', 'Director de Operaciones', 'Proyecto Especial Hidráulico Valle Norte', null, 'ozapata@pehvn-demo.test', 'Referido por Agrícola Pampa Verde: telemetría de canales.', 70, 'convertido', null],
            [$referidos, 'REFERENCIA', 'Daniela Ruiz', 'Gerente de Finanzas', 'Transportes Costa Azul S.A.C.', null, 'druiz@costaazul-demo.test', 'Referida por Transportes Ruta Andina: GPS para su flota.', 9, 'nuevo', null],
            [$referidos, 'REFERENCIA', 'Raúl Sandoval', 'Administrador', 'Condominio Los Pinos', null, 'rsandoval@lospinos-demo.test', 'Videoportero para 40 departamentos.', 15, 'contactado', null],
        ];

        foreach ($leads as [$campania, $o, $nombre, $cargo, $organizacion, $ruc, $correo, $mensaje, $dias, $estado, $motivo]) {
            $llego = $ahora->subDays($dias);
            $lead = Lead::query()->create(['campania_id' => $campania->id, 'origen_comercial_id' => $origen($o), 'nombre_contacto' => $nombre, 'cargo' => $cargo, 'organizacion' => $organizacion,
                'ruc' => $ruc, 'email' => $correo, 'telefono' => '9'.str_pad((string) (40000000 + $dias * 1371), 8, '0', STR_PAD_LEFT), 'mensaje' => $mensaje, 'estado' => $estado === 'convertido' ? 'contactado' : $estado,
                'motivo_descarte' => $motivo, 'asignado_user_id' => $estado === 'nuevo' ? null : $this->dev->id, 'atendido_at' => $estado === 'nuevo' ? null : $llego->addDays(1)]);
            $lead->forceFill(['created_at' => $llego])->save();

            if ($estado === 'convertido') {
                $op = match ($nombre) {
                    'Fernando Ríos' => $ganadaClinica,
                    'Óscar Zapata' => Oportunidad::query()->where('nombre', 'Telemetría de canales de riego')->first(),
                    default => null,
                };

                if ($op === null) {
                    $op = Oportunidad::query()->findOrFail(app(LeadsService::class)->convertir($lead->id, null, '20699400018', 'Monitoreo de cámaras frigoríficas y perímetro', '145000', (int) DB::table('lineas_solucion')->where('codigo', 'VIDEOVIGILANCIA')->value('id'), $ahora->addDays(45), $this->dev)->oportunidad_id);
                } else {
                    $lead->update(['estado' => 'convertido', 'cuenta_id' => $op->cuenta_id, 'oportunidad_id' => $op->id]);
                }
            }
        }
    }

    /**
     * El contrato de un proyecto de la cartera (ficha t2-contratos): se activa a los 3 días de ganar, con sus hitos; las
     * entidades públicas piden carta fianza del 10 % (su comisión va a gastos generales); P6 tiene una adenda y P1 la
     * penalidad tope del plan viejo (S/ 250,498.54).
     */
    private function contratoDe(array $p, int $proyectoId): void
    {
        $servicio = app(ContratosService::class);
        $c = Contrato::query()->where('proyecto_id', $proyectoId)->firstOrFail();
        $ganada = CarbonImmutable::parse($p['ganada']);
        $publica = CarteraDemo::cuentas()[$p['cuenta']][2];
        $anio = substr($p['ganada'], 0, 4);
        $numero = $publica ? sprintf('Contrato N.° %03d-%s-%s', 10 + (int) substr($p['clave'], 1) * 7, $anio, strtoupper(substr($p['cuenta'], 0, 4))) : 'OS-'.$anio.'-0'.substr($p['clave'], 1);

        $this->en($ganada->addDays(3), fn () => $servicio->activar($c->id, $numero, $ganada->addDays(3), $publica ? '100 % a la conformidad de cada entrega' : '50 % de adelanto y 50 % a la conformidad',
            24, '10', null));
        $hoy = CarbonImmutable::parse('2026-11-19');
        $c->hitos()->get()->each(fn ($h) => $h->fecha_prevista->lt($hoy) && $h->fecha_real === null ? $h->update(['fecha_real' => $h->fecha_prevista->toDateString()]) : null);

        if ($publica) {
            $vence = match ($p['estado']) {
                'cerrado' => CarbonImmutable::parse($p['fin'])->addDays(30),
                'en_preparacion' => CarbonImmutable::parse($p['fin'])->addDays(60),
                default => $p['clave'] === 'P8' ? CarbonImmutable::parse('2026-12-12') : CarbonImmutable::parse($p['fin'])->addDays(30),
            };
            $monto = $servicio->tope($c->refresh());
            $estado = match ($p['estado']) {
                'cerrado' => 'devuelta', 'en_preparacion' => 'solicitada', default => 'vigente'
            };
            $this->en($ganada->addDays(8), fn () => $servicio->registrarGarantia($c->id, 'fiel_cumplimiento', ['Banco Norte', 'Banco del Pacífico', 'Caja Valle Norte'][(int) substr($p['clave'], 1) % 3],
                'CF-'.$anio.'-'.str_pad((string) (300 + (int) substr($p['clave'], 1)), 5, '0', STR_PAD_LEFT), $monto, $ganada->addDays(8), $vence,
                $estado === 'solicitada' ? null : bcadd(bcmul($monto, '0.025', 4), '0.005', 2), $estado));
        }

        if ($p['clave'] === 'P6') {
            $this->en('2026-02-16', fn () => $servicio->registrarAdenda($c->id, 'Adenda N.° 1', CarbonImmutable::parse('2026-02-16'), 'Doce computadoras más para el servicio de emergencia', '49625.14', 30));
        }

        if ($p['clave'] === 'P1') {
            $this->en('2024-11-15', fn () => $servicio->aplicarPenalidad($c->id, 70, CarbonImmutable::parse('2024-11-15'), 'Atraso de 70 días en la conformidad por la reubicación de postes.'));
        }
    }

    /** Índices unificados inventados, de enero de 2025 a noviembre de 2026 (ficha t2-formula-polinomica). */
    private function indicesUnificados(): void
    {
        $indices = ['47' => ['Mano de obra (incluye leyes sociales)', '598.20', '0.0042'], '06' => ['Cable de cobre y fibra óptica', '380.40', '0.0051'],
            '49' => ['Maquinaria y equipo electrónico importado', '515.30', '-0.0006'], '39' => ['Índice general de precios al consumidor', '180.10', '0.0019'],
            '30' => ['Dólar más inflación del mercado de EE. UU.', '402.60', '0.0013']];
        $mes = CarbonImmutable::parse('2025-01-01');

        for ($n = 0; $n < 23; $n++) {
            foreach ($indices as $codigo => [$nombre, $base, $tasa]) {
                $valor = bcmul($base, bcpow(bcadd('1', $tasa, 6), (string) $n, 8), 2);
                DB::table('indices_unificados')->insert(['codigo' => $codigo, 'nombre' => $nombre, 'periodo' => $mes->addMonths($n)->format('Y-m'), 'valor' => $valor, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** La fórmula del costeo (viaja a la copia del proyecto y a sus versiones). */
    private function formulaDe(int $presupuestoId, string $linea): void
    {
        $servicio = app(FormulaPolinomicaService::class);
        $monomios = $linea === 'REDES'
            ? [['Mano de obra', '47', '0.320'], ['Cable de fibra óptica', '06', '0.285'], ['Equipos electrónicos', '49', '0.190'], ['Índice general de precios', '39', '0.205']]
            : [['Mano de obra', '47', '0.280'], ['Equipos electrónicos', '49', '0.420'], ['Dólar más inflación', '30', '0.180'], ['Índice general de precios', '39', '0.120']];

        foreach ($monomios as [$descripcion, $indice, $coef]) {
            $servicio->agregar($presupuestoId, $descripcion, $indice, $coef);
        }
    }
}
