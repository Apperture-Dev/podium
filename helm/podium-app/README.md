# podium-app

Chart Helm genérico — un `Deployment`/`Service`/`Ingress` por servicio desplegado (un Project puede
declarar varios servicios en su `podium.yaml`, todos con el mismo `hash`), publicado como artefacto
OCI en `registry.apperture.dev/charts` y referenciado por un `Application` de ArgoCD distinto por
servicio (`tenant-{serviceName}-{hash}`), sin git de por medio para el despliegue por tenant — ver
`docs/hackathon-plan/plan-hackbarna-2026.md` § "Deploy: ArgoCD Application apuntando a un chart
Helm en el registro OCI — sin git".

## Values

Los tres del `Application` de ejemplo del plan del hackathon, más `port` (añadido tras revisar
que no todas las apps escuchan en el mismo puerto — no se puede asumir un valor fijo):

```yaml
hash: ""              # identificador del Project — va como label; lo que nombra el
                       # Deployment/Service/Ingress es el Release de Helm
                       # (services/deploy-launcher/internal/argospec ya lo fija a
                       # "tenant-{serviceName}-{hash}" al crear la Application)
image:
  repository: ""
  tag: ""
ingress:
  host: ""             # "{serviceName}-{hash}.apperture.dev" — nunca "{serviceName}.{hash}.apperture.dev":
                        # el wildcard solo cubre un nivel (ver TLS más abajo), y un solo hash puede
                        # tener varios servicios (un Project con varias claves en podium.yaml)
port: 3000             # puerto del contenedor — default a la convención Node (única real hoy),
                        # sobreescribible por values
```

```yaml
database:              # traducido del bloque `database:` del podium.yaml del equipo,
  mode: none           # ya resuelto por el BC Deploy — aquí no se decide nada
  urlVar: ""           # mode=url: env var donde va la cadena de conexión
  vars: {}             # mode=parts: {clave del Secret de CNPG: nombre de env var}
  instances: 1
  storage: 1Gi
```

`port` tiene origen en `Template.defaultPort` (Build BC, convención por lenguaje/framework — igual
que `jobImage`) y viaja sin tocar por `BuildSucceeded` → `ApplicationDeployRequested` →
`DeployAttemptRequested` hasta `services/deploy-launcher`, que lo pasa directo a este value — ver
`deploy/deploy-launcher/README.md`.

TLS: `apperture-wildcard-tls` (cubre `*.apperture.dev`, un solo nivel — el host de un servicio es
`{serviceName}-{hash}.apperture.dev`, exactamente ese nivel — un subdominio de dos niveles como
`{serviceName}.{hash}.apperture.dev` NO estaría cubierto, confirmado contra el certificado real) —
ya reflejado por Reflector en cualquier namespace nuevo, sin paso manual.

## Base de datos por servicio

Con `database.mode` distinto de `none`, el chart añade un `Cluster` de CNPG llamado
`{release}-db` y le pasa sus credenciales al contenedor. Podium **nunca ve la contraseña**: CNPG
genera el Secret `{release}-db-app` con `dbname`, `username`, `password`, `host`, `port` y `uri`
dentro, y el `Deployment` lo referencia por `secretKeyRef`.

Los dos modos corresponden a las dos formas de declararlo en el `podium.yaml`:

| `mode` | Qué declaró el equipo | Qué recibe el contenedor |
|---|---|---|
| `none` (por defecto) | nada, o `enable: false` | nada — ni Cluster ni env vars, el chart queda como antes de existir este bloque |
| `url` | `URL: ${DB_URL}` | la cadena de conexión completa en `DB_URL` (clave `uri` del Secret) |
| `parts` | `database:`, `user:`, `password:`, `host:`, `port:` | una env var por dato, con el nombre que pidió |

Las claves de `database.vars` son **las del Secret de CNPG** (`dbname`, `username`…), no las del
`podium.yaml`: así la plantilla las copia sin tabla de traducción. Quien traduce es el BC Deploy,
que es donde vive la regla de que `URL` gana sobre los campos sueltos.

**El orden lo fuerza un initContainer**, no sólo la sync-wave. El `Cluster` lleva
`argocd.argoproj.io/sync-wave: "-1"` para que ArgoCD lo aplique antes, pero esa wave sólo espera
de verdad si ArgoCD sabe evaluar la salud de un `Cluster` de CNPG — no está confirmado que lo
haga. Por eso el `Deployment` arranca con un initContainer que hace `pg_isready` en bucle: el
contenedor de la app no se ejecuta hasta que Postgres acepta conexiones, sepa ArgoCD lo que sepa.

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
defecto. Requiere las variables de CI/CD `ZOT_REGISTRY_USER`/`ZOT_REGISTRY_PASSWORD` (masked; la
contraseña además hidden, y ninguna de las dos protected, así que no dependen de que la rama lo
esté) — las credenciales reales solo las puede dar quien administre el registro `zot`.

**El job también se puede lanzar a mano** desde cualquier pipeline de la rama por defecto: tiene
una segunda regla `when: manual` justo para eso. Hizo falta porque el disparo por `changes` no
siempre ocurre, y este artefacto es especialmente traicionero cuando no se publica: el
`deploy-launcher` pide una `CHART_VERSION` fija, así que un chart no publicado no rompe ningún
despliegue — simplemente los tenants siguen renderizando la versión anterior.

**Al subir `version` en `Chart.yaml` hay que hacer dos cosas, en este orden**: publicar el chart
(este job) y sólo después actualizar `CHART_VERSION` en `deploy/deploy-launcher/deployment.yaml`.
Al revés, ArgoCD intentaría resolver una versión que todavía no existe en el registro.

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
- **Las env vars propias del equipo** (bloque `environment:` del `podium.yaml`) siguen sin llegar
  al contenedor: hoy el `env` del `Deployment` sólo lo generan los modos de base de datos. Cuando
  se añadan, hay que **fusionar** ambas listas, no sustituir una por otra.
- Sin probar todavía un sync real de ArgoCD contra este chart — pendiente de que
  `services/deploy-launcher` exista y de registrar el repo OCI en el ArgoCD real.
