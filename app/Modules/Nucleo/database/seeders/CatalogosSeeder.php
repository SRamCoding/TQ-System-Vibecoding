<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\IconoRed;
use App\Modules\Nucleo\Enums\OrigenUbigeo;
use App\Modules\Nucleo\Enums\ProgramaEscritorio;
use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\CentroCosto;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\NivelGobierno;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\Rubro;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Models\TipoDocumento;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\TipoPenalidad;
use App\Modules\Nucleo\Models\TipoTrabajo;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\IconosAplicacionService;
use App\Modules\Nucleo\Ubigeos\PadronUbigeos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos maestros de M5 (plan 1.2, D8), los catálogos de identidad de M1 (plan 1.3, D3) y las redes sociales (plan 1.4o) y las aplicaciones del panel (plan 1.4p). Corre en todo ambiente y se puede repetir: busca por `codigo`.
 *
 * Solo crea lo que falta y nunca pisa lo que alguien editó desde la pantalla (plan 1.2b, E9), tampoco en
 * las listas oficiales de SUNAT e ISO. Ubigeos: crea los que faltan; los actualiza la acción de la
 * pantalla contra el INEI.
 *
 * Solo datos públicos o de las notas del DBML; ninguno de la empresa (regla 10). CUBSO no se carga.
 */
