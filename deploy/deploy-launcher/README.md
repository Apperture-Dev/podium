# Deploy launcher (lanzador de ArgoCD, Go)

Ver `docs/podium-domain/deploy/model.md` § "Frontera de infraestructura" y el plan en
`/home/adrian/.claude/plans/mossy-percolating-treehouse.md` para el contexto completo. Resumen:
consume `DeployAttemptRequested` de `podium.deploy-attempt-requested` (Redis Streams), crea o
actualiza el `Application` de ArgoCD del tenant (`tenant-{hash}`, chart OCI `podium-app`), y
reporta `HealthCheckSucceeded`/`HealthCheckExhausted` de vuelta a Deploy por
`podium.health-check-succeeded`/`podium.health-check-exhausted`.

Se despliega **git-tracked con Kustomize** (`deploy/argocd/deploy-launcher.yaml`), igual que
`deploy/activator/`/`deploy/build-launcher/` — ver el comentario en ese fichero para el porqué.

## RBAC cross-namespace (excepción deliberada)

A diferencia de `deploy/build-launcher/` (que solo actúa en su propio namespace), el `Application`
que este lanzador manipula vive en `argocd`, no en `podium-deploy` — por eso este directorio **no**
usa el campo `namespace:` global de `kustomization.yaml` (reescribiría también el namespace de la
RBAC). Cada manifiesto fija su `metadata.namespace` explícito — ver el comentario en
`serviceaccount.yaml` y `argocd-rbac.yaml`.

## `CHART_VERSION` — sincronización manual con `helm/podium-app/Chart.yaml`

`CHART_REPO_URL`/`CHART_NAME`/`CHART_VERSION` (en `deployment.yaml`) son config propia del
lanzador, nunca del evento — mismo criterio que `IMAGE_REGISTRY` en `build-launcher`.
`CHART_REPO_URL`/`CHART_NAME` no cambian nunca (es el registro y el nombre del chart). `CHART_VERSION`
**sí** cambia cuando se toca una plantilla de `helm/podium-app/` y se publica una versión nueva del
chart — y hoy **no hay ningún mecanismo que lo sincronice solo**: quien suba
`helm/podium-app/Chart.yaml` a una `version` nueva tiene que tocar también `CHART_VERSION` aquí, en
el mismo commit. Decisión deliberada por ahora (coherente con el resto de config estática de esta
primera versión) — automatizarlo con un job `deploy-podium-app-chart` que haga el mismo patrón que
`deploy-php`/`deploy-web` (bump + commit `[skip ci]` tras el `helm push`) es un follow-up, no algo
que se haya construido todavía.

## `DEFAULT_PORT` — hueco real de dominio, sin resolver

El puerto en el que escucha el contenedor de cada tenant no tiene origen en ningún punto de la
cadena de eventos: ni `DeclaredService` (Project), ni `BuildSucceeded` (Build), ni
`ApplicationDeployRequested` (App Manager), ni `DeployAttemptRequested` (Deploy) lo llevan — y
`Template` tampoco guarda un puerto por lenguaje/framework, a pesar de que sí guarda `jobImage`
para la convención de build. `docs/podium-config/podium-yaml-guide.md` dice explícitamente "no hay
campo de comando, cada Template asume la convención de su lenguaje" — el mismo principio aplicaría
al puerto (Node/NestJS/Next.js: `3000`; un futuro template PHP/FrankenPHP: `80`), pero hoy nadie lo
ha modelado así.

**Mientras tanto**: `DEFAULT_PORT` (env var, mismo criterio que `IMAGE_REGISTRY`/`CHART_VERSION` —
config del lanzador, nunca del evento) fija **un único puerto para todos los tenants**, sea cual
sea su framework real. Funciona para la convención Node actual porque es la única que existe hoy,
pero es una simplificación conocida, no una solución — en cuanto haya un segundo template con un
puerto distinto, esto rompe en silencio (el `Deployment` se crea, pero el health-check de ArgoCD
nunca verá el Pod sano). Resolverlo de verdad significa añadir `port` a `Template` y dejar que
fluya por las cuatro capas hasta aquí — cambio deliberadamente no hecho en esta sesión, fuera del
alcance de "lanzador de ArgoCD + chart", pendiente de su propia decisión.

## Dependencia de la Fase A (chart + registro OCI)

**Este lanzador no sirve de nada por sí solo** hasta que:

1. El chart `podium-app` esté empaquetado y publicado en `oci://registry.apperture.dev/charts`
   (ver `helm/podium-app/README.md`).
2. Ese repo OCI esté registrado en ArgoCD (`Secret` de tipo `helm` con `enableOCI: "true"` en el
   namespace `argocd` — también documentado en `helm/podium-app/README.md`).

Sin esos dos pasos, cualquier `Application` que este lanzador cree quedará en `Unknown` (ArgoCD no
puede resolver el chart) — no es un bug del lanzador, es la Fase A sin terminar/aplicar.

## Prerrequisitos ya resueltos

- **`gitlab-token-auth`**: el registro de GitLab es privado — copiarlo a mano al namespace
  `podium-deploy` antes del primer despliegue (mismo problema/mismo comando ya documentado en
  `deploy/build-launcher/README.md` — no se reflejaba solo, se descubrió con un `ImagePullBackOff`
  real la primera vez que se desplegó `build-launcher`).
- **Redis**: el mismo `redis` (namespace `hostium`, sin sufijo `-master` — ver
  `deploy/base/redis.yaml`) que ya usan `php`/`build-launcher`.

## Pendiente

- Job `deploy-deploy-launcher` en `.gitlab-ci.yml` (bump de tag automático) — no existe todavía,
  mismo patrón que `deploy-php`/`deploy-web`/`deploy-activator` una vez este directorio esté
  probado contra un clúster real.
- Probado solo con `kubectl kustomize` + build local de la imagen — falta el primer despliegue
  real y la prueba de humo end-to-end (publicar un `DeployAttemptRequested` real, con la Fase A ya
  aplicada, y observar que el `Application` sincroniza de verdad hasta `Healthy`).
