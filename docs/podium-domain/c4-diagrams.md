# Hostium (Podium) — Arquitectura en C4

Los cuatro niveles del modelo C4 sobre **lo que está construido y desplegado hoy**, no sobre el
plan. Cuando algo del plan no existe todavía, aparece anotado en "Lo que estos diagramas no
muestran" al final — nunca dibujado como si existiera.

- **Nivel 1 — Contexto**: Hostium frente a sus usuarios y a los sistemas externos.
- **Nivel 2 — Contenedores**: las unidades desplegables dentro del namespace `hostium`.
- **Nivel 3 — Componentes**: qué hay dentro de cada contenedor con lógica propia.
- **Nivel 4 — Código**: el agregado `Application` de App Manager, que es el orquestador del ciclo.

El modelo de dominio que hay detrás está en [`context-map.md`](context-map.md); aquí se mira la
misma verdad desde el eje del despliegue.

---

## Nivel 1 — Contexto del sistema

```mermaid
C4Context
    title Nivel 1 - Hostium en su contexto

    Person(team, "Equipo del hackathon", "Trae un repo publico con su podium.yaml. No configura CI, ni Dockerfile, ni cuentas de cloud")
    Person(visitor, "Jurado / visitante", "Abre la URL publica de una demo desde su propio movil")
    Person(platform, "Equipo de plataforma", "Opera Hostium durante el evento")

    System(hostium, "Hostium (Podium)", "Plataforma de despliegue del evento: repo publico entra, URL publica viva sale. Descubre los servicios declarados, construye, despliega y muestra el estado")

    System_Ext(github, "GitHub", "Repos publicos de los equipos y su API de commits: la unica fuente de codigo hoy")
    System_Ext(gitlab, "GitLab CI + Container Registry", "Construye las imagenes del propio Hostium y aloja las imagenes de los equipos")
    System_Ext(k8s, "Cluster Kubernetes + ArgoCD", "apperture.dev. Ejecuta tanto Hostium como las apps de cada equipo, cada una en su namespace")
    System_Ext(dns, "external-dns + TLS wildcard", "Publica el registro DNS de cada tenant y le presta el certificado *.apperture.dev")
    System_Ext(tenantapp, "App desplegada del equipo", "El resultado: un servicio del equipo corriendo en su propio namespace, con URL publica")

    Rel(team, hostium, "Registra equipo y proyecto, consulta estado", "HTTPS")
    Rel(team, github, "Hace push de su codigo", "git")
    Rel(hostium, github, "Sondea el ultimo commit y lee el podium.yaml", "HTTPS / API")
    Rel(hostium, gitlab, "Empuja la imagen construida de cada servicio", "buildah push")
    Rel(hostium, k8s, "Crea Jobs de build y una Application de ArgoCD por servicio", "API de Kubernetes")
    Rel(k8s, gitlab, "Descarga la imagen del tenant", "image pull")
    Rel(k8s, tenantapp, "Sincroniza y mantiene viva", "ArgoCD auto-sync")
    Rel(k8s, dns, "Publica el host del Ingress del tenant", "external-dns")
    Rel(visitor, tenantapp, "Abre la demo", "HTTPS")
    Rel(platform, hostium, "Opera y despliega Hostium con el mismo camino", "GitLab CI + ArgoCD")

    UpdateLayoutConfig($c4ShapeInRow="3", $c4BoundaryInRow="1")
```

**Lo que este nivel dice y conviene no perder de vista:** Hostium nunca escribe en el repo del
equipo — solo lee. Y la app desplegada aparece como sistema aparte a propósito: una vez viva, el
jurado la usa sin pasar por Hostium.

---

## Nivel 2 — Contenedores

Todo lo de dentro del boundary vive en el namespace `hostium` del clúster y se despliega con
Kustomize + una `Application` de ArgoCD (`deploy/argocd/hostium.yaml`) — el mismo mecanismo que
Hostium le da a los equipos.