final class CatalogosSeeder extends Seeder
{
    public function run(PadronUbigeos $padron): void
    {
        DB::transaction(function () use ($padron): void {
            $this->crearFaltantes(Moneda::class, [
                ['codigo' => 'PEN', 'nombre' => 'Sol', 'simbolo' => 'S/', 'decimales' => 2],
                ['codigo' => 'USD', 'nombre' => 'Dólar estadounidense', 'simbolo' => 'US$', 'decimales' => 2],
            ]);

            // Los cinco del v4 (parte 1), por confirmar con Contabilidad (pendiente «Centros de costo»; plan 1.9, D2).
            $this->crearFaltantes(CentroCosto::class, [
                ['codigo' => 'ADM', 'nombre' => 'Administración'],
                ['codigo' => 'COM', 'nombre' => 'Comercial'],
                ['codigo' => 'OPE', 'nombre' => 'Operaciones'],
                ['codigo' => 'SUM', 'nombre' => 'Suministro'],
                ['codigo' => 'RRHH', 'nombre' => 'Recursos humanos'],
            ]);

            // Catálogo 01 de SUNAT. El signo dice si suma o resta en la cuenta corriente.
            $this->crearFaltantes(TipoDocumento::class, [
                ['codigo' => '01', 'nombre' => 'Factura', 'es_electronico' => true, 'afecta_cuenta_corriente' => true, 'signo' => 1],
                ['codigo' => '02', 'nombre' => 'Recibo por honorarios', 'es_electronico' => true, 'afecta_cuenta_corriente' => true, 'signo' => 1],
                ['codigo' => '03', 'nombre' => 'Boleta de venta', 'es_electronico' => true, 'afecta_cuenta_corriente' => true, 'signo' => 1],
                ['codigo' => '07', 'nombre' => 'Nota de crédito', 'es_electronico' => true, 'afecta_cuenta_corriente' => true, 'signo' => -1],
                ['codigo' => '08', 'nombre' => 'Nota de débito', 'es_electronico' => true, 'afecta_cuenta_corriente' => true, 'signo' => 1],
                ['codigo' => '09', 'nombre' => 'Guía de remisión remitente', 'es_electronico' => true, 'afecta_cuenta_corriente' => false, 'signo' => 1],
            ]);

            // Códigos del catálogo 03 de SUNAT (van en el comprobante electrónico).
            $this->crearFaltantes(UnidadMedida::class, [
                ['codigo' => 'NIU', 'nombre' => 'Unidad', 'abreviatura' => 'und', 'permite_decimales' => false],
                ['codigo' => 'ZZ', 'nombre' => 'Servicio', 'abreviatura' => 'serv', 'permite_decimales' => false],
                ['codigo' => 'MTR', 'nombre' => 'Metro', 'abreviatura' => 'm', 'permite_decimales' => true],
                ['codigo' => 'MTK', 'nombre' => 'Metro cuadrado', 'abreviatura' => 'm²', 'permite_decimales' => true],
                ['codigo' => 'MTQ', 'nombre' => 'Metro cúbico', 'abreviatura' => 'm³', 'permite_decimales' => true],
                ['codigo' => 'KGM', 'nombre' => 'Kilogramo', 'abreviatura' => 'kg', 'permite_decimales' => true],
                ['codigo' => 'LTR', 'nombre' => 'Litro', 'abreviatura' => 'l', 'permite_decimales' => true],
                ['codigo' => 'HUR', 'nombre' => 'Hora', 'abreviatura' => 'h', 'permite_decimales' => true],
                ['codigo' => 'DAY', 'nombre' => 'Día', 'abreviatura' => 'día', 'permite_decimales' => false],
                ['codigo' => 'MON', 'nombre' => 'Mes', 'abreviatura' => 'mes', 'permite_decimales' => false],
                ['codigo' => 'BX', 'nombre' => 'Caja', 'abreviatura' => 'cja', 'permite_decimales' => false],
                ['codigo' => 'PK', 'nombre' => 'Paquete', 'abreviatura' => 'paq', 'permite_decimales' => false],
                ['codigo' => 'RO', 'nombre' => 'Rollo', 'abreviatura' => 'rll', 'permite_decimales' => false],
                ['codigo' => 'SET', 'nombre' => 'Juego', 'abreviatura' => 'jgo', 'permite_decimales' => false],
                ['codigo' => 'PR', 'nombre' => 'Par', 'abreviatura' => 'par', 'permite_decimales' => false],
                // Plan 2.9: la del presupuesto («glb»). No es de SUNAT: se factura como ZZ (servicio).
                ['codigo' => 'GLB', 'nombre' => 'Global', 'abreviatura' => 'glb', 'permite_decimales' => false, 'codigo_sunat' => 'ZZ'],
            ]);

            $this->crearFaltantes(FormaPago::class, [
                ['codigo' => 'CONTADO', 'nombre' => 'Contado', 'dias' => null],
                ['codigo' => 'CREDITO_30', 'nombre' => 'Crédito a 30 días', 'dias' => 30],
                ['codigo' => 'CREDITO_60', 'nombre' => 'Crédito a 60 días', 'dias' => 60],
            ]);

            // Las cuatro que nombra el diseño; la lista real de nueve la confirma el negocio (pendientes.md).
            $this->crearFaltantes(LineaSolucion::class, [
                ['codigo' => 'VIDEOVIGILANCIA', 'nombre' => 'Videovigilancia'],
                ['codigo' => 'REDES', 'nombre' => 'Redes'],
                ['codigo' => 'DATACENTER', 'nombre' => 'Data center'],
                ['codigo' => 'SOFTWARE', 'nombre' => 'Software'],
            ]);

            // Plan 2.20a, N3: los del diseño del CRM y las convocatorias privadas (mapa, «Licitaciones privadas»). Sin «Otro».
            $this->crearFaltantes(OrigenComercial::class, [
                ['codigo' => 'CONVOCATORIA_PUBLICA', 'nombre' => 'Convocatoria pública'],
                ['codigo' => 'CONVOCATORIA_PRIVADA', 'nombre' => 'Convocatoria privada'],
                ['codigo' => 'REFERENCIA', 'nombre' => 'Referencia'],
                ['codigo' => 'FERIA', 'nombre' => 'Feria'],
                ['codigo' => 'SITIO_WEB', 'nombre' => 'Sitio web'],
                ['codigo' => 'VISITA_FRIO', 'nombre' => 'Visita en frío'],
                // Plan 2.0, D5: con detalle obligatorio en el registro que lo usa.
                ['codigo' => 'OTRO', 'nombre' => 'Otro'],
            ]);

            $this->crearFaltantes(NivelGobierno::class, [
                ['codigo' => 'LOCAL', 'nombre' => 'Gobierno local'],
                ['codigo' => 'REGIONAL', 'nombre' => 'Gobierno regional'],
                ['codigo' => 'NACIONAL', 'nombre' => 'Gobierno nacional'],
            ]);

            // Las 21 secciones de la CIIU Rev. 4 (`database/data/rubros-ciiu.php`); el catálogo es cerrado.
            $this->crearFaltantes(Rubro::class, array_map(
                fn (string $letra, array $nombres): array => ['codigo' => $letra, 'nombre' => $nombres[0]],
                array_keys(Catalogo::datosCiiu()),
                Catalogo::datosCiiu(),
            ));

            $this->crearFaltantes(TipoPenalidad::class, [
                ['codigo' => 'MORA', 'nombre' => 'Mora'],
                ['codigo' => 'INCUMPLIMIENTO', 'nombre' => 'Incumplimiento parcial'],
                ['codigo' => 'RESOLUCION', 'nombre' => 'Resolución'],
            ]);

            // Ninguno se factura por defecto hasta que Jefatura de Desarrollo lo defina (pendientes.md).
            $this->crearFaltantes(TipoTrabajo::class, [
                ['codigo' => 'DESARROLLO', 'nombre' => 'Desarrollo', 'es_facturable_default' => false],
                ['codigo' => 'ANALISIS', 'nombre' => 'Análisis', 'es_facturable_default' => false],
                ['codigo' => 'PRUEBAS', 'nombre' => 'Pruebas', 'es_facturable_default' => false],
                ['codigo' => 'SOPORTE', 'nombre' => 'Soporte', 'es_facturable_default' => false],
                ['codigo' => 'REUNION', 'nombre' => 'Reunión', 'es_facturable_default' => false],
            ]);

            // Catálogo 06 de SUNAT. DNI y RUC tienen longitud fija y los usa el código (Catalogo::codigosDelSistema()).
            $this->crearFaltantes(TipoDocumentoIdentidad::class, [
                ['codigo' => '0', 'nombre' => 'Doc. tributario de no domiciliado sin RUC', 'longitud' => null],
                ['codigo' => '1', 'nombre' => 'DNI', 'longitud' => 8],
                ['codigo' => '4', 'nombre' => 'Carné de extranjería', 'longitud' => null],
                ['codigo' => '6', 'nombre' => 'RUC', 'longitud' => 11],
                ['codigo' => '7', 'nombre' => 'Pasaporte', 'longitud' => null],
            ]);

            // Plan 1.4o, D1. Los dominios incluyen los cortos que comparte la propia red (`youtu.be`, `fb.com`).
            $this->crearFaltantes(RedSocial::class, [
                ['codigo' => 'linkedin', 'nombre' => 'LinkedIn', 'dominios' => 'linkedin.com', 'icono' => IconoRed::Linkedin],
                ['codigo' => 'facebook', 'nombre' => 'Facebook', 'dominios' => 'facebook.com,fb.com', 'icono' => IconoRed::Facebook],
                ['codigo' => 'instagram', 'nombre' => 'Instagram', 'dominios' => 'instagram.com', 'icono' => IconoRed::Instagram],
                ['codigo' => 'tiktok', 'nombre' => 'TikTok', 'dominios' => 'tiktok.com', 'icono' => IconoRed::Tiktok],
                ['codigo' => 'youtube', 'nombre' => 'YouTube', 'dominios' => 'youtube.com,youtu.be', 'icono' => IconoRed::Youtube],
                ['codigo' => 'x', 'nombre' => 'X (Twitter)', 'dominios' => 'x.com,twitter.com', 'icono' => IconoRed::X],
                ['codigo' => 'sitio_web', 'nombre' => 'Sitio web', 'dominios' => null, 'icono' => IconoRed::SitioWeb],
                // Plan 2.0, D5: una red que no está en la lista; quien la elige escribe cuál es.
                ['codigo' => 'otra', 'nombre' => 'Otra red', 'dominios' => null, 'icono' => IconoRed::Enlace],
            ]);

            // Plan 1.4z, D9: el tipo de adjunto del ícono subido de una aplicación (solo PNG o WEBP, hasta 1 MB). Plan 1.15,
            // D2: el logo de una ficha de Personas y empresas; el de Techquk sale en los documentos.
            $this->crearFaltantes(TipoAdjunto::class, [
                ['codigo' => IconosAplicacionService::TIPO, 'nombre' => 'Ícono de aplicación', 'documento_type' => 'aplicacion', 'extensiones' => 'png,webp', 'tamano_maximo_mb' => 1],
                ['codigo' => EmpresaService::TIPO_LOGO, 'nombre' => 'Logo', 'documento_type' => 'party', 'extensiones' => 'png,jpg,jpeg,webp', 'tamano_maximo_mb' => 2],
            ]);
            // Plan 1.4p, D5 y D6, y plan 1.4x, D4: la versión web, en este orden. Las 3 primeras son las principales de
            // quien todavía no eligió las suyas. Plan 1.4z, D6: su programa de escritorio viene al crearlas; en una base que
            // ya las tenía, lo puso una sola vez la migración `2026_10_30_000001` (la semilla no lo vuelve a poner si
            // administración lo quitó).
            $this->reordenarAplicacionesDeFabrica();
            $this->crearFaltantes(Aplicacion::class, [
                ['codigo' => 'claude', 'nombre' => 'Claude', 'enlace' => 'https://claude.ai', 'icono' => IconoAplicacion::Claude, 'orden' => 1, 'escritorio' => ProgramaEscritorio::Claude],
                ['codigo' => 'excel', 'nombre' => 'Excel', 'enlace' => 'https://www.office.com/launch/excel', 'icono' => IconoAplicacion::Excel, 'orden' => 2, 'escritorio' => ProgramaEscritorio::Excel],
                ['codigo' => 'whatsapp', 'nombre' => 'WhatsApp', 'enlace' => 'https://web.whatsapp.com', 'icono' => IconoAplicacion::Whatsapp, 'orden' => 3, 'escritorio' => ProgramaEscritorio::Whatsapp],
                ['codigo' => 'word', 'nombre' => 'Word', 'enlace' => 'https://www.office.com/launch/word', 'icono' => IconoAplicacion::Word, 'orden' => 4, 'escritorio' => ProgramaEscritorio::Word],
                ['codigo' => 'powerpoint', 'nombre' => 'PowerPoint', 'enlace' => 'https://www.office.com/launch/powerpoint', 'icono' => IconoAplicacion::Powerpoint, 'orden' => 5, 'escritorio' => ProgramaEscritorio::Powerpoint],
                ['codigo' => 'outlook', 'nombre' => 'Outlook', 'enlace' => 'https://outlook.office.com/mail', 'icono' => IconoAplicacion::Outlook, 'orden' => 6, 'escritorio' => ProgramaEscritorio::Outlook],
                ['codigo' => 'onedrive', 'nombre' => 'OneDrive', 'enlace' => 'https://www.office.com/launch/onedrive', 'icono' => IconoAplicacion::Onedrive, 'orden' => 7],
                ['codigo' => 'google_drive', 'nombre' => 'Google Drive', 'enlace' => 'https://drive.google.com', 'icono' => IconoAplicacion::GoogleDrive, 'orden' => 8],
                ['codigo' => 'linkedin', 'nombre' => 'LinkedIn', 'enlace' => 'https://www.linkedin.com', 'icono' => IconoAplicacion::Linkedin, 'orden' => 9],
                ['codigo' => 'gmail', 'nombre' => 'Gmail', 'enlace' => 'https://mail.google.com', 'icono' => IconoAplicacion::Gmail, 'orden' => 10],
                // Plan 1.4z, D5. Excalidraw guarda lo dibujado en ese navegador, salvo que se exporte.
                ['codigo' => 'teams', 'nombre' => 'Teams', 'enlace' => 'https://teams.microsoft.com', 'icono' => IconoAplicacion::Teams, 'orden' => 11, 'escritorio' => ProgramaEscritorio::Teams],
                ['codigo' => 'google_meet', 'nombre' => 'Google Meet', 'enlace' => 'https://meet.google.com', 'icono' => IconoAplicacion::GoogleMeet, 'orden' => 12],
                // Whiteboard salió el 2026-10-30 (migración `2026_10_30_000002`): Techquk usa otra pizarra.
                ['codigo' => 'excalidraw', 'nombre' => 'Excalidraw', 'enlace' => 'https://excalidraw.com', 'icono' => IconoAplicacion::Excalidraw, 'orden' => 14],
            ]);

            $this->crearFaltantes(RolParty::class, [
                ['codigo' => 'cliente', 'nombre' => 'Cliente', 'descripcion' => 'Hay relación comercial: cuenta, cotizaciones o contratos.'],
                ['codigo' => 'proveedor', 'nombre' => 'Proveedor', 'descripcion' => 'Se le compra o se le contrata.'],
                ['codigo' => 'aliado', 'nombre' => 'Aliado', 'descripcion' => 'Socio comercial o de consorcio.'],
                ['codigo' => 'entidad', 'nombre' => 'Entidad pública', 'descripcion' => 'Organismo que contrata por el Estado, aunque nunca se le haya vendido.'],
                ['codigo' => 'colaborador', 'nombre' => 'Colaborador', 'descripcion' => 'Trabaja para la empresa.'],
                ['codigo' => 'banco', 'nombre' => 'Banco', 'descripcion' => 'Entidad financiera de las cuentas bancarias.'],
                // Plan 2.21, D4: se asigna solo al registrar la ficha como competidor de una oportunidad.
                ['codigo' => 'competidor', 'nombre' => 'Competidor', 'descripcion' => 'Compite con la empresa por una oportunidad.'],
            ]);

            // Plan 1.4b, D1a: las categorías de las frases del saludo. Las frases las siembra FrasesSaludoSeeder.
            $this->crearFaltantes(CategoriaFrase::class, [
                ['codigo' => 'propias', 'nombre' => 'Frases propias'],
                ['codigo' => 'autores', 'nombre' => 'Citas de autores'],
                ['codigo' => 'refranes', 'nombre' => 'Refranes'],
                ['codigo' => 'versiculos', 'nombre' => 'Versículos de la Biblia'],
            ]);

            $this->ubigeos($padron);
        });
    }

