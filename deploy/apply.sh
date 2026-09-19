#!/usr/bin/env bash
set -euo pipefail

# Primer despliegue de Hostium: namespace, secretos manuales (ver
# deploy/README.md) y alta de la Application de ArgoCD. Idempotente — los
# secretos que ya existen no se tocan (para no rotar contraseñas de BD ya en
# uso), así que se puede volver a ejecutar sin problema si se corta a medias.
#
# Requiere GITLAB_REGISTRY_USER y GITLAB_REGISTRY_TOKEN en el entorno (Deploy
# Token o Personal Access Token con scope read_registry) salvo que el secret
# 'gitlab-token-auth' ya exista.

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NAMESPACE="hostium"

log() { printf '\n\033[1;34m==>\033[0m %s\n' "$1"; }
warn() { printf '\033[1;33mAVISO:\033[0m %s\n' "$1" >&2; }
die() { printf '\033[1;31mERROR:\033[0m %s\n' "$1" >&2; exit 1; }

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "Falta '$1' en el PATH."
}

require_cmd kubectl
require_cmd openssl

kubectl cluster-info >/dev/null 2>&1 \
  || die "No hay acceso al clúster (revisa tu kubeconfig/contexto)."

log "Verificando prerrequisitos del clúster"
blocking=0
kubectl get crd databases.postgresql.cnpg.io >/dev/null 2>&1 \
  || { warn "No existe el CRD 'databases.postgresql.cnpg.io' (CNPG >=1.24 requerido)."; blocking=1; }
kubectl get crd redis.redis.redis.opstreelabs.in >/dev/null 2>&1 \
  || warn "No existe el CRD del operador Redis de OpsTree."
kubectl get ingressclass nginx >/dev/null 2>&1 \
  || warn "No existe la IngressClass 'nginx'."
kubectl get clusterissuer letsencrypt-prod >/dev/null 2>&1 \
  || warn "No existe el ClusterIssuer 'letsencrypt-prod'."
if [ "$blocking" -eq 1 ]; then
  die "Falta un prerrequisito bloqueante — ver deploy/README.md antes de continuar."
fi

log "Creando namespace '$NAMESPACE' (si no existe)"
kubectl create namespace "$NAMESPACE" --dry-run=client -o yaml | kubectl apply -f -

ensure_secret() {
  local name="$1"
  shift
  if kubectl -n "$NAMESPACE" get secret "$name" >/dev/null 2>&1; then
    log "Secret '$name' ya existe, no se toca"
    return
  fi
  log "Creando secret '$name'"
  kubectl -n "$NAMESPACE" create secret "$@"
}

ensure_secret keycloak-db-role generic keycloak-db-role \
  --from-literal=username=keycloak \
  --from-literal=password="$(openssl rand -base64 32)"

ensure_secret keycloak-admin generic keycloak-admin \
  --from-literal=username=admin \
  --from-literal=password="$(openssl rand -base64 32)"

ensure_secret podium-app generic podium-app \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)"

if kubectl -n "$NAMESPACE" get secret gitlab-token-auth >/dev/null 2>&1; then
  log "Secret 'gitlab-token-auth' ya existe, no se toca"
else
  : "${GITLAB_REGISTRY_USER:?Falta GITLAB_REGISTRY_USER (Deploy Token o PAT con scope read_registry)}"
  : "${GITLAB_REGISTRY_TOKEN:?Falta GITLAB_REGISTRY_TOKEN}"
  log "Creando secret 'gitlab-token-auth'"
  kubectl -n "$NAMESPACE" create secret docker-registry gitlab-token-auth \
    --docker-server=registry.gitlab.com \
    --docker-username="$GITLAB_REGISTRY_USER" \
    --docker-password="$GITLAB_REGISTRY_TOKEN"
fi

log "Dando de alta la Application de ArgoCD"
kubectl apply -f "$REPO_ROOT/deploy/argocd/hostium.yaml"

log "Listo. Comprueba el estado con:"
echo "  argocd app get hostium"
echo "  kubectl -n $NAMESPACE get pods -w"
