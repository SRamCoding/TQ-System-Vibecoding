{{--
    MVP rápido (ficha op-gran-momento, plan 1.4d paso 6): en Navidad (24 y 25 de diciembre) y Año Nuevo (31 y 1), una
    ambientación de pantalla completa de 10 segundos que no tapa ni bloquea (el clic la atraviesa): nieve en Navidad y
    fuegos lejanos en Año Nuevo. Una vez cada `interfaz.ambientacion_minutos` (240 por omisión) por persona y navegador.
    Con «reducir animaciones» no se muestra. Para verla otro día: `tqAmbientacionFestiva('navidad')` en la consola.
--}}
@php
    $hoy = \Carbon\CarbonImmutable::parse(\App\Modules\Nucleo\Tiempo\Calendario::hoy());
    $md = $hoy->format('m-d');
    $tipo = in_array($md, ['12-24', '12-25'], true) ? 'navidad' : (in_array($md, ['12-31', '01-01'], true) ? 'anio_nuevo' : null);
@endphp
<style>
    #tq-amb-festiva { position: fixed; inset: 0; z-index: 2147481000; pointer-events: none; overflow: hidden; transition: opacity 1.5s ease; }
    #tq-amb-festiva i { position: absolute; top: -20px; display: block; border-radius: 50%; background: white; opacity: .85; animation: tq-amb-cae linear forwards; }
    #tq-amb-festiva b { position: absolute; display: block; border-radius: 50%; opacity: 0; animation: tq-amb-abre 2.2s ease-out forwards; }
    @keyframes tq-amb-cae { to { transform: translate(var(--dx), 110vh); } }
    @keyframes tq-amb-abre { 0% { opacity: 0; transform: scale(.1); } 30% { opacity: .8; } 100% { opacity: 0; transform: scale(1); } }
</style>
<script>
(() => {
    window.tqAmbientacionFestiva = (tipo) => {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones') || document.getElementById('tq-amb-festiva')) return
        const capa = document.createElement('div')
        capa.id = 'tq-amb-festiva'
        capa.setAttribute('aria-hidden', 'true')
        if (tipo === 'navidad') {
            for (let i = 0; i < 70; i++) {
                const c = document.createElement('i'), t = 4 + Math.random() * 5, s = 3 + Math.random() * 5
                c.style.cssText = `left:${Math.random() * 100}vw;width:${s}px;height:${s}px;animation-duration:${t}s;animation-delay:${Math.random() * 5}s;--dx:${Math.random() * 80 - 40}px`
                capa.appendChild(c)
            }
        } else {
            const colores = ['#ffd166', '#ef476f', '#06d6a0', '#118ab2', '#f4f1de']
            for (let i = 0; i < 9; i++) {
                const f = document.createElement('b'), s = 120 + Math.random() * 120
                f.style.cssText = `left:${10 + Math.random() * 80}vw;top:${8 + Math.random() * 35}vh;width:${s}px;height:${s}px;animation-delay:${i * 1}s;border:3px dotted ${colores[i % 5]};margin:-${s / 2}px`
                capa.appendChild(f)
            }
        }
        document.body.appendChild(capa)
        setTimeout(() => { capa.style.opacity = 0 }, 9000)
        setTimeout(() => capa.remove(), 10500)
    }

    const tipo = @js($tipo), minutos = @js((int) app(\App\Modules\Nucleo\Services\ParametrosService::class)->entero('interfaz.ambientacion_minutos'));
    if (! tipo) return
    let ultima = 0
    try { ultima = Number(localStorage.getItem('tq-amb-festiva') || 0) } catch (e) {}
    if (Date.now() - ultima < minutos * 60000) return
    try { localStorage.setItem('tq-amb-festiva', String(Date.now())) } catch (e) {}
    const lanzar = () => setTimeout(() => window.tqAmbientacionFestiva(tipo), 1200)
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', lanzar) : lanzar()
})()
</script>
