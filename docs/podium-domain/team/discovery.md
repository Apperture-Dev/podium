# Discovery — Team

**Status**: Ready for Modeling
**Last session**: 2026-09-19
**Feature context**: Podium — plataforma de despliegue para HackBarna AI Summit 26. Team es el último BC "Still-Proposed" de `context-map.md`; hoy se decidió construirlo como servicio real (no como referencia opaca) para el walking skeleton de hoy.
**Contexto heredado**: descubierto al modelar App Manager (ver `app-manager/discovery.md`). Ya hay decisiones capturadas ahí que este discovery hereda, no reabre sin motivo:

## Ubiquitous Language

| Term | Definition | Status |
|---|---|---|
| Team | Dueño real de un `Project` (no de cada `Application` suelta) | ✅ Confirmed (`context-map.md:38`, `app-manager/discovery.md:15`) |
| teamId | Identificador de Team, referenciado por `Project` (dueño real) y copiado de solo lectura en `Application` | ✅ Confirmed |
| slug | Identificador legible del Team, "representación mínima" para identificarlo | ✅ Confirmed que existe — construcción sin discutir |
| membership | Relación many-to-many `Team` ↔ `userId` (un usuario puede pertenecer a varios teams). Sin rol por ahora — todos los miembros con el mismo nivel de acceso | ✅ Confirmed para MVP |

## Aggregate Candidates

| Name | Responsibilities | Invariants | Status |
|---|---|---|---|
| Team | Dueño de `Project`(s). Tiene `name`, `slug` (MVP: usa el `id`/uuid directamente). Mantiene su lista de miembros (`userId`, many-to-many, sin rol por ahora). Al registrarse, quien lo crea pasa a ser su primer miembro | Mínimo 1 miembro siempre (garantizado desde `registerTeam`: el creador es el primer miembro) | ✅ Confirmed |

## Value Object Candidates

| Name | Construction Rule | Scope | Status |
|---|---|---|---|
| Slug | Diferido para el MVP de hoy — se usa el `id` (uuid) del Team directamente como identificador. La regla real (slug legible, único) queda pendiente, mismo patrón que `Hash` en Project (`project/discovery.md:28`) | Local | 🕓 Deferred |
| Membership | Lista de `userId` (many-to-many) dentro de Team — sin identidad ni rol propio, sin aggregate propio | Local | ✅ Confirmed para MVP |
| TeamName | Texto libre, máximo 150 caracteres, no vacío (asumido, como cualquier VO de nombre) | Local | ✅ Confirmed |

## Domain Events

| Name | Trigger | Payload | Status |
|---|---|---|---|
| TeamDeleted | Team se borra → debería disparar borrado en cascada de sus `Project`(s) | `teamId` | 🕓 Deferred — nombre y disparador confirmados, implementación fuera del MVP de hoy |

## Open Ambiguities

| Question | Context | Resolution |
|---|---|---|
| ¿`Team` es rico (registro manual, miembros, roles) o mínimo (autogenerado, sin registro)? | Team | ✅ Resuelto (parcial) — Team tiene `name`, `slug`, y miembros (`userId`). Miembro es un VO dentro de Team, no aggregate propio: no tiene reglas ni identidad más allá de "este userId pertenece a este Team" |
| ¿El rol de membership (editor/viewer) es necesario para el walking skeleton de hoy, o se difiere (todos los miembros con el mismo nivel de acceso)? | Team | ✅ Resuelto — diferido. Hoy todos los miembros tienen el mismo nivel de acceso; rol queda anotado como deseable futuro, no MVP |
| ¿`slug` único es invariante real o se puede posponer? | Team | ✅ Resuelto — invariante real pero diferida. Para el MVP de hoy, Team no genera un slug legible: usa su propio `id` (uuid) como identificador, igual que `Hash` en Project sigue sin regla de generación definitiva |
| ¿Quién satisface "mínimo 1 miembro siempre" al crear el Team? | Team | ✅ Resuelto — quien crea el Team pasa a ser automáticamente su primer miembro |

## Session Log

### 2026-09-19
- Sesión iniciada a pedido del arquitecto, usando `/speckit.bc`. Bootstrap: no existe `.speckit.constitution` ni `.speckit.specify` en el repo — se usa como contexto equivalente `docs/podium-domain/context-map.md` (shared kernel / mapa de contextos) y los `discovery.md`/`model.md` ya cerrados de Project, AppSource, Build y App Manager.
- Heredado de `app-manager/discovery.md`: `Team` es aggregate confirmado de este BC (no de App Manager), dueño de `Project`. `Application.teamId` es copia materializada de solo lectura — no reabrir esa decisión aquí salvo que Team introduzca una razón real de negocio para hacerlo.
- Heredado: identidad de usuario (`userId`) no se modela como dominio propio en ningún BC — vive en Keycloak/JWT, referencia externa opaca. Si Team tiene miembros, la pregunta relevante es si el `userId` (claim `sub`) alcanza como identificador de miembro, o si hace falta más.
- `Team` confirmado como aggregate root: `name` (VO `TeamName`, libre, máx. 150 caracteres, no vacío), `slug` diferido (MVP usa el `id`/uuid), lista de miembros (`userId`, many-to-many con `User` — que no se modela, es referencia opaca).
- Descartado un aggregate `Membership` propio: no tiene identidad ni reglas independientes, es solo "este `userId` pertenece a este `Team`, sí o no". Rol (editor/viewer) queda anotado como deseable futuro, diferido — hoy todos los miembros tienen el mismo nivel de acceso.
- Invariante confirmado: mínimo 1 miembro siempre, garantizado porque quien registra el Team pasa a ser automáticamente su primer miembro (`registerTeam(name, creatorUserId)`).
- Evento `TeamDeleted` identificado (borrado en cascada de los `Project`s del team) pero diferido fuera del MVP de hoy — no hay ningún evento de dominio que implementar hoy para Team.
- Checklist de cierre cumplido: aggregate confirmado (Team), VO con regla no trivial confirmado (TeamName), invariante confirmado (mínimo 1 miembro), evento nombrado y confirmado (TeamDeleted, implementación diferida), lenguaje ubicuo cerrado, sin ambigüedades abiertas que bloqueen el modelo. Propuesto pasar a `/speckit.model`.
