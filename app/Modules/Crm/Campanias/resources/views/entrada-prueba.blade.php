{{-- Observación 9.3: página de prueba que hace lo mismo que hará la landing: envía el formulario a /entrada/leads con la
     clave. Solo para quien tiene sesión; la clave se muestra porque es la de este equipo. --}}
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Prueba de la landing · Techquk</title>
<style>
    :root { --fondo: #F7F5F0; --sup: #fff; --borde: #E5E0D6; --texto: #1F2328; --suave: #5F5B53; --acento: #0E8FAE; --ok: #2F7D4F; --ok-fondo: #E5F3EA; --err: #C2532F; --err-fondo: #FBE9E3; }
    @media (prefers-color-scheme: dark) { :root { --fondo: #1E1D1B; --sup: #2B2925; --borde: #45413A; --texto: #ECEDEF; --suave: #A8ADB4; --ok-fondo: #1F3326; --err-fondo: #3A2520; } }
    body { margin: 0; font-family: Inter, system-ui, sans-serif; background: var(--fondo); color: var(--texto); }
    main { max-width: 30rem; margin: 0 auto; padding: 2.5rem 1rem; }
    h1 { font-size: 1.3rem; margin: 0 0 .25rem; } p { color: var(--suave); margin: 0 0 1rem; font-size: .9rem; }
    label { display: block; font-size: .85rem; font-weight: 600; margin: .8rem 0 .3rem; }
    input, textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--borde); border-radius: 10px; padding: .6rem .8rem; font: inherit; background: var(--sup); color: inherit; }
    button { margin-top: 1.2rem; width: 100%; height: 2.7rem; border: 0; border-radius: 10px; background: var(--acento); color: #fff; font: 600 1rem inherit; cursor: pointer; }
    .aviso { padding: .9rem 1rem; border-radius: 12px; margin-bottom: 1rem; font-size: .9rem; }
    .ok { background: var(--ok-fondo); color: var(--ok); } .err { background: var(--err-fondo); color: var(--err); }
    .trampa { position: absolute; left: -5000px; }
    code { font-size: .8rem; background: var(--sup); border: 1px solid var(--borde); border-radius: 6px; padding: 1px 5px; }
    a { color: var(--acento); }
</style></head>
<body><main>
    <div style="font-weight: 700; letter-spacing: .08em; color: var(--acento); margin-bottom: 1rem">TECHQUK · PRUEBA DE LA LANDING</div>
    <h1>Formulario de contacto de ejemplo</h1>
    <p>Envía a <code>POST /entrada/leads</code> lo mismo que enviará la página web. Lo enviado aparece en Oportunidades, «Por calificar», con el canal «Página web» (o el de <code>utm_source</code>), y avisa en Mien.</p>
    @if ($enviado)
        <div class="aviso ok">Listo: el contacto entró a Oportunidades como «Por calificar». <a href="/oportunidades?etapa[0]=por_calificar">Verlo</a></div>
    @elseif ($error)
        <div class="aviso err">La entrada lo rechazó (clave, casilla trampa, datos o demasiados envíos seguidos). No se creó nada.</div>
    @endif
    @if ($clave === '')
        <div class="aviso err">La entrada está cerrada: falta <code>TECHQUK_ENTRADA_LEADS_CLAVE</code> en el <code>.env</code>.</div>
    @endif
    <form method="post" action="{{ route('entrada.leads') }}">
        <input type="hidden" name="clave" value="{{ $clave }}">
        <input type="hidden" name="volver" value="{{ url('/entrada/prueba') }}">
        <label for="nombre">Nombre</label><input id="nombre" name="nombre" required maxlength="150" value="Rosa Quispe Torres">
        <label for="organizacion">Organización</label><input id="organizacion" name="organizacion" maxlength="200" value="Clínica San Gabriel del Norte">
        <label for="telefono">Teléfono</label><input id="telefono" name="telefono" maxlength="30" value="987 111 222">
        <label for="correo">Correo</label><input id="correo" name="correo" type="email" maxlength="150" value="rosa.quispe@ejemplo.pe">
        <label for="mensaje">Mensaje</label><textarea id="mensaje" name="mensaje" rows="3" maxlength="2000">Necesitamos cámaras para la recepción y el estacionamiento.</textarea>
        <label for="utm_source">utm_source (de qué canal vino; vacío: Página web)</label><input id="utm_source" name="utm_source" maxlength="40" placeholder="instagram, facebook, google…">
        <label for="campania">Campaña (código o nombre; opcional)</label><input id="campania" name="campania" maxlength="150">
        <div class="trampa" aria-hidden="true"><label for="sitio_web">No llenar</label><input id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off"></div>
        <button type="submit">Enviar como la landing</button>
    </form>
</main></body></html>
