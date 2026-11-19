{{--
    MVP rápido (ficha t3b-transicion-cielo, MV-012): en el tema «Sistema», la pantalla sigue el cielo en vez del sistema
    operativo. La ubicación es la del navegador si la persona ya dio el permiso (no se pide solo para esto); si no, la de
    la sede principal de la empresa (o la de respaldo del clima). Con la altura del sol se calcula cuánta noche hay: de
    día (sol sobre +6°) claro; de noche (bajo −6°) oscuro; entre medio, el crepúsculo, un velo que oscurece de a poco y
    el cambio a oscuro a la mitad. El clima que deja la ambientación (`data-tq-clima`) lo matiza: con nubes o lluvia el
    crepúsculo llega antes y el día se ve más apagado. Se recalcula cada minuto; respeta «reducir animaciones».
--}}
@php
    $sede = null;
    $empresa = app(\App\Modules\Nucleo\Services\EmpresaService::class)->ficha();

    if ($empresa !== null) {
        $s = \Illuminate\Support\Facades\DB::table('party_sedes')->where('party_id', $empresa->id)->where('es_activa', true)->whereNotNull('latitud')->orderBy('id')->first(['latitud', 'longitud']);
        $sede = $s === null ? null : [(float) $s->latitud, (float) $s->longitud];
    }

    if ($sede === null) {
        [$lat, $lon] = array_map('floatval', explode(',', app(\App\Modules\Nucleo\Services\ParametrosService::class)->texto('interfaz.clima_ubicacion_respaldo')) + [0, 0]);
        $sede = [$lat, $lon];
    }
@endphp
<style>
    #tq-velo-cielo { position: fixed; inset: 0; pointer-events: none; z-index: 2147483000; opacity: 0; background: rgb(18 24 52); mix-blend-mode: multiply; transition: opacity 50s linear; }
    html.tq-sin-animaciones #tq-velo-cielo { transition: none; }
    @media (prefers-reduced-motion: reduce) { #tq-velo-cielo { transition: none; } }
    html.tq-cielo body, html.tq-cielo .fi-topbar, html.tq-cielo .fi-sidebar { transition: background-color 2s ease, color 2s ease; }
</style>
<script>
    (() => {
        const raiz = document.documentElement
        const sede = @js($sede)
        let ubicacion = sede

        // La altura del sol en grados (aproximación de la NOAA; basta para saber día, crepúsculo o noche).
        const alturaDelSol = (lat, lon, fecha) => {
            const rad = Math.PI / 180
            const d = (fecha.getTime() - 946728000000) / 86400000
            const g = ((357.529 + 0.98560028 * d) % 360) * rad
            const q = (280.459 + 0.98564736 * d) % 360
            const l = (q + 1.915 * Math.sin(g) + 0.020 * Math.sin(2 * g)) * rad
            const e = (23.439 - 0.00000036 * d) * rad
            const ra = Math.atan2(Math.cos(e) * Math.sin(l), Math.cos(l)) / rad
            const dec = Math.asin(Math.sin(e) * Math.sin(l))
            const gmst = (18.697374558 + 24.06570982441908 * d) % 24
            const h = ((gmst * 15 + lon - ra) % 360) * rad

            return Math.asin(Math.sin(lat * rad) * Math.sin(dec) + Math.cos(lat * rad) * Math.cos(dec) * Math.cos(h)) / rad
        }

        // Cuánta noche hay, de 0 (día) a 1 (noche). Las nubes adelantan el crepúsculo 3°; la lluvia, 5°.
        const noche = () => {
            const clima = raiz.dataset.tqClima ?? 'despejado'
            const corrimiento = ['llovizna', 'lluvia', 'tormenta'].includes(clima) ? 5 : (['nublado', 'niebla'].includes(clima) ? 3 : 0)
            const altura = alturaDelSol(ubicacion[0], ubicacion[1], new Date()) - corrimiento

            return { valor: Math.min(1, Math.max(0, (6 - altura) / 12)), apagado: corrimiento > 0 ? (corrimiento === 5 ? 0.10 : 0.06) : 0 }
        }

        const velo = () => document.getElementById('tq-velo-cielo') ?? (() => {
            const v = document.createElement('div')
            v.id = 'tq-velo-cielo'
            document.body.appendChild(v)

            return v
        })()

        const aplicar = () => {
            const tema = (() => { try { return localStorage.getItem('theme') } catch (e) { return null } })() ?? 'system'

            if (tema !== 'system') {
                raiz.classList.remove('tq-cielo')
                if (document.body) velo().style.opacity = 0

                return
            }

            const { valor, apagado } = noche()
            raiz.classList.add('tq-cielo')
            raiz.classList.toggle('dark', valor >= 0.5)
            raiz.dataset.tqNoche = valor.toFixed(2)

            // El velo crece hacia la mitad del crepúsculo y se va al terminar de oscurecer (de noche manda el tema oscuro).
            const crepusculo = valor < 0.5 ? valor / 0.5 : (1 - valor) / 0.5
            if (document.body) velo().style.opacity = Math.min(0.45, 0.35 * crepusculo + apagado * (1 - valor))
        }

        // Si la persona ya permitió la ubicación, manda la suya; nunca se pide solo para esto.
        if (navigator.permissions && navigator.geolocation) {
            navigator.permissions.query({ name: 'geolocation' }).then((p) => {
                if (p.state === 'granted') {
                    navigator.geolocation.getCurrentPosition((pos) => { ubicacion = [pos.coords.latitude, pos.coords.longitude]; aplicar() }, () => {}, { maximumAge: 30 * 60000, timeout: 8000 })
                }
            }).catch(() => {})
        }

        aplicar()
        document.addEventListener('DOMContentLoaded', aplicar)
        document.addEventListener('livewire:navigated', aplicar)
        window.addEventListener(@js(\App\Modules\Nucleo\Filament\Apariencia::EVENTO), () => setTimeout(aplicar, 0))
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => setTimeout(aplicar, 0))
        new MutationObserver(aplicar).observe(raiz, { attributes: true, attributeFilter: ['data-tq-clima'] })
        setInterval(aplicar, 60000)
        window.tqCielo = { aplicar, noche, alturaDelSol, ubicacion: () => ubicacion }
    })()
</script>
