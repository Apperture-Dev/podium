# Model — Project

**Derivado de**: `discovery.md` (sesión cerrada, sin ambigüedades bloqueantes)
**Fecha**: 2026-09-12

---

## Diagrama de clases

```mermaid
classDiagram
    class Project {
        <<Aggregate Root>>
        +id: uuid
        +hash: string
        +name: string
        +teamId: string
        +knownServiceNames: List~string~
        +registerProject()
        +processSourceChanged()
    }

    class Team {
        <<Aggregate — otro BC, sin nombre aún>>
    }

    class AppSource {
        <<Aggregate — BC AppSource>>
    }

    class Application {
        <<Aggregate — BC App Manager>>
    }

    Project ..> Team : referencia por id (teamId)
    Project ..> AppSource : emits ProjectRegistered — AppSource reacciona creando el suyo, sin llamada directa
    Project "1" --> "*" Application : aglutina, por serviceName conocido

    note for Project "hash vive aquí, nunca en Application.\nCreación: dar una repositoryUrl crea Project —\npuerta de entrada real del sistema.\nAppSource nace por separado, reactivo\na ProjectRegistered (nunca en el mismo paso).\nMantiene su propia lista de serviceName\nconocidos, sin consulta directa a App Manager."
```

## Resumen de responsabilidades

| Elemento | Tipo | Responsabilidad |
|---|---|---|
| `Project` | Aggregate Root | Aglutina los servicios (Applications) declarados en el `podium.yaml` de un repo. Dueño del `hash` público, de `name` (etiqueta legible, elegida por el equipo) y de `teamId`. Descubre servicios nuevos y reparte los cambios de fuente por servicio conocido |

## Domain Actions

| Action | Comportamiento | Produce |
|---|---|---|
| `registerProject` | El equipo da a Podium una `repositoryUrl` y un `name` por primera vez → crea `Project` (con `hash`, `name`, `teamId`) | `Project` creado, publica `ProjectRegistered` |
| `processSourceChanged` *(consume evento `SourceChanged`)* | Lee `podium.yaml` en la revisión recibida, recorre los servicios declarados: si `serviceName` ya está en `knownServiceNames` → publica `ApplicationSourceChanged`; si es nuevo → lo añade a la lista y publica `ServiceDiscovered` | `ApplicationSourceChanged` y/o `ServiceDiscovered`, uno por servicio afectado |

## Decisiones de alcance (no son dominio, pero afectan el diseño)

| Decisión | Razón |
|---|---|
| El reparto de `ApplicationSourceChanged` no filtra por carpeta (`src`) — se dispara para *todos* los servicios conocidos ante cualquier cambio | MVP simple. El desperdicio de reconstruir sin cambios reales se mitiga en Build (`ImageNotChanged`, vía caché de `buildah`), no aquí |
| Empaquetado de deploy por Application (servicio), no por Project | Un build roto de un servicio no bloquea el despliegue de los demás; revisable si en el futuro hace falta atomicidad entre servicios de un mismo Project |
| **`name` añadido (2026-09-19)**: `ProjectName` VO (mismo patrón que `TeamName` — libre, máx. 150 caracteres), requerido al registrar | Necesario para el endpoint `GET /api/projects?teamId=` — mostrar un hash no es útil para un listado, cada equipo necesita distinguir sus Projects por nombre |

## Eventos publicados

| Evento | Disparado por | Payload | Consumido por |
|---|---|---|---|
| `ApplicationSourceChanged` | `processSourceChanged`, servicio ya conocido | `serviceName`, `projectId`, `revision`, `repositoryUrl`, `provider` | App Manager |
| `ServiceDiscovered` | `processSourceChanged`, servicio nuevo | `serviceName`, `projectId`, `lang`, `framework` | App Manager (dispara `registerApplication`) |
| `ProjectRegistered` | `registerProject` | `projectId`, `repositoryUrl`, `teamId` | AppSource (dispara su propio `registerAppSource`) |

## Eventos consumidos

| Evento | Origen | Acción resultante |
|---|---|---|
| `SourceChanged` | AppSource | `processSourceChanged` |
