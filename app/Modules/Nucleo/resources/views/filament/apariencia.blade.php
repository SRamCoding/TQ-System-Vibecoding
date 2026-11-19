{{--
    Preferencias de apariencia (plan 1.4g, D4 y D5; Filament\Apariencia).
    - Antes de dibujar la página, `<html>` recibe las clases de la persona y el tema de la base pasa al navegador, donde
      lo lee Filament: el que corrió antes en el `<head>` ya puso o no la clase `dark`, y aquí se corrige. Sin parpadeo.
    - Tamaño de letra: el tamaño de la raíz; Filament mide en `rem` y todo crece en proporción.
    - Densidad compacta, solo en tablas: achica la variable de espaciado de Tailwind (`--spacing`, con la que Filament
      calcula los rellenos) dentro de la celda y el encabezado (`fi-ta-cell`, `fi-ta-header-cell`: excepción anotada en
      docs/convenciones/interfaz.md).
    - Reducir animaciones: cada animación nuestra la respeta en su vista, junto con «reducir movimiento» del sistema.
    - Al cambiar una desde «Mis preferencias», el evento `tq-apariencia` las aplica sin recargar.
--}}
@php
    $usuario = filament()->auth()->user();
    $clases = $usuario instanceof \App\Modules\Nucleo\Models\User ? \App\Modules\Nucleo\Filament\Apariencia::clases($usuario) : [];
    $tema = $usuario instanceof \App\Modules\Nucleo\Models\User ? \App\Modules\Nucleo\Filament\Apariencia::tema($usuario) : null;
@endphp
<style>
    html.tq-letra-grande { font-size: 112.5%; }
    html.tq-compacto .fi-ta-cell,
    html.tq-compacto .fi-ta-header-cell { --spacing: 0.15rem; }
</style>
<script>
    (() => {
        const raiz = document.documentElement
        const aplicar = (clases, tema) => {
            @js(\App\Modules\Nucleo\Filament\Apariencia::CLASES).forEach((clase) => raiz.classList.toggle(clase, clases.includes(clase)))

            if (! tema) {
                return
            }

            try { localStorage.setItem('theme', tema) } catch (e) {}
            raiz.classList.toggle('dark', tema === 'dark' || (tema === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches))
        }

        aplicar(@js($clases), @js($tema))
        window.addEventListener(@js(\App\Modules\Nucleo\Filament\Apariencia::EVENTO), (evento) => aplicar(evento.detail?.clases ?? [], evento.detail?.tema ?? null))
    })()
</script>
