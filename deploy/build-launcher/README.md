# Build launcher (lanzador de builds, Go)

Ver `docs/podium-domain/build/model.md` § "Frontera de infraestructura" y el plan en
`/home/adrian/.claude/plans/mossy-percolating-treehouse.md` para el contexto completo. Resumen:
consume `BuildJobRequested` de `podium.build-job-requested` (Redis Streams), crea el `Job` de
Kubernetes que corre `services/build-runner`, y reporta `JobSucceeded`/`JobFailed` de vuelta a
Build por `podium.job-succeeded`/`podium.job-failed`.

Se despliega **git-tracked con Kustomize** (`deploy/argocd/build-launcher.yaml`), igual que
`deploy/activator/` — ver el comentario en ese fichero para el porqué.

## Prerrequisitos

- **`zot-pull-secret`** (credenciales de push a `registry.apperture.dev`, usadas por los `Job` de
  build que este lanzador crea): ya está reflejado automáticamente por el operador Reflector en
  **todos** los namespaces (confirmado contra el clúster real), incluido `podium-build` en cuanto
  ArgoCD lo crea. No hay que crearlo ni copiarlo a mano.
- **`gitlab-token-auth`** (pull de la propia imagen `build-launcher`, que vive en el registro
  **privado** de GitLab — a diferencia de `zot-pull-secret`, este **no** se refleja
  automáticamente): hay que copiarlo a mano al namespace `podium-build` antes del primer
  despliegue (confirmado con un `ImagePullBackOff` real la primera vez que se desplegó):
  ```bash
  kubectl get secret gitlab-token-auth -n default -o json | python3 -c "
  import json, sys
  src = json.load(sys.stdin)
  out = {
      'apiVersion': 'v1', 'kind': 'Secret', 'type': src['type'],
      'metadata': {'name': 'gitlab-token-auth', 'namespace': 'podium-build'},
      'data': src['data'],
  }
  json.dump(out, sys.stdout)
  " | kubectl apply -f -
  ```
- **Redis**: el mismo `redis` que ya usa `php` (namespace `hostium`, Service llamado literalmente
  `redis` — sin sufijo `-master`, el CR es `kind: Redis` standalone, no `RedisReplication`) — el
  `Deployment` de aquí lo referencia por su nombre completo
  (`redis.hostium.svc.cluster.local`), ya que vive en un namespace distinto.

## Pendiente

- Job `deploy-build-launcher` en `.gitlab-ci.yml` (bump de tag automático) — no existe todavía,
  mismo patrón que `deploy-php`/`deploy-web`/`deploy-activator` una vez este directorio esté
  probado contra un clúster real.
- Probado solo con `kubectl kustomize` + build local de la imagen — falta el primer despliegue
  real y la prueba de humo end-to-end (publicar un `BuildJobRequested` real y observar que
  aparece un `Job`, termina, y llega `JobSucceeded`/`JobFailed` de vuelta).
