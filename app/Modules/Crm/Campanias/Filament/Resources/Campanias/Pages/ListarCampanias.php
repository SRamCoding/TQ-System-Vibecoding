<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Campanias\Pages;

use App\Modules\Crm\Campanias\Filament\Resources\Campanias\CampaniaResource;
use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Services\CampaniasService;
use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Erp\Presupuestos\Services\PresupuestoEmpresaService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\QRCode;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Livewire\Attributes\Url;
use RuntimeException;

/**
 * Observación 7.6 (MV-079, lienzos `campanias/Main.dc.html` y `Clientes.dc.html`): la caja Campañas con secciones en
 * chips: **Campañas** (cinco cifras del año y una tarjeta por campaña con su mini embudo, gasto real contra presupuesto,
 * lo ganado, el margen de lo ganado y el retorno; Formulario y QR, Registrar gasto, Ver contactos) y **Mis clientes**
 * (tres grupos que arma el sistema, con «Ver la lista» y «Crear campaña»). El Radar del PAC espera la decisión de
 * Sebastián (investigación hecha); el calendario queda para más adelante.
 */
final class ListarCampanias extends Page
{
    protected static string $resource = CampaniaResource::class;

    protected static ?string $title = 'Campañas';

    #[Url(as: 'seccion', except: 'campanias')]
    public string $seccion = 'campanias';

    /** Observación 9.4: el periodo y el orden de la vista Canales. */
    #[Url(as: 'periodo', except: 'mes')]
    public string $periodo = 'mes';

    #[Url(as: 'orden', except: 'leads')]
    public string $ordenCanales = 'leads';

    /** El grupo de Mis clientes abierto en «Ver la lista». */
    #[Url(as: 'grupo')]
    public ?string $grupo = null;

    /** Los clientes marcados de ese grupo (cuenta => incluido). */
    public array $incluidos = [];

    public ?int $campaniaId = null;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getHeading(): string
    {
        return '';
    }

    private static function puede(): bool
    {
        return LeadResource::puede(LeadsService::PERMISO_GESTIONAR);
    }

    public function mount(): void
    {
        if ($this->grupo !== null) {
            $grupo = $this->grupo;
            $this->grupo = null;
            $this->verLista($grupo);
        }
    }

    public function verLista(string $grupo): void
    {
        $this->grupo = $this->grupo === $grupo ? null : $grupo;
        $this->incluidos = [];

        foreach ($this->grupo === null ? [] : app(CampaniasService::class)->grupos()[$grupo]['clientes'] as $c) {
            $this->incluidos[(string) $c['cuenta']] = true;
        }
    }

    public function sobre(string $accion, int $campaniaId): void
    {
        $this->campaniaId = $campaniaId;
        $this->mountAction($accion);
    }

