{{-- Observación 7.6: el formulario corto y público de una campaña. Lo enviado entra a Oportunidades como «Por calificar». --}}
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $campania->nombre }} · Techquk</title>
<style>
    body { margin: 0; font-family: Inter, system-ui, sans-serif; background: #F7F5F0; color: #1F2328; }
    main { max-width: 28rem; margin: 0 auto; padding: 2.5rem 1rem; }
    h1 { font-size: 1.35rem; margin: 0 0 .25rem; } p { color: #5F5B53; margin: 0 0 1.25rem; }
    label { display: block; font-size: .85rem; font-weight: 600; margin: .9rem 0 .3rem; }
    input, textarea { width: 100%; box-sizing: border-box; border: 1px solid #E5E0D6; border-radius: 10px; padding: .65rem .8rem; font: inherit; background: #fff; }
    button { margin-top: 1.25rem; width: 100%; height: 2.75rem; border: 0; border-radius: 10px; background: #0E8FAE; color: #fff; font: 600 1rem inherit; cursor: pointer; }
    .ok { background: #E5F3EA; color: #2F7D4F; padding: 1rem; border-radius: 12px; } .trampa { position: absolute; left: -5000px; }
    .err { color: #D9534A; font-size: .8rem; }
    @media (prefers-color-scheme: dark) { body { background: #1E1D1B; color: #ECEDEF; } input, textarea { background: #2B2925; border-color: #45413A; color: inherit; } p { color: #A8ADB4; } }
</style></head>
<body><main>
    <div style="font-weight: 700; letter-spacing: .08em; color: #0E8FAE; margin-bottom: 1rem">TECHQUK</div>
    <h1>{{ $campania->nombre }}</h1>
    @if ($enviado)
        <div class="ok">¡Gracias! Recibimos tus datos y te contactaremos pronto.</div>
    @else
        <p>Déjanos tus datos y cuéntanos qué necesitas.</p>
        <form method="post">
            @csrf
            <label for="nombre">Nombre</label><input id="nombre" name="nombre" required maxlength="150" value="{{ old('nombre') }}">
            @error('nombre')<div class="err">{{ $message }}</div>@enderror
            <label for="organizacion">Organización</label><input id="organizacion" name="organizacion" maxlength="200" value="{{ old('organizacion') }}">
            <label for="telefono">Teléfono</label><input id="telefono" name="telefono" maxlength="30" value="{{ old('telefono') }}">
            <label for="correo">Correo</label><input id="correo" name="correo" type="email" maxlength="150" value="{{ old('correo') }}">
            @error('correo')<div class="err">{{ $message }}</div>@enderror
            <label for="necesita">¿Qué necesitas?</label><textarea id="necesita" name="necesita" rows="4" maxlength="1000">{{ old('necesita') }}</textarea>
            <input class="trampa" name="sitio" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button type="submit">Enviar</button>
        </form>
    @endif
</main></body></html>
