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

`./deploy/apply.sh` automatiza los pasos 1-3 (verificaciones, namespace, secretos idempotentes,
alta de la `Application`) — requiere `GITLAB_REGISTRY_USER`/`GITLAB_REGISTRY_TOKEN` en el entorno
salvo que `gitlab-token-auth` ya exista. Los pasos manuales se documentan igual abajo por si hace
falta ejecutarlos sueltos o depurar algo.

## 1. Secretos a crear a mano (antes del primer sync)

No se usa Sealed Secrets en esta primera versión — los secretos que no gestiona CNPG
automáticamente se crean directamente en el clúster, fuera de git:

```bash
kubectl create namespace hostium

# Rol de BD de Keycloak (referenciado por managed.roles en deploy/base/database.yaml
# y por KC_DB_USERNAME/KC_DB_PASSWORD en deploy/base/keycloak-deployment.yaml)
kubectl create secret generic keycloak-db-role -n hostium \
  --from-literal=username=keycloak \
  --from-literal=password="$(openssl rand -base64 32)"

# Admin inicial de Keycloak (KEYCLOAK_ADMIN/KEYCLOAK_ADMIN_PASSWORD del propio
# Keycloak, y KEYCLOAK_ADMIN_USERNAME/PASSWORD que usa el flujo /register de web)
kubectl create secret generic keycloak-admin -n hostium \
  --from-literal=username=admin \
  --from-literal=password="$(openssl rand -base64 32)"

# APP_SECRET de Symfony
kubectl create secret generic podium-app -n hostium \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)"

# Credenciales para pull de imágenes desde el Container Registry de GitLab
# (usuario: un Deploy Token o Personal Access Token con scope read_registry)
kubectl create secret docker-registry gitlab-token-auth -n hostium \
  --docker-server=registry.gitlab.com \
  --docker-username=<usuario-o-deploy-token> \
  --docker-password=<token>
```

`postgres-app` (contraseña de la base `app` que consume `php`) la genera y gestiona CNPG
automáticamente al aplicar `deploy/base/database.yaml` — no se crea a mano.

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

## 4. Actualizar el tag de imagen tras un build de CI

Todavía no hay stage de deploy automático en `.gitlab-ci.yml` (solo build). Tras un push que dispare
`build-php`/`build-web`, actualizar el tag a mano:

```bash
cd deploy/overlays/prod
kustomize edit set image registry.gitlab.com/apperturedev/podium/php=registry.gitlab.com/apperturedev/podium/php:<sha>
git add kustomization.yaml
git commit -m "deploy: hostium php@<sha> [skip ci]"
git push
```

El `[skip ci]` evita que este commit dispare una pipeline nueva sobre el mismo repo.

## Cosas pendientes de esta primera iteración

- El client `podium-api` importado en Keycloak trae el secret de desarrollo
  (`podium-dev-secret`) — rotarlo desde la consola de admin tras el primer despliegue.
- No hay `nodeSelector`/afinidad de nodo declarada (a diferencia de `craft-market-gitops`,
  que usa `type: worker`) — no se pudo verificar contra el clúster real si aplica aquí; añadirla
  si hace falta.
- Bump de tag de imagen manual — automatizarlo (como hace `craft-market`, con un job de CI que
  hace commit al propio repo) es una iteración futura, fuera de alcance ahora.
