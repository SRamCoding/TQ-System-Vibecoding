{{--
    Observación 8.7 (MV-086), para todo el sistema:
    - Paneles deslizantes y ventanas con fondo sólido (la superficie elevada del ambiente) y una sombra que los separa;
      el vidrio queda para lo pequeño que flota (muelle, globos, menús).
    - Los paneles que salen de un costado oscurecen la pantalla de atrás con una capa negra pareja (42 %; 58 % en
      oscuro). Las ventanas que salen al medio la desenfocan y la aclaran un poco, sin oscurecer.
    - El asistente no tapa: con un panel del costado abierto se aparta al otro lado; con una ventana al medio, se esconde.
--}}
<style>
    .fi-modal-window {
        background-color: var(--tq-superficie-elevada, #fff) !important;
        -webkit-backdrop-filter: none !important; backdrop-filter: none !important;
        border: 1px solid var(--tq-borde, rgb(0 0 0 / .08)) !important;
        box-shadow: 0 28px 70px -18px rgb(0 0 0 / .45) !important;
        color: var(--tq-texto, inherit);
    }
    .dark .fi-modal-window { background-color: var(--tq-superficie-elevada, var(--gray-900)) !important; }
    .fi-modal-window .fi-modal-header, .fi-modal-window .fi-modal-footer { background-color: transparent !important; }
    .fi-modal.fi-modal-slide-over > .fi-modal-close-overlay { background-color: rgb(0 0 0 / .42) !important; -webkit-backdrop-filter: none; backdrop-filter: none; }
    .dark .fi-modal.fi-modal-slide-over > .fi-modal-close-overlay { background-color: rgb(0 0 0 / .58) !important; }
    .fi-modal:not(.fi-modal-slide-over) > .fi-modal-close-overlay { background-color: rgb(255 255 255 / .16) !important; -webkit-backdrop-filter: blur(7px); backdrop-filter: blur(7px); }
    .dark .fi-modal:not(.fi-modal-slide-over) > .fi-modal-close-overlay { background-color: rgb(255 255 255 / .05) !important; }
    html:has(.fi-modal.fi-modal-open.fi-modal-slide-over) #tq-asis { left: 24px !important; }
    html:has(.fi-modal.fi-modal-open:not(.fi-modal-slide-over)) #tq-asis,
    html:has(.fi-modal.fi-modal-open:not(.fi-modal-slide-over)) .tq-asis-globo { opacity: 0 !important; pointer-events: none !important; transition: opacity .2s ease; }
    html:has(.fi-modal.fi-modal-open.fi-modal-slide-over) .tq-asis-globo { display: none !important; }
</style>
