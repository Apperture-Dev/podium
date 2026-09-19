#!/usr/bin/env bash
# Prueba cada regla de la política de tenant usando pods persistentes + curl -w '%{http_code}',
# leyendo el código HTTP en vez del exit code de "kubectl run --rm" (que no es fiable para
# conexiones muy rápidas: hay una carrera conocida entre el attach y la finalización del pod).
set -u

NS=np-test
INGRESS_NS=${INGRESS_NS:-ingress-nginx}          # TODO: nombre real del ns de nginx
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
}
trap cleanup EXIT

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

echo "== Segunda línea (si aplicaste 02-protect-monitoring.yaml) =="
kubectl -n $NS delete netpol 00-default-deny 30-allow-egress-https --ignore-not-found >/dev/null
sleep "$PROPAGATION_WAIT"
c=$(probe $NS probe-tenant "$PROM");                          result blocked "$c" "sin política de tenant, monitoring sigue rechazando"
kubectl apply -f "$(dirname "$0")/$TENANT_YAML" >/dev/null
sleep "$PROPAGATION_WAIT"

echo
echo "PASS: $pass  FAIL: $fail"
[ "$fail" -eq 0 ]