```mermaid
C4Container
    title Nivel 2 - Contenedores de Hostium

    Person(team, "Equipo del hackathon")
    Person(visitor, "Jurado / visitante")

    Container_Boundary(hostium, "Hostium - namespace hostium") {
        Container(web, "web", "Next.js 15 / React", "Dashboard y BFF. Las rutas /api/* del propio Next adjuntan el JWT de la cookie httpOnly y proxifican a la API: el navegador solo habla con su propio dominio")
        Container(api, "php", "Symfony 7 / FrankenPHP", "API REST de los seis bounded contexts. Solo escrituras y lecturas sincronas: registrar equipo, registrar proyecto, listar aplicaciones")
        Container(worker, "worker", "Symfony Messenger consumer", "Ejecuta los EventHandler de cada BC. Sin el, los eventos se quedan publicados en Redis sin que nadie los procese")
        Container(poller, "appsource-poll", "CronJob PHP", "Cada minuto compara la ultima revision conocida de cada AppSource contra la API de GitHub y publica SourceChanged")
        Container(buildlauncher, "build-launcher", "Go", "Consume BuildJobRequested, crea el Job de Kubernetes, espera su estado terminal y devuelve JobSucceeded o JobFailed")
        Container(deploylauncher, "deploy-launcher", "Go", "Consume DeployAttemptRequested, crea o parchea la Application de ArgoCD del tenant y sondea su salud hasta agotar intentos")
        Container(activator, "activator", "Go", "Escala a cero las apps ociosas y sostiene la peticion mientras la app despierta. Lleva su propio interruptor de apagado")
        Container(keycloak, "keycloak", "Keycloak", "Identity provider, realm podium. Emite el JWT que valida la API")
        ContainerDb(db, "database", "PostgreSQL (CNPG)", "Estado de dominio de los seis BCs")
        ContainerQueue(redis, "redis", "Redis Streams", "El bus de eventos entre bounded contexts, y la frontera PHP-Go: un stream por evento, un consumer group por consumidor")
    }

    Container_Ext(buildrunner, "build-runner", "Imagen de Job - buildah", "Efimero: clona el repo, elige el Dockerfile de plantilla segun lang/framework y empuja la imagen")
    Container_Ext(chart, "Chart podium-app", "Helm, repo OCI", "Chart generico de tenant - Deployment, Service, Ingress. Se publica una vez, fuera del ciclo de cada deploy")

    System_Ext(github, "GitHub")
    System_Ext(gitlab, "GitLab Container Registry")
    System_Ext(argocd, "ArgoCD")
    System_Ext(tenantapp, "App del equipo", "Namespace propio por Project")

    Rel(team, web, "Usa el dashboard", "HTTPS")
    Rel(web, keycloak, "Login por password grant, guarda el JWT en cookie httpOnly", "OIDC")
    Rel(web, api, "Proxifica las lecturas con Bearer JWT", "HTTPS/JSON")
    Rel(api, keycloak, "Valida la firma del JWT", "JWKS")
    Rel(api, db, "Lee y escribe agregados", "Doctrine")
    Rel(api, redis, "Publica eventos de dominio", "Streams")
    Rel(worker, redis, "Consume y publica eventos", "Streams")
    Rel(worker, db, "Lee y escribe agregados", "Doctrine")
    Rel(poller, github, "Pide el ultimo commit de la rama", "API con token")
    Rel(poller, redis, "Publica SourceChanged", "Streams")
    Rel(worker, github, "Lee el podium.yaml del repo", "API")
    Rel(buildlauncher, redis, "Consume BuildJobRequested, publica JobSucceeded/JobFailed", "Streams - JSON")
    Rel(deploylauncher, redis, "Consume DeployAttemptRequested, publica HealthCheck*", "Streams - JSON")
    Rel(buildlauncher, buildrunner, "Crea el Job que lo ejecuta", "API de Kubernetes")
    Rel(buildrunner, github, "Clona el repo en el commit pedido", "git")
    Rel(buildrunner, gitlab, "Empuja la imagen etiquetada con el commit", "buildah push")
    Rel(deploylauncher, argocd, "Crea o parchea la Application del tenant", "CRD argoproj.io")
    Rel(argocd, chart, "Renderiza el chart con los values del tenant", "Helm OCI")
    Rel(argocd, tenantapp, "Sincroniza - auto-sync, prune, selfHeal", "")
    Rel(activator, tenantapp, "Escala a cero y despierta bajo demanda", "API de Kubernetes")
    Rel(visitor, tenantapp, "Abre la demo", "HTTPS")

    UpdateLayoutConfig($c4ShapeInRow="3", $c4BoundaryInRow="1")
```

**Las dos decisiones que explican este dibujo:**

1. **Redis Streams es la única frontera entre PHP y Go.** Los transportes que cruzan a Go llevan
   serializador JSON explícito y `delete_after_ack: false`, porque PHP solo publica en ellos y es
   el consumidor Go quien crea su propio grupo y limpia (`config/packages/messenger.yaml`).
