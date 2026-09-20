# Build launcher (lanzador de builds, Go)

Ver `docs/podium-domain/build/model.md` § "Frontera de infraestructura" y el plan en
`/home/adrian/.claude/plans/mossy-percolating-treehouse.md` para el contexto completo. Resumen:
consume `BuildJobRequested` de `podium.build-job-requested` (Redis Streams), crea el `Job` de
Kubernetes que corre `services/build-runner`, y reporta `JobSucceeded`/`JobFailed` de vuelta a
Build por `podium.job-succeeded`/`podium.job-failed`.

Se despliega **git-tracked con Kustomize** (`deploy/argocd/build-launcher.yaml`), igual que
`deploy/activator/` — ver el comentario en ese fichero para el porqué.

## Prerrequisitos ya resueltos (no hace falta ningún secret nuevo)

- **`zot-pull-secret`** (credenciales de push a `registry.apperture.dev`): ya está reflejado
  automáticamente por el operador Reflector en **todos** los namespaces (confirmado contra el
  clúster real), incluido `podium-build` en cuanto ArgoCD lo cree. No hay que crearlo ni copiarlo
  a mano.
- **Redis**: el mismo `redis-master` que ya usa `php` (namespace `hostium`) — el `Deployment` de
  aquí lo referencia por su nombre completo (`redis-master.hostium.svc.cluster.local`), ya que
  vive en un namespace distinto.

## Pendiente

- Job `deploy-build-launcher` en `.gitlab-ci.yml` (bump de tag automático) — no existe todavía,
  mismo patrón que `deploy-php`/`deploy-web`/`deploy-activator` una vez este directorio esté
  probado contra un clúster real.
- Probado solo con `kubectl kustomize` + build local de la imagen — falta el primer despliegue
  real y la prueba de humo end-to-end (publicar un `BuildJobRequested` real y observar que
  aparece un `Job`, termina, y llega `JobSucceeded`/`JobFailed` de vuelta).
