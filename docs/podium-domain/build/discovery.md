# Discovery — Build

**Status**: In Progress
**Last session**: 2026-09-12
**Bounded Context**: Build — decide y ejecuta cómo se construye la imagen (plantillas, lenguajes, sus propias reglas de construcción). Agnóstico a salud y despliegue
**Contexto heredado**: consume `ApplicationBuildRequested` (App Manager); publica `BuildSucceeded`/`BuildFailed`. Solo repos públicos — sin credenciales de equipo (ver `appsource/discovery.md`). El equipo elige lenguaje/framework explícitamente; Podium provee el Dockerfile de plantilla — sin autodetección

---

## Ubiquitous Language

| Term | Definition | Status |
|---|---|---|
| serviceName | Referencia a la Application (servicio) que pide el build — **renombrado desde `appHash`**, ver `app-manager/discovery.md` y `project/discovery.md` | ✅ Confirmed |
| projectId | Referencia al Project al que pertenece el servicio — necesario para saber en qué namespace opera el build | ✅ Confirmed |
| podium.yaml | Fichero en el repo del equipo que declara los `BuildParams` — build y deploy env vars. Se busca en la carpeta del repo (**cómo se localiza exactamente, sin resolver — ¿siempre en la raíz?**) | ✅ Confirmed nombre — mecanismo de localización pendiente |

## Mecanismo de ejecución (infraestructura, no dominio — capturado para no perderlo)

Imagen de `jobImage` (buildah) → clona el repo en `commitId` → localiza `podium.yaml` → valida y ejecuta el build con las opciones de `BuildYamlSnapshot` → al terminar, señaliza el resultado. Todo esto corre **dentro** del propio Job de Kubernetes (el script de la imagen `jobImage`), nunca en el proceso de Build — eso ya estaba resuelto así desde el principio.

**Revisión 2026-09-19 (split de responsabilidad, fuera de BC Build):** lo que sí estaba dentro de Build hasta ahora era *quién llama a la API de Kubernetes* para crear ese `Job` — y eso se saca del BC. Build (PHP, como todo lo demás) decide y publica `BuildJobRequested` (imagen, comando, variables de entorno) vía Redis Streams; un componente aparte, mínimo, en Go (**diferido — no se construye hoy**) consume ese evento y llama a la API de Kubernetes para crear el `Job`. Ese componente no tiene lógica de dominio propia — no sabe qué es un `Template` ni valida nada, solo traduce "lanza esto" en una llamada a la API. Deploy necesitará lo mismo simétricamente cuando se aborde (crear/actualizar un `Deployment`) — mismo componente Go, ampliado, o uno nuevo; decisión pendiente para cuando se toque Deploy. Motivo del split: mantener un solo lenguaje/convención DDD para toda la lógica de negocio (ya establecido en el resto del backend), y acotar quién tiene credenciales del clúster a un binario pequeño y auditable — el coste es un componente desplegable más, asumible porque desplegar en el clúster real es sencillo con ArgoCD.

## Aggregate Candidates

| Name | Responsibilities | Invariants | Status |
|---|---|---|---|
| Template | Catálogo de plantillas por lenguaje/framework — compartido, no por Application. Declara un `HashMap<ParamFieldName, ParamField>`: el esquema de qué parámetros necesita esa plantilla (tipo, obligatoriedad, restricción de forma). Campo `jobImage`: la imagen de contenedor con la que corre el Job de build (p. ej. buildah) para ese lenguaje | El esquema es la única fuente de validación — ningún `if (lenguaje === X)` en el dominio | ✅ Confirmed |
| BuildJob | Estado de un intento de build concreto — termina en `Succeeded` o `Failed`. Campos: `id` (UUID), `teamId`, `serviceName` (antes `appHash`), `projectId`, `templateId`, `commitId` (llegó vía `SourceChanged`→`ApplicationBuildRequested`, no duplica la detección de AppSource), `repositoryUrl` y `provider` (materializados — necesarios para clonar y ejecutar, no una referencia viva a AppSource), snapshot del yaml resuelto y validado del repo (build+deploy env vars) | El yaml se relee entero en cada build — sin estado persistente entre intentos | ✅ Confirmed |

~~`Registry`~~ — **no es aggregate**. Para el hackathon, el destino de push es configuración de infraestructura (variable de entorno del proceso que ejecuta el build), no un concepto de dominio. Fuera del modelo, igual que las decisiones de ArgoCD/KEDA.

## Domain Actions (derivadas del ciclo de `BuildJob` — a confirmar)

