<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament;

use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\Pages\VerLicitacion;
use App\Modules\Crm\Licitaciones\Models\ConsultaLicitacion;
use App\Modules\Crm\Licitaciones\Models\DocumentoLicitacion;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Licitaciones\Services\PostulacionService;
use App\Modules\Crm\Oportunidades\Pestanas\ComponentePestanaOportunidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * MV-023 y MV-076 (observación 7.3, lienzo «A · Ruta por pasos»): la licitación dentro de su oportunidad. Arriba, la
 * cabecera con la cuenta regresiva a la presentación y «Listo para presentar N %»; a la izquierda, los ocho pasos con su
 * estado; a la derecha, el contenido del paso elegido (al entrar, el actual). Todo el trabajo se hace aquí; en el SEACE
 * solo se registra la participación, se pegan las consultas y se sube el paquete, a mano.
 */
final class LicitacionDeOportunidad extends ComponentePestanaOportunidad implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** El paso que se ve a la derecha (1 a 8); nulo abre el actual. */
    public ?int $paso = null;

    /** El precio que se está probando en la Oferta económica (con IGV), para ver el margen al instante. */
    public ?string $precio = null;

    /** El documento o la consulta sobre el que se abrió una acción. */
    public ?int $elegido = null;

    public static function tieneLicitacion(int $oportunidadId): bool
    {
        return Licitacion::query()->where('oportunidad_id', $oportunidadId)->exists();
    }

    public function licitacion(): Licitacion
    {
        return Licitacion::query()->with(['hitos', 'documentos.responsable', 'consultas', 'participantes', 'responsable', 'subsanaciones'])->where('oportunidad_id', $this->oportunidadId)->firstOrFail();
    }

    public function elegirPaso(int $paso): void
    {
        $this->paso = max(1, min(8, $paso));
    }

    /** Abre una acción sobre un documento o una consulta. */
    public function sobre(string $accion, int $id): void
    {
        $this->elegido = $id;
        $this->mountAction($accion);
    }

    /** @return array<string, Action> */
    private function acciones(): array
    {
        return AccionesLicitacion::todas(fn (): Licitacion => $this->licitacion(), function (Closure $paso, string $exito): void {
            $this->hacer($paso, $exito);
            // La oportunidad pudo cerrarse con el resultado: se vuelve a dibujar entera.
            $this->redirect(request()->header('Referer') ?? '/oportunidades/'.$this->oportunidadId);
        });
    }

    private function hacer(Closure $paso, string $exito): bool
    {
        try {
            $paso();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return false;
        }

        Notification::make()->success()->title($exito)->send();

        return true;
    }

    private static function postulacion(): PostulacionService
    {
        return app(PostulacionService::class);
    }

    private function licitacionId(): int
    {
        return $this->licitacion()->id;
    }

    private static function puede(): bool
    {
        $u = auth()->user();

        return $u instanceof User && $u->can(LicitacionesService::PERMISO_GESTIONAR);
    }

    public function participarAction(): Action
    {
        return $this->acciones()['participar'];
    }

    public function noParticiparAction(): Action
    {
        return $this->acciones()['noParticipar'];
    }

    public function confirmarSugerenciasAction(): Action
    {
        return $this->acciones()['confirmarSugerencias'];
    }

    public function resultadoAction(): Action
    {
        return $this->acciones()['resultado'];
    }

    /** Paso 1: «Ya me registré», con la fecha y, si se tiene, la constancia. */
    public function registrarParticipacionAction(): Action
    {
        return Action::make('registrarParticipacion')->label('Ya me registré')->icon('heroicon-o-check')->visible(self::puede(...))
            ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
            ->schema([
                DatePicker::make('fecha')->label('Me registré el')->required()->format('Y-m-d'),
                FileUpload::make('constancia')->label('Constancia (opcional)')->disk('local')->directory('licitaciones/constancias'),
            ])
            ->action(fn (array $data) => $this->hacer(fn () => self::postulacion()->registrarParticipacion($this->licitacionId(), CarbonImmutable::parse($data['fecha']), $data['constancia'] ?? null), 'Registro como participante anotado.'));
    }

    /** Paso 2: una consulta u observación nueva, en borrador. */
    public function redactarConsultaAction(): Action
    {
        return Action::make('redactarConsulta')->label('+ Consulta u observación')->color('gray')->visible(self::puede(...))
            ->schema([
                Select::make('tipo')->label('Tipo')->options(['consulta' => 'Consulta', 'observacion' => 'Observación'])->default('consulta')->required(),
                TextInput::make('numeral')->label('Página o numeral de las bases')->maxLength(60)->placeholder('Capítulo III, numeral 3.1'),
                Textarea::make('texto')->label('Texto')->required()->rows(4),
            ])
            ->action(fn (array $data) => $this->hacer(fn () => self::postulacion()->redactarConsulta($this->licitacionId(), $data['tipo'], $data['numeral'] ?? null, $data['texto']), 'Quedó en borrador.'));
    }

    public function sugerirConsultasAction(): Action
    {
        return Action::make('sugerirConsultas')->label('Sugerir con IA')->icon('heroicon-o-sparkles')->color('gray')->visible(self::puede(...))
            ->action(function (): void {
                $n = self::postulacion()->sugerirConsultas($this->licitacionId());
                Notification::make()->success()->title($n === 0 ? 'La IA no encontró nada nuevo que consultar.' : "La IA dejó {$n} en borrador: revísalas antes de enviar.")->send();
            });
    }

    public function enviarConsultaAction(): Action
    {
        return Action::make('enviarConsulta')->label('Enviada')->visible(self::puede(...))
            ->modalHeading('La pegué en el SEACE')
            ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
            ->schema([
                DatePicker::make('fecha')->label('Enviada el')->required()->format('Y-m-d'),
                FileUpload::make('constancia')->label('Constancia (opcional)')->disk('local')->directory('licitaciones/constancias'),
            ])
            ->action(fn (array $data) => $this->hacer(fn () => self::postulacion()->marcarEnviada((int) $this->elegido, CarbonImmutable::parse($data['fecha']), $data['constancia'] ?? null), 'Consulta enviada.'));
    }

    public function absolverConsultaAction(): Action
    {
        return Action::make('absolverConsulta')->label('Registrar absolución')->visible(self::puede(...))
            ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
            ->schema([
                Textarea::make('respuesta')->label('Lo que absolvió la entidad')->required()->rows(4),
                DatePicker::make('fecha')->label('Publicada el')->required()->format('Y-m-d'),
            ])
            ->action(fn (array $data) => $this->hacer(fn () => self::postulacion()->absolver((int) $this->elegido, $data['respuesta'], CarbonImmutable::parse($data['fecha'])), 'Absolución registrada.'));
    }

    /** Paso 3. */
    public function basesIntegradasAction(): Action
    {
        return Action::make('basesIntegradas')->label('Ya las descargué')->icon('heroicon-o-arrow-down-tray')->visible(self::puede(...))
            ->fillForm(fn (): array => ['fecha' => Calendario::hoy(), 'url' => $this->licitacion()->url_bases])
            ->schema([
                DatePicker::make('fecha')->label('Descargadas el')->required()->format('Y-m-d'),
                TextInput::make('url')->label('Dirección de las bases integradas')->url()->maxLength(500),
            ])
            ->action(fn (array $data) => $this->hacer(fn () => self::postulacion()->basesIntegradas($this->licitacionId(), CarbonImmutable::parse($data['fecha']), $data['url'] ?? null), 'Bases integradas anotadas.'));
    }

    /** Paso 4: editar un documento del expediente (o, sin elegido, agregar uno). */
    public function documentoAction(): Action
    {
        return Action::make('documento')->label('+ Documento')->color('gray')->visible(self::puede(...))->slideOver()
            ->modalHeading(fn (): string => $this->elegido === null ? 'Documento nuevo' : 'Documento del expediente')
            ->fillForm(function (): array {
                $d = $this->elegido === null ? null : DocumentoLicitacion::query()->find($this->elegido);

                return $d === null ? ['estado' => 'falta'] : [
                    'nombre' => $d->nombre, 'estado' => $d->estado, 'responsable_user_id' => $d->responsable_user_id, 'vence' => $d->vence?->toDateString(),
                    'requiere_firma' => $d->requiere_firma, 'nota' => $d->nota, 'archivo' => $d->archivo,
                ];
            })
            ->schema([
                TextInput::make('nombre')->label('Documento')->required()->maxLength(200),
                Select::make('estado')->label('Estado')->options(DocumentoLicitacion::ESTADOS)->required(),
                Select::make('responsable_user_id')->label('Responsable')->searchable()
                    ->options(fn (): array => User::query()->where('es_activo', true)->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all()),
                DatePicker::make('vence')->label('Vence o hay que pedirlo hasta')->format('Y-m-d'),
                Toggle::make('requiere_firma')->label('Necesita firma'),
                FileUpload::make('archivo')->label('Archivo')->disk('local')->directory('licitaciones/expedientes'),
                TextInput::make('nota')->label('Nota')->maxLength(300),
            ])
            ->action(function (array $data): void {
                $this->hacer(fn () => self::postulacion()->guardarDocumento($this->licitacionId(), $this->elegido, [
                    'nombre' => $data['nombre'], 'estado' => $data['estado'], 'responsable_user_id' => filled($data['responsable_user_id'] ?? null) ? (int) $data['responsable_user_id'] : null,
                    'vence' => $data['vence'] ?? null, 'requiere_firma' => (bool) ($data['requiere_firma'] ?? false), 'archivo' => $data['archivo'] ?? null, 'nota' => $data['nota'] ?? null,
                ]), 'Documento guardado.');
                $this->elegido = null;
            });
    }

    public function nuevoDocumento(): void
    {
        $this->elegido = null;
        $this->mountAction('documento');
    }

    /** Paso 5: el precio lo decide una persona. */
    public function guardarOferta(): void
    {
        if (! self::puede()) {
            return;
        }

        $precio = preg_replace('/[^0-9.]/', '', (string) $this->precio) ?: '0';
        $this->hacer(fn () => self::postulacion()->guardarOferta($this->licitacionId(), number_format((float) $precio, 2, '.', '')), 'Oferta guardada.');
    }

    /** Paso 6. */
    public function armarPaqueteAction(): Action
    {
        return Action::make('armarPaquete')->label('Armar paquete para el SEACE')->icon('heroicon-o-document-duplicate')->visible(self::puede(...))
            ->requiresConfirmation()->modalDescription('Un PDF único en el orden de las bases, foliado, con índice y las páginas que necesitan firma marcadas.')
            ->action(fn () => $this->hacer(fn () => self::postulacion()->armarPaquete($this->licitacionId()), 'Paquete armado: descárgalo y súbelo al SEACE.'));
    }

    /** Paso 6: el PDF armado, listo para subir al SEACE. */
    public function descargarPaquete(): mixed
    {
        $l = $this->licitacion();

        return $l->paquete_archivo === null || ! Storage::disk('local')->exists($l->paquete_archivo) ? null
            : Storage::disk('local')->download($l->paquete_archivo, "Paquete {$l->nomenclatura}.pdf");
    }

    /** Paso 7: al volver del SEACE. */
    public function registrarPresentacionAction(): Action
    {
        return Action::make('registrarPresentacion')->label('Registrar presentación')->icon('heroicon-o-paper-airplane')->visible(self::puede(...))
            ->fillForm(fn (): array => ['cuando' => Calendario::ahora()->format('Y-m-d H:i')])
            ->schema([
                DateTimePicker::make('cuando')->label('Presentada el')->required()->seconds(false),
                FileUpload::make('constancia')->label('Constancia (archivo o foto de pantalla)')->required()->disk('local')->directory('licitaciones/constancias'),
            ])
            ->action(fn (array $data) => $this->hacer(fn () => self::postulacion()->registrarPresentacion($this->licitacionId(), CarbonImmutable::parse($data['cuando']), $data['constancia'] ?? null), 'Presentación registrada: la licitación quedó presentada.'));
    }

    /** Paso 8: la entidad pide subsanar. */
    public function pedirSubsanacionAction(): Action
    {
        return Action::make('pedirSubsanacion')->label('La entidad pide subsanar')->color('gray')->visible(self::puede(...))
            ->schema([
                Textarea::make('pedido')->label('Qué pide')->required()->rows(3),
                DatePicker::make('plazo')->label('Plazo')->required()->format('Y-m-d'),
            ])
            ->action(fn (array $data) => $this->hacer(fn () => self::postulacion()->pedirSubsanacion($this->licitacionId(), $data['pedido'], CarbonImmutable::parse($data['plazo'])), 'Pedido de subsanación registrado: va como aviso en Mien.'));
    }

    public function responderSubsanacionAction(): Action
    {
        return Action::make('responderSubsanacion')->label('Responder')->visible(self::puede(...))
            ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
            ->schema([
                Textarea::make('respuesta')->label('Lo que se envió')->required()->rows(3),
                DatePicker::make('fecha')->label('Enviada el')->required()->format('Y-m-d'),
                FileUpload::make('constancia')->label('Constancia')->disk('local')->directory('licitaciones/constancias'),
            ])
            ->action(fn (array $data) => $this->hacer(fn () => self::postulacion()->responderSubsanacion((int) $this->elegido, $data['respuesta'], $data['constancia'] ?? null, CarbonImmutable::parse($data['fecha'])), 'Subsanación respondida.'));
    }

    public function render(): View
    {
        $l = $this->licitacion();
        $p = self::postulacion();
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $ruta = $p->ruta($l);
        $this->paso ??= $ruta['actual'];
        $oferta = $p->oferta($l);
        $this->precio ??= $l->monto_ofertado === null ? ($l->valor_referencial === null ? null : (string) $l->valor_referencial) : (string) $l->monto_ofertado;
        $precio = preg_replace('/[^0-9.]/', '', (string) $this->precio) ?: null;
        $presentacion = $p->fechaPresentacion($l);
        $cerrada = in_array($l->estado, [EstadoLicitacion::BuenaPro, EstadoLicitacion::Perdida, EstadoLicitacion::Desierta, EstadoLicitacion::NoParticipa], true);

        return view('licitaciones::oportunidad.licitacion', [
            'l' => $l,
            'hoy' => $hoy,
            'ruta' => $ruta,
            'docs' => $p->documentos($l),
            'oferta' => $oferta,
            'margen' => $oferta === null || $precio === null ? null : $p->margenDe($oferta, $precio),
            'sobreVr' => $precio === null || $l->valor_referencial === null || (float) $l->valor_referencial <= 0 ? null : number_format((float) $precio * 100 / (float) $l->valor_referencial, 1),
            'bajar' => $oferta === null ? null : $p->cuantoBajar($l, $oferta, $precio),
            'presentacion' => $presentacion,
            'diasPresentacion' => $presentacion === null ? null : (int) $hoy->diffInDays($presentacion, false),
            'etapaOportunidad' => (string) DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->where('o.id', $this->oportunidadId)->value('e.nombre'),
            'entidad' => (string) DB::table('cuentas as c')->join('parties as pa', 'pa.id', '=', 'c.party_id')->where('c.id', $l->cuenta_id)->value('pa.razon_social'),
            'urlFicha' => VerLicitacion::getUrl(['record' => $l->id]),
            'urlSeace' => PostulacionService::URL_SEACE,
            'puede' => self::puede(),
            'cerrada' => $cerrada,
            'textoSeace' => fn (ConsultaLicitacion $c): string => PostulacionService::textoParaSeace($c),
        ]);
    }
}