2. **Los dos lanzadores Go no tienen lógica de dominio.** Traducen un evento a un objeto de
   Kubernetes y devuelven exactamente un evento terminal. Todas las reglas viven en PHP.

---

## Nivel 3 — Componentes

### 3.1 · `php` + `worker` — los seis bounded contexts

Mismo código fuente en dos contenedores: `php` sirve HTTP, `worker` consume eventos. Cada BC
expone **un único `ApplicationService`** con un método público por caso de uso — sin command bus ni
query bus — y publica sus eventos por el bus de Messenger.

```mermaid
C4Component
    title Nivel 3.1 - Componentes de php / worker

    Container_Boundary(api, "php + worker - Symfony") {
        Component(http, "Controladores HTTP", "Symfony, por BC", "RegisterTeam, RegisterProject, List/Get de Team, Project y Application. Validacion en el borde con un Request tipado")
        Component(team, "BC Team", "ApplicationService + Domain", "Team, TeamName, UserId. Invariante: minimo un miembro, el creador")
        Component(project, "BC Project", "ApplicationService + EventHandler", "Duenno del hash publico. Lee el podium.yaml, descubre servicios y reparte el cambio de fuente por servicio")
        Component(appsource, "BC AppSource", "ApplicationService + EventHandler", "De donde viene el codigo y si cambio. Agnostico al proveedor detras de un puerto, con un adaptador GitHub")
        Component(appmanager, "BC App Manager", "ApplicationService + 6 EventHandler", "Orquestador del ciclo. Agregado Application con su maquina de estados y su log de historial")
        Component(build, "BC Build", "ApplicationService + 3 EventHandler", "Catalogo de Template y BuildJob. Decide que plantilla aplica y publica BuildJobRequested")
        Component(deploy, "BC Deploy", "ApplicationService + 3 EventHandler", "DeployAttempt y DeployValues. Publica DeployAttemptRequested y resuelve el intento con la sennal de salud")
        Component(shared, "Shared", "Doctrine types, seguridad", "Tipos Doctrine de cada Value Object y validacion del JWT. El dominio no importa nada del framework")
    }

    ContainerDb(db, "database", "PostgreSQL")
    ContainerQueue(redis, "redis", "Redis Streams")
    System_Ext(github, "GitHub API")
    System_Ext(keycloak, "Keycloak")

    Rel(http, team, "Registra y consulta", "")
    Rel(http, project, "Registra y consulta", "")
    Rel(http, appmanager, "Consulta estado de aplicaciones", "")
    Rel(http, keycloak, "Valida el Bearer JWT", "JWKS")
    Rel(appsource, github, "Ultimo commit de la rama", "adaptador GitHub")
    Rel(project, github, "Lee el podium.yaml", "adaptador GitHub")
    Rel(appsource, redis, "SourceChanged", "publica")
    Rel(project, redis, "ProjectRegistered, ServiceDiscovered, ApplicationSourceChanged", "publica y consume")
    Rel(appmanager, redis, "ApplicationBuildRequested, ApplicationDeployRequested", "publica y consume")
    Rel(build, redis, "BuildJobRequested, BuildSucceeded, BuildFailed", "publica y consume")
    Rel(deploy, redis, "DeployAttemptRequested, DeploySucceeded, DeployFailed", "publica y consume")
    Rel(team, db, "", "Doctrine")
    Rel(appmanager, db, "", "Doctrine")
    Rel(shared, db, "Mapea los Value Object", "tipos custom")

    UpdateLayoutConfig($c4ShapeInRow="3", $c4BoundaryInRow="1")
```

**Ningún BC llama a otro.** Todas las flechas entre contextos pasan por Redis. Por eso App Manager
puede ser el orquestador sin que Build y Deploy se conozcan entre sí.

### 3.2 · `build-launcher` y `deploy-launcher` — los dos lanzadores Go

Misma forma deliberadamente: un paquete de envelope para hablar Messenger, un paquete de mapeo
puro (testeable sin clúster) y un `launcher` que solo secuencia llamadas.