| Action | Comportamiento | Produce |
|---|---|---|
| `startBuildJob` *(nombre provisional)* | Consume `ApplicationBuildRequested` → resuelve `Template` por `templateId` (para su `jobImage`) → crea `BuildJob` (`Pending`) con `serviceName`, `projectId`, `templateId`, `version`, `commitId` (= `revision`), `repositoryUrl`, `provider` (materializados del payload) | `BuildJob` creado, publica `BuildJobRequested` (imagen, comando, env vars — para el lanzador de Kubernetes, ver arriba) |
| `completeBuildJob` *(nombre provisional)* | Traduce la señal de infraestructura "el Job clonó, validó `podium.yaml` y construyó bien" (imagen resultante + `BuildYamlSnapshot` leído por el propio Job) → `BuildJob` → `Succeeded` | Publica `BuildSucceeded` |
| `failBuildJob` *(nombre provisional)* | Build falla, o el yaml no valida contra el esquema de `Template` (ambos decididos y señalizados por el propio Job, no por Build) → `BuildJob` → `Failed` | Publica `BuildFailed` |

## Value Object Candidates

| Name | Construction Rule | Scope | Status |
|---|---|---|---|
| id | UUID — identidad técnica interna (convención general) | Todo aggregate de Build | ✅ Confirmed |
| ParamField | `{tipo, obligatorio, restricción de forma}` — restricción es lo que impide inyección (no basta con tipo, hace falta regex o lista cerrada de valores permitidos) | Dentro de `Template` | 🆕 Proposed |
| BuildYamlSnapshot | El yaml (`podium.yaml`) resuelto y validado de un `BuildJob` — build env vars, deploy env vars (secrets `${SECRET_NAME}`, **sin valores por defecto** — sintaxis simple, confirmado), declaración de base de datos (se reenvía a Deploy, quien la materializa como manifiesto CNPG — Provisioning solo declara el derecho, no la crea), y referencias cruzadas entre servicios del mismo `podium.yaml` (`${app.url}`) | Dentro de `BuildJob`, snapshot de un intento — no persiste independiente. Base de datos: si se declara `URL` directamente, esa gana sobre cualquier campo suelto (`database`, `user`, etc.), sin discriminador explícito — precedencia por peso | ✅ Confirmed |

## Domain Events

| Name | Trigger | Payload | Status |
|---|---|---|---|
| `BuildSucceeded` | Build produce una imagen válida | `serviceName`, `projectId`, `version`, referencia a la imagen, env vars de deploy (leídas del yaml), declaración de base de datos (para que Deploy la materialice como CNPG — Build nunca crea infraestructura, solo la reenvía) | ✅ Confirmed nombre y forma general del payload |
| `BuildFailed` | Build no logra producir una imagen | *(sin discutir)* | ✅ Confirmed nombre — payload pendiente |

## Open Ambiguities

| Question | Context | Resolution |
|---|---|---|
| ~~¿Un yaml que no valida contra el paramSchema de Template es el mismo tipo de fallo...?~~ | BuildJob / Remediation | ✅ Resuelto — mismo `BuildFailed`, con mensaje de error claro en el payload. Sin categoría aparte |
| ~~Dijiste que el script del Job, al terminar, publica un evento "JobSucceeded"...?~~ | BuildJob | ✅ Resuelto — `JobSucceeded` es una señal de infraestructura (el propio Job de Kubernetes avisando que terminó bien), no un evento de dominio. `BuildJob` la traduce: recibe la señal, decide `completeBuildJob`/`failBuildJob`, y **esas** acciones son las que publican `BuildSucceeded`/`BuildFailed`. Frontera hexagonal limpia — la señal de infra nunca cruza como evento de dominio |
| ~~¿Dónde vive la elección de lenguaje/plantilla...?~~ | Build / App Manager | ✅ Resuelto — `Template` es aggregate propio de Build, catálogo compartido. **Cross-context**: `Application` (App Manager) necesita un `templateId` de referencia plana — ver `app-manager/discovery.md` |
| ~~`Template` es solo catálogo...¿dónde viven datos de ejecución por app?~~ | Template / Application | ✅ Resuelto — ni en `Application` ni en un aggregate propio: **el yaml del repo del equipo es la fuente de verdad**, Podium no la posee ni la duplica. Build lo lee, valida contra el `HashMap` de `Template`, y reparte: usa la parte de build, pasa la de deploy en `BuildSucceeded`. `BuildParams` no es aggregate — es snapshot dentro de `BuildJob` |

## Captured Invariants (deseable, no MVP)

