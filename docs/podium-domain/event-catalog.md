# Event Catalog — Podium

**Propósito**: única fuente de verdad del *payload* de cada evento de dominio que cruza bounded contexts. Necesario porque Go (Build, Deploy) y PHP (Project, AppSource, App Manager, Team) no comparten tipos — sin este catálogo, un cambio de forma (`projectId` vs `project_id`) rompe la integración en runtime, no en compilación.

Fuente: `project/model.md`, `build/model.md`, `app-manager/model.md`, `appsource/discovery.md`. Este documento no reemplaza esos — si hay conflicto, esos mandan y este archivo se corrige.

**Contrato formal y tipado**: [`asyncapi.yaml`](./asyncapi.yaml) (AsyncAPI 3.1, validado con `@asyncapi/cli validate`) — mismo contenido que este documento pero en JSON Schema por evento, pensado para generar tipos/validar payloads en Go y PHP en vez de copiar los campos a mano. Si hay conflicto entre este Markdown y `asyncapi.yaml`, corregir ambos juntos — no deberían divergir.

---

## Envelope canónico

Todo mensaje publicado en Redis (vía Symfony Messenger en PHP, `go-redis` en Go) lleva este sobre, independiente del payload de negocio:

```json
{
  "eventId": "uuid-v7",
  "type": "podium.source-changed",
  "occurredAt": "2026-09-19T14:32:00Z",
  "correlationId": "uuid — se propaga desde el primer evento de la cadena, para trazar un flujo completo",
  "payload": { }
}
```

- `type` = nombre del stream Redis (ver tabla abajo), formato `podium.<kebab-case>`.
- `correlationId`: no está confirmado en ningún `model.md` — se agrega aquí como convención de infraestructura para poder trazar `SourceChanged → ... → DeploySucceeded` en logs/observabilidad. No es parte del lenguaje ubicuo de ningún BC (es un concern de plataforma, no de dominio) — cada BC lo reenvía sin interpretarlo.
- **PHP↔PHP (mismo monorepo)**: el BC consumidor escucha directamente la clase de evento de dominio del BC productor (ej. `App\AppSource\Domain\Event\SourceChanged`), no una copia local traducida — Symfony Messenger enruta por clase exacta del mensaje decodificado, así que una clase local homónima nunca sería invocada con el mensaje real llegando por Redis (solo funcionaría en un test que invoca el handler a mano, sin pasar por serialización — así se descubrió el problema). La traducción de payload a objeto propio (`Application/Message/...`) solo tiene sentido en el borde Go↔PHP, donde sí hay dos lenguajes distintos sin clases compartibles.

---

## Eventos

### `SourceChanged`
- **Emite**: AppSource
- **Consume**: Project (`processSourceChanged`)
- **Stream**: `podium.source-changed`
- **Payload**:
  | Campo | Tipo | Nota |
  |---|---|---|
  | `projectId` | string (uuid) | |
  | `revision` | string | SHA de la revisión detectada |
  | `repositoryUrl` | string | materializado, Build lo necesita para clonar |
  | `provider` | string | `"github"` hoy — único adaptador |

### `ApplicationSourceChanged`
- **Emite**: Project (servicio ya conocido)
- **Consume**: App Manager (`markSourceChanged`)
- **Stream**: `podium.application-source-changed`
- **Payload**: `serviceName`, `projectId`, `revision`, `repositoryUrl`, `provider`

### `ServiceDiscovered`
- **Emite**: Project (servicio nuevo en `podium.yaml`)
- **Consume**: App Manager (`registerApplication`)
- **Stream**: `podium.service-discovered`
- **Payload**: `serviceName`, `projectId`, `lang`, `framework`

### `ProjectRegistered`
- **Emite**: Project (`registerProject`)
- **Consume**: AppSource (`registerAppSource`) — reemplaza la idea anterior de "Project y AppSource se crean a la vez"
- **Stream**: `podium.project-registered`
- **Payload**: `projectId`, `repositoryUrl`, `teamId`

### `ApplicationRegistered`
- **Emite**: App Manager (`Application` entra en `Created`)
- **Consume**: *(sin consumidor identificado — `app-manager/model.md:121`)*
- **Stream**: `podium.application-registered`
- **Payload**: no confirmado aún — al no tener consumidor, no bloquea implementación; publicar con el mismo shape de `ApplicationDTO` (`serviceName`, `projectId`, `teamId`, `state`, `version`, `hasPendingSourceChange`) hasta que aparezca un consumidor real con requisitos propios.

