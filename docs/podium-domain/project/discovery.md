# Discovery — Project

**Status**: In Progress
**Last session**: 2026-09-12
**Bounded Context**: Project — aglutina las Applications (servicios) declaradas en el `podium.yaml` de un mismo repo. Dueño del `hash` público. Nace de sacar de `AppSource` la responsabilidad de interpretar el yaml y decidir cuántas Applications existen

---

## Ubiquitous Language

| Term | Definition | Status |
|---|---|---|
| Project | Un repo registrado — puede declarar uno o más servicios desplegables en su `podium.yaml` | ✅ Confirmed |
| hash | Identificador público del Project — compone la URL de cada servicio: `{serviceName}.{hash}.apperture.dev` | ✅ Confirmed — vive en Project, no en Application |
| serviceName | Nombre de un servicio dentro del `podium.yaml` (`app`, `frontend`...) — identifica a una `Application` *dentro* de su Project, no globalmente | ✅ Confirmed — reemplaza lo que `Application` llamaba `appHash` |

## Aggregate Candidates

| Name | Responsibilities | Invariants | Status |
|---|---|---|---|
| Project | Identidad (`id`, `hash`), `teamId` — dueño real, Team es dueño de Project, no de cada Application suelta. Lee `podium.yaml` en cada `SourceChanged`, descubre servicios declarados, mantiene su propia lista de `serviceName` ya conocidos (sin consulta a App Manager) | Un `serviceName` nuevo dispara `ServiceDiscovered`; uno ya conocido dispara `ApplicationSourceChanged` | ✅ Confirmed |

## Value Object Candidates

| Name | Construction Rule | Scope | Status |
|---|---|---|---|
| id | UUID — identidad técnica interna (convención general) | Project | ✅ Confirmed |
| hash | *(regla de generación sin discutir — antes vivía en Application como `appHash`)* | Project | 🆕 Proposed |

## Domain Events

| Name | Trigger | Payload | Status |
|---|---|---|---|
| `ApplicationSourceChanged` *(nombre propuesto)* | Project procesa `SourceChanged` y reparte por cada `serviceName` ya conocido | `serviceName`, `projectId`, `revision`, `repositoryUrl`, `provider` | 🆕 Proposed — consumido por App Manager (reemplaza el consumo directo de `SourceChanged` de AppSource) |
| `ServiceDiscovered` *(nombre propuesto)* | Project encuentra un `serviceName` en el yaml que no conocía todavía | `serviceName`, `projectId`, `lang`, `framework` | 🆕 Proposed — dispara el registro de una `Application` nueva. Mecanismo exacto sin resolver |

## Open Ambiguities

| Question | Context | Resolution |
|---|---|---|
| ~~¿`Team` es dueño de `Project`...?~~ | Team / Project / Application | ✅ Resuelto — `Team` es dueño de `Project`. `Application` mantiene un `teamId` propio, pero **materializado** (copia de solo lectura de `Project.teamId`, fijada al crear la Application) — para filtrar "todas las apps de un equipo" sin tener que pasar por `Project` cada vez. Un solo dueño de la verdad, una copia de lectura |
| ~~¿Cómo se traduce exactamente `ServiceDiscovered` en una `Application` nueva registrada...?~~ | Project / App Manager | ✅ Resuelto — mismo patrón evento-consume-acción que el resto del modelo: App Manager consume `ServiceDiscovered` y dispara `registerApplication`, igual que consume `BuildSucceeded` para `markBuildSucceeded` |
| ~~¿`Project` guarda la lista de `serviceName` conocidos él mismo, o eso vive como consulta a App Manager?~~ | Project / App Manager | ✅ Resuelto — Project la guarda él mismo. Ninguna consulta directa entre BCs (regla ya establecida) — Project no puede preguntarle a App Manager qué existe |
| ~~¿Cómo nace un Project en primer lugar?~~ | Project / AppSource | ✅ Resuelto — dar una `repositoryUrl` a Podium crea `Project` y `AppSource` a la vez. Es la puerta de entrada real del sistema, no depende de ningún evento de dominio previo |
| ~~¿El reparto de ApplicationSourceChanged por servicio filtra por la carpeta src...?~~ | Project / AppSource | ✅ Resuelto — MVP simple: Project reparte `ApplicationSourceChanged` a **todos** los servicios conocidos ante cualquier cambio, sin filtrar por carpeta. El desperdicio de reconstruir algo sin cambios reales se mitiga más abajo, en Build, no aquí — ver `ImageNotChanged` en `build/discovery.md` (deseable, diferido) |

## Session Log

### 2026-09-12
- BC creado tras descubrir, con un `podium.yaml` real, que un repo puede declarar varios servicios desplegables (monorepo) — rompe la asunción "un AppSource = una Application".
- Confirmado: `hash` es de Project, nunca de Application. Empaquetado de deploy sigue siendo por Application (servicio), no por Project — decisión explícita, revisable si en el futuro hace falta atomicidad entre servicios.
- `Application.appHash` se renombra a `serviceName` (identidad dentro del Project, no global). Pendiente: relación exacta con `Team` y mecanismo de registro automático de servicios nuevos.
- Resuelto: `Team` es dueño de `Project` (no de cada `Application`). `Application.teamId` se mantiene, pero como copia materializada de solo lectura — un solo dueño de la verdad (`Project`), sin segundo camino de escritura.
- Resueltas las dos ambigüedades restantes, aplicando reglas ya fijadas en el resto del modelo: `ServiceDiscovered` lo consume App Manager y dispara `registerApplication` (mismo patrón evento→acción de siempre); Project guarda su propia lista de `serviceName` conocidos (ninguna consulta directa entre BCs). Nueva pregunta: cómo nace un `Project` la primera vez que un equipo da una `repositoryUrl`.
- Resuelto: dar una `repositoryUrl` crea `Project` y `AppSource` a la vez — puerta de entrada real del sistema. Nueva pregunta, reabre una decisión ya diferida en AppSource: si el reparto de `ApplicationSourceChanged` por servicio necesita filtrar por carpeta `src` (monorepo) ya en el MVP, o se queda simple (dispara para todos los servicios conocidos).
- Resuelto: MVP simple, sin filtrar por carpeta — Project reparte a todos los servicios conocidos. El desperdicio de reconstruir sin cambios reales se mitiga en Build vía caché de buildah (`ImageNotChanged`, deseable diferido) — no aquí. Project queda sin ambigüedades bloqueantes.
