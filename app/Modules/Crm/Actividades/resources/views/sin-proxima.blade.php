{{-- MVP rápido (t1-agenda): las oportunidades abiertas sin próxima acción, con enlace a su ficha para programarla. --}}
<ul class="space-y-2">
    @foreach ($oportunidades as $id => $nombre)
        <li>
            <a href="{{ \App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad::getUrl(['record' => $id]) }}" class="text-primary-600 dark:text-primary-400 hover:underline">{{ $nombre }}</a>
        </li>
    @endforeach
</ul>