### `ApplicationBuildRequested`
- **Emite**: App Manager (`Application` entra en `Building`)
- **Consume**: Build (`startBuildJob`)
- **Stream**: `podium.application-build-requested`
- **Payload**: `serviceName`, `projectId`, `templateId`, `version`, `revision`, `repositoryUrl`, `provider`

### `BuildJobRequested`
- **Emite**: Build (`startBuildJob`)
- **Consume**: lanzador de Kubernetes — componente Go mínimo, sin lógica de dominio, **diferido, no construido en esta sesión** (ver `build/discovery.md`, revisión 2026-09-19: se saca de BC Build quién llama a la API de Kubernetes, Build solo publica esto)
- **Stream**: `podium.build-job-requested`
- **Payload**: `buildJobId`, `jobImage` (del `Template` resuelto), `command` (siempre `[]` hoy — convención de `Template`, sin override), `envVars` (`SERVICE_NAME`, `PROJECT_ID`, `TEMPLATE_ID`, `VERSION`, `COMMIT_ID`, `REPOSITORY_URL`, `PROVIDER`, `BUILD_JOB_ID`)

### `JobSucceeded` / `JobFailed`
- **Emite**: el mismo lanzador Go diferido, traduciendo la señal de infraestructura "el Job de Kubernetes terminó" — **sin productor real todavía**
- **Consume**: Build (`completeBuildJob`/`failBuildJob`) — hoy modelado como DTO local sin productor, mismo patrón que `BuildSucceeded`/`BuildFailed` en App Manager antes de que Build existiera
- **Stream**: `podium.job-succeeded` / `podium.job-failed`
- **Payload**: `buildJobId` + (éxito) imagen, `buildEnvVars`, `deployEnvVars`, `databaseDeclaration` leídos de `podium.yaml` por el propio Job / (fallo) `errorMessage`

### `BuildSucceeded`
- **Emite**: Build (`completeBuildJob`)
- **Consume**: App Manager (`markBuildSucceeded`) — dispara `ApplicationDeployRequested` al transicionar `Built → Deploying`
- **Stream**: `podium.build-succeeded`
- **Payload**: `serviceName`, `projectId`, `version`, referencia a la imagen (tag/digest), `deployEnvVars` (de `podium.yaml`, vía `JobSucceeded` — hoy siempre vacío), `databaseDeclaration` (ídem)

### `BuildFailed`
- **Emite**: Build (`failBuildJob`)
- **Consume**: App Manager (`markBuildFailed`), Notification, Remediation — **único punto real de fanout a 3 consumidores**
- **Stream**: `podium.build-failed`
- **Payload**: `serviceName`, `projectId`, `version`, `errorMessage` (yaml inválido o fallo de compilación, misma categoría)

### `ApplicationDeployRequested`
- **Emite**: App Manager (`Application` transiciona `Built → Deploying`)
- **Consume**: Deploy
- **Stream**: `podium.application-deploy-requested`
- **Payload**: no confirmado en ningún `model.md` (Deploy no tiene discovery propio todavía) — mínimo inferible por simetría con `ApplicationBuildRequested`: `serviceName`, `projectId`, `version`, referencia a la imagen, `deployEnvVars`, `databaseDeclaration` (heredados de `BuildSucceeded`). **Confirmar contra el discovery real de Deploy cuando se abra.**

### `DeploySucceeded`
- **Emite**: Deploy
- **Consume**: App Manager (`markDeploySucceeded`)
- **Stream**: `podium.deploy-succeeded`
- **Payload**: no confirmado — Deploy sin discovery propio. Mínimo inferible: `serviceName`, `projectId`, `version`.

### `DeployFailed`
- **Emite**: Deploy (agota reintentos de health-check — invariante: 3 intentos fallidos, `context-map.md:60`)
- **Consume**: App Manager (`markDeployFailed`), Remediation
- **Stream**: `podium.deploy-failed`
- **Payload**: no confirmado — Deploy sin discovery propio. Mínimo inferible: `serviceName`, `projectId`, `version`, `errorMessage`.

---

## Pendientes

- `ApplicationDeployRequested`, `DeploySucceeded`, `DeployFailed`: payload exacto depende del discovery de Deploy, todavía no iniciado. No bloquea implementar Project/AppSource/App Manager/Team hoy — sí bloquea la integración real con Deploy.
- `correlationId` es una convención de infraestructura agregada en este documento, no discutida en ninguna sesión de discovery — confirmar que no choca con ninguna decisión de dominio futura.