```mermaid
C4Component
    title Nivel 3.2 - build-launcher y deploy-launcher

    Container_Boundary(bl, "build-launcher - Go") {
        Component(blenv, "messenger/envelope", "Go", "Lee y escribe el sobre de Symfony Messenger sobre Redis Streams: body mas headers.type")
        Component(blev, "events", "Go", "BuildJobRequested entrante, JobSucceeded y JobFailed salientes")
        Component(bljob, "jobspec", "Go", "Mapeo puro evento a Job de Kubernetes: imagen del runner, env vars, secreto del registro montado para el push. Sin cliente de Kubernetes, unit-testable")
        Component(bllau, "launcher", "Go", "Crea el Job, sondea hasta estado terminal y devuelve exactamente uno de los dos eventos")
    }

    Container_Boundary(dl, "deploy-launcher - Go") {
        Component(dlenv, "messenger/envelope", "Go", "Mismo sobre, mismo formato")
        Component(dlev, "events", "Go", "DeployAttemptRequested entrante, HealthCheckSucceeded y HealthCheckExhausted salientes")
        Component(dlargo, "argospec", "Go", "Mapeo puro evento a Application de ArgoCD. Duenno unico del nombre del tenant y del host publico: serviceName-hash, un solo nivel bajo el wildcard")
        Component(dllau, "launcher", "Go", "Get-or-create de la Application: si existe, parchea solo valuesObject. Despues sondea health hasta Healthy o hasta agotar intentos")
    }

    ContainerQueue(redis, "redis", "Redis Streams")
    System_Ext(k8sapi, "API de Kubernetes")
    System_Ext(argocd, "ArgoCD")

    Rel(blenv, redis, "Consume y publica", "Streams")
    Rel(blenv, blev, "Deserializa", "")
    Rel(bllau, bljob, "Pide el Job a crear", "")
    Rel(bllau, k8sapi, "Create Job y sondeo de estado", "client-go")
    Rel(dlenv, redis, "Consume y publica", "Streams")
    Rel(dlenv, dlev, "Deserializa", "")
    Rel(dllau, dlargo, "Pide la Application o su valuesObject", "")
    Rel(dllau, argocd, "Create, Update y sondeo de health", "dynamic client, CRD")

    UpdateLayoutConfig($c4ShapeInRow="2", $c4BoundaryInRow="2")
```

> El nombre de la `Application` y el host público salen de **una sola función** (`ApplicationName`
> sobre `tenantSlug`). Fue un bug real: el creador ponía `tenant-{serviceName}-{hash}` y el sondeo
> buscaba `tenant-{hash}`, así que el health check vigilaba un objeto inexistente y el intento no
> resolvía nunca.

### 3.3 · `activator` — escalado a cero y despertar bajo demanda

```mermaid
C4Component
    title Nivel 3.3 - Componentes del activator

    Container_Boundary(act, "activator - Go") {
        Component(routing, "routing/table", "Go, informers", "Tabla hostname a Service/Deployment, mantenida por watch sobre los objetos etiquetados podium.dev/managed")
        Component(proxy, "proxy/handler", "Go", "Sostiene la peticion entrante mientras la app arranca y hace de proxy inverso en cuanto responde")
        Component(wake, "wake", "Go", "Espera a que el Deployment tenga replica lista, con presupuesto de 60s antes de considerarlo atascado")
        Component(scale, "scale/patch", "Go", "Parche acotado a spec.replicas con reintento ante conflicto de version: el poller o un humano pueden tocar el mismo objeto")
        Component(lastseen, "lastseen/tracker", "Go", "Marca de ultima peticion por tenant - el activator ve todo el trafico, asi que es el sitio natural para llevarla")
        Component(sweep, "sweep", "Go", "Barrido periodico: escala a cero lo que lleva mas del umbral sin trafico")
        Component(kill, "killswitch", "Go", "Interruptor de apagado: revierte el Ingress al Service de la app y saca al activator del camino del trafico sin tocar nada mas")
    }

    System_Ext(ingress, "Ingress NGINX")
    System_Ext(k8sapi, "API de Kubernetes")
    System_Ext(tenantapp, "App del equipo")

    Rel(ingress, proxy, "Enruta el trafico del tenant", "HTTP")
    Rel(proxy, routing, "Resuelve el destino por hostname", "")
    Rel(proxy, wake, "Despierta si esta a cero", "")
    Rel(proxy, lastseen, "Anota la peticion", "")
    Rel(proxy, tenantapp, "Proxy inverso en cuanto hay replica lista", "HTTP")
    Rel(wake, scale, "Sube a una replica", "")
    Rel(scale, k8sapi, "Patch de spec.replicas", "client-go")
    Rel(sweep, lastseen, "Consulta ociosidad", "")
    Rel(sweep, scale, "Baja a cero", "")
    Rel(routing, k8sapi, "Watch de Deployments y Services etiquetados", "informers")
    Rel(kill, ingress, "Reconcilia el Ingress fuera del activator", "API de Kubernetes")

    UpdateLayoutConfig($c4ShapeInRow="3", $c4BoundaryInRow="1")
```

