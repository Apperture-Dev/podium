# Event Catalog — Podium

**Propósito**: única fuente de verdad del *payload* de cada evento de dominio que cruza bounded contexts. Necesario porque Go (Build, Deploy) y PHP (Project, AppSource, App Manager, Team) no comparten tipos — sin este catálogo, un cambio de forma (`projectId` vs `project_id`) rompe la integración en runtime, no en compilación.

Fuente: `project/model.md`, `build/model.md`, `app-manager/model.md`, `appsource/discovery.md`. Este documento no reemplaza esos — si hay conflicto, esos mandan y este archivo se corrige.

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

### `BuildSucceeded`
- **Emite**: Build (`completeBuildJob`)
- **Consume**: App Manager (`markBuildSucceeded`) — dispara `ApplicationDeployRequested` al transicionar `Built → Deploying`
- **Stream**: `podium.build-succeeded`
- **Payload**: `serviceName`, `projectId`, `version`, referencia a la imagen (tag/digest), `deployEnvVars` (de `podium.yaml`), `databaseDeclaration` (de `podium.yaml`)

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
