{{--
    MVP rápido (ficha t3b-transicion-cielo, MV-012 y MV-013): en el tema «Sistema», la pantalla sigue el cielo real.
    De día es el tema claro tal cual; de noche, el oscuro tal cual. En el crepúsculo (sol entre +6° y −8°) el ambiente
    cambia de forma continua, como afuera: dorado mientras el sol baja, naranja y rosado al ocultarse, la hora azul y la
    oscuridad. Dos velos lo hacen: «sombra» (multiplica) oscurece y tiñe el tema claro; «luz» (trama) aclara el oscuro.
    En el punto más oscuro, cuando el claro ensombrecido ya se ve como el oscuro aclarado, el tema cambia fundiendo
    todos los colores en 10 s mientras un velo se apaga y el otro se enciende. Al amanecer, al revés. Ubicación: la del
    navegador si ya está permitida (no se pide solo para esto); si no, la sede principal (o la de respaldo del clima).
    Nubes o lluvia (`data-tq-clima`) adelantan el crepúsculo y apagan los colores. Se recalcula cada 20 s y cada cambio
    se funde en 20 s: no hay saltos.
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
    .tq-velo { position: fixed; inset: 0; pointer-events: none; z-index: 2147483000; opacity: 0; transition: opacity 20s linear, background-color 20s linear; }
    #tq-velo-sombra { mix-blend-mode: multiply; }
    #tq-velo-luz { mix-blend-mode: screen; }
    html.tq-cielo-cambio .tq-velo { transition: opacity 10s ease-in-out, background-color 10s linear; }
    html.tq-cielo-cambio *, html.tq-cielo-cambio *::before, html.tq-cielo-cambio *::after {
        transition: background-color 10s ease-in-out, color 10s ease-in-out, border-color 10s ease-in-out, fill 10s ease-in-out, stroke 10s ease-in-out, box-shadow 10s ease-in-out !important;
    }
    html.tq-sin-animaciones .tq-velo { transition: none; }
    @media (prefers-reduced-motion: reduce) { .tq-velo { transition: none; } }
</style>
<script>
    (() => {
        const raiz = document.documentElement
        const sede = @js($sede);
        let ubicacion = sede

        // La altura del sol en grados (aproximación de la NOAA).
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

        const mezcla = (a, b, t) => a.map((v, i) => Math.round(v + (b[i] - v) * t))
        // El color del cielo a lo largo del crepúsculo claro (q de 0 a 1): dorado, naranja, rosado, violeta, azul noche.
        const TONOS = [[0, [255, 214, 150]], [0.3, [255, 170, 110]], [0.5, [236, 128, 118]], [0.7, [150, 100, 160]], [0.85, [70, 70, 140]], [1, [34, 42, 96]]]
        const tono = (q) => {
            for (let i = 1; i < TONOS.length; i++) {
                if (q <= TONOS[i][0]) {
                    return mezcla(TONOS[i - 1][1], TONOS[i][1], (q - TONOS[i - 1][0]) / (TONOS[i][0] - TONOS[i - 1][0]))
                }
            }

            return TONOS[TONOS.length - 1][1]
        }

        // El estado del cielo: p de 0 (día) a 1 (noche) entre +6° y −8° del sol; las nubes lo adelantan 3°, la lluvia 5°.
        const cielo = () => {
            const clima = raiz.dataset.tqClima ?? 'despejado'
            const corrimiento = ['llovizna', 'lluvia', 'tormenta'].includes(clima) ? 5 : (['nublado', 'niebla'].includes(clima) ? 3 : 0)
            const altura = alturaDelSol(ubicacion[0], ubicacion[1], new Date()) - corrimiento

            return { p: Math.min(1, Math.max(0, (6 - altura) / 14)), gris: corrimiento / 8 }
        }

        const velo = (id) => document.getElementById(id) ?? (() => {
            const v = document.createElement('div')
            v.id = id
            v.className = 'tq-velo'
            document.body.appendChild(v)

            return v
        })()

        const aplicar = () => {
            if (! document.body) {
                return
            }

            const tema = (() => { try { return localStorage.getItem('theme') } catch (e) { return null } })() ?? 'system'
            const sombra = velo('tq-velo-sombra')
            const luz = velo('tq-velo-luz')

            if (tema !== 'system') {
                sombra.style.opacity = 0
                luz.style.opacity = 0
                raiz.removeAttribute('data-tq-noche')

                return
            }

            const { p, gris } = cielo()
            const oscuro = p >= 0.5

            if (oscuro !== raiz.classList.contains('dark') && raiz.dataset.tqNoche !== undefined) {
                // El cambio de tema en el punto más oscuro: todo se funde en 10 s.
                raiz.classList.add('tq-cielo-cambio')
                setTimeout(() => raiz.classList.remove('tq-cielo-cambio'), 11000)
            }

            raiz.classList.toggle('dark', oscuro)
            raiz.dataset.tqNoche = p.toFixed(3)

            if (! oscuro) {
                const q = p / 0.5
                sombra.style.backgroundColor = 'rgb(' + mezcla(tono(q), [150, 150, 160], gris).join(',') + ')'
                sombra.style.opacity = (0.88 * Math.pow(q, 1.4) + gris * 0.15 * (1 - q)).toFixed(3)
                luz.style.opacity = 0
            } else {
                const r = (p - 0.5) / 0.5
                luz.style.backgroundColor = 'rgb(' + mezcla([96, 108, 160], [60, 66, 100], r).join(',') + ')'
                luz.style.opacity = (0.5 * Math.pow(1 - r, 1.4)).toFixed(3)
                sombra.style.opacity = 0
            }
        }

        // Si la persona ya permitió la ubicación, manda la suya; nunca se pide solo para esto.
        if (navigator.permissions && navigator.geolocation) {
            navigator.permissions.query({ name: 'geolocation' }).then((permiso) => {
                if (permiso.state === 'granted') {
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
        setInterval(aplicar, 20000)
        window.tqCielo = { aplicar, cielo, alturaDelSol, ubicacion: () => ubicacion }
    })()
</script>
