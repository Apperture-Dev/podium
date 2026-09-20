# Build launcher (lanzador de builds, Go)

Ver `docs/podium-domain/build/model.md` § "Frontera de infraestructura" y el plan en
`/home/adrian/.claude/plans/mossy-percolating-treehouse.md` para el contexto completo. Resumen:
consume `BuildJobRequested` de `podium.build-job-requested` (Redis Streams), crea el `Job` de
Kubernetes que corre `services/build-runner`, y reporta `JobSucceeded`/`JobFailed` de vuelta a
Build por `podium.job-succeeded`/`podium.job-failed`.

Se despliega **git-tracked con Kustomize** (`deploy/argocd/build-launcher.yaml`), igual que
`deploy/activator/` — ver el comentario en ese fichero para el porqué.

## Un único namespace: `hostium`

Como el resto de infraestructura propia de Podium (`php`, `worker`, `web`, `keycloak`,
`deploy-launcher`), este lanzador vive en `hostium` — no en un namespace propio. Decisión
deliberada de simplificación: un solo namespace de plataforma que administrar, sin duplicar
secretos/RBAC/NetworkPolicy por cada pieza de infraestructura (los namespaces por tenant del
hackathon son otra cosa — esos sí son uno por equipo, vía `Application.spec.destination.namespace`
del chart `podium-app`). Migrado desde `podium-build` (namespace dedicado de la primera versión)
tras confirmar contra el clúster real que no aportaba aislamiento real sobre infraestructura de
confianza.

## Prerrequisitos

- **`zot-pull-secret`** (credenciales de push a `registry.apperture.dev`, usadas por los `Job` de
  build que este lanzador crea): reflejado automáticamente por el operador Reflector en **todos**
  los namespaces (confirmado contra el clúster real), incluido `hostium`. No hay que crearlo ni
  copiarlo a mano.
- **`gitlab-token-auth`** (pull de la propia imagen `build-launcher`, registro **privado** de
  GitLab): ya existe en `hostium` — lo usa también `php-deployment.yaml`/`worker.yaml` — no hace
  falta copiarlo aparte como sí hacía falta cuando este lanzador vivía en su propio namespace
  (`podium-build`, ver `deploy/README.md` para el porqué no se refleja solo como `zot-pull-secret`).
- **Redis**: el mismo `redis` que ya usa `php` — ambos viven ahora en `hostium`, así que el
  `Deployment` de aquí podría referenciarlo por nombre corto (`redis:6379`) en vez del FQDN
  (`redis.hostium.svc.cluster.local:6379`); se deja el FQDN por ahora porque sigue siendo
  correcto y evita otro cambio simultáneo — simplificarlo es un follow-up sin urgencia.

## Pendiente

- Job `deploy-build-launcher` en `.gitlab-ci.yml` (bump de tag automático) — no existe todavía,
  mismo patrón que `deploy-php`/`deploy-web`/`deploy-activator` una vez este directorio esté
  probado contra un clúster real.
- Probado solo con `kubectl kustomize` + build local de la imagen — falta el primer despliegue
  real y la prueba de humo end-to-end (publicar un `BuildJobRequested` real y observar que
  aparece un `Job`, termina, y llega `JobSucceeded`/`JobFailed` de vuelta).