    public function crearDesde(string $grupo): void
    {
        if ($this->grupo !== $grupo) {
            $this->verLista($grupo);
        }

        $this->mountAction('crearCampania');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nueva')->label('Nueva campaña')->icon('heroicon-o-plus')->slideOver()->visible(self::puede(...))
                ->schema([
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(150),
                    Select::make('tipo')->label('Tipo')->required()->options(Campania::TIPOS),
                    // Observación 9.3: el canal de la campaña; sus contactos llegan con ese canal.
                    Select::make('canal_lead_id')->label('Canal')->required()
                        ->options(fn (): array => array_map(fn (array $c): string => $c['nombre'], app(LeadsService::class)->canales(soloActivos: true))),
                    DatePicker::make('desde')->label('Desde')->required(),
                    DatePicker::make('hasta')->label('Hasta'),
                    TextInput::make('presupuesto')->label('Presupuesto (S/)')->numeric()->minValue(0),
                    Textarea::make('objetivo')->label('Objetivo')->rows(3),
                ])
                ->action(fn (array $data) => $this->redirect(VerCampania::getUrl(['record' => Campania::query()->create([...$data, 'es_activa' => true])]))),
        ];
    }

    /** Un gasto real, con el centro de costo Comercial y el rubro «Publicidad y ferias». */
    public function registrarGastoAction(): Action
    {
        return Action::make('registrarGasto')->label('Registrar gasto')->visible(self::puede(...))->slideOver()
            ->modalHeading(fn (): string => 'Registrar gasto · '.Campania::query()->whereKey($this->campaniaId)->value('nombre'))
            ->modalDescription('Un gasto directo de la empresa: centro de costo Comercial, rubro «Publicidad y ferias», vinculado a esta campaña. Se compara con el saldo de la sede en el mes.')
            ->fillForm(fn (): array => ['fecha' => Calendario::hoy()])
            ->schema([
                Select::make('sede')->label('Sede')->required()->options(fn (): array => app(PresupuestoEmpresaService::class)->sedes()),
                TextInput::make('descripcion')->label('Descripción')->required()->maxLength(200),
                TextInput::make('proveedor')->label('Proveedor')->required()->maxLength(150),
                DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                TextInput::make('monto')->label('Monto (S/)')->numeric()->required()->minValue(0.01),
                TextInput::make('motivo')->label('Motivo del exceso (si pasa el saldo)')->maxLength(300),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    app(CampaniasService::class)->registrarGasto((int) $this->campaniaId, $data['sede'] === 'empresa' ? null : (int) $data['sede'], CarbonImmutable::parse($data['fecha']),
                        number_format((float) $data['monto'], 2, '.', ''), $data['descripcion'], $data['proveedor'], $data['motivo'] ?? null);
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title('Gasto registrado.')->send();
            });
    }

    /** El enlace público, su QR para imprimir y el enlace de WhatsApp. */
    public function formularioAction(): Action
    {
        return Action::make('formulario')->label('Formulario y QR')->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
            ->modalHeading(fn (): string => 'Formulario y QR · '.Campania::query()->whereKey($this->campaniaId)->value('nombre'))
            ->schema(fn (): array => [View::make('campanias::qr')->viewData($this->datosQr())]);
    }

    /** @return array<string, string> */
    private function datosQr(): array
    {
        $c = Campania::query()->findOrFail($this->campaniaId);
        $url = url('/c/'.app(CampaniasService::class)->token($c->id));
        $numero = preg_replace('/\D/', '', app(ParametrosService::class)->texto('crm.whatsapp_comercial'));

        return ['url' => $url, 'qr' => (new QRCode)->render($url), 'whatsapp' => 'https://wa.me/'.$numero.'?text='.rawurlencode("Hola, vengo de {$c->nombre}")];
    }

    /** «Crear campaña» desde un grupo de Mis clientes: con los marcados y el seguimiento editable. */
    public function crearCampaniaAction(): Action
    {
        return Action::make('crearCampania')->label('Crear campaña')->visible(self::puede(...))->slideOver()
            ->modalHeading(fn (): string => 'Nueva campaña · '.(CampaniasService::GRUPOS[$this->grupo ?? ''][1] ?? ''))
            ->fillForm(fn (): array => ['nombre' => ucfirst(CampaniasService::GRUPOS[$this->grupo ?? 'garantia'][2]).' '.CarbonImmutable::parse(Calendario::hoy())->year,
                'responsable' => auth()->id(), 'pasos' => CampaniasService::SEGUIMIENTO])
            ->schema([
                Text::make(fn (): string => count(array_filter($this->incluidos)).' clientes · '.(CampaniasService::GRUPOS[$this->grupo ?? ''][0] ?? '')),
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(150),
                Select::make('responsable')->label('Responsable de las tareas')->required()
                    ->options(fn (): array => User::query()->where('es_activo', true)->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all()),
                Repeater::make('pasos')->label('Seguimiento')->columns(6)->reorderable(false)->schema([
                    TextInput::make('dia')->label('Día')->numeric()->required()->minValue(1)->columnSpan(1),
                    Select::make('canal')->label('Canal')->required()->options(['correo' => 'Correo', 'whatsapp' => 'WhatsApp', 'llamada' => 'Llamada'])->columnSpan(2),
                    Textarea::make('texto')->label('Plantilla')->required()->rows(2)->columnSpan(6)->helperText('{cliente}, {oferta} y {proyecto} se reemplazan.'),
                ]),
                Text::make('No se envía nada: cada paso es una tarea en la agenda (y en Mien) del responsable, con el texto listo para copiar. Las respuestas entran a Oportunidades como «Por calificar», con esta campaña.'),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    /** @var User $u */
                    $u = User::query()->findOrFail((int) $data['responsable']);
                    $c = app(CampaniasService::class)->crearCampaniaAClientes((string) $this->grupo, $data['nombre'], array_map('intval', array_keys(array_filter($this->incluidos))), array_values($data['pasos']), $u);
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title('Campaña creada: sus pasos ya están como tareas en Mien.')->send();
                $this->redirect(VerCampania::getUrl(['record' => $c]), navigate: true);
            });
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('campanias::pantalla')->viewData(fn (): array => [
                'retorno' => $this->seccion === 'campanias' ? app(CampaniasService::class)->retorno(CarbonImmutable::parse(Calendario::hoy())->year) : null,
                'grupos' => app(CampaniasService::class)->grupos(),
                'canales' => $this->seccion === 'canales' ? app(CampaniasService::class)->canales(
                    array_key_exists($this->periodo, CampaniasService::PERIODOS) ? $this->periodo : 'mes',
                    array_key_exists($this->ordenCanales, CampaniasService::ORDENES_CANALES) ? $this->ordenCanales : 'leads',
                ) : null,
                'puede' => self::puede(),
            ]),
        ]);
    }
}
