# podium-app

Chart Helm genérico — un `Deployment`/`Service`/`Ingress` por tenant (equipo del hackathon),
publicado como artefacto OCI en `registry.apperture.dev/charts` y referenciado por un
`Application` de ArgoCD distinto por tenant (`tenant-{hash}`), sin git de por medio para el
despliegue por tenant — ver `docs/hackathon-plan/plan-hackbarna-2026.md` § "Deploy: ArgoCD
Application apuntando a un chart Helm en el registro OCI — sin git".

## Values

Los tres del `Application` de ejemplo del plan del hackathon, más `port` (añadido tras revisar
que no todas las apps escuchan en el mismo puerto — no se puede asumir un valor fijo):

```yaml
hash: ""              # identificador del tenant — nombra el Deployment/Service/Ingress y va como label
image:
  repository: ""
  tag: ""
ingress:
  host: ""             # "{hash}.apperture.dev"
port: 3000             # puerto del contenedor — default a la convención Node (única real hoy),
                        # sobreescribible por values
```

**Pendiente real, sin resolver todavía**: `port` no tiene ningún origen en la cadena de dominio
(`DeclaredService`, `BuildSucceeded`, `ApplicationDeployRequested`, `DeployAttemptRequested` — ni
`Template` guarda un puerto por framework). El chart ya lo soporta como value, pero
`services/deploy-launcher` no tiene de dónde sacar un valor real todavía y usa el default de
`3000` — ver la discusión pendiente en `deploy/deploy-launcher/README.md`.

TLS: `apperture-wildcard-tls` (cubre `*.apperture.dev`, un solo nivel — el host de un tenant es
`{hash}.apperture.dev`, exactamente ese nivel) — ya reflejado por Reflector en cualquier namespace
nuevo, sin paso manual.

## Namespace

El chart **no** declara `Namespace` — lo crea `Application.spec.syncPolicy.syncOptions:
[CreateNamespace=true]`, fuera de aquí (mismo patrón que ya usan `deploy/activator/` y
`deploy/build-launcher/` en este repo).

## Versionado

Manual: si cambia cualquier plantilla, sube `version` en `Chart.yaml` a mano. Sin CI que lo
incremente solo.

## Publicar (CI)

El job `package-podium-app-chart` de `.gitlab-ci.yml` hace `helm package` + `helm push` a
`oci://registry.apperture.dev/charts` en cada cambio bajo `helm/podium-app/**/*` en la rama por
defecto. Requiere las variables de CI/CD `ZOT_REGISTRY_USER`/`ZOT_REGISTRY_PASSWORD` (masked +
protected) — las credenciales reales solo las puede dar quien administre el registro `zot`
(o decodificarse a mano desde el secret `zot-pull-secret` ya presente en el clúster).

## Registrar el repo OCI en ArgoCD (una sola vez, fuera de esta pipeline)

Paso de infraestructura manual — ArgoCD necesita saber que ese registro OCI es un repo de charts
antes de poder resolver `chart: podium-app` en ningún `Application`:

```bash
kubectl apply -n argocd -f - <<'EOF'
apiVersion: v1
kind: Secret
metadata:
  name: podium-app-chart-repo
  namespace: argocd
  labels:
    argocd.argoproj.io/secret-type: repository
stringData:
  type: helm
  name: podium-app
  url: registry.apperture.dev/charts
  enableOCI: "true"
  username: <mismo usuario de ZOT_REGISTRY_USER>
  password: <mismo password de ZOT_REGISTRY_PASSWORD>
EOF
```

## Pendiente

- NetworkPolicy (las 5 ya validadas en `docs/hackathon-netpol/`) — fuera de alcance de esta
  primera versión del chart, tarea aparte.
- Sin probar todavía un sync real de ArgoCD contra este chart — pendiente de que
  `services/deploy-launcher` exista y de registrar el repo OCI en el ArgoCD real.
