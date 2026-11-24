<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament;

use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Services\CampaniasService;
use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Erp\Presupuestos\Services\PresupuestoEmpresaService;
use App\Modules\Nucleo\Filament\Imputacion\SelectorImputacion;
use App\Modules\Nucleo\Imputacion\EleccionImputacion;
use App\Modules\Nucleo\Imputacion\TipoImputacion;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\QRCode;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Observación 9.10: lo que comparten la caja Campañas y la ficha de una campaña: los atajos de la tarjeta (Contactos,
 * Oportunidades, Ganadas, Presupuesto y gasto) en una ventana al medio, «Registrar gasto» al medio con el selector de
 * imputación y el comprobante, y «Formulario y QR».
 */
trait AtajosDeCampania
{
    public ?int $campaniaId = null;

    /** El atajo abierto: contactos, oportunidades, ganadas o gastos. */
    public string $atajo = '';

    public function sobre(string $accion, int $campaniaId): void
    {
        $this->campaniaId = $campaniaId;
        $this->mountAction($accion);
    }

    public function verAtajo(string $atajo, int $campaniaId): void
    {
        $this->campaniaId = $campaniaId;
        $this->atajo = in_array($atajo, ['contactos', 'oportunidades', 'ganadas', 'gastos'], true) ? $atajo : 'contactos';
        $this->dispatch('open-modal', id: 'tq-camp-atajo');
    }

    /** @return array<string, mixed> lo que dibuja la ventana del atajo */
    public function datosAtajo(): array
    {
        if ($this->campaniaId === null || $this->atajo === '') {
            return ['titulo' => '', 'tipo' => '', 'filas' => []];
        }

        $s = app(CampaniasService::class);
        $nombre = (string) Campania::query()->whereKey($this->campaniaId)->value('nombre');
        $filas = match ($this->atajo) {
            'contactos' => $s->contactosLista($this->campaniaId),
            'oportunidades' => $s->oportunidadesLista($this->campaniaId),
            'ganadas' => $s->oportunidadesLista($this->campaniaId, true),
            default => $s->gastosLista($this->campaniaId),
        };
        $n = $this->atajo === 'gastos' ? count($filas['gastos']) : count($filas);
        $titulo = ['contactos' => 'Contactos', 'oportunidades' => 'Oportunidades', 'ganadas' => 'Ganadas', 'gastos' => 'Presupuesto y gasto'][$this->atajo];

        return ['titulo' => "{$titulo} de {$nombre} · {$n}", 'tipo' => $this->atajo, 'filas' => $filas];
    }

    private static function puedeGestionar(): bool
    {
        return LeadResource::puede(LeadsService::PERMISO_GESTIONAR);
    }

    /**
     * Observación 9.10: un gasto real en una ventana al medio: fecha, concepto, monto, comprobante y el selector de
     * imputación (gasto directo del centro de costo Comercial, vinculado a la campaña). Al guardar, la tarjeta y la
     * ficha se actualizan sin recargar.
     */
    public function registrarGastoAction(): Action
    {
        $p = EleccionImputacion::PREFIJO;

        return Action::make('registrarGasto')->label('Registrar gasto')->icon('heroicon-o-banknotes')->visible(self::puedeGestionar(...))
            ->modalWidth('2xl')
            ->modalHeading(fn (): string => 'Registrar gasto · '.Campania::query()->whereKey($this->campaniaId)->value('nombre'))
            ->modalDescription('Un gasto directo de la empresa, rubro «Publicidad y ferias», vinculado a esta campaña. Se compara con el saldo de la sede en el mes.')
            ->fillForm(fn (): array => ['fecha' => Calendario::hoy(), 'moneda' => 'PEN', "{$p}tipo" => TipoImputacion::CentroDeCosto->value,
                "{$p}centro_costo_id" => DB::table('centros_costo')->where('codigo', CampaniasService::CENTRO_COSTO)->value('id')])
            ->schema([
                DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                TextInput::make('descripcion')->label('Concepto')->required()->maxLength(200),
                TextInput::make('monto')->label('Monto (S/)')->numeric()->required()->minValue(0.01)->live(onBlur: true),
                TextInput::make('proveedor')->label('Proveedor')->required()->maxLength(150),
                Select::make('sede')->label('Sede')->required()->options(fn (): array => app(PresupuestoEmpresaService::class)->sedes()),
                FileUpload::make('comprobante')->label('Comprobante (opcional)')->disk('public')->directory('campanias/comprobantes')->acceptedFileTypes(['application/pdf', 'image/*'])->maxSize(10240),
                Hidden::make('moneda'),
                ...array_map(fn (\Closure $control) => $control(), array_values(SelectorImputacion::controles())),
                TextInput::make('motivo')->label('Motivo del exceso (si pasa el saldo)')->maxLength(300),
            ])
            ->action(function (array $data, Action $action) use ($p): void {
                if (($data["{$p}tipo"] ?? null) !== TipoImputacion::CentroDeCosto->value) {
                    Notification::make()->danger()->title('El gasto de una campaña es un gasto directo: imputa a un centro de costo.')->send();
                    $action->halt();

                    return;
                }

                try {
                    app(CampaniasService::class)->registrarGasto((int) $this->campaniaId, $data['sede'] === 'empresa' ? null : (int) $data['sede'], CarbonImmutable::parse($data['fecha']),
                        number_format((float) $data['monto'], 2, '.', ''), $data['descripcion'], $data['proveedor'], $data['motivo'] ?? null,
                        isset($data["{$p}centro_costo_id"]) ? (int) $data["{$p}centro_costo_id"] : null, is_string($data['comprobante'] ?? null) ? $data['comprobante'] : null);
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
}