    /**
     * @param  class-string<Model>  $modelo
     * @param  list<array<string, mixed>>  $filas
     */
    private function crearFaltantes(string $modelo, array $filas): void
    {
        foreach ($filas as $fila) {
            $modelo::query()->firstOrCreate(['codigo' => $fila['codigo']], $fila);
        }
    }

    /**
     * Plan 1.4x, D4: en una base que ya tiene Word, Excel y PowerPoint con el orden de fábrica viejo (1, 2, 3), los pasa
     * al nuevo (Claude, Excel y WhatsApp arriba). Si administración ya cambió alguno, no toca nada: manda lo editado.
     */
    private function reordenarAplicacionesDeFabrica(): void
    {
        $viejo = ['word' => 1, 'excel' => 2, 'powerpoint' => 3];
        $actual = Aplicacion::query()->whereIn('codigo', array_keys($viejo))->pluck('orden', 'codigo')->all();
        ksort($actual);
        ksort($viejo);

        if ($actual === $viejo && Aplicacion::query()->count() === 3) {
            // Por el modelo, no en bloque: sube `version` y deja bitácora, así un guardado abierto en Catálogos choca.
            foreach (['word' => 4, 'excel' => 2, 'powerpoint' => 5] as $codigo => $orden) {
                $aplicacion = Aplicacion::query()->where('codigo', $codigo)->sole();
                $aplicacion->orden = $orden;
                $aplicacion->version++;
                $aplicacion->save();
            }
        }
    }

    /**
     * Instalación inicial desde el CSV del repositorio: solo crea los distritos que faltan. Renombrar y
     * desactivar es trabajo de la actualización desde la pantalla (acción `nucleo.actualizar-ubigeos`),
     * que trae la lista vigente del INEI; el seeder no la deshace aunque el CSV esté atrasado.
     */
    private function ubigeos(PadronUbigeos $padron): void
    {
        $existentes = Ubigeo::query()->pluck('codigo')->flip();

        foreach ($padron->leerGuardado() as $distrito) {
            if (! $existentes->has($distrito->codigo)) {
                Ubigeo::query()->create([
                    'codigo' => $distrito->codigo,
                    'departamento' => $distrito->departamento,
                    'provincia' => $distrito->provincia,
                    'distrito' => $distrito->distrito,
                    'origen' => OrigenUbigeo::Inei,
                ]);
            }
        }
    }
}
