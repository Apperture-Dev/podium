# Discovery — AppSource

**Status**: Ready for Modeling
**Last session**: 2026-09-19
**Bounded Context**: AppSource — de dónde viene el código de un Project y si cambió. Dominio agnóstico al proveedor (GitHub/GitLab/etc.); toda la diferencia de proveedor vive detrás de un único puerto, con un solo adaptador por ahora (repos públicos)
**Contexto heredado**: nace del discovery de App Manager, al descartar `Token`/soporte a repos privados. Ver `context-map.md` y `app-manager/discovery.md`. **Corrección (ver `project/discovery.md`)**: un repo puede declarar varios servicios desplegables (monorepo) — AppSource referencia un `Project`, no una `Application` directamente; `Project` es quien reparte los cambios por servicio

---

## Modeling Conventions (heredadas)

- Sin reglas de proveedor dentro del dominio: un puerto genérico, adaptadores por proveedor. Hoy: un adaptador (público).

## Ubiquitous Language

| Term | Definition | Status |
|---|---|---|
| AppSource | La fuente de código de un Project — de dónde viene y su última revisión conocida | 🆕 Proposed |
| projectId | Identificador del Project al que pertenece esta fuente (Shared Kernel). **Renombrado desde `appHash`** — un repo ya no mapea 1:1 a una Application, sino a un Project que puede contener varias | ✅ Confirmed |

## Aggregate Candidates

| Name | Responsibilities | Invariants | Status |
|---|---|---|---|
| AppSource | Rastrea la ubicación del código (`repositoryUrl`) y su última revisión conocida; detecta cambios. Identidad técnica propia (`id`, UUID); `projectId` es la referencia al `Project` al que pertenece, no su propia identidad | `SourceChanged` solo se dispara si `revision` difiere de la última conocida | ✅ Confirmed |

## Value Object Candidates

| Name | Construction Rule | Scope | Status |
|---|---|---|---|
| adapterType / providerType | *(regla de construcción sin discutir — hoy solo un valor posible: público)* | AppSource | 🆕 Proposed — dato plano, sin lógica de negocio asociada |

## Domain Events

| Name | Trigger | Payload | Status |
|---|---|---|---|
| `SourceChanged` *(nombre propuesto, a confirmar)* | La revisión actual difiere de la última conocida | MVP: `{ projectId, revision, repositoryUrl, provider }` — Build necesita clonar, así que estos dos viajan siempre, no son opcionales | ✅ Confirmed para MVP. **Consumido por Project** (no por App Manager ni Build directamente — Project reparte por servicio, ver `project/discovery.md`) |

## Captured Invariants (deseable, no MVP)

| Rule | Status |
|---|---|
| Un cambio solo debería disparar `SourceChanged` si toca rutas relevantes (`src`/`app`, config, manifiestos de dependencias) — cambios solo en documentación (README, `/docs`) no deberían disparar build | 🕓 Deseable, diferido — MVP dispara ante cualquier cambio, sin filtrar |

## Open Ambiguities

| Question | Context | Resolution |
|---|---|---|
| ~~¿Qué evento emite AppSource...?~~ | AppSource → Build | ✅ Resuelto para MVP — `SourceChanged` con `{ projectId, revision }`. El filtrado por ruta queda diferido |
| ~~Para cuando se construya el filtrado por ruta...?~~ | AppSource | ✅ Resuelto — mismo evento `SourceChanged`, mismo nombre. MVP: payload mínimo (asume "algo cambió"). Si hay tiempo: se enriquece el payload de ese mismo evento con la estructura de ficheros cambiados. Nunca un evento nuevo |
| ~~¿El puerto genérico necesita que el propio AppSource sepa qué tipo de fuente es...?~~ | AppSource | ✅ Resuelto — sí, AppSource conoce su tipo de adaptador (dato plano, para que la infraestructura elija la implementación del puerto). El dominio sigue sin reglas de negocio por proveedor — es solo un selector, no lógica condicional |
| ~~¿Cómo nace un `AppSource` — en el mismo paso que `Project`, o por separado?~~ | AppSource / Project | ✅ Resuelto (2026-09-19) — por separado, de forma reactiva: consume `ProjectRegistered` (publicado por Project al registrarse) y crea su propio aggregate (`registerAppSource`). `repositoryUrl` y `provider` llegan materializados en ese mismo evento. `revision` inicial: sin última revisión conocida todavía (próximo `SourceChanged` la fija) |

## Session Log

### 2026-09-12
- BC creado a partir del discovery de App Manager: al descartar `Token` (solo repos públicos), surge la necesidad de un contexto propio para "de dónde viene el código", agnóstico al proveedor.
- Principio de modelado capturado: dominio sin reglas de proveedor, un puerto, un adaptador por proveedor (hoy: uno solo, público).
- MVP confirmado: cualquier cambio dispara `SourceChanged` (sin filtrar). Deseable diferido: filtrar por ruta (src/app/config/deps sí, docs/README no).
- Resuelto: el payload evoluciona sobre el mismo evento, nunca un evento nuevo. MVP mínimo, se enriquece después si hay tiempo.
- Resuelto: AppSource conoce su tipo de adaptador (dato plano); el dominio sigue sin reglas de negocio por proveedor. Sin ambigüedades abiertas por ahora — pendiente retomar invariantes propios del aggregate.
- Corrección (desde el discovery de App Manager): `SourceChanged` lo consume App Manager, no Build directamente. App Manager es el orquestador central del ciclo build→deploy.
- Convención general aplicada: `AppSource` tiene su propio `id` (UUID); `appHash` queda como referencia a la `Application` dueña, no como identidad propia.
- Cross-context (desde el discovery de Build): `repositoryUrl` y `provider` confirmados como campos explícitos de AppSource, propagados en el payload de `SourceChanged` — Build los necesita materializados para clonar, y nunca puede consultarlos directamente.
- **Revisión importante (nace `Project`)**: un `podium.yaml` real reveló monorepos con varios servicios. `appHash` se renombra a `projectId` — AppSource referencia un `Project`, no una `Application`. `SourceChanged` ahora lo consume `Project`, que reparte por servicio (`ApplicationSourceChanged`) — App Manager deja de consumir `SourceChanged` directamente.

### 2026-09-19
- Resuelto cómo nace `AppSource`: reacciona a `ProjectRegistered` (evento publicado por Project al registrarse), no se crea en el mismo paso ni por llamada directa. `registerAppSource` consume ese evento y crea el aggregate con `projectId`, `repositoryUrl`, `provider` — sin última revisión conocida todavía.
- Invariante capturado: `SourceChanged` solo se dispara si la `revision` recibida difiere de la última conocida — ya implícito en el trigger del evento, ahora explícito como invariante del aggregate.
- Checklist de cierre cumplido: aggregate confirmado (AppSource), invariante confirmado, evento confirmado (`SourceChanged`), lenguaje ubicuo cerrado, sin ambigüedades abiertas que bloqueen el modelo. Propuesto pasar a `/speckit.model`.