### 3.4 · `web` — dashboard y BFF

```mermaid
C4Component
    title Nivel 3.4 - Componentes de web

    Container_Boundary(web, "web - Next.js") {
        Component(pages, "Rutas de UI", "React Server Components", "login, register, dashboard, proyecto, alta de proyecto y equipo, pantalla de secretos")
        Component(apiroutes, "Rutas /api/*", "Route Handlers", "El BFF: teams, projects, applications, me y token. El navegador nunca habla con Symfony directamente")
        Component(authsession, "auth/session", "server-only", "El JWT es el valor de una cookie httpOnly, ilegible desde JavaScript del cliente")
        Component(authproxy, "auth/proxy", "server-only", "Adjunta el Bearer y reenvia a la API: same-origin de entrada, cross-origin de salida")
        Component(contexts, "Contextos de cliente", "React context", "Equipo, proyectos y secretos en el cliente")
        Component(ui, "Componentes de UI", "shadcn/ui, Phosphor", "Tarjetas de aplicacion con estado y URL publica, avatares, estados vacios")
    }

    Container(api, "php", "Symfony")
    System_Ext(keycloak, "Keycloak")

    Rel(pages, apiroutes, "Lee datos", "fetch same-origin")
    Rel(apiroutes, authsession, "Recupera el token de la cookie", "")
    Rel(apiroutes, authproxy, "Reenvia la peticion", "")
    Rel(authproxy, api, "Bearer JWT", "HTTPS/JSON")
    Rel(apiroutes, keycloak, "Password grant en login y alta de usuario en registro", "OIDC")
    Rel(pages, contexts, "Estado de cliente", "")
    Rel(pages, ui, "", "")

    UpdateLayoutConfig($c4ShapeInRow="3", $c4BoundaryInRow="1")
```

---

## Nivel 4 — Código: el agregado `Application`

El nivel 4 solo se dibuja donde aporta. Aquí aporta en **App Manager**, porque es el único sitio
donde vive la decisión de cuándo construir y cuándo desplegar.

```mermaid
classDiagram
    class Application {
        -ApplicationId id
        -string serviceName
        -string projectId
        -string teamId
        -string templateId
        -string framework
        -DateTimeImmutable createdAt
        -ApplicationState state
        -string version
        -bool hasPendingSourceChange
        -array recordedEvents
        -array historyLogs
        +markSourceChanged(revision, repositoryUrl, provider) array
        +markBuildSucceeded(image, port, deployEnvVars, databaseDeclaration) array
        +markBuildFailed() array
        +markDeploySucceeded() array
        +markDeployFailed() array
        +toDTO() ApplicationDTO
        +releaseEvents() array
        +register(serviceName, projectId, teamId, templateId, framework)$ self
        -record(event) void
        -logMutation(before) void
    }

    class ApplicationState {
        <<enumeration>>
        Created
        Building
        Built
        Deploying
        Deployed
        BuildFailed
        DeployFailed
    }

    class ApplicationDTO {
        <<value object>>
        +string serviceName
        +string projectId
        +string teamId
        +string framework
        +DateTimeImmutable createdAt
        +ApplicationState state
        +string version
        +bool hasPendingSourceChange
        +toArray() array
    }

    class ApplicationHistoryLog {
        <<aggregate>>
        +Uuid id
        +ApplicationId applicationId
        +ApplicationDTO before
        +ApplicationDTO after
        +DateTimeImmutable createdAt
        +record(applicationId, serviceName, before, after)$ self
    }

    class ApplicationRepository {
        <<port>>
        +save(Application)
        +byId(ApplicationId)
    }

    class TemplateResolver {
        <<port>>
        +resolve(lang, framework) TemplateId
    }

    class ApplicationRegistered {
        <<domain event>>
    }
    class ApplicationBuildRequested {
        <<domain event>>
    }
    class ApplicationDeployRequested {
        <<domain event>>
    }

    Application --> ApplicationState
    Application --> ApplicationId
    Application ..> ApplicationDTO : toDTO
    Application "1" --> "*" ApplicationHistoryLog : logMutation
    Application ..> ApplicationRegistered : record
    Application ..> ApplicationBuildRequested : record
    Application ..> ApplicationDeployRequested : record
    ApplicationRepository ..> Application
    DoctrineApplicationRepository ..|> ApplicationRepository
    DoctrineTemplateResolver ..|> TemplateResolver
```

