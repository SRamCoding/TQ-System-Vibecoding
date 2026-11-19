# TechQuk MVP · modo rápido

Copia del sistema integral de Techquk S.A.C. (ERP + CRM) para llegar rápido a un MVP funcional que se pueda ver y
usar de punta a punta. Parte del repo `SRamCoding/TechQuk-IntegralSystem` tal como estaba el 19 de noviembre de 2026
(commit `a5e5972`): al abrirla se ve igual que aquella. Desde aquí los agentes construyen sin pruebas, sin planes ni
PR, y Sebastián mira el resultado y pide cambios.

Dos etiquetas marcan el punto de partida: `original-a5e5972` es el repo original tal cual, y `arranque` es esa
misma copia ya adaptada al modo rápido. Todo lo construido aquí es lo que hay después de `arranque`.

Cómo trabajan los agentes: `AGENTS.md`. Qué falta y en qué orden: `docs/vibe/ruta-mvp.md`. Qué se decidió, qué
se supuso y qué atajos se tomaron, para que el repo original lo construya en serio: `docs/traspaso/`.

## Ponerlo a andar (WSL2)

Esta copia usa el puerto **8001** para la app y el **5433** para PostgreSQL, así puede correr al mismo tiempo que la
app del repo original (8000 y 5432).

```bash
docker compose up -d                       # PostgreSQL 16 en el puerto 5433
composer install
cp .env.example .env && php artisan key:generate
# en .env, opcional: DEV_USER_NOMBRES, DEV_USER_APELLIDOS, DEV_USER_EMAIL (tu usuario de desarrollo)
php artisan migrate --seed
npm install && npm run build
php artisan serve --port=8001              # http://localhost:8001 · clave del usuario de desarrollo: password
```

## Traer los datos que ya tienes en la app original (opcional)

Si quieres que se vea con lo mismo que cargaste a mano en tu app actual, y no solo con los datos sembrados:

```bash
# 1. En la carpeta del repo original, con su base levantada:
docker compose exec -T postgres pg_dump -U techquk -Fc techquk > ~/techquk-datos.dump

# 2. En la carpeta de esta copia, con su base levantada y después de `php artisan migrate --seed`:
docker compose exec -T postgres pg_restore -U techquk -d techquk --clean --if-exists --no-owner < ~/techquk-datos.dump

# 3. Las fotos y archivos subidos viven en storage/app: cópialos también.
cp -r ../TechQuk-IntegralSystem/storage/app/. storage/app/
```

Ajusta la ruta del paso 3 a donde tengas el repo original. Si usas el mismo `APP_KEY` en los dos `.env`, las sesiones
y cualquier dato cifrado se leen igual.

## Lo que se quitó respecto del repo original

Las pruebas (`tests/`, `phpunit.xml`), la integración continua (`.github/`), el sitio de documentación (`sitio/`,
`netlify.toml`), los scripts de verificación y copias de trabajo (`scripts/`), los comandos `composer check` y
`check:tarea`, y los flujos y el revisor de arquitectura de `.claude/`. Los hooks que formatean el PHP y protegen el
`.env` se quedaron. Toda la documentación de diseño se quedó como referencia del «qué».
