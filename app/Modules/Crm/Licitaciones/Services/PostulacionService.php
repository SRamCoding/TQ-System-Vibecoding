<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Services;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Services\CalculoCotizacion;
use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Models\ConsultaLicitacion;
use App\Modules\Crm\Licitaciones\Models\DocumentoLicitacion;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Licitaciones\Models\SubsanacionLicitacion;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Erp\Presupuestos\Services\ResumenPresupuesto;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * MVP rápido (observación 7.3, MV-076): la postulación se gestiona entera desde el sistema y en el SEACE solo se envía,
 * a mano, por una persona (el SEACE no deja que otro sistema registre participación, consultas ni ofertas; no se
 * automatiza el portal ni se guardan sus claves). Ocho pasos en orden: participar en el SEACE, consultas y
 * observaciones, bases integradas, expediente, oferta económica, paquete para el SEACE, presentar y resultado.
 */
final readonly class PostulacionService
{
    /** Los ocho pasos de la ruta, en orden. */
    public const array PASOS = [
        1 => 'Participar en el SEACE', 2 => 'Consultas y observaciones', 3 => 'Bases integradas', 4 => 'Expediente',
        5 => 'Oferta económica', 6 => 'Paquete para el SEACE', 7 => 'Presentar en el SEACE', 8 => 'Resultado',
    ];

    /** A menos de estos días, la cuenta regresiva y los plazos van en coral. */
    public const int DIAS_URGENTE = 5;

    /** El buscador público del SEACE: el sistema solo lo abre; la persona entra con su certificado, usuario y clave. */
    public const string URL_SEACE = 'https://prod2.seace.gob.pe/seacebus-uiwd-pub/buscadorPublico/buscadorPublico.xhtml';

    /**
     * Lo que la IA propone consultar leyendo las bases (en el MVP, una lista fija; la lectura real llega con la IA
     * configurada). Queda en borrador hasta que una persona la revisa y la envía.
     */
    public const array CONSULTAS_SUGERIDAS = [
        ['consulta', 'Capítulo III, numeral 3.1', 'Se solicita confirmar si las cámaras pueden ser de 4 MP con lente varifocal, equivalentes a las de 5 MP fijas pedidas en las especificaciones técnicas.'],
        ['observacion', 'Capítulo II, numeral 2.2.1.2', 'Se observa que la experiencia del postor exigida (tres veces el valor referencial) restringe la participación; se solicita reducirla a una vez el valor referencial.'],
    ];

    /**
     * El estado de los ocho pasos: hecho, actual o por hacer, con su línea de resumen. El actual es el primero sin hacer.
     *
     * @return array{pasos: list<array{numero: int, nombre: string, estado: string, resumen: string}>, actual: int}
     */
    public function ruta(Licitacion $l): array
    {
        $presentacion = $this->fechaPresentacion($l);
        $presentada = in_array($l->estado, [EstadoLicitacion::Presentada, EstadoLicitacion::BuenaPro, EstadoLicitacion::Perdida, EstadoLicitacion::Desierta], true);
        $cerrada = in_array($l->estado, [EstadoLicitacion::BuenaPro, EstadoLicitacion::Perdida, EstadoLicitacion::Desierta], true);
        $consultas = $l->consultas;
        $enviadas = $consultas->whereIn('estado', ['enviada', 'absuelta'])->count();
        $docs = $this->documentos($l);
        $oferta = $this->oferta($l);
        $buenaPro = $l->hitos->firstWhere('etapa', 'Buena pro')?->fecha;
        $sub = $l->subsanaciones->whereNull('respondida_el')->first();

        $hechos = [
            1 => $l->registrado_seace_el !== null || $presentada,
            2 => $l->consultas_absueltas_el !== null || $presentada || ($consultas->isNotEmpty() && $consultas->every(fn (ConsultaLicitacion $c): bool => $c->estado === 'absuelta')),
            3 => $l->bases_integradas_el !== null || $presentada,
            4 => $docs['faltan'] === 0 && $docs['aplican'] > 0 || $presentada,
            5 => $l->monto_ofertado !== null,
            6 => $l->paquete_armado_at !== null || $presentada,
            7 => $presentada,
            8 => $cerrada,
        ];
        $resumenes = [
            1 => $l->registrado_seace_el !== null ? 'Registrado el '.self::dia($l->registrado_seace_el) : ($presentada ? 'Registrado' : ($presentacion === null ? 'Registrarse como participante' : 'Registrarse hasta el '.self::dia($presentacion->subDay()))),
            2 => $consultas->isEmpty() ? 'Sin consultas' : $enviadas.($enviadas === 1 ? ' enviada' : ' enviadas').($l->consultas_absueltas_el !== null ? ' · absueltas el '.self::dia($l->consultas_absueltas_el) : ($consultas->where('estado', 'borrador')->count() > 0 ? ' · '.$consultas->where('estado', 'borrador')->count().' en borrador' : '')),
            3 => $l->bases_integradas_el !== null ? 'Descargadas el '.self::dia($l->bases_integradas_el) : (($h = $l->hitos->firstWhere('etapa', 'Integración de bases')) ? 'Se publican el '.self::dia($h->fecha) : 'Por descargar'),
            4 => "{$docs['listos']} de {$docs['aplican']} documentos listos",
            5 => $l->monto_ofertado === null ? ($oferta === null ? 'Sin presupuesto todavía' : 'Por decidir el precio') : 'S/ '.number_format((float) $l->monto_ofertado, 2).($oferta !== null && ($m = $this->margenDe($oferta, (string) $l->monto_ofertado)) !== null ? ' · margen '.$m.' %' : ''),
            6 => $l->paquete_armado_at !== null ? 'Armado el '.self::dia($l->paquete_armado_at) : ($presentada ? 'Subido con la propuesta' : 'Se arma cuando el expediente esté completo'),
            7 => $l->presentada_at !== null ? 'Presentada el '.self::dia($l->presentada_at).' · '.$l->presentada_at->format('H:i') : ($presentada ? 'Presentada' : ($presentacion === null ? 'Sin fecha' : 'Hasta el '.self::dia($presentacion).' 23:59')),
            8 => match (true) {
                $l->estado === EstadoLicitacion::BuenaPro => 'Buena pro para Techquk',
                $l->estado === EstadoLicitacion::Perdida => 'La ganó otro',
                $l->estado === EstadoLicitacion::Desierta => 'Desierta',
                $sub !== null => 'Subsanar hasta el '.self::dia($sub->plazo),
                default => $buenaPro === null ? 'Por registrar' : 'Buena pro el '.self::dia($buenaPro),
            },
        ];

        $actual = 8;

        foreach ($hechos as $n => $hecho) {
            if (! $hecho) {
                $actual = $n;

                break;
            }
        }

        if ($sub !== null) {
            $actual = 8;
        }

        $pasos = [];

        foreach (self::PASOS as $n => $nombre) {
            $pasos[] = ['numero' => $n, 'nombre' => $nombre, 'estado' => $hechos[$n] && ! ($n === 8 && $sub !== null) ? 'hecho' : ($n === $actual ? 'actual' : 'pendiente'), 'resumen' => $resumenes[$n]];
        }

        return ['pasos' => $pasos, 'actual' => $actual];
    }

    /**
     * El avance del expediente: los que aplican (todos menos «No aplica»), los listos o firmados y los que faltan.
     *
     * @return array{aplican: int, listos: int, faltan: int, enRevision: int, porcentaje: int}
     */
    public function documentos(Licitacion $l): array
    {
        $aplican = $l->documentos->where('estado', '!=', 'no_aplica');
        $listos = $aplican->whereIn('estado', ['listo', 'firmado'])->count();
        $enRevision = $aplican->where('estado', 'en_revision')->count();
        // «Listo para presentar»: documentos listos o firmados más la oferta, sobre los que aplican más la oferta.
        $porcentaje = (int) round(($listos + ($l->monto_ofertado !== null ? 1 : 0)) * 100 / max(1, $aplican->count() + 1));

        return ['aplican' => $aplican->count(), 'listos' => $listos, 'faltan' => $aplican->count() - $listos, 'enRevision' => $enRevision, 'porcentaje' => $porcentaje];
    }

    /** La fecha de presentación de ofertas del cronograma. */
    public function fechaPresentacion(Licitacion $l): ?CarbonImmutable
    {
        $h = $l->hitos->firstWhere('etapa', 'Presentación de ofertas');

        return $h === null ? null : CarbonImmutable::parse($h->fecha);
    }

    /**
     * La oferta desde el presupuesto: el de la licitación o, si no tiene, el más reciente de su oportunidad. Las partidas
     * con su costo (costo directo) y los gastos generales; el costo es la suma. Nulo sin presupuesto.
     *
     * @return array{presupuesto: ResumenPresupuesto, partidas: list<array{descripcion: string, cantidad: string, unidad: string, unitario: string, costo: string}>, costo: string, igv: string}|null
     */
    public function oferta(Licitacion $l): ?array
    {
        $servicio = app(PresupuestosService::class);
        $id = $l->presupuesto_id ?? ($l->oportunidad_id === null ? null : ($servicio->deOportunidad($l->oportunidad_id)[0]->id ?? null));

        if ($id === null) {
            return null;
        }

        $r = $servicio->resumen((int) $id);
        $partidas = array_map(fn ($p): array => ['descripcion' => $p->descripcion, 'cantidad' => $p->metrado, 'unidad' => $p->unidad, 'unitario' => $p->precioUnitario, 'costo' => $p->parcial], $r->partidas);

        if (bccomp($r->gastosGenerales, '0', 2) > 0) {
            $partidas[] = ['descripcion' => 'Gastos generales y garantías', 'cantidad' => '1', 'unidad' => 'glb', 'unitario' => $r->gastosGenerales, 'costo' => $r->gastosGenerales];
        }

        return ['presupuesto' => $r, 'partidas' => $partidas, 'costo' => bcadd($r->costoDirecto, $r->gastosGenerales, 2), 'igv' => $r->igvPct];
    }

    /**
     * El margen de un precio ofertado (con IGV), con el mismo cálculo que las cotizaciones: (precio sin IGV − costo) ÷
     * precio sin IGV.
     *
     * @param  array{costo: string, igv: string}  $oferta
     */
    public function margenDe(array $oferta, string $precioConIgv): ?string
    {
        if (bccomp($precioConIgv, '0', 2) <= 0) {
            return null;
        }

        $sinIgv = bcdiv($precioConIgv, bcadd('1', bcdiv($oferta['igv'], '100', 6), 6), 2);
        $m = CalculoCotizacion::margen($sinIgv, $oferta['costo']);

        return $m === null ? null : number_format((float) $m, 1);
    }

    /**
     * «¿Cuánto bajar?»: el descuento promedio de los ganadores en esta entidad (de lo guardado: sus licitaciones con
     * resultado) y el margen que queda ofertando a varios porcentajes del valor referencial, con su color.
     *
     * @param  array{costo: string, igv: string}  $oferta
     * @return array{descuento: ?string, procesos: int, filas: list<array{etiqueta: string, porcentaje: string, margen: ?string, tono: string, ancho: int}>}
     */
    public function cuantoBajar(Licitacion $l, array $oferta, ?string $precioActual): array
    {
        $historico = app(LicitacionesService::class)->historico($l->cuenta_id, $l->id);
        $porcentajes = array_values(array_filter(array_column($historico['filas'], 'porcentaje'), fn (?string $p): bool => $p !== null));
        $descuento = $porcentajes === [] ? null : number_format(100 - array_sum(array_map('floatval', $porcentajes)) / count($porcentajes), 1);
        $vr = $l->valor_referencial === null ? null : (string) $l->valor_referencial;
        $filas = [];

        if ($vr !== null && bccomp($vr, '0', 2) > 0) {
            $niveles = ['98' => '98 % del VR'];

            if ($precioActual !== null && bccomp($precioActual, '0', 2) > 0) {
                $actual = number_format((float) bcmul(bcdiv($precioActual, $vr, 6), '100', 2), 1, '.', '');
                $niveles[$actual] = $actual.' % del VR (tu oferta)';
            }

            $niveles += ['90' => '90 % del VR', '85' => '85 % del VR'];
            uksort($niveles, fn ($a, $b): int => bccomp((string) $b, (string) $a, 2));
            $minimo = app(ParametrosService::class)->decimal(CotizacionesServiceProvider::PARAMETRO_MARGEN_MINIMO);

            foreach ($niveles as $pct => $etiqueta) {
                $precio = str_ends_with($etiqueta, '(tu oferta)') && $precioActual !== null ? $precioActual : bcdiv(bcmul($vr, (string) $pct, 4), '100', 2);
                $margen = $this->margenDe($oferta, $precio);
                $tono = match (true) {
                    $margen === null => 'coral',
                    bccomp($margen, $minimo, 1) >= 0 => 'verde',
                    bccomp($margen, bcdiv($minimo, '2', 1), 1) >= 0 => 'ambar',
                    default => 'coral',
                };
                $filas[] = ['etiqueta' => $etiqueta, 'porcentaje' => (string) $pct, 'margen' => $margen, 'tono' => $tono, 'ancho' => (int) max(4, min(100, round((float) ($margen ?? 0) * 3)))];
            }
        }

        return ['descuento' => $descuento, 'procesos' => count($porcentajes), 'filas' => $filas];
    }

    public function registrarParticipacion(int $id, CarbonImmutable $fecha, ?string $constancia): void
    {
        $this->licitacion($id)->update(['registrado_seace_el' => $fecha->toDateString(), 'constancia_registro' => $constancia]);
    }

    /** Una consulta u observación nueva, en borrador (la persona la revisa antes de copiarla al SEACE). */
    public function redactarConsulta(int $id, string $tipo, ?string $numeral, string $texto, string $origen = 'manual'): ConsultaLicitacion
    {
        return $this->licitacion($id)->consultas()->create([
            'tipo' => $tipo === 'observacion' ? 'observacion' : 'consulta', 'numeral' => $numeral, 'pregunta' => $texto, 'estado' => 'borrador',
            'origen' => $origen, 'fecha' => Calendario::hoy(),
        ]);
    }

    /** La IA propone consultas leyendo las bases; quedan en borrador. No repite las que ya están. */
    public function sugerirConsultas(int $id): int
    {
        $l = $this->licitacion($id);
        $n = 0;

        foreach (self::CONSULTAS_SUGERIDAS as [$tipo, $numeral, $texto]) {
            if (! $l->consultas()->where('pregunta', $texto)->exists()) {
                $this->redactarConsulta($id, $tipo, $numeral, $texto, 'ia');
                $n++;
            }
        }

        return $n;
    }

    /** El texto como lo pide el formulario del SEACE: tipo, numeral o página de las bases y el texto. */
    public static function textoParaSeace(ConsultaLicitacion $c): string
    {
        return ($c->tipo === 'observacion' ? 'OBSERVACIÓN' : 'CONSULTA')."\nSección/numeral de las bases: ".($c->numeral ?? '—')."\n\n".$c->pregunta;
    }

    public function marcarEnviada(int $consultaId, CarbonImmutable $fecha, ?string $constancia): void
    {
        ConsultaLicitacion::query()->findOrFail($consultaId)->update(['estado' => 'enviada', 'enviada_el' => $fecha->toDateString(), 'constancia' => $constancia]);
    }

    /** La absolución que publicó la entidad; si ya no queda ninguna sin absolver, el paso queda hecho. */
    public function absolver(int $consultaId, string $respuesta, CarbonImmutable $fecha): void
    {
        $c = ConsultaLicitacion::query()->findOrFail($consultaId);
        $c->update(['estado' => 'absuelta', 'respuesta' => $respuesta]);

        if (! ConsultaLicitacion::query()->where('licitacion_id', $c->licitacion_id)->where('estado', '!=', 'absuelta')->exists()) {
            Licitacion::query()->whereKey($c->licitacion_id)->update(['consultas_absueltas_el' => $fecha->toDateString()]);
        }
    }

    public function basesIntegradas(int $id, CarbonImmutable $fecha, ?string $url): void
    {
        $l = $this->licitacion($id);
        $l->update(['bases_integradas_el' => $fecha->toDateString(), 'url_bases' => $url ?: $l->url_bases]);
        $l->hitos()->where('etapa', 'Integración de bases')->update(['es_cumplido' => true]);
    }

    /** @param  array{nombre?: string, estado?: string, responsable_user_id?: ?int, archivo?: ?string, vence?: ?string, requiere_firma?: bool, nota?: ?string}  $datos */
    public function guardarDocumento(int $licitacionId, ?int $documentoId, array $datos): DocumentoLicitacion
    {
        $l = $this->licitacion($licitacionId);

        if (isset($datos['estado']) && ! array_key_exists($datos['estado'], DocumentoLicitacion::ESTADOS)) {
            throw new RuntimeException('Estado de documento desconocido.');
        }

        if ($documentoId === null) {
            return $l->documentos()->create([...$datos, 'origen' => 'manual', 'es_confirmado' => true, 'orden' => (int) $l->documentos()->max('orden') + 1]);
        }

        $d = $l->documentos()->findOrFail($documentoId);
        $d->update($datos);

        return $d;
    }

    /** El precio lo decide una persona: se guarda el monto ofertado (con IGV) y el presupuesto del que sale. */
    public function guardarOferta(int $id, string $precio): void
    {
        if (bccomp($precio, '0', 2) <= 0) {
            throw new RuntimeException('El precio ofertado va mayor que cero.');
        }

        $l = $this->licitacion($id);
        $oferta = $this->oferta($l);
        $l->update(['monto_ofertado' => $precio, 'presupuesto_id' => $oferta['presupuesto']->id ?? $l->presupuesto_id]);
    }

    /**
     * Arma el paquete: un PDF único en el orden de las bases, foliado, con índice, que marca qué documentos necesitan
     * firma. Solo sin faltantes y con la oferta decidida. Atajo del MVP: cada documento va como una página foliada con
     * sus datos; los archivos subidos no se incrustan (se adjuntan aparte al SEACE).
     */
    public function armarPaquete(int $id): string
    {
        $l = $this->licitacion($id)->load(['documentos.responsable', 'cuenta']);
        $docs = $this->documentos($l);

        if ($docs['faltan'] > 0) {
            throw new RuntimeException("Faltan {$docs['faltan']} documentos del expediente: el paquete se arma sin faltantes.");
        }

        if ($l->monto_ofertado === null) {
            throw new RuntimeException('Falta decidir la oferta económica.');
        }

        $folio = 1;
        $indice = [];

        foreach ($l->documentos->where('estado', '!=', 'no_aplica') as $d) {
            $indice[] = ['folio' => ++$folio, 'nombre' => $d->nombre, 'firma' => $d->requiere_firma, 'responsable' => $d->responsable?->name, 'archivo' => $d->archivo];
        }

        $indice[] = ['folio' => ++$folio, 'nombre' => 'Oferta económica · S/ '.number_format((float) $l->monto_ofertado, 2), 'firma' => true, 'responsable' => null, 'archivo' => null];
        $ruta = "licitaciones/{$l->id}/paquete-{$l->codigo}.pdf";
        $entidad = (string) DB::table('cuentas as c')->join('parties as p', 'p.id', '=', 'c.party_id')->where('c.id', $l->cuenta_id)->value('p.razon_social');
        Storage::disk('local')->put($ruta, Pdf::loadView('licitaciones::paquete', ['l' => $l, 'entidad' => $entidad, 'indice' => $indice])->output());
        $l->update(['paquete_armado_at' => Calendario::ahora(), 'paquete_archivo' => $ruta]);

        return $ruta;
    }

    /** Al volver del SEACE: la constancia y la hora; la licitación pasa a «presentada». */
    public function registrarPresentacion(int $id, CarbonImmutable $cuando, ?string $constancia): void
    {
        $l = $this->licitacion($id);

        if ($l->monto_ofertado === null) {
            throw new RuntimeException('Falta decidir la oferta económica.');
        }

        app(LicitacionesService::class)->presentar($id, (string) $l->monto_ofertado, $cuando->startOfDay());
        $l->refresh()->update(['presentada_at' => $cuando, 'constancia_presentacion' => $constancia]);
    }

    public function pedirSubsanacion(int $id, string $pedido, CarbonImmutable $plazo): void
    {
        $this->licitacion($id)->subsanaciones()->create(['pedido' => $pedido, 'pedida_el' => Calendario::hoy(), 'plazo' => $plazo->toDateString()]);
    }

    public function responderSubsanacion(int $subsanacionId, string $respuesta, ?string $constancia, CarbonImmutable $fecha): void
    {
        SubsanacionLicitacion::query()->findOrFail($subsanacionId)->update(['respuesta' => $respuesta, 'constancia' => $constancia, 'respondida_el' => $fecha->toDateString()]);
    }

    /**
     * Los avisos para Mien: las fechas del cronograma que se acercan (5 días) y lo que le falta a cada paso.
     *
     * @return list<array{texto: string, nota: ?string, url: string, tono: ?string}>
     */
    public function avisos(): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $items = [];
        $abiertas = Licitacion::query()->with(['hitos', 'documentos', 'consultas', 'subsanaciones'])
            ->whereIn('estado', [EstadoLicitacion::EnPreparacion->value, EstadoLicitacion::Presentada->value])->whereNotNull('oportunidad_id')->get();

        foreach ($abiertas as $l) {
            $url = '/oportunidades/'.$l->oportunidad_id.'?pestana='.urlencode('licitacion::tab');

            foreach ($l->subsanaciones->whereNull('respondida_el') as $s) {
                $dias = (int) $hoy->diffInDays($s->plazo, false);
                $items[] = ['texto' => "Subsanar {$l->nomenclatura}", 'nota' => $dias < 0 ? 'venció' : ($dias === 0 ? 'hoy' : "en {$dias} días"), 'url' => $url, 'tono' => 'danger'];
            }

            if ($l->estado !== EstadoLicitacion::EnPreparacion) {
                continue;
            }

            $ruta = $this->ruta($l);
            $actual = $ruta['pasos'][$ruta['actual'] - 1];
            $presentacion = $this->fechaPresentacion($l);
            $dias = $presentacion === null ? null : (int) $hoy->diffInDays($presentacion, false);

            if ($dias !== null && $dias <= self::DIAS_URGENTE) {
                $items[] = ['texto' => "Presentar {$l->nomenclatura}", 'nota' => ($dias < 0 ? 'venció' : ($dias === 0 ? 'hoy' : "en {$dias} días")).' · falta: '.mb_strtolower($actual['nombre']), 'url' => $url, 'tono' => 'danger'];
            } else {
                $items[] = ['texto' => "{$actual['nombre']} · {$l->nomenclatura}", 'nota' => $actual['resumen'], 'url' => $url, 'tono' => null];
            }

            foreach ($l->documentos->where('estado', 'falta')->filter(fn (DocumentoLicitacion $d): bool => $d->vence !== null && $hoy->diffInDays($d->vence, false) <= self::DIAS_URGENTE) as $d) {
                $items[] = ['texto' => "Pedir «{$d->nombre}»", 'nota' => $l->nomenclatura.' · '.self::dia($d->vence), 'url' => $url, 'tono' => 'danger'];
            }
        }

        return $items;
    }

    public static function dia(\DateTimeInterface $fecha): string
    {
        return str_replace('.', '', CarbonImmutable::instance($fecha)->locale('es')->translatedFormat('d M'));
    }

    private function licitacion(int $id): Licitacion
    {
        return Licitacion::query()->findOrFail($id);
    }
}
