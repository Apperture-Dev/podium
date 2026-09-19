# Model — App Manager

**Derivado de**: `discovery.md` (sesión cerrada, sin ambigüedades bloqueantes)
**Fecha**: 2026-09-12

---

## Diagrama de clases

```mermaid
classDiagram
    class Application {
        <<Aggregate Root>>
        +id: uuid
        +serviceName: string
        +projectId: string
        +teamId: string
        +templateId: string
        +state: ApplicationState
        +version: string
        +hasPendingSourceChange: bool
        +registerApplication()
        +markSourceChanged()
        +markBuildSucceeded()
        +markBuildFailed()
        +markDeploySucceeded()
        +markDeployFailed()
    }

    class ApplicationState {
        <<Enumeration>>
        Created
        Building
        Built
        Deploying
        Deployed
        BuildFailed
        DeployFailed
    }

    class ApplicationHistoryLog {
        <<Aggregate>>
        +id: uuid
        +serviceName: string
        +before: ApplicationDTO
        +after: ApplicationDTO
        +createdAt: timestamp
    }

    class ApplicationDTO {
        <<Value Object>>
        +serviceName: string
        +projectId: string
        +teamId: string
        +state: ApplicationState
        +version: string
        +hasPendingSourceChange: bool
    }

    class Project {
        <<Aggregate — BC Project>>
    }

    class Team {
        <<Aggregate — otro BC, sin nombre aún>>
    }

    class Template {
        <<Aggregate — BC Build>>
    }

    Application "1" --> "*" ApplicationHistoryLog : produce (única fábrica autorizada)
    Application --> ApplicationState : tiene
    Application ..> ApplicationDTO : snapshot antes/después
    ApplicationHistoryLog ..> ApplicationDTO : before / after
    Application ..> Project : referencia por id (projectId) — dueño real del hash y de teamId
    Application ..> Team : teamId materializado, copia de solo lectura desde Project
    Application ..> Template : referencia por id (templateId)

    note for Application "id (UUID) = identidad técnica interna.\nserviceName = atributo de negocio, único\ndentro de su Project (antes 'appHash').\nteamId = copia materializada de Project,\nnunca un segundo dueño de la verdad.\nOrquestador central del ciclo build→deploy.\nPublica: ApplicationRegistered, ApplicationBuildRequested,\nApplicationDeployRequested.\nToda mutación produce un ApplicationHistoryLog."
    note for Team "userId NO se modela aquí ni en ningún BC propio —\nvive en Keycloak/JWT, referencia externa opaca."
```

## Máquina de estados

```mermaid
stateDiagram-v2
    [*] --> Created
    Created --> Building : ApplicationSourceChanged
    Building --> Building : ApplicationSourceChanged (cancela y reinicia)
    Building --> Built : build ok
    Building --> BuildFailed : build falla
    Built --> Deploying
    Deploying --> Deployed : deploy ok
    Deploying --> DeployFailed : deploy falla
    Deployed --> Building : ApplicationSourceChanged
    BuildFailed --> Building : ApplicationSourceChanged
    DeployFailed --> Building : ApplicationSourceChanged
    note right of Deploying
        ApplicationSourceChanged durante Deploying:
        no interrumpe, marca hasPendingSourceChange
    end note
```

## Resumen de responsabilidades

| Elemento | Tipo | Responsabilidad |
|---|---|---|
| `Application` | Aggregate Root | Identidad técnica (`id`, UUID) y estado general de un servicio dentro de un Project. `serviceName` es su atributo de negocio (único dentro del Project). Único orquestador del ciclo build→deploy: decide cuándo cada fase debe actuar y lo comunica por evento |
| `ApplicationHistoryLog` | Aggregate (fábrica restringida) | Auditoría append-only de toda mutación de `Application` — snapshot `ApplicationDTO` antes/después |
| `ApplicationDTO` | Value Object | Forma canónica del estado de una `Application` en un instante, reutilizada en vivo y en cada log |
| `version` | Value Object (campo de `Application`) | Timestamp incremental — optimistic lock + tag de despliegue. No semver |
| `Project` | Referencia externa | Dueño real del `hash` público y de `teamId`; `Application` lo referencia por id |
| `Team` | Referencia externa (vía Project) | `teamId` en `Application` es copia materializada de solo lectura, no un segundo dueño |
| `Template` | Referencia externa | Catálogo de Build; `Application` lo referencia por `templateId` |

## Eventos publicados

| Evento | Disparado por | Consumido por |
|---|---|---|
| `ApplicationRegistered` | `Application` entra en `Created` | *(por descubrir)* |
| `ApplicationBuildRequested` | `Application` entra en `Building` | Build |
| `ApplicationDeployRequested` | `Application` transiciona `Built` → `Deploying` | Deploy |

## Eventos consumidos

| Evento | Origen | Acción resultante |
|---|---|---|
| `ServiceDiscovered` | Project | `registerApplication` |
| `ApplicationSourceChanged` | Project | `markSourceChanged` |
| `BuildSucceeded` | Build | `markBuildSucceeded` |
| `BuildFailed` | Build | `markBuildFailed` |
| `DeploySucceeded` | Deploy | `markDeploySucceeded` |
| `DeployFailed` | Deploy | `markDeployFailed` |
