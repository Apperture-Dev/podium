# build-runner

La imagen `jobImage` que el (futuro, diferido) lanzador de Kubernetes usa para
ejecutar un `BuildJob` — ver `docs/podium-domain/build/model.md`. Genérica: no
hay una imagen por lenguaje, esta misma clona el repo, localiza `podium.yaml`
y elige el Dockerfile de plantilla correcto en `templates/` según
`lang`/`framework` — el propio Template en el dominio (BC Build) solo
referencia esta imagen por su tag, nunca contiene lógica de build.

## Contrato de entrada

Variables de entorno — mismas claves que `BuildJobRequested.envVars`
(`docs/podium-domain/build/model.md`), más `IMAGE_REGISTRY` (config de
infraestructura, no de dominio — ver "Decisiones de alcance" en ese mismo
documento):

| Variable | Origen |
|---|---|
| `SERVICE_NAME`, `PROJECT_ID`, `TEMPLATE_ID`, `VERSION`, `COMMIT_ID`, `REPOSITORY_URL`, `PROVIDER`, `BUILD_JOB_ID` | `BuildJobRequested` |
| `IMAGE_REGISTRY` | Configuración del clúster (dónde empuja el lanzador) |

## Qué hace

1. Clona `REPOSITORY_URL` en `COMMIT_ID`.
2. Lee `podium.yaml` en la raíz del repo, busca la clave `SERVICE_NAME` — de
   ahí saca `src` (subcarpeta), `lang`, `framework`.
3. Selecciona `templates/{lang}-{framework}.Dockerfile` — falla con mensaje
   claro si no existe esa combinación (sin autodetección, "Podium provee el
   Dockerfile de plantilla" — `build/discovery.md`).
4. `buildah bud` con ese Dockerfile contra `<repo>/<src>`, tag
   `${IMAGE_REGISTRY}/${PROJECT_ID}-${SERVICE_NAME}:${VERSION}`.
5. `buildah push`.

Señalizar el resultado (`JobSucceeded`/`JobFailed`) queda diferido — ver
`build/model.md`, "Frontera de infraestructura".

## Plantillas disponibles

| lang | framework | Convención | Puerto |
|---|---|---|---|
| `nodejs` | `nestjs` | `npm ci && npm run build`, arranca con `npm start` | 3000 |
| `nodejs` | `nextjs` | `npm ci && npm run build`, arranca con `npm start` (`next start`); sin asumir `output: "standalone"` | 3000 |
| `nodejs` | `react` | `npm ci && npm run build`; el estático resultante (`dist/`, o `build/` si es CRA) lo sirve nginx, sin Node en runtime | 80 |
| `nodejs` | `vue` | Igual que `react`: build de Vite y nginx sirviendo `dist/` | 80 |
| `python` | `fastapi` | `pip install -r requirements.txt`, arranca con `uvicorn main:app`; uvicorn lo aporta la plantilla, no hace falta declararlo | 8000 |

El puerto de cada plantilla es el `defaultPort` de su `Template` en el catálogo de Build
(`app:build:seed-templates`), y es el que acaba en `values.port` del chart `podium-app`. Una
plantilla nueva se añade aquí **y** en ese comando: el Dockerfile sin entrada en el catálogo no
lo resuelve nadie, y la entrada sin Dockerfile falla en el `Job` de build.

Las dos plantillas de SPA escriben su configuración de nginx dentro del propio Dockerfile en vez
de copiarla: el contexto de build es el repo del equipo, no este directorio. El `try_files` de esa
configuración es lo que evita un 404 al abrir en frío una ruta profunda de la SPA.

## Publicación (CI)

El job `build-build-runner` de `.gitlab-ci.yml` publica esta imagen a
`$CI_REGISTRY_IMAGE/build-runner` en cada cambio bajo `services/build-runner/**/*`
en la rama por defecto — igual que `build-web`, sin job de test (sin suite propia
todavía) ni `--target prod` (Dockerfile de una sola etapa).
