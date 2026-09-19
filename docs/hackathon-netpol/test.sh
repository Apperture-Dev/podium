#!/usr/bin/env bash
# Prueba cada regla de la política de tenant usando pods persistentes + curl -w '%{http_code}',
# leyendo el código HTTP en vez del exit code de "kubectl run --rm" (que no es fiable para
# conexiones muy rápidas: hay una carrera conocida entre el attach y la finalización del pod).
set -u

NS=np-test
INGRESS_NS=${INGRESS_NS:-ingress-nginx}          # TODO: nombre real del ns de nginx
ACTIVATOR_NS=${ACTIVATOR_NS:-activator-system}   # namespace del activator — opcional, ver abajo
TENANT_YAML=${TENANT_YAML:-01-tenant-test.yaml}  # ajusta si tu archivo se llama distinto
PROM=http://prometheus-operator-kube-p-prometheus.monitoring.svc.cluster.local:9090/-/ready
IMG=curlimages/curl:8.10.1
PROPAGATION_WAIT=3   # segundos de margen tras cada apply/delete de netpol (sync de ipset en kube-router)

pass=0; fail=0
result() { # $1=expected(ok|blocked) $2=http_code $3=descripción
  local okmatch=false
  [ "$1" = ok ]      && [ "$2" != "000" ] && okmatch=true
  [ "$1" = blocked ] && [ "$2" = "000" ]  && okmatch=true
  if $okmatch; then
    echo "PASS  $3 (code=$2)"; pass=$((pass+1))
  else
    echo "FAIL  $3 (esperado: $1, code=$2)"; fail=$((fail+1))
  fi
}

# Ejecuta curl dentro de un pod ya existente y devuelve el http_code ("000" si no conecta).
probe() { # $1=namespace $2=pod $3..=args de curl
  local ns=$1 pod=$2; shift 2
  kubectl -n "$ns" exec "$pod" -- curl -s -o /dev/null -m 5 -w '%{http_code}' "$@" 2>/dev/null
}

cleanup() {
  kubectl -n default delete pod probe-default --ignore-not-found >/dev/null 2>&1
  kubectl -n "$INGRESS_NS" delete pod probe-ingress --ignore-not-found >/dev/null 2>&1
  kubectl -n "$NS" delete pod probe-tenant --ignore-not-found >/dev/null 2>&1
  if $HAS_ACTIVATOR_NS; then
    kubectl -n "$ACTIVATOR_NS" delete pod probe-activator --ignore-not-found >/dev/null 2>&1
  fi
}
trap cleanup EXIT

# 03-activator-netpol.yaml es opcional — si no se aplicó, se prueba solo la
# regla de ingress del tenant que lo tiene en cuenta (ya viene en
# 01-tenant-test.yaml) y se omiten las pruebas de la política propia del
# activator, sin fallar el script.
HAS_ACTIVATOR_NS=false
kubectl get namespace "$ACTIVATOR_NS" >/dev/null 2>&1 && HAS_ACTIVATOR_NS=true

echo "== Preparando pods de prueba =="
kubectl -n $NS wait --for=condition=Ready pod/target --timeout=60s >/dev/null || { echo "target no arranca"; exit 1; }
TARGET_IP=$(kubectl -n $NS get pod target -o jsonpath='{.status.podIP}')

cleanup >/dev/null 2>&1   # por si quedó algo de una ejecución anterior interrumpida
kubectl -n $NS run probe-tenant --image=$IMG --restart=Never -- sleep 3600 >/dev/null
kubectl -n default run probe-default --image=$IMG --restart=Never -- sleep 3600 >/dev/null
kubectl -n "$INGRESS_NS" run probe-ingress --image=$IMG --restart=Never -- sleep 3600 >/dev/null
kubectl -n $NS wait --for=condition=Ready pod/probe-tenant --timeout=30s >/dev/null
kubectl -n default wait --for=condition=Ready pod/probe-default --timeout=30s >/dev/null
kubectl -n "$INGRESS_NS" wait --for=condition=Ready pod/probe-ingress --timeout=30s >/dev/null