| Rule | Status |
|---|---|
| Comando de arranque: convención sobre configuración para el hackathon — cada `Template` asume su propia convención (`npm start`, único `.sln` del repo) en vez de pedir el comando como campo de usuario. Evita construir un comando con lógica condicional por lenguaje y el riesgo de inyección de un campo libre | 🕓 MVP confirmado así. Comando explícito como override queda diferido si hace falta más adelante |
| **Registro variable / soberanía de imagen**: en el futuro, un equipo podría traer su propio registro (control sobre dónde vive su imagen), o usar Podium solo como servicio de "imagen + job" sin desplegar. Si esto se construye, `Registry` sí pasaría a ser un concepto de dominio real (con su propio ciclo de vida, quizás por equipo) — hoy es prematuro | 🕓 Idea de pivote de producto, no de este hackathon |
| Campo `dockerfile` en `Template` (contenido o referencia a un Dockerfile propio por plantilla) — ideal para dar más control sobre el build, pero no necesario para el hackathon con `jobImage` ya cubriendo la ejecución básica | 🕓 Deseable, diferido |
| **`ImageNotChanged`**: si la caché de `buildah` detecta que las capas resultantes son idénticas a la imagen ya publicada (nada cambió de verdad en la carpeta del servicio), `BuildJob` no pushea nada nuevo y señaliza esto en vez de un `BuildSucceeded` con imagen nueva — evita desperdiciar un deploy cuando el reparto de Project (sin filtrar por carpeta) dispara un build innecesario. `BuildStatus` necesitaría un tercer resultado terminal además de `Succeeded`/`Failed`; falta resolver qué hace `Application` al recibirlo (¿se queda en su estado actual, sin transicionar?) | 🕓 Deseable, diferido — mitiga el reparto simple sin filtrar de Project |

## Session Log

### 2026-09-12
- BC arrancado tras cerrar App Manager y AppSource. Contexto heredado: agnóstico a proveedor de fuente (ver AppSource), agnóstico a salud/despliegue, el equipo elige lenguaje explícitamente.
- Tres aggregates confirmados: `Template` (reglas de construcción), `BuildJob` (estado de un intento, `teamId`+`serviceName`), `Registry` (`location`+`secretName`). Pendiente: alcance de `Template` (catálogo compartido vs. por Application).
- Sesión larga de exploración (patito de goma) resuelta: `Template` confirmado como catálogo compartido con esquema `HashMap<ParamFieldName, ParamField>` (tipo + restricción de forma, evita inyección). Datos de ejecución por app (nombre de solución, script npm) NO viven en `Application` ni en aggregate propio — viven en el yaml del repo del equipo, leído y validado por `BuildJob` en cada intento (`BuildParams` no es aggregate, es snapshot). Comando: convención sobre configuración para el hackathon, diferido como configurable. Env vars de build y deploy: ambas del mismo yaml, sin BC propio (sin comportamiento que lo justifique); Build reparte la parte de deploy en `BuildSucceeded` para que Deploy nunca toque el repo. Secrets por convención `${SECRET_NAME}`, forma validada, no resuelta por el dominio. `BuildJob` guarda `commitId` como hecho plano, sin duplicar la detección de cambios de AppSource. Pendiente: alcance de `Registry` (único vs. varios).
- `Registry` resuelto: no es aggregate para el hackathon — configuración de infraestructura (env var del runner de build). Idea de pivote de producto anotada (registro propio del equipo, Podium como servicio de solo imagen+job) sin construir ahora.
- Acciones de dominio derivadas (a confirmar): `startBuildJob`, `completeBuildJob`, `failBuildJob`. Corrección de consistencia: el payload de `ApplicationBuildRequested` debe llevar `revision` propagada desde `SourceChanged` — Build nunca consulta a AppSource directamente. Nueva pregunta: si un yaml inválido cuenta como el mismo `BuildFailed` que un fallo de compilación, o como categoría distinta.
- Resuelto: yaml inválido = mismo `BuildFailed`, mensaje de error en el payload. `BuildJob` gana `repositoryUrl`/`provider` materializados — propagado hacia atrás por toda la cadena (`AppSource` los expone, `SourceChanged` y `ApplicationBuildRequested` los llevan en el payload). `Template` gana `jobImage` (imagen del Job de build); `dockerfile` como campo queda diferido. Capturado el mecanismo de ejecución (buildah, clon, `podium.yaml`, K8s Job, candidato a Go). Pendiente: si "JobSucceeded" es lo mismo que `BuildSucceeded` o una señal de infraestructura distinta.
- Resuelto: `JobSucceeded` es señal de infraestructura, no evento de dominio — `BuildJob` la traduce a `BuildSucceeded`/`BuildFailed` vía sus propias acciones. Build queda sin ambigüedades bloqueantes (la localización exacta de `podium.yaml` en el repo sigue como nota abierta menor, no bloqueante).
- Revisado un candidato real de `podium.yaml`: confirma base de datos declarada (Provisioning declara el derecho, Deploy materializa el manifiesto CNPG), precedencia URL-gana-por-peso sin discriminador, sin sintaxis de valores por defecto en secrets, y referencia cruzada entre servicios `${app.url}`. El mismo yaml reveló que un repo puede declarar **más de un servicio desplegable** (monorepo) — ver nota en `context-map.md`, nuevo candidato BC `Project`.
- **Revisión (nace `Project`)**: `appHash` se renombra a `serviceName` + `projectId` en `BuildJob` y en los payloads de eventos — un servicio dentro de un Project, no una Application global. Ver `project/discovery.md`.
- Capturado `ImageNotChanged` (deseable, diferido): mitigación vía caché de `buildah` para el reparto simple de Project (sin filtrar por carpeta) — si nada cambió de verdad, no se pushea ni se dispara deploy. Pendiente resolver qué transición hace `Application` al recibirlo.
