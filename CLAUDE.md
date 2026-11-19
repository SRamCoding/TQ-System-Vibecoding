@AGENTS.md

## Específico de Claude Code

- Este es el repo de **modo rápido**: sin pruebas, sin planes, sin ramas ni PR. Se trabaja en `main` y se hace
  commit y push al terminar cada pedido.
- Los hooks formatean el PHP que edites y bloquean cambios a `.env`. Si falta una variable, agrégala a `.env.example`
  y avisa.
- La app corre en `http://localhost:8001` y su PostgreSQL en el puerto `5433`, para convivir con la app del repo
  original (8000 y 5432) en la misma máquina.
- Antes de avisar, la ficha de `docs/traspaso/fichas/` al día y cada decisión nueva de Sebastián con su número en
  `docs/traspaso/decisiones.md` (nunca se reescribe una entrada: se reemplaza con otra). Si Sebastián dice «así me gusta», ficha aprobada y
  etiqueta `aprobado/<ficha>-v<n>` subida.
- Al avisar que algo está listo: qué cambió en dos o tres líneas, las URL que hay que abrir y cualquier supuesto
  tomado. Sin listas largas ni reportes: Sebastián prefiere la explicación narrada y corta.
- Cuida el cupo: lee partes de documentos, no documentos completos.
