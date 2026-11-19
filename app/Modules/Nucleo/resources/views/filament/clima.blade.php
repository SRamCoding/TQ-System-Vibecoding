{{--
    El clima del lugar de la computadora, en todas las pantallas (plan 1.4u, D1; antes solo en el dibujo del saludo,
    plan 1.4e2, D5). Pone en <html> `data-tq-clima` (despejado, nublado, niebla, llovizna, lluvia o tormenta),
    `data-tq-temperatura` (calor, templado o frío); el dibujo del saludo y los efectos de la estación lo leen de ahí (la
    noche, `data-tq-noche`, la pone la vista de navegación). Antes de dibujar, desde lo recordado; si no hay o pasaron 30 minutos, pide la ubicación (una
    vez; si se niega, la de respaldo) y consulta Open-Meteo **desde el navegador**, con la posición redondeada a un
    decimal (unos 10 km): ni la ubicación ni el clima pasan por el servidor ni se guardan en la base. Sin respuesta, nada:
    todo queda como sin clima. En el modo desarrollo, lo elegido en «Clima» y «Temperatura».
    Con los grados pone también `--tq-calor` (0 a 1, `LuzDelDia::calor()`), con el que la barra y el fondo mezclan su
    tinte (plan 1.4ac); una temperatura forzada usa sus grados de `ClimaDelEntorno::GRADOS_FORZADOS`.
--}}
@php /** @var array<string, mixed> $datos ClimaDelEntorno::datos(), ya calculado por el proveedor del panel */ @endphp
    <script>
        (() => {
            const html = document.documentElement, datos = @js($datos), CLAVE = 'tq-clima', DURA = datos.minutos * 60 * 1000
            const temperatura = (grados) => grados === null || grados === undefined ? null : grados >= datos.calor ? 'calor' : grados <= datos.frio ? 'frio' : 'templado'
            const poner = (clima, grados) => {
                clima = datos.forzado ?? clima
                const temp = datos.temperatura ?? temperatura(grados)
                const g = datos.temperatura ? datos.grados[datos.temperatura] : grados
                if (g !== null && g !== undefined) html.style.setProperty('--tq-calor', String(Math.min(1, Math.max(0, (g - 10) / 24))))
                if (clima) html.dataset.tqClima = clima
                if (temp) html.dataset.tqTemperatura = temp
                if (clima) document.querySelectorAll('.tq-compuesto[data-clima-activo]:not([data-clima-forzado])').forEach((d) => { d.dataset.clima = clima })
            }
            const alDibujar = (f) => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', f) : f()
            if (datos.forzado) { poner(null, null); alDibujar(() => poner(null, null)); return }
            let guardado = null
            try { guardado = JSON.parse(localStorage.getItem(CLAVE) || 'null') } catch (e) {}
            const vigente = guardado && Date.now() - guardado.t < DURA
            poner(vigente ? guardado.c : null, vigente ? guardado.g : null)
            alDibujar(() => poner(vigente ? guardado.c : null, vigente ? guardado.g : null))
            if (vigente && guardado.g !== undefined) return
            const categoria = (c) => c <= 1 ? 'despejado' : c <= 3 ? 'nublado' : (c === 45 || c === 48) ? 'niebla'
                : (c >= 51 && c <= 57) ? 'llovizna' : ((c >= 61 && c <= 67) || (c >= 80 && c <= 82)) ? 'lluvia' : c >= 95 ? 'tormenta' : 'nublado'
            const consultar = (lat, lon) => fetch('https://api.open-meteo.com/v1/forecast?latitude=' + lat.toFixed(1) + '&longitude=' + lon.toFixed(1) + '&current=weather_code,temperature_2m')
                .then((r) => r.ok ? r.json() : Promise.reject())
                .then((d) => {
                    const c = categoria(d.current.weather_code), g = d.current.temperature_2m
                    alDibujar(() => poner(c, g))
                    try { localStorage.setItem(CLAVE, JSON.stringify({ c, g, t: Date.now() })) } catch (e) {}
                })
                .catch(() => {})
            const [lat, lon] = (datos.respaldo || '').split(',').map(Number)
            const respaldo = () => { if (Number.isFinite(lat) && Number.isFinite(lon)) consultar(lat, lon) }
            if (! navigator.geolocation) return respaldo()
            navigator.geolocation.getCurrentPosition((p) => consultar(p.coords.latitude, p.coords.longitude), respaldo, { maximumAge: DURA, timeout: 8000 })
        })()
    </script>
