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

| lang | framework | Convención |
|---|---|---|
| `nodejs` | `nestjs` | `npm ci && npm run build`, arranca con `npm start` |
| `nodejs` | `nextjs` | `npm ci && npm run build`, arranca con `npm start` (`next start`); sin asumir `output: "standalone"` |
