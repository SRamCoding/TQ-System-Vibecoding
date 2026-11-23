{{--
    MVP rápido (observación 7.7, MV-013): en el tema «Sistema» manda el cielo, no el sistema operativo. Va al inicio del
    `<head>`, antes que el guion de Filament, porque Filament decide la clase `dark` en tres lugares (al cargar, al
    navegar sin recargar y en su almacén de Alpine) preguntando `prefers-color-scheme: dark`. Aquí esa pregunta se
    responde con el cielo (sol bajo −1° en la ubicación, con las nubes adelantándolo, como `cielo.blade.php`), así
    todos deciden lo mismo y la transición del cielo no se pisa. Las demás consultas de `matchMedia` siguen intactas.
    En modo desarrollo con fecha simulada, el cielo usa esa hora (`ModoDesarrollo::ahoraDecorativo`).
--}}
@php
    $sede = null;
    $empresa = rescue(fn () => app(\App\Modules\Nucleo\Services\EmpresaService::class)->ficha(), null, false);

    if ($empresa !== null) {
        $s = \Illuminate\Support\Facades\DB::table('party_sedes')->where('party_id', $empresa->id)->where('es_activa', true)->whereNotNull('latitud')->orderBy('id')->first(['latitud', 'longitud']);
        $sede = $s === null ? null : [(float) $s->latitud, (float) $s->longitud];
    }

    if ($sede === null) {
        [$lat, $lon] = array_map('floatval', explode(',', app(\App\Modules\Nucleo\Services\ParametrosService::class)->texto('interfaz.clima_ubicacion_respaldo')) + [0, 0]);
        $sede = [$lat, $lon];
    }

    $simulada = \App\Modules\Nucleo\Desarrollo\ModoDesarrollo::fechaSimulada();
    $desfase = $simulada === null ? 0 : (int) (($simulada->getTimestamp() - time()) * 1000);
@endphp
<script>
    (() => {
        const desfase = @js($desfase);
        const sede = @js($sede);

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

        const ahora = () => new Date(Date.now() + desfase)

        // La ubicación: la del navegador si ya se permitió (la guarda `cielo.blade.php`), si no, la sede.
        const ubicacion = () => {
            try {
                const guardada = JSON.parse(localStorage.getItem('tq-ubicacion') ?? 'null')

                if (Array.isArray(guardada) && guardada.length === 2) {
                    return guardada
                }
            } catch (e) {}

            return sede
        }

        // p de 0 (día) a 1 (noche) entre +6° y −8° del sol; las nubes lo adelantan 3°, la lluvia 5°.
        const cielo = () => {
            const clima = document.documentElement.dataset.tqClima ?? 'despejado'
            const corrimiento = ['llovizna', 'lluvia', 'tormenta'].includes(clima) ? 5 : (['nublado', 'niebla'].includes(clima) ? 3 : 0)
            const [lat, lon] = ubicacion()
            const altura = alturaDelSol(lat, lon, ahora()) - corrimiento

            return { p: Math.min(1, Math.max(0, (6 - altura) / 14)), gris: corrimiento / 8 }
        }

        window.tqCieloBase = { alturaDelSol, ahora, ubicacion, cielo, sede, oscuro: () => cielo().p >= 0.5 }

        const original = window.matchMedia.bind(window)
        window.matchMedia = (consulta) => {
            if (! /prefers-color-scheme:\s*dark/.test(String(consulta))) {
                return original(consulta)
            }

            // Una lista «muda»: el cambio del sistema operativo ya no mueve el tema; el cielo avisa por su cuenta.
            return {
                media: String(consulta),
                get matches() { return window.tqCieloBase.oscuro() },
                onchange: null,
                addEventListener() {},
                removeEventListener() {},
                addListener() {},
                removeListener() {},
                dispatchEvent() { return false },
            }
        }
    })()
</script>
