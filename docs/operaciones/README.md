# Operaciones

Documentos para **operar** el sistema en el servidor de Techquk. Están escritos para que TI pueda leerlos
sin conocer el código.

| Documento | Para qué |
|---|---|
| `responsabilidades.md` | Qué hace TI y qué hace desarrollo |
| `requisitos-servidor.md` | Qué necesita el servidor |
| `despliegue.md` | Cómo se instala y actualiza una versión |
| `respaldos.md` | Qué se respalda, cada cuánto y dónde |
| `restauracion.md` | Cómo se recupera el sistema desde un respaldo |
| `monitoreo.md` | Qué se vigila y quién recibe las alertas |
| `antes-de-datos-reales.md` | Qué se configura en las pantallas antes de registrar los primeros datos reales |

**Regla para agentes de IA:** nunca acceden al servidor de producción, a sus credenciales ni a su base
de datos. Estos documentos se escriben y se mantienen; no se ejecutan desde un agente.