### La máquina de estados que gobierna el ciclo

```mermaid
stateDiagram-v2
    [*] --> Created : ServiceDiscovered - la Application se registra
    Created --> Building : ApplicationBuildRequested
    Building --> Built : BuildSucceeded
    Building --> BuildFailed : BuildFailed
    Built --> Deploying : ApplicationDeployRequested
    Deploying --> Deployed : DeploySucceeded
    Deploying --> DeployFailed : DeployFailed
    Deployed --> Building : ApplicationSourceChanged - commit nuevo
    BuildFailed --> Building : ApplicationSourceChanged
    DeployFailed --> Building : ApplicationSourceChanged

    note right of Building
        Un cambio de fuente que llega
        con un ciclo en curso no lo
        interrumpe: queda marcado como
        pendiente y se atiende al cerrar
    end note
```

Los eventos **no los despacha el agregado**: los registra al mutar y el `ApplicationService` los
recoge con `releaseEvents()` y los publica después de persistir con éxito.

---

## Anexo — El ciclo completo, de `git push` a URL pública

No es un diagrama C4, pero es la lectura que cose los cuatro niveles.

```mermaid
sequenceDiagram
    autonumber
    participant GH as GitHub
    participant PO as appsource-poll
    participant AS as BC AppSource
    participant PR as BC Project
    participant AM as BC App Manager
    participant BU as BC Build
    participant BL as build-launcher
    participant K8 as Kubernetes
    participant DE as BC Deploy
    participant DL as deploy-launcher
    participant AR as ArgoCD

    PO->>GH: ultimo commit de la rama
    PO->>AS: revision nueva
    AS-->>PR: SourceChanged
    PR->>GH: lee podium.yaml
    PR-->>AM: ServiceDiscovered (servicio nuevo)
    PR-->>AM: ApplicationSourceChanged (servicio ya conocido)
    AM-->>BU: ApplicationBuildRequested
    BU-->>BL: BuildJobRequested
    BL->>K8: crea el Job de build-runner
    K8-->>BL: Job terminal
    BL-->>BU: JobSucceeded / JobFailed
    BU-->>AM: BuildSucceeded / BuildFailed
    AM-->>DE: ApplicationDeployRequested
    DE-->>DL: DeployAttemptRequested
    DL->>AR: crea o parchea la Application del tenant
    AR->>K8: sincroniza el chart podium-app
    DL->>AR: sondea health
    DL-->>DE: HealthCheckSucceeded / HealthCheckExhausted
    DE-->>AM: DeploySucceeded / DeployFailed
    Note over AM: Deployed - la tarjeta del dashboard<br/>muestra serviceName-hash.apperture.dev
```

---

## Lo que estos diagramas no muestran, porque hoy no existe

Se anota aquí en vez de dibujarse en gris, para que ningún diagrama prometa lo que el clúster no hace:

| Pieza | Estado real |
|---|---|
| **Agente de ensayo / Remediation** | No implementado. `AgentEntryPoint` en el dashboard es un mock declarado, sin llamada a LLM; el BC Remediation nunca se arrancó |
| **Notification** | Confirmado en el context map, sin código. El transporte `build_failed_notification` existe en `messenger.yaml` sin consumidor |
| **Provisioning (quota, NetworkPolicy, PSS, TTL por tenant)** | El namespace se crea (`CreateNamespace=true`), pero el chart `podium-app` solo renderiza Deployment, Service e Ingress. Las cinco NetworkPolicy están **validadas 11/11 como fixture** en `docs/hackathon-netpol/`, no aplicadas por el camino de despliegue |
| **Secretos / env vars del tenant** | `DeployValues` los lleva en el dominio, pero el `valuesObject` que llega al chart son solo `hash`, `image`, `ingress.host` y `port`. La pantalla de secretos del dashboard es fixture de frontend |
| **Base de datos por tenant (CNPG)** | Declarable en `podium.yaml`, sin materializar en el chart |
| **Catálogo de plantillas** | Dos: `nodejs/nestjs` y `nodejs/nextjs`. Otro lenguaje es un Dockerfile más en `services/build-runner/templates/` |
