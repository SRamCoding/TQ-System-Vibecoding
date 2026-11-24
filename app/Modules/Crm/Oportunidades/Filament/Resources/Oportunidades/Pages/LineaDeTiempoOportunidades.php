<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Oportunidades\Filament\DibujoDelCronograma;
use App\Modules\Crm\Oportunidades\Filament\MuestraLaFicha;
use App\Modules\Crm\Oportunidades\Filament\MuestraLasVistas;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\VistasDeOportunidades;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * La vista «Línea de tiempo» de Oportunidades como la propone el prototipo aprobado (plan 2.21d, D11; reemplaza la de
 * gemas por carril de la 2.21c): una fila por oportunidad con una barra por cada etapa que pasó, lo que falta hasta el
 * cierre esperado y el hito del cierre (`DibujoDelCronograma`, vista `oportunidades::linea.cronograma`). Arriba, las
 * pestañas de vista y la fila de cifras (`MuestraLasVistas`). Tocar una fila abre su ficha en el panel deslizante
 * (`MuestraLaFicha`); `?ficha=<id>` la abre directo.
 */
final class LineaDeTiempoOportunidades extends Page
{
    use MuestraLaFicha;
    use MuestraLasVistas;

    /** MV-060: orden («Por urgencia» por defecto) y filtros de la Línea de tiempo, en la dirección. */
    #[Url(as: 'orden', except: 'urgencia')]
    public string $orden = 'urgencia';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'vence')]
    public ?string $vencimiento = null;

    #[Url(as: 'etapa')]
    public ?string $etapa = null;

    #[Url(as: 'de', except: 'mias')]
    public string $responsable = 'mias';

    #[Url(as: 'rango', except: 'semestre')]
    public string $rango = 'semestre';

    /** Un clic pone el filtro; otro clic en el mismo, lo quita. */
    public function poner(string $filtro, ?string $valor): void
    {
        match ($filtro) {
            'vencimiento' => $this->vencimiento = $this->vencimiento === $valor ? null : $valor,
            'etapa' => $this->etapa = $this->etapa === $valor ? null : $valor,
            'responsable' => $this->responsable = (string) ($valor ?? 'mias'),
            'rango' => $this->rango = array_key_exists((string) $valor, DibujoDelCronograma::RANGOS) ? (string) $valor : 'semestre',
            'orden' => $this->orden = array_key_exists((string) $valor, DibujoDelCronograma::ORDENES) ? (string) $valor : 'urgencia',
            default => null,
        };
    }

    public function quitarFiltros(): void
    {
        $this->buscar = '';
        $this->vencimiento = $this->etapa = null;
        $this->responsable = 'todas';
    }

    protected static string $resource = OportunidadResource::class;

    protected static ?string $title = 'Línea de tiempo';

    protected static ?string $breadcrumb = 'Línea de tiempo';

    public static function canAccess(array $parameters = []): bool
    {
        return OportunidadResource::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    public static function vista(): string
    {
        return VistasDeOportunidades::LINEA_DE_TIEMPO;
    }

    public function mount(): void
    {
        $this->calcularCifras();
        $this->abrirFichaDeLaDireccion();
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->cabeceraDeVistas(),
            // MV-060: el Cronograma refinado, con su orden y sus filtros.
            View::make('oportunidades::linea.cronograma')->viewData(fn (): array => [
                ...app(DibujoDelCronograma::class)->datos(['orden' => $this->orden, 'buscar' => $this->buscar, 'vencimiento' => $this->vencimiento, 'etapa' => $this->etapa, 'responsable' => $this->responsable, 'rango' => $this->rango], (int) auth()->id()),
                'personas' => DB::table('oportunidades as o')->join('users as u', 'u.id', '=', 'o.ejecutivo_user_id')->distinct()->orderBy('u.name')->pluck('u.name', 'u.id')->all(),
                'fichaAbierta' => $this->ficha,
            ]),
            $this->fichaDeOportunidad(),
        ]);
    }
}
