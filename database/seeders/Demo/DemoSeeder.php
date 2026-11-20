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
use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\DatosDelProyectoAlGanar;
use App\Modules\Crm\Oportunidades\Services\DatosOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Erp\Compras\Models\Orden;
use App\Modules\Erp\Compras\Services\ComprasService;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Erp\Personal\Models\Colaborador;
use App\Modules\Erp\Personal\Services\PersonalService;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\AjustesPropuesta;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Presupuestos\Services\DatosInsumo;
use App\Modules\Erp\Presupuestos\Services\DatosLineaApu;
use App\Modules\Erp\Presupuestos\Services\DatosPresupuesto;
use App\Modules\Erp\Presupuestos\Services\FormulaPolinomicaService;
use App\Modules\Erp\Presupuestos\Services\GastosDemoService;
use App\Modules\Erp\Presupuestos\Services\InsumosService;
use App\Modules\Erp\Presupuestos\Services\PresupuestoEmpresaService;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
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
use App\Modules\Nucleo\Parties\DatosSede;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\AsistenteService;
use App\Modules\Nucleo\Services\ContactosPartyService;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\OrganigramaService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\SedesPartyService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\SolicitudesPermisoService;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
                $this->empresaYSedes();
                $this->aprobacionPorMargen();
                $this->jefesDeProyecto();
            });

            foreach (CarteraDemo::proyectos() as $p) {
                $this->command?->info("Demo: {$p['clave']} · {$p['nombre']}");
                $this->proyecto($p);
            }

            $this->command?->info('Demo: catálogo');
            $this->en('2026-06-01', fn () => $this->catalogo());

            $this->command?->info('Demo: Plaza de Armas (escenarios del v4)');
            $this->plazaDeArmas();

            foreach (CarteraDemo::embudo() as $o) {
                $this->command?->info("Demo: oportunidad · {$o['nombre']}");
                $this->oportunidadAbierta($o);
            }

            $this->command?->info('Demo: licitaciones');
            $this->licitaciones();

            $this->command?->info('Demo: campañas y leads');
            $this->en('2026-11-19', fn () => $this->campanias());

            $this->command?->info('Demo: presupuesto de la empresa');
            $this->presupuestoEmpresa();

            $this->command?->info('Demo: personal, tareo, valorizaciones y subcontratos');
            $this->tramo4();

            $this->command?->info('Demo: facturación, cobranza y rendiciones');
            $this->tramo5();

            $this->command?->info('Demo: solicitudes de permiso');
            $this->solicitudesPermiso();

            $this->en('2026-11-18', function (): void {
                $a = app(AsistenteService::class);
                $a->abrirTicket($this->jefes['diego'], 'No me deja cerrar la semana de tareo: dice que la semana no tiene horas.', '/tareo', "Persona: no me deja cerrar la semana\nAsistente: Lo siento. Si quieres, abro un ticket.");
                $a->abrirTicket($this->dev, 'El PDF de la cotización sale sin el logo.', '/cotizaciones/12', "Persona: el pdf sale sin logo, es un error\nAsistente: Lo siento. Si quieres, abro un ticket.");
            });

            $this->command?->info('Demo: bandeja de convocatorias del SEACE');
            $this->en('2026-11-19', fn () => $this->convocatoriasSeace());

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
                $this->en($fecha, fn () => $this->compraDemo($hoja->id, "{$prefijo}: {$hoja->nombre}", $proveedores[($i + $j) % count($proveedores)], $fecha, $monto, $ejecutado, $pagado));
            }
        }

        $pc99 = PartidaControl::query()->where('proyecto_id', $proyectoId)->where('es_no_presupuestado', true)->first();

        foreach ($p['pc99'] ?? [] as $k => [$motivo, $comentario, $monto]) {
            $fecha = $inicio->addDays((int) round($dias * (0.5 + 0.15 * $k)));
            $motivoId = (int) DB::table('motivos as m')->join('tipos_motivo as t', 't.id', '=', 'm.tipo_motivo_id')->where('t.codigo', 'erp.presupuestos.gasto_no_presupuestado')->where('m.codigo', $motivo)->value('m.id');
            $this->en($fecha, fn () => $this->compraDemo($pc99->id, $comentario, $proveedores[$k % count($proveedores)], $fecha, $monto, $cerrado ? $monto : self::r2(bcmul($monto, '0.5', 4)), $cerrado ? $monto : '0', $motivoId, $comentario));
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

    /** La ficha de Techquk como empresa del sistema, con tres sedes inventadas (ficha t2-presupuesto-empresa). */
    private function empresaYSedes(): void
    {
        if (app(EmpresaService::class)->ficha() !== null) {
            return;
        }

        $base = '2069900001';
        $techquk = app(PartiesService::class)->registrar(new DatosParty(Catalogo::RUC, $base.DocumentoIdentidad::digitoVerificadorRuc($base), razonSocial: 'Techquk S.A.C.'));
        $parametros = app(ParametrosService::class);
        $parametros->cambiarDesdeTexto(EmpresaService::PARAMETRO, $base.DocumentoIdentidad::digitoVerificadorRuc($base), $parametros->ficha(EmpresaService::PARAMETRO)['version']);

        foreach (['Sede principal Valle Norte' => 'Av. Los Próceres 1250, Valle Norte', 'Oficina Lima' => 'Calle Las Begonias 415, Lima', 'Almacén La Pampa' => 'Carretera a La Pampa km 4, Valle Norte'] as $nombre => $direccion) {
            app(SedesPartyService::class)->agregar($techquk->id, new DatosSede($nombre, $direccion, latitud: str_contains($nombre, 'Lima') ? '-12.0931' : '-6.7714', longitud: str_contains($nombre, 'Lima') ? '-77.0465' : '-79.8409'));
        }
    }

    /** El presupuesto 2026 de la empresa, aprobado en enero, con su gasto directo de enero a noviembre y un traspaso. */
    private function presupuestoEmpresa(): void
    {
        $s = app(PresupuestoEmpresaService::class);
        $sedes = array_keys($s->sedes());
        $p = $this->en('2025-12-15', fn (): int => $s->crear(2026));
        // [sede (índice), rubro, monto mensual, meses con monto distinto]
        $lineas = [
            [0, 'Alquiler y mantenimiento de local', '6500.00', []], [0, 'Servicios (luz, agua, internet, telefonía)', '1850.00', [1 => '2100.00', 2 => '2100.00', 12 => '2000.00']],
            [0, 'Movilidad y viáticos', '2400.00', []], [0, 'Útiles y suministros de oficina', '650.00', []],
            [1, 'Alquiler y mantenimiento de local', '4800.00', []], [1, 'Servicios (luz, agua, internet, telefonía)', '980.00', []], [1, 'Movilidad y viáticos', '1600.00', []],
            [2, 'Alquiler y mantenimiento de local', '3200.00', []], [2, 'Servicios (luz, agua, internet, telefonía)', '540.00', []],
            [3, 'Seguros', '0.00', [3 => '18600.00']], [3, 'Software y licencias', '2350.00', []], [3, 'Capacitación', '0.00', [5 => '6000.00', 10 => '6000.00']], [3, 'Publicidad y ferias', '0.00', [9 => '8500.00']],
        ];

        foreach ($lineas as [$sede, $rubro, $mensual, $distintos]) {
            $s->guardarLinea($p, $sedes[$sede] === 'empresa' ? null : (int) $sedes[$sede], $rubro, array_replace(array_fill(1, 12, $mensual), $distintos), null);
        }

        $this->en('2026-01-05', fn () => $s->aprobar($p));
        $proveedores = ['Inmobiliaria Los Próceres S.A.C.', 'Electro Norte S.A.', 'Transportes Ejecutivos del Valle E.I.R.L.', 'Librería y Útiles El Estudiante S.A.C.', 'Seguros Andinos S.A.', 'Nube Andina S.A.C.'];
        $cc = fn (string $codigo): int => (int) DB::table('centros_costo')->where('codigo', $codigo)->value('id');
        $variaciones = ['0.96', '1.00', '0.92', '1.04', '0.98', '0.95', '1.02', '0.97', '0.99', '1.08', '0.88'];

        for ($mes = 1; $mes <= 11; $mes++) {
            foreach ($lineas as $n => [$sede, $rubro, $mensual, $distintos]) {
                $monto = (string) ($distintos[$mes] ?? $mensual);

                if (bccomp($monto, '0', 2) === 0) {
                    continue;
                }

                $factor = str_starts_with($rubro, 'Alquiler') || $rubro === 'Seguros' ? '1' : $variaciones[($mes + $n) % count($variaciones)];

                // Oficina Lima se pasa en octubre con la movilidad (una visita larga al GORE), con su motivo.
                if ($sede === 1 && $rubro === 'Movilidad y viáticos' && $mes === 10) {
                    $factor = '1.85';
                }

                $gasto = bcadd(bcmul($monto, $factor, 4), '0.005', 2);
                $fecha = CarbonImmutable::parse(sprintf('2026-%02d-%02d', $mes, 8 + $n));
                $codigoCc = match (true) {
                    str_contains($rubro, 'Publicidad') => 'COM', str_contains($rubro, 'Capacitación') => 'RRHH', $sede === 2 => 'SUM', default => 'ADM'
                };
                $saldo = $s->saldo(2026, $sedes[$sede] === 'empresa' ? null : (int) $sedes[$sede], $mes)['saldo'];
                $motivo = bccomp($gasto, $saldo, 2) > 0 ? 'Viaje de una semana al GORE para la entrega de la red de datos.' : null;
                $this->en($fecha, fn () => $s->gastoDirecto($sedes[$sede] === 'empresa' ? null : (int) $sedes[$sede], $rubro, $cc($codigoCc), $fecha, $gasto, $mes < 11 ? $gasto : '0',
                    "{$rubro} · ".$fecha->translatedFormat('F'), $proveedores[$n % count($proveedores)], $motivo ?? (bccomp($gasto, $saldo, 2) > 0 ? 'Ajuste de tarifa del proveedor.' : null)));
            }
        }

        // Un traspaso: en noviembre, la sede principal cede movilidad a la Oficina Lima.
        $desde = (int) DB::table('presupuesto_empresa_lineas')->where('presupuesto_empresa_id', $p)->where('sede_id', $sedes[0])->where('rubro', 'Movilidad y viáticos')->value('id');
        $hacia = (int) DB::table('presupuesto_empresa_lineas')->where('presupuesto_empresa_id', $p)->where('sede_id', $sedes[1])->where('rubro', 'Movilidad y viáticos')->value('id');
        $this->en('2026-11-12', fn () => $s->traspasar($desde, 12, $hacia, 11, '600.00', 'La Oficina Lima tiene más visitas por la cartera del GORE.'));
    }

    /**
     * Un costo de la demo por el camino real (ficha t3-compras): orden directa de 100 unidades de su monto ÷ 100,
     * aprobada (con motivo si excede), recibida en la proporción de lo ejecutado y pagada si todo lo ejecutado se pagó.
     */
    private function compraDemo(int $partidaId, string $descripcion, string $proveedor, CarbonImmutable $fecha, string $monto, string $ejecutado, string $pagado, ?int $motivoId = null, ?string $comentario = null): void
    {
        $compras = app(ComprasService::class);
        $proyectoId = (int) DB::table('partidas_control')->where('id', $partidaId)->value('proyecto_id');
        $esGg = (bool) DB::table('partidas_control')->where('id', $partidaId)->value('es_gastos_generales');
        $orden = $compras->ordenDirecta($esGg ? 'servicio_puntual' : 'bien_proyecto', null, $proveedor, $proyectoId, '30 días', [[
            'descripcion' => mb_substr($descripcion, 0, 250), 'unidad_medida_id' => $this->unidad('GLB'), 'cantidad' => '100', 'precio_unitario' => bcdiv($monto, '100', 4),
            'partida_control_id' => $partidaId, 'motivo_id' => $motivoId, 'comentario' => $comentario,
        ]], $fecha->addDays(5));
        $compras->aprobarOrden($orden->id, $compras->excesos($orden->load('lineas')) === [] ? null : 'Ajuste de precio del proveedor.', $this->dev);

        $recibido = bcmul(bcdiv($ejecutado, $monto, 6), '100', 3);

        if (bccomp($recibido, '0', 3) > 0) {
            $linea = $orden->lineas()->first();
            $compras->recibir($orden->id, [$linea->id => $recibido], $fecha->addDays(4), 'GR-'.str_pad((string) $orden->id, 6, '0', STR_PAD_LEFT));
        }

        // Tramo 5: la factura del proveedor contra la orden y su pago por Tesorería.
        if (bccomp($recibido, '0', 3) > 0) {
            $fin = app(FinanzasService::class);
            $factura = $fin->registrarCompra($orden->id, sprintf('F%03d-%08d', 1 + $orden->id % 9, 4000 + $orden->id), null, $fecha->addDays(6), 30);

            if (bccomp($pagado, '0', 2) > 0 && bccomp($pagado, $ejecutado, 2) === 0) {
                $this->en($fecha->addDays(25), fn () => $fin->pagar($factura->id, $fecha->addDays(25)));
            } elseif ($orden->id % 3 === 0) {
                $fin->programar($factura->id, CarbonImmutable::parse('2026-11-27'));
            }
        }
    }

    /**
     * El proyecto de los escenarios del v4 (flujos 1 y 2, parte 8), con fechas de octubre y noviembre de 2026: presupuesto
     * con APU (costo directo 17,750.00; total 24,715.10), árbol de 9 partidas (19,525.00), contrato a 30 días (penalidad
     * 205.96; tope 2,471.51), carta fianza con comisión de 180.00 a PC-04 y las compras: cimentación reservada del stock,
     * cuadro comparativo del cable y OC de 650 m a 2.40 que excede PC-02.01 en 48.00, recibida 520 m (ejecutado 1,248.00).
     */
    private function plazaDeArmas(): void
    {
        $nombre = 'Videovigilancia de la Plaza de Armas de Valle Norte';
        $jefe = $this->jefes['rosa'];

        // Stock inicial del Almacén Central y los proveedores.
        $compras = app(ComprasService::class);
        $proveedores = [];
        $this->en('2026-09-01', function () use ($compras, &$proveedores): void {
            $kit = app(PreciosCatalogoService::class)->crear(['codigo' => 'CIM-KIT', 'descripcion' => 'Materiales de cimentación para poste (kit)', 'unidad_medida_id' => $this->unidad('NIU'),
                'familia_producto_id' => (int) DB::table('familias_producto')->where('codigo', 'VV-POS')->value('id')], '160.00', '120.00', '25');
            $rj = app(PreciosCatalogoService::class)->crear(['codigo' => 'CON-RJ45', 'descripcion' => 'Conector RJ45 Cat 6', 'unidad_medida_id' => $this->unidad('NIU'),
                'familia_producto_id' => (int) DB::table('familias_producto')->where('codigo', 'RD-CAB')->value('id')], '2.20', '1.50', '30');

            foreach ([[$kit->id, '6', '120.00'], [(int) DB::table('productos')->where('codigo', 'CAM-D4M')->value('id'), '8', '850.00'], [(int) DB::table('productos')->where('codigo', 'SW-24P')->value('id'), '2', '3104.20'],
                [(int) DB::table('productos')->where('codigo', 'CAB-UTP6')->value('id'), '5', '398.00'], [(int) DB::table('productos')->where('codigo', 'HDD-8T')->value('id'), '12', '720.00']] as [$producto, $cantidad, $costo]) {
                $compras->ingresoStock($producto, $cantidad, $costo, 'Inventario inicial', CarbonImmutable::parse('2026-09-01'));
            }

            foreach (['andino' => ['Proveedor Andino S.A.C.', '2069950001'], 'redes_norte' => ['Redes del Norte E.I.R.L.', '2069950002'], 'gruas' => ['Grúas y Montajes del Norte S.A.C.', '2069950003']] as $clave => [$razon, $base]) {
                $proveedores[$clave] = app(PartiesService::class)->registrar(new DatosParty(Catalogo::RUC, $base.DocumentoIdentidad::digitoVerificadorRuc($base), razonSocial: $razon))->id;
            }
        });

        // Flujo comercial y presupuesto con APU (PRE del v4: costo directo 17,750.00).
        $o = $this->en('2026-09-20', function () use ($nombre): Oportunidad {
            $o = $this->nuevaOportunidad('mpvn', $nombre, 'VIDEOVIGILANCIA', '24000.00', CarbonImmutable::parse('2026-10-23'), true);
            $this->avanzar($o, 'calificar');

            return $o->refresh();
        });
        $costeo = $this->en('2026-10-01', function () use ($o, $nombre): Presupuesto {
            $armado = app(ArmadoPresupuestoService::class);
            $pre = $armado->crear(new DatosPresupuesto($nombre, $this->pen, '10', '8', usaApu: true, oportunidadId: $o->id), $this->dev);
            $insumo = fn (string $c, string $n, string $t, string $u): int => DB::table('insumos')->where('codigo', $c)->value('id')
                ?? app(InsumosService::class)->crear(new DatosInsumo($c, $n, $t, $this->unidad($u)))->id;
            $tec = $insumo('MO-TEC', 'Técnico', Insumo::MANO_OBRA, 'HUR');
            $ayu = $insumo('MO-AYU', 'Ayudante', Insumo::MANO_OBRA, 'HUR');
            $hereda = fn (int $i, string $p, string $c = '1'): DatosLineaApu => new DatosLineaApu($i, cuadrilla: $c, precio: $p);
            $mat = fn (int $i, string $neta, string $p, string $d = '0'): DatosLineaApu => new DatosLineaApu($i, cantidadNeta: $neta, desperdicioPct: $d, precio: $p);
            $titulos = [];

            foreach (['01' => 'CÁMARAS', '02' => 'CABLEADO Y POSTES', '03' => 'CENTRO DE MONITOREO'] as $c => $n) {
                $titulos[$c] = $armado->agregarTitulo($pre->id, null, $c, $n)->id;
            }

            $partidas = [
                ['01', '01.01', 'Instalación de cámara domo IP', 'NIU', '10', ['0.5', 'hora'], [$mat($insumo('CAM-DOMO', 'Cámara domo IP', Insumo::MATERIAL, 'NIU'), '1', '583.00'), $hereda($tec, '18.00'), $hereda($ayu, '12.00')]],
                ['02', '02.01', 'Tendido de cable UTP', 'MTR', '600', ['20', 'hora'], [$mat($insumo('CAB-UTP6', 'Cable UTP Cat6', Insumo::MATERIAL, 'MTR'), '1', '2.40', '5'), $hereda($tec, '18.00'), $hereda($ayu, '12.00')]],
                ['02', '02.02', 'Instalación de poste metálico', 'NIU', '4', ['2', 'jornada'], [$mat($insumo('POS-MET', 'Poste metálico', Insumo::MATERIAL, 'NIU'), '1', '900.00'), $hereda($tec, '18.00'), $hereda($ayu, '12.00'),
                    new DatosLineaApu($insumo('EQ-GRUA', 'Camión grúa', Insumo::EQUIPO, 'HUR'), rendimiento: '8', unidadRendimiento: 'jornada', precio: '70.00')]],
                ['03', '03.01', 'NVR con almacenamiento', 'NIU', '1', ['0.125', 'hora'], [$mat($insumo('NVR-16', 'NVR de 16 canales', Insumo::MATERIAL, 'NIU'), '1', '4116.00'), $hereda($tec, '18.00'),
                    new DatosLineaApu($ayu, rendimiento: '0.25', unidadRendimiento: 'hora', precio: '12.00')]],
                ['03', '03.02', 'Pruebas y puesta en marcha', 'GLB', '1', ['0.5', 'hora'], [$hereda($tec, '18.00', '3'), $hereda($ayu, '12.00', '2'),
                    new DatosLineaApu($insumo('EQ-INST', 'Instrumental de pruebas', Insumo::EQUIPO, 'HUR'), rendimiento: '1', unidadRendimiento: 'jornada', precio: '10.50')]],
            ];

            foreach ($partidas as [$t, $codigo, $desc, $und, $metrado, [$rend, $urend], $lineas]) {
                $partida = $armado->agregarPartida($pre->id, $titulos[$t], $codigo, $desc, $this->unidad($und), $metrado, null, $rend, $urend);

                foreach ($lineas as $linea) {
                    $armado->agregarLineaApu($partida->id, $linea, PartidaDePresupuesto::query()->findOrFail($partida->id)->version);
                }
            }

            return $pre->refresh();
        });
        $cot = $this->en('2026-10-08', function () use ($o, $costeo): Cotizacion {
            $c = app(CotizacionesService::class)->crear($o->id, new DatosCotizacion(contactoId: $this->cuentas['mpvn']['decisor'], diasValidez: 30, plazoEntregaDias: 30, garantiaMeses: 24), $this->dev);
            $c = app(CotizacionesService::class)->enlazarPresupuesto($c->id, $c->refresh()->version, $costeo->id, '0', $this->dev);

            return $this->emitir($c->refresh());
        });
        $this->en('2026-10-09', fn () => $this->avanzar($o->refresh(), 'enviar_propuesta'));
        $this->en('2026-10-18', fn () => $this->avanzar($o->refresh(), 'negociar'));
        $this->en('2026-10-23', function () use ($o, $cot, $jefe): void {
            $cot->refresh();
            app(CotizacionesService::class)->aceptar($cot->id, $cot->version, CarbonImmutable::parse('2026-10-23'), TipoRespaldo::BuenaPro, 'AS-SM-22-2026-MPVN-1', null, null, $this->dev);
            $o->refresh();
            app(OportunidadesService::class)->ganar($o->id, $o->version, CarbonImmutable::parse('2026-10-23'), null, null, $this->dev,
                new DatosDelProyectoAlGanar($jefe->id, CarbonImmutable::parse('2026-11-06'), CarbonImmutable::parse('2026-12-06')));
        });
        $proyectoId = (int) app(ProyectosService::class)->deOportunidad($o->id);
        $contrato = Contrato::query()->where('proyecto_id', $proyectoId)->firstOrFail();
        $this->en('2026-10-27', fn () => app(ContratosService::class)->activar($contrato->id, 'Contrato N.° 118-2026-MPVN', CarbonImmutable::parse('2026-10-27'), '100 % a la conformidad', 24, '10', '0.40'));

        // El árbol del v4: separaron material de mano de obra; PC-04 son los gastos generales.
        $ids = [];
        $this->en('2026-10-30', function () use ($proyectoId, $jefe, &$ids): void {
            $servicio = app(ArbolPartidasService::class);
            $arbol = $servicio->crear($proyectoId, '19525.00', $this->dev);
            $nodos = [['PC-01', 'Cámaras', null, null], ['PC-01.01', 'Suministro de cámaras y conectores', 'PC-01', '5830.00'], ['PC-01.02', 'Mano de obra de instalación', 'PC-01', '600.00'],
                ['PC-02', 'Cableado y postes', null, null], ['PC-02.01', 'Cable', 'PC-02', '1512.00'], ['PC-02.02', 'Postes y cimentación', 'PC-02', '3880.00'], ['PC-02.03', 'Mano de obra', 'PC-02', '1380.00'],
                ['PC-03', 'Centro de monitoreo', null, '4548.00'], ['PC-04', 'Gastos generales', null, '1775.00']];

            foreach ($nodos as [$codigo, $nombre, $padre]) {
                $ids[$codigo] = $servicio->agregarPartida($arbol->id, $padre === null ? null : $ids[$padre], $codigo, $nombre, $jefe->id, $codigo === 'PC-04')->id;
            }

            foreach ($nodos as [$codigo, , , $monto]) {
                if ($monto !== null) {
                    $servicio->fijarPresupuestado($ids[$codigo], $monto, PartidaControl::query()->findOrFail($ids[$codigo])->version);
                }
            }

            $arbol->refresh();
            app(TransicionesService::class)->transicionar($arbol, 'aprobar', $arbol->version, $this->dev);
        });

        // Carta fianza de fiel cumplimiento: comisión 180.00, OS a PC-04.
        $this->en('2026-11-02', fn () => app(ContratosService::class)->registrarGarantia($contrato->id, 'fiel_cumplimiento', 'Banco Norte', 'CF-2026-00412', '2471.51',
            CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2027-01-05'), '180.00'));

        // REQ de postes: la cimentación hay en almacén (se reserva); la grúa y los conectores van a Compras.
        $this->en('2026-11-10', function () use ($compras, $proyectoId, $ids, $proveedores): void {
            $req = $compras->crearRequerimiento($proyectoId, 'Instalación de postes', CarbonImmutable::parse('2026-11-19'), [
                ['tipo' => 'bien_proyecto', 'producto_id' => (int) DB::table('productos')->where('codigo', 'CIM-KIT')->value('id'), 'descripcion' => 'Materiales de cimentación', 'unidad_medida_id' => $this->unidad('NIU'), 'cantidad' => '4', 'partida_control_id' => $ids['PC-02.02']],
                ['tipo' => 'alquiler_equipo', 'descripcion' => 'Alquiler de grúa · 1 día', 'unidad_medida_id' => $this->unidad('GLB'), 'cantidad' => '1', 'precio_estimado' => '650.00', 'partida_control_id' => $ids['PC-04']],
                ['tipo' => 'bien_proyecto', 'producto_id' => (int) DB::table('productos')->where('codigo', 'CON-RJ45')->value('id'), 'descripcion' => 'Conectores RJ45 de repuesto', 'unidad_medida_id' => $this->unidad('NIU'), 'cantidad' => '10', 'partida_control_id' => $ids['PC-01.01']],
            ], $this->jefes['diego']);
            $compras->enviar($req->id);
            $compras->aprobar($req->id, null, $this->dev);
            $cuadro = $compras->crearCuadro($req->id);
            $lineas = $req->lineas()->where('estado', 'en_cuadro')->pluck('id', 'descripcion');
            $compras->agregarCotizacion($cuadro->id, $proveedores['gruas'], 1, 'contado', '4.0', [$lineas['Alquiler de grúa · 1 día'] => '650.00', $lineas['Conectores RJ45 de repuesto'] => '1.60']);
        });

        // El cable: cuadro con dos cotizaciones, OC de 650 m a 2.40 que excede PC-02.01 en 48.00, recibida 520 m.
        $orden = $this->en('2026-11-11', function () use ($compras, $proyectoId, $ids, $proveedores): Orden {
            $req = $compras->crearRequerimiento($proyectoId, 'Cable para el tendido de la plaza', CarbonImmutable::parse('2026-11-18'), [
                ['tipo' => 'bien_proyecto', 'descripcion' => 'Cable UTP Cat6 exterior', 'unidad_medida_id' => $this->unidad('MTR'), 'cantidad' => '650', 'precio_estimado' => '2.40', 'partida_control_id' => $ids['PC-02.01']],
            ], $this->jefes['diego']);
            $compras->enviar($req->id);
            $compras->aprobar($req->id, 'Faltaron 50 m por el desvío del tendido por la pileta.', $this->dev);
            $cuadro = $compras->crearCuadro($req->id);
            $linea = (int) $req->lineas()->value('id');
            $andino = $compras->agregarCotizacion($cuadro->id, $proveedores['andino'], 2, '30 días', '4.5', [$linea => '2.40']);
            $compras->agregarCotizacion($cuadro->id, $proveedores['redes_norte'], 1, 'contado', '3.8', [$linea => '2.60']);

            return $compras->adjudicar($cuadro->id, $andino->id, 'Menor precio, crédito a 30 días y buen historial; el día adicional no afecta el hito del 19.', 'Obra · Plaza de Armas', CarbonImmutable::parse('2026-11-18'), $this->dev);
        });
        $this->en('2026-11-13', fn () => $compras->aprobarOrden($orden->id, 'El tendido real es 650 m; la diferencia de 48.00 se absorbe en el margen de la partida de postes.', $this->dev));
        $this->en('2026-11-18', function () use ($compras, $orden): void {
            $compras->recibir($orden->id, [(int) $orden->lineas()->value('id') => '520'], CarbonImmutable::parse('2026-11-18'), 'GR-001-004512', 'obra', 'Recibido en obra por el especialista.');
            $orden->update(['seguimiento' => 'Faltan 130 m · el proveedor confirmó para el 20/11']);
        });
    }

    /** Tramo 4 (fichas t4-personal-tareo y t4-valorizaciones): la gente, el tareo del v4, valorizaciones y un subcontrato. */
    private function tramo4(): void
    {
        $plaza = (int) DB::table('proyectos')->where('nombre', 'like', '%Plaza de Armas%')->value('id');
        $p8 = (int) DB::table('proyectos')->where('nombre', 'like', 'Red de datos y enlaces%')->value('id');
        $p6 = (int) DB::table('proyectos')->where('nombre', 'like', 'Sistema de información hospitalaria%')->value('id');
        $p9 = (int) DB::table('proyectos')->where('nombre', 'like', 'Historia clínica%')->value('id');
        $cat = fn (string $c): int => (int) DB::table('categorias_personal')->where('codigo', $c)->value('id');
        $col = [];

        foreach ([['julio', 'Julio Chávez Sandoval', '40112233', 'TEC', 'eventual', '2027-03-31'], ['luis', 'Luis Vela Paredes', '40223344', 'AYU', 'eventual', '2027-03-31'],
            ['marco', 'Marco Ruiz Ugaz', '40334455', 'ESP', 'planilla', '2027-06-30'], ['carla', 'Carla Ríos Seminario', '40445566', 'ESP', 'planilla', '2027-06-30'],
            ['pedro', 'Pedro Salas Cieza', '40556677', 'DES', 'planilla', null], ['tomas', 'Tomás León Fiestas', '40667788', 'TEC', 'eventual', '2026-10-31']] as [$k, $n, $dni, $c, $v, $sctr]) {
            $col[$k] = Colaborador::query()->create(['nombres' => $n, 'dni' => $dni, 'categoria_id' => $cat($c), 'vinculo' => $v, 'habilitado_hasta' => $sctr])->id;
        }

        $s = app(PersonalService::class);
        $d = fn (string $f): CarbonImmutable => CarbonImmutable::parse($f);
        $s->asignar($col['julio'], $plaza, 100, $d('2026-11-06'), $d('2026-12-06'));
        $s->asignar($col['luis'], $plaza, 100, $d('2026-11-06'), $d('2026-12-06'));
        $s->asignar($col['marco'], $plaza, 60, $d('2026-11-09'), $d('2026-12-06'));
        $s->asignar($col['marco'], $p8, 60, $d('2026-11-23'), null);
        $s->asignar($col['carla'], $p8, 100, $d('2026-06-01'), null);
        $s->asignar($col['pedro'], $p6, 50, $d('2025-09-15'), null);
        $s->asignar($col['pedro'], $p9, 50, $d('2026-11-23'), null);

        // La semana del v4: 68 h por S/ 1,080.00; PC-02.03 = 648.00; PC-01.02 = 432.00 (72 %).
        $pc = fn (string $codigo): int => (int) DB::table('partidas_control')->where('proyecto_id', $plaza)->where('codigo', $codigo)->value('id');
        $this->en('2026-11-14', function () use ($s, $plaza, $col, $pc, $d): void {
            $t = $s->semana($plaza, $d('2026-11-09'));
            $s->tarear($t->id, $col['julio'], $pc('PC-02.03'), ['lu' => '8', 'ma' => '8', 'mi' => '4']);
            $s->tarear($t->id, $col['julio'], $pc('PC-01.02'), ['mi' => '4', 'ju' => '8', 'vi' => '8', 'sa' => '4']);
            $s->tarear($t->id, $col['luis'], $pc('PC-02.03'), ['lu' => '8', 'ma' => '8', 'mi' => '8']);
            $s->cerrar($t->id, $this->dev);
        });
        $this->en('2026-11-19', function () use ($s, $plaza, $col, $pc, $d): void {
            $t = $s->semana($plaza, $d('2026-11-16'));
            $s->tarear($t->id, $col['julio'], $pc('PC-02.03'), ['lu' => '8', 'ma' => '8', 'mi' => '8']);
            $s->tarear($t->id, $col['luis'], $pc('PC-02.03'), ['lu' => '8', 'ma' => '6', 'mi' => '8']);
        });

        // Valorizaciones de P8 con adelanto directo del 10 % y la fórmula polinómica del presupuesto.
        $v = app(ValorizacionesService::class);
        $ct = Contrato::query()->where('proyecto_id', $p8)->firstOrFail();
        $v->registrarAdelanto($ct->id, 'directo', '100064.00', '10', '2026-06-05');

        foreach ([['2026-07', ['01.01' => '20', '01.02' => '30']], ['2026-08', ['01.01' => '25', '01.02' => '40', '02.01' => '30']],
            ['2026-09', ['01.01' => '25', '02.01' => '40', '03.01' => '30']], ['2026-10', ['01.01' => '15', '01.02' => '20', '03.01' => '30', '03.02' => '50']]] as $n => [$periodo, $avances]) {
            $this->en(CarbonImmutable::parse($periodo.'-28'), function () use ($v, $ct, $periodo, $avances, $n): void {
                $val = $v->armar($ct->id, $periodo, $avances);

                if ($n < 3) {
                    $v->aprobar($val->id);
                }
            });
        }

        // El subcontrato del v4: 70,000.00 con adelanto 20 % y retención 5 %; la valorización 2 queda por aprobar.
        $hojas = DB::table('partidas_control')->where('proyecto_id', $p8)->where('es_hoja', true)->where('es_no_presupuestado', false)->where('es_gastos_generales', false)->orderBy('codigo')->pluck('id')->all();
        $sc = $this->en('2026-07-20', fn () => $v->crearSubcontrato($p8, 'Obras Civiles Norte E.I.R.L.', 'Obra civil del centro de datos y canalizaciones', '20', '5', [$hojas[3] => '50000.00', $hojas[0] => '20000.00']));
        $this->en('2026-08-30', function () use ($v, $sc): void {
            $val = $v->valorizarSubcontrato($sc->id, '2026-08', '35');
            $v->aprobarSubcontrato($val->id);
            $val->update(['estado' => 'pagada']);
        });
        $this->en('2026-09-30', fn () => $v->valorizarSubcontrato($sc->id, '2026-09', '40'));
    }

    /** Tramo 5 (ficha t5-finanzas): lo que cada proyecto facturó y cobró, la factura del cable, y fondos con su rendición. */
    private function tramo5(): void
    {
        $fin = app(FinanzasService::class);
        $contratos = app(ContratosService::class);
        // [proyecto (nombre empieza con), [[% del vendido, emisión, cobrado el o null]]]
        $plan = [
            ['Videovigilancia y centro de monitoreo', [['100', '2024-11-20', '2025-01-15']]],
            ['Cableado estructurado', [['100', '2024-09-15', '2024-10-20']]],
            ['Suministro de 60 computadoras', [['100', '2024-11-04', '2024-12-05']]],
            ['Sala de videoconferencia', [['50', '2025-02-12', '2025-02-14'], ['50', '2025-03-25', '2025-04-24']]],
            ['Mantenimiento de CCTV', [['25', '2026-04-05', '2026-05-04'], ['25', '2026-07-05', '2026-08-03'], ['25', '2026-10-05', null]]],
            ['Sistema de información hospitalaria', [['30', '2025-12-20', '2026-02-10'], ['25', '2026-05-20', '2026-07-15'], ['15', '2026-10-20', null]]],
            ['Telemetría de pozos', [['50', '2025-04-25', '2025-04-28'], ['50', '2025-09-05', '2025-10-06']]],
        ];

        foreach ($plan as [$nombre, $facturas]) {
            $pid = (int) DB::table('proyectos')->where('nombre', 'like', $nombre.'%')->value('id');
            $ct = Contrato::query()->where('proyecto_id', $pid)->firstOrFail();
            $vendido = (string) $contratos->ventaNeta($pid);

            foreach ($facturas as [$pct, $emision, $cobro]) {
                $comp = $this->en($emision, fn () => $fin->facturarContrato($ct->id, bcadd(bcdiv(bcmul($vendido, $pct, 6), '100', 6), '0.005', 2), CarbonImmutable::parse($emision)));

                if ($cobro !== null) {
                    $this->en($cobro, fn () => $fin->cobrar($comp->id, CarbonImmutable::parse($cobro)));
                }
            }
        }

        // P8 factura sus valorizaciones aprobadas; cobra las dos primeras.
        foreach (DB::table('valorizaciones')->where('estado', 'aprobada')->orderBy('numero')->get() as $n => $v) {
            $emision = CarbonImmutable::parse($v->periodo.'-28')->addDays(5);
            $comp = $this->en($emision, fn () => $fin->facturarValorizacion($v->id, $emision));

            if ($n < 2) {
                $this->en($emision->addDays(32), fn () => $fin->cobrar($comp->id, $emision->addDays(32)));
            }
        }

        // Plaza de Armas: la factura del cable por lo recibido (1,248.00 + IGV), por pagar; y la rendición de Julio.
        $plaza = (int) DB::table('proyectos')->where('nombre', 'like', '%Plaza de Armas%')->value('id');
        $cable = (int) DB::table('ordenes')->where('proyecto_id', $plaza)->where('proveedor_nombre', 'Proveedor Andino S.A.C.')->value('id');
        $this->en('2026-11-19', fn () => $fin->registrarCompra($cable, 'F001-00004512', '2069950001'.DocumentoIdentidad::digitoVerificadorRuc('2069950001'), CarbonImmutable::parse('2026-11-19'), 30));
        $pc = fn (string $c): int => (int) DB::table('partidas_control')->where('proyecto_id', $plaza)->where('codigo', $c)->value('id');
        $this->en('2026-11-10', function () use ($fin, $plaza, $pc): void {
            $f = $fin->crearFondo($plaza, 'rendicion', 'Julio Chávez Sandoval', '800.00', CarbonImmutable::parse('2026-11-10'));
            $a = $fin->registrarGasto($f->id, CarbonImmutable::parse('2026-11-11'), 'Movilidad a la obra (semana)', 'B002-000318', '120.00', $pc('PC-04'));
            $b = $fin->registrarGasto($f->id, CarbonImmutable::parse('2026-11-12'), 'Ferretería: tarugos, cintillos y cinta aislante', 'B011-004422', '86.50', $pc('PC-02.03'));
            $fin->registrarGasto($f->id, CarbonImmutable::parse('2026-11-18'), 'Almuerzo de la cuadrilla (3 personas)', 'B003-001207', '54.00', $pc('PC-04'), true);
            $fin->aprobarGasto($a->id);
            $fin->aprobarGasto($b->id);
        });

        $p8 = (int) DB::table('proyectos')->where('nombre', 'like', 'Red de datos y enlaces%')->value('id');
        $gg8 = (int) DB::table('partidas_control')->where('proyecto_id', $p8)->where('es_gastos_generales', true)->where('es_hoja', true)->value('id');
        $this->en('2026-10-01', function () use ($fin, $p8, $gg8): void {
            $f = $fin->crearFondo($p8, 'caja_chica', 'Carla Ríos Seminario', '1500.00', CarbonImmutable::parse('2026-10-01'));

            foreach ([['2026-10-06', 'Fotocopias de planos y expediente', '45.00'], ['2026-10-14', 'Movilidad a la sede descentralizada', '180.00'], ['2026-10-22', 'Útiles para el almacén de obra', '96.40']] as [$fecha, $desc, $monto]) {
                $fin->aprobarGasto($fin->registrarGasto($f->id, CarbonImmutable::parse($fecha), $desc, 'B001-0'.random_int(10000, 99999), $monto, $gg8)->id);
            }

            $fin->rendir($f->id, CarbonImmutable::parse('2026-10-31'));
        });

        // MV-014. Moneda extranjera (v4 F10): importación por USD 6,000 a 3.740 (22,440.00), pagada a 3.752 → diferencia 72.00.
        $p6 = (int) DB::table('proyectos')->where('nombre', 'like', 'Sistema de información hospitalaria%')->value('id');
        $srv = (int) DB::table('partidas_control')->where('proyecto_id', $p6)->where('es_hoja', true)->where('nombre', 'like', '%Servidor%')->value('id');
        $compras = app(ComprasService::class);
        $imp = $this->en('2026-10-05', function () use ($compras, $p6, $srv): Orden {
            $o = $compras->ordenDirecta('importacion', null, 'Dataserv Inc. (Miami)', $p6, '30 días', [['descripcion' => 'Switch de núcleo importado (2 unidades)', 'unidad_medida_id' => $this->unidad('NIU'),
                'cantidad' => '2', 'precio_unitario' => '11220.00', 'partida_control_id' => $srv]], CarbonImmutable::parse('2026-10-20'));
            $compras->aprobarOrden($o->id, 'Importación para reemplazar los switches del servidor.', $this->dev);
            $compras->recibir($o->id, [$o->lineas()->value('id') => '2'], CarbonImmutable::parse('2026-10-20'), 'DUA 118-2026-10-004512', 'obra');

            return $o;
        });
        $usd = $this->en('2026-10-21', fn () => $fin->registrarCompra($imp->id, 'INV-2026-0918', null, CarbonImmutable::parse('2026-10-21'), 30, '6000.00', 'manual', 'USD', '3.7400'));
        $this->en('2026-11-12', fn () => $fin->pagar($usd->id, CarbonImmutable::parse('2026-11-12'), FinanzasService::CUENTAS[0], '3.7520'));

        // Una factura de proveedor leída de su XML (P8).
        $orden8 = DB::table('ordenes')->where('proyecto_id', $p8)->where('estado', 'recibida')->whereNotExists(fn ($q) => $q->from('comprobantes')->whereColumn('comprobantes.orden_id', 'ordenes.id'))->first();

        if ($orden8 === null) {
            $o = $compras->ordenDirecta('servicio_puntual', null, 'Redes y Fibra del Pacífico S.A.C.', $p8, '30 días', [['descripcion' => 'Certificación de puntos de red (lote 3)', 'unidad_medida_id' => $this->unidad('GLB'), 'cantidad' => '1', 'precio_unitario' => '3200.00', 'partida_control_id' => $hojasP8 = (int) DB::table('partidas_control')->where('proyecto_id', $p8)->where('es_hoja', true)->where('es_no_presupuestado', false)->orderBy('codigo')->value('id')]], CarbonImmutable::parse('2026-11-15'));
            $compras->aprobarOrden($o->id, 'Certificación.', $this->dev);
            $compras->recibir($o->id, [$o->lineas()->value('id') => '1'], CarbonImmutable::parse('2026-11-15'), null);
            $orden8 = DB::table('ordenes')->find($o->id);
        }

        $base = DB::table('orden_lineas')->where('orden_id', $orden8->id)->selectRaw('SUM(ROUND(cantidad_recibida * precio_unitario, 2)) AS b')->value('b');
        $igv = bcadd(bcmul((string) $base, '0.18', 4), '0.005', 2);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
            .'<cbc:ID>F007-00012874</cbc:ID><cbc:IssueDate>2026-11-17</cbc:IssueDate><cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>'
            .'<cac:AccountingSupplierParty><cac:Party><cac:PartyIdentification><cbc:ID schemeID="6">20699600016</cbc:ID></cac:PartyIdentification><cac:PartyLegalEntity><cbc:RegistrationName>'.htmlspecialchars($orden8->proveedor_nombre).'</cbc:RegistrationName></cac:PartyLegalEntity></cac:Party></cac:AccountingSupplierParty>'
            .'<cac:TaxTotal><cbc:TaxAmount currencyID="PEN">'.$igv.'</cbc:TaxAmount></cac:TaxTotal><cac:LegalMonetaryTotal><cbc:LineExtensionAmount currencyID="PEN">'.bcadd((string) $base, '0', 2).'</cbc:LineExtensionAmount><cbc:PayableAmount currencyID="PEN">'.bcadd((string) $base, $igv, 2).'</cbc:PayableAmount></cac:LegalMonetaryTotal></Invoice>';
        $this->en('2026-11-18', fn () => $fin->registrarCompraXml((int) $orden8->id, $xml, 30));

        // Servicios con vigencia; la orden de noviembre del SCTR de P8.
        foreach ([['sctr', 'Seguros Andinos S.A.', 'SCTR del personal de obra de la red del GORE', $p8, 'mensual', '850.00', '2026-06-01', '2026-12-05'],
            ['poliza', 'Seguros Andinos S.A.', 'Póliza todo riesgo de equipos y herramientas', null, 'anual', '4800.00', '2025-12-18', '2026-12-18'],
            ['alquiler', 'Inmobiliaria Las Begonias S.A.C.', 'Alquiler de la Oficina Lima', null, 'mensual', '4800.00', '2026-03-01', '2027-02-28'],
            ['licencia', 'Distribuidora de Software Andina S.A.C.', 'Licencias de presupuestos (5 usuarios)', null, 'anual', '6200.00', '2026-02-01', '2027-01-31'],
            ['suscripcion', 'Nube Andina S.A.C.', 'Servidor en la nube de la telemetría de clientes', null, 'mensual', '420.00', '2026-01-01', '2027-06-30']] as [$tipo, $prov, $desc, $pry, $per, $monto, $desde, $hasta]) {
            $id = $fin->crearServicio($tipo, $prov, $desc, $pry, null, $per, $monto, $desde, $hasta);

            if ($tipo === 'sctr') {
                $this->en('2026-11-03', fn () => $fin->ordenDelPeriodo($id, CarbonImmutable::parse('2026-11-03')));
            }
        }

        // Liquidación de dos contratos ya culminados, y un consorcio (P9: Techquk 40 %).
        foreach (['Cableado estructurado', 'Telemetría de pozos'] as $nombre) {
            $ct = Contrato::query()->where('proyecto_id', (int) DB::table('proyectos')->where('nombre', 'like', $nombre.'%')->value('id'))->firstOrFail();
            $contratos->liquidar($ct->id, CarbonImmutable::parse('2026-02-15'));
        }

        Contrato::query()->where('proyecto_id', (int) DB::table('proyectos')->where('nombre', 'like', 'Historia clínica%')->value('id'))
            ->update(['consorcio' => 'Consorcio Salud Digital Norte', 'participacion_pct' => 40, 'modo_consorcio' => 'participacion']);

        // Conciliado todo lo que pasó hasta octubre.
        DB::table('movimientos_caja')->where('fecha', '<', '2026-11-01')->update(['conciliado' => true]);
    }

    /**
     * Una vendedora con un rol corto (ficha op-solicitudes-permiso), para ver los botones bloqueados; y sus pedidos de
     * permiso: dos pendientes, uno concedido y uno rechazado. Entra con ventas.demo@techquk.test y la clave de la fábrica.
     */
    private function solicitudesPermiso(): void
    {
        $rol = Role::findOrCreate('comercial-demo', 'web');
        $rol->syncPermissions(Permission::query()->where(fn ($q) => $q->where('name', 'like', 'crm.%.ver')->orWhereIn('name', ['crm.actividades.registrar', 'crm.cotizaciones.crear', 'crm.oportunidades.crear']))->pluck('name')->all());
        $persona = Party::factory()->create(['numero_documento' => '00000021', 'nombres' => 'Valeria', 'apellidos' => 'Montalván Ríos', 'razon_social' => 'Montalván Ríos Valeria']);
        $vendedora = User::factory()->create(['party_id' => $persona->id, 'name' => 'Valeria Montalván Ríos', 'email' => 'ventas.demo@techquk.test', 'es_activo' => true]);
        $vendedora->assignRole($rol);
        $s = app(SolicitudesPermisoService::class);

        foreach ([['crm.oportunidades.oportunidad.ganar', 'Ganar oportunidad', 'Cierro las privadas pequeñas yo misma.'], ['crm.licitaciones.gestionar', 'Registrar convocatoria', 'Me tocó llevar las convocatorias de la UGEL.'],
            ['crm.cuentas.crear', 'Nueva cuenta', 'Para registrar los leads de la feria.'], ['crm.catalogo.gestionar', 'Nuevo precio', null]] as $i => [$permiso, $accion, $motivo]) {
            $s->solicitar($vendedora, $permiso, $accion, $motivo);
        }

        $ids = DB::table('solicitudes_permiso')->where('user_id', $vendedora->id)->orderBy('id')->pluck('id')->all();
        $s->conceder($ids[2], $rol->id, $this->dev);
        $s->rechazar($ids[3], 'Los precios del catálogo los cambia solo Compras.', $this->dev);
    }

    /** Una muestra OCDS inventada (ficha t1-licitaciones v2), importada por el mismo lector que usa la sincronización. */
    private function convocatoriasSeace(): void
    {
        $ruc = fn (string $base): string => $base.DocumentoIdentidad::digitoVerificadorRuc($base);
        $release = fn (string $ocid, string $nom, string $entidad, string $rucE, string $region, string $titulo, string $metodo, ?string $valor, string $pub, string $pres, string $bp) => [
            'ocid' => "ocds-dgv273-{$ocid}", 'date' => "{$pub}T09:00:00-05:00", 'buyer' => ['id' => "PE-RUC-{$rucE}", 'name' => $entidad],
            'parties' => [['id' => "PE-RUC-{$rucE}", 'name' => $entidad, 'identifier' => ['scheme' => 'PE-RUC', 'id' => $rucE], 'address' => ['region' => $region], 'roles' => ['buyer']]],
            'tender' => ['id' => $nom, 'title' => $titulo, 'procurementMethodDetails' => $metodo, 'value' => $valor === null ? null : ['amount' => (float) $valor, 'currency' => 'PEN'],
                'tenderPeriod' => ['endDate' => "{$pres}T23:59:00-05:00"], 'awardPeriod' => ['startDate' => "{$bp}T10:00:00-05:00"],
                'documents' => [['url' => 'https://prod2.seace.gob.pe/seacebus-uiwd-pub/buscadorPublico/buscadorPublico.xhtml', 'documentType' => 'biddingDocuments']]],
        ];
        $paquete = ['uri' => 'demo', 'version' => '1.1', 'releases' => [
            $release('1001', 'AS-SM-31-2026-MPVN-1', 'Municipalidad Provincial de Valle Norte', $ruc('2069910001'), 'Valle Norte', 'Adquisición e instalación de cámaras de videovigilancia para el malecón', 'Adjudicación Simplificada', '286000', '2026-11-16', '2026-12-02', '2026-12-09'),
            $release('1002', 'LP-SM-12-2026-GRVN-1', 'Gobierno Regional de Valle Norte', $ruc('2069910005'), 'Valle Norte', 'Ampliación de la red de datos y fibra óptica de las sedes provinciales', 'Licitación Pública', '1450000', '2026-11-12', '2026-12-10', '2026-12-18'),
            $release('1003', 'CP-SM-9-2026-HRVN-1', 'Hospital Regional de Valle Norte', $ruc('2069910004'), 'Valle Norte', 'Implementación de software de gestión de farmacia y almacén', 'Concurso Público', '520000', '2026-11-18', '2026-12-15', '2026-12-22'),
            $release('1004', 'AS-SM-7-2026-MDLP-1', 'Municipalidad Distrital de Las Palmeras', $ruc('2069960001'), 'Valle Norte', 'Servicio de instalación de wifi gratuito en plazas y parques', 'Adjudicación Simplificada', '98000', '2026-11-17', '2026-11-28', '2026-12-04'),
            $release('1005', 'SIE-21-2026-DIRESA-VN-1', 'Dirección Regional de Salud Valle Norte', $ruc('2069960002'), 'Valle Norte', 'Adquisición de servidores y switch para el centro de datos regional', 'Subasta Inversa Electrónica', '640000', '2026-11-19', '2026-12-03', '2026-12-05'),
            $release('1006', 'AS-SM-15-2026-UNVN-1', 'Universidad Nacional del Valle Norte', $ruc('2069910007'), 'Valle Norte', 'Cableado estructurado del nuevo pabellón de ingeniería', 'Adjudicación Simplificada', '240000', '2026-11-14', '2026-11-27', '2026-12-03'),
            $release('1007', 'LP-SM-3-2026-MDLP-1', 'Municipalidad Distrital de Las Palmeras', $ruc('2069960001'), 'Valle Norte', 'Mejoramiento de pistas y veredas del jirón Libertad', 'Licitación Pública', '2300000', '2026-11-15', '2026-12-12', '2026-12-20'),
            $release('1008', 'AS-SM-40-2026-UGEL-1', 'Unidad de Gestión Educativa Local Valle Norte', $ruc('2069910002'), 'Valle Norte', 'Adquisición de uniformes para el personal administrativo', 'Adjudicación Simplificada', '64000', '2026-11-18', '2026-11-30', '2026-12-05'),
            $release('1009', 'COMPRE-88-2026-MPVN-1', 'Municipalidad Provincial de Valle Norte', $ruc('2069910001'), 'Valle Norte', 'Mantenimiento de cámaras de videovigilancia del centro histórico', 'Comparación de Precios', '12500', '2026-11-19', '2026-11-26', '2026-11-27'),
        ]];
        $s = app(ConvocatoriasSeaceService::class);
        $s->importar((string) json_encode($paquete));
        $s->registrar((int) DB::table('convocatorias_seace')->where('ocid', 'ocds-dgv273-1002')->value('id'), $this->dev);
        $s->descartar((int) DB::table('convocatorias_seace')->where('ocid', 'ocds-dgv273-1003')->value('id'), 'No es nuestro rubro');
    }
}
