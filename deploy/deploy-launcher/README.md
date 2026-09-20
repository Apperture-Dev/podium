# Deploy launcher (lanzador de ArgoCD, Go)

Ver `docs/podium-domain/deploy/model.md` § "Frontera de infraestructura" y el plan en
`/home/adrian/.claude/plans/mossy-percolating-treehouse.md` para el contexto completo. Resumen:
consume `DeployAttemptRequested` de `podium.deploy-attempt-requested` (Redis Streams), crea o
actualiza el `Application` de ArgoCD del servicio (`tenant-{serviceName}-{hash}`, chart OCI
`podium-app`), y
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
`helm/podium-app/Chart.yaml` a una `version` nueva tiene que tocar también `CHART_VERSION` aquí,
**después** de que el chart esté publicado en el registro: ArgoCD sincroniza este directorio desde
git, así que apuntar a una versión que todavía no existe en OCI rompe los despliegues nuevos. Decisión deliberada por ahora (coherente con el resto de config estática de esta
primera versión) — automatizarlo con un job `deploy-podium-app-chart` que haga el mismo patrón que
`deploy-php`/`deploy-web` (bump + commit `[skip ci]` tras el `helm push`) es un follow-up, no algo
que se haya construido todavía.

Cambiar `CHART_VERSION` **sí** mueve a los tenants ya existentes: en cada deploy, el lanzador
reescribe `spec.source.targetRevision` de su `Application` además del `valuesObject`. Sin eso, un
tenant renderizaría para siempre la versión del chart con la que nació, en silencio — nada falla,
simplemente despliega la plantilla antigua.

## Despliegue de la imagen

Lo automatiza el job `deploy-deploy-launcher` de `.gitlab-ci.yml`: tras construir, escribe en el
`kustomization.yaml` de este directorio el tag por SHA con su digest y commitea `[skip ci]`, y
ArgoCD aplica el cambio. Antes el manifiesto apuntaba fijo a `:latest`, así que una imagen nueva
no cambiaba nada del PodSpec y el rollout había que provocarlo a mano — con la carrera añadida de
no saber si el registro ya tenía la imagen publicada.

## Un mensaje que falla deja el deploy colgado — y nadie lo relee

Aprendido en el primer despliegue real de un repo con dos servicios. Si el lanzador no puede
procesar un `DeployAttemptRequested` (por ejemplo, un payload que no deserializa), hace `return`
sin `XACK`: el mensaje se queda en la *pending entries list* del consumer group. Pero el bucle lee
con `>`, o sea **sólo mensajes nuevos**, así que ese pendiente no se vuelve a intentar nunca, ni
reiniciando el pod.

La consecuencia no se queda en Redis: el `DeployAttempt` nunca recibe señal de salud, así que la
`Application` de App Manager se queda en `Deploying` para siempre. Y como `markSourceChanged`
marca los cambios de fuente como pendientes mientras está en `Deploying`, un commit nuevo tampoco
la desatasca — no hay timeout que la rescate.

Para recuperar un deploy así, sin tocar la base de datos, se reinyecta el mensaje original en el
stream (copiándolo con `XRANGE` + `XADD`) y se hace `XACK` del pendiente. El ciclo se completa
solo: se crea la `Application`, llega la señal de salud y App Manager pasa a `Deployed`.

Pendiente, no resuelto: recuperar los pendientes al arrancar (`XAUTOCLAIM`) y darle a `DeployAttempt`
una salida por tiempo, para que un fallo del lanzador no deje una `Application` bloqueada.

## Puerto del contenedor — convención sobre configuración, no config del lanzador

El puerto en el que escucha el contenedor de cada tenant viaja en `DeployAttemptRequested.port`,
con origen en `Template.defaultPort` (Build BC) — la misma convención por lenguaje/framework que ya
usaba `jobImage` (`docs/podium-config/podium-yaml-guide.md`: "no hay campo de comando, cada
Template asume la convención de su lenguaje"; hoy Node/NestJS/Next.js → `3000`, un futuro template
PHP/FrankenPHP → `80`). Fluye sin tocar por `BuildSucceeded` → `ApplicationDeployRequested` →
`DeployAttemptRequested` hasta este lanzador, que lo usa directo (`internal/argospec.Build` lee
`req.Port`) — ya no hay ningún `DEFAULT_PORT`/config propia del lanzador para esto.

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
