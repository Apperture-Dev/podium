# Activator (stretch goal del hackathon)

Ver `docs/hackathon-plan/plan-hackbarna-2026.md` § "activator propio en Go" para el contexto
completo. Resumen: escala a 0 los tenants inactivos y los despierta al primer request, enrutando
después del Ingress. **Solo se activa tras el congelado de las 22:30** del sábado — nunca antes,
nunca en horas de jurado salvo que ya lleve un rato estable.

Se despliega **git-tracked con Kustomize** (`deploy/argocd/activator.yaml`), a diferencia del
despliegue de apps de equipos del hackathon (chart OCI sin git) — ver el comentario en ese mismo
fichero para el porqué.

## Arranca inerte

`configmap.yaml` fija `enabled: "false"` por defecto: el activator corre (informers activos,
tabla de rutas construida) pero **ningún tráfico real pasa por él** hasta que se hace el cutover
a mano.

## Un único namespace: `hostium`

Como el resto de infraestructura propia de Podium (`php`, `worker`, `web`, `keycloak`,
`build-launcher`, `deploy-launcher`), el activator vive en `hostium` — no en un namespace propio
(`activator-system`, como en el diseño original). Mismo criterio que `deploy/build-launcher/README.md`
documenta: un único namespace de plataforma que administrar, sin duplicar secretos/RBAC por cada
pieza — los namespaces por tenant del hackathon (donde sí aplica aislamiento real) son otra cosa.

## Runbook: cutover / kill switch

Es la misma operación en sentido opuesto (ver `internal/killswitch`):

```bash
# Cutover (activar) — solo tras el congelado de las 22:30, y probado antes con un tenant de prueba
kubectl patch configmap activator-config -n hostium --type merge -p '{"data":{"enabled":"true"}}'

# Kill switch (apagar) — instantáneo, sin tocar nada más, en cualquier momento
kubectl patch configmap activator-config -n hostium --type merge -p '{"data":{"enabled":"false"}}'
```

Sin reinicio del Deployment: el activator lo observa por informer y reacciona en segundos.

## Pendiente

- Job `deploy-activator` en `.gitlab-ci.yml` (bump de tag automático) — no existe todavía, mismo
  patrón que `deploy-php`/`deploy-web` una vez este directorio esté probado contra un clúster real.
- NetworkPolicy propia y la regla adicional en el namespace de tenant — siguiente fase. La de
  `docs/hackathon-netpol/03-activator-netpol.yaml` asumía un namespace `activator-system` dedicado
  (`podSelector: {}` = toda regla aplica a cualquier pod del namespace); ahora que comparte
  `hostium` con `php`/`worker`/`web`/`keycloak`/`build-launcher`/`deploy-launcher`, ese diseño
  rompería el resto — hay que rehacerla con `podSelector: {app: activator}` en cada regla antes de
  aplicarla. Nunca se llegó a aplicar contra el clúster real (`activator-system` no existe hoy),
  así que no hay ningún corte que gestionar, solo el fixture que reescribir.
- Probado solo con `kubectl kustomize` + build local de la imagen — falta el primer despliegue
  real contra un clúster (kind/k3d o el de Apperture) antes de considerar esto listo para el
  cutover real.