if $HAS_ACTIVATOR_NS; then
  ACTIVATOR_TARGET_IP=$(kubectl -n "$ACTIVATOR_NS" get pod target -o jsonpath='{.status.podIP}' 2>/dev/null)
  kubectl -n "$ACTIVATOR_NS" run probe-activator --image=$IMG --restart=Never -- sleep 3600 >/dev/null
  kubectl -n "$ACTIVATOR_NS" wait --for=condition=Ready pod/probe-activator --timeout=30s >/dev/null
fi

echo "== Margen de propagación (${PROPAGATION_WAIT}s) =="
sleep "$PROPAGATION_WAIT"

echo "== Egress =="
c=$(probe $NS probe-tenant "$PROM");                          result blocked "$c" "tenant -> Prometheus (monitoring) bloqueado"
c=$(probe $NS probe-tenant -k https://10.43.0.1:443/version); result blocked "$c" "tenant -> API de k3s (service-cidr) bloqueado"
c=$(probe $NS probe-tenant -k https://100.64.0.1:443/);       result blocked "$c" "tenant -> red Tailscale bloqueado"
c=$(probe $NS probe-tenant http://example.com/);              result blocked "$c" "tenant -> Internet :80 bloqueado (solo 443)"
c=$(probe $NS probe-tenant https://api.github.com/);          result ok      "$c" "tenant -> Internet :443 permitido"
c=$(probe $NS probe-tenant https://1.1.1.1/);                 result ok      "$c" "tenant -> IP pública :443 permitido (sin DNS)"

echo "== DNS =="
c=$(probe $NS probe-tenant https://api.github.com/);          result ok      "$c" "resolución DNS + egress desde el tenant"

echo "== Ingress =="
c=$(probe default probe-default "http://$TARGET_IP:8080/");           result blocked "$c" "default -> tenant bloqueado"
c=$(probe "$INGRESS_NS" probe-ingress "http://$TARGET_IP:8080/");     result ok      "$c" "$INGRESS_NS -> tenant permitido"
c=$(probe $NS probe-tenant "http://$TARGET_IP:8080/");                result ok      "$c" "tenant -> mismo tenant permitido"
if $HAS_ACTIVATOR_NS; then
  c=$(probe "$ACTIVATOR_NS" probe-activator "http://$TARGET_IP:8080/"); result ok "$c" "$ACTIVATOR_NS -> tenant permitido (tramo 2, proxy del activator)"
fi

if $HAS_ACTIVATOR_NS; then
  echo "== Activator (si aplicaste 03-activator-netpol.yaml) =="
  c=$(probe "$ACTIVATOR_NS" probe-activator "$PROM");                            result blocked "$c" "activator -> Prometheus (monitoring) bloqueado"
  c=$(probe "$ACTIVATOR_NS" probe-activator -k https://10.43.0.1:443/version);   result ok      "$c" "activator -> API de k3s permitido (a diferencia del tenant)"
  c=$(probe default probe-default "http://$ACTIVATOR_TARGET_IP:8080/");          result blocked "$c" "default -> activator bloqueado"
  c=$(probe "$INGRESS_NS" probe-ingress "http://$ACTIVATOR_TARGET_IP:8080/");    result ok      "$c" "$INGRESS_NS -> activator permitido (tramo 1)"
fi

echo "== Segunda línea (si aplicaste 02-protect-monitoring.yaml) =="
kubectl -n $NS delete netpol 00-default-deny 30-allow-egress-https --ignore-not-found >/dev/null
sleep "$PROPAGATION_WAIT"
c=$(probe $NS probe-tenant "$PROM");                          result blocked "$c" "sin política de tenant, monitoring sigue rechazando"
kubectl apply -f "$(dirname "$0")/$TENANT_YAML" >/dev/null
sleep "$PROPAGATION_WAIT"

echo
echo "PASS: $pass  FAIL: $fail"
[ "$fail" -eq 0 ]
