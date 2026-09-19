# Despliegue de Hostium (Kustomize + ArgoCD)

Primera versión: `php` (API Symfony), `web` (dashboard Next.js) y `keycloak` (auth), todo en el
namespace `hostium`, sincronizado por ArgoCD directamente desde este repo — sin capa de GitOps
separada, para que se pueda ver el despliegue completo en git.

```
deploy/
├── base/            # Deployments, Services, Ingress, CNPG Cluster, Redis CR
├── overlays/prod/    # namespace + tags de imagen
├── argocd/           # Application CR
└── apply.sh          # automatiza los pasos 1-3 de abajo
```

`./deploy/apply.sh` automatiza los pasos 1-3 (verificaciones, namespace, secretos idempotentes —
`gitlab-token-auth` se copia del namespace `default`, configurable con `GITLAB_TOKEN_SOURCE_NS` —
y alta de la `Application`). Los pasos manuales se documentan igual abajo por si hace falta
ejecutarlos sueltos o depurar algo.

## 1. Secretos a crear a mano (antes del primer sync)

No se usa Sealed Secrets en esta primera versión — los secretos que no gestiona CNPG
automáticamente se crean directamente en el clúster, fuera de git:

```bash
kubectl create namespace hostium

# Admin inicial de Keycloak (KEYCLOAK_ADMIN/KEYCLOAK_ADMIN_PASSWORD del propio
# Keycloak, y KEYCLOAK_ADMIN_USERNAME/PASSWORD que usa el flujo /register de web)
kubectl create secret generic keycloak-admin -n hostium \
  --from-literal=username=admin \
  --from-literal=password="$(openssl rand -base64 32)"

# APP_SECRET de Symfony
kubectl create secret generic podium-app -n hostium \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)"

# Pull de imágenes desde el Container Registry de GitLab: NO se crea desde
# cero — el secret "gitlab-token-auth" ya existe en el clúster (p.ej. en el
# namespace "default", reutilizado por otros proyectos del grupo
# apperturedev) y solo se copia, sin decodificar su contenido:
kubectl get secret gitlab-token-auth -n default -o json | python3 -c "
import json, sys
src = json.load(sys.stdin)
out = {
    'apiVersion': 'v1', 'kind': 'Secret', 'type': src['type'],
    'metadata': {'name': 'gitlab-token-auth', 'namespace': 'hostium'},
    'data': src['data'],
}
json.dump(out, sys.stdout)
" | kubectl apply -f -
```

`postgres-app` (credenciales del rol `app`) la genera y gestiona CNPG automáticamente al aplicar
`deploy/base/database.yaml` — no se crea a mano. Keycloak reutiliza ese mismo secret (mismo rol,
solo cambia la base a la que apunta: `keycloak` en vez de `app`) en lugar de tener un rol y un
secret propios — ver el comentario en `deploy/base/database.yaml`.

## 2. Verificaciones previas en el clúster real

Antes de aplicar nada, confirmar que la infraestructura asumida por estos manifiestos existe:

```bash
kubectl get crd databases.postgresql.cnpg.io   # CRD Database de CNPG (>=1.24)
kubectl get crd redis.redis.redis.opstreelabs.in  # operador Redis de OpsTree
kubectl get ingressclass                        # debe existir "nginx"
kubectl get clusterissuer letsencrypt-prod       # cert-manager
```

Si el CRD `Database` de CNPG no existe, hay que sustituir el bloque `Database` de
`deploy/base/database.yaml` por un `bootstrap.initdb.postInitApplicationSQL` en el propio `Cluster`.

## 3. Dar de alta la `Application` de ArgoCD (una sola vez)

```bash
kubectl apply -f deploy/argocd/hostium.yaml
argocd app get hostium
kubectl -n hostium get pods -w
```

A partir de aquí, ArgoCD sincroniza automáticamente cada push a `main` sobre `deploy/overlays/prod`.

## 4. Bump del tag de imagen (automático)

`.gitlab-ci.yml` tiene un stage `deploy` (`deploy-php`/`deploy-web`) que, tras cada build en `main`,
clona el propio repo con el `CI_JOB_TOKEN` del propio job, hace `kustomize edit set image` con el
tag+digest recién publicado sobre `deploy/overlays/prod/kustomization.yaml`, y empuja el commit con
`[skip ci]` (para no disparar una pipeline nueva) — mismo patrón que usa `craft-market` para su
repo de gitops, adaptado a que aquí todo vive en un único repo. ArgoCD recoge ese commit y
sincroniza solo.

El proyecto ya tiene el permiso de push del job token configurado (Settings → CI/CD → Job token
permissions) — no hace falta crear ninguna variable ni token adicional.

Este job solo empuja al remoto de GitLab — el espejo de GitHub (`origin`) no se entera solo, hay
que traer el commit a mano (`git pull origin-gitlab main` + `git push origin main`) antes de seguir
trabajando en local con la rama al día.

## Cosas pendientes de esta primera iteración

- El client `podium-api` importado en Keycloak trae el secret de desarrollo
  (`podium-dev-secret`) — rotarlo desde la consola de admin tras el primer despliegue.
- No hay `nodeSelector`/afinidad de nodo declarada (a diferencia de `craft-market-gitops`,
  que usa `type: worker`) — no se pudo verificar contra el clúster real si aplica aquí; añadirla
  si hace falta.
- El espejo de GitHub no se actualiza solo con el commit del job `deploy-*` (ver arriba) — si se
  quiere automatizar también eso, haría falta que el job empuje a ambos remotos.
