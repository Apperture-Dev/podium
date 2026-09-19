# Design

## Context

See `proposal.md` — `Why` and `What Changes`. Relevant constraints already established:

- Backend (`services/php`) exposes exactly two write endpoints and nothing to read: `POST /api/teams` (`{ name, creatorUserId }`), `POST /api/projects` (`{ repositoryUrl, teamId }`, both validated `NotBlank`, `repositoryUrl` validated as a URL). Neither endpoint returns more than `{ id }`. There is no `name` field on project registration.
- No CORS is configured on the Symfony side today; enabling it is a separate change. Real calls from this app will 404/fail with a CORS error in a browser until that lands — accepted for this change.
- `AppManager`'s `Application` + `ApplicationHistoryLog` aggregates exist in the domain but have no HTTP read surface.
- Secrets have no backend at all yet, but their intended design is documented in `docs/podium-config/podium-yaml-guide.md`: real values live as native Kubernetes Secrets per project namespace; `podium.yaml` only ever holds a `${SECRET_NAME}` reference.
- Visual tokens (colors, radius, type, icons) are fixed by `docs/PRD/01_PRD_visual.md`.
- Wireframes (Figma file `V32R1h3rGTE8kATd33DIXm`) fix the information architecture for Proyectos, Nuevo proyecto, Detalle de proyecto, and Secrets. No wireframe exists for Despliegues, Equipo, Ajustes, or team registration.

## Goals / Non-Goals

**Goals:**
- Ship a Next.js app whose real screens (project + team registration) work end-to-end against the actual backend contract, and whose mock screens (project list, project detail, secrets) are visually and structurally complete against fixture data, ready to swap for real data later.
- Keep the mock/real boundary legible in the code: a reviewer should be able to tell, per screen, whether data comes from a fetch or a fixture without reading implementation details.

**Non-Goals:**
- No CORS configuration on the backend (separate change).
- No authentication/login flow — matches the hackathon plan's explicit "no accounts" decision; "María Rey / Admin" in the wireframes' sidebar footer is treated as static placeholder chrome, not a real session.
- No functional agent behavior — the ✦ entry point renders an Assistant UI `AssistantModal` shell with a static welcome state; no LLM call, tool contract, or conversation persistence.
- No new backend endpoints (Secrets, project/deploy read APIs) — those are out of scope for a frontend-only change.

## Decisions

**Next.js App Router, `services/web`, TypeScript.**
Matches the shadcn/ui and Assistant UI ecosystem's default target and the repo's existing per-service folder convention (`services/php` sibling).

**Component libraries kept strictly separated: shadcn/ui for everything general-purpose, Assistant UI only for the agent panel's chat behavior.**
Per explicit instruction: general UI (cards, forms, sidebar, badges, dialogs) is shadcn/ui; the ✦ panel's message list and composer are built from Assistant UI's headless primitives (`ThreadPrimitive`, `ComposerPrimitive`, `MessagePrimitive`) and nothing else in the app depends on Assistant UI. This keeps the deliberately-unscoped agent feature isolated so it can be developed or replaced independently later.

The panel's *shell* (open/close state, the right-docked layout, backdrop) is shadcn's `Sheet`, not Assistant UI's own `AssistantModalPrimitive.Root/Trigger/Content`. The first implementation used `AssistantModalPrimitive`, whose positioning is a floating popover (anchored to the trigger via `floating-ui`, recalculated on every layout pass) — with a 600px-tall panel anchored to a small topbar button, this produced a continuous reposition loop (the panel's measured position changed on every check, confirmed via a Playwright script polling `boundingClientRect` every 300ms and seeing the panel jump to a different position each time) rather than settling. Reusing `Sheet` — already a fixed, viewport-docked panel, not a floating one — removes the feedback loop entirely and better matches the wireframe's docked-right intent besides.

**Data access: direct client-side fetch to the Symfony API for real screens; static fixture modules for mock screens — no shared abstraction between them.**
The two are intentionally *not* unified behind one data-access interface (e.g. a repository pattern returning either a fetch or a fixture). A real screen's component calls `fetch()` against a documented endpoint and handles its documented error shape; a mock screen's component imports a plain TS object/array from `lib/fixtures/*.ts`. This makes each screen's real/mock status visible from its imports alone, at the cost of an explicit rewrite (not a config flip) when a mock screen gets a real endpoint later — acceptable since that rewrite will need to happen anyway once the real response shape exists.

**Project name is a real form field, submitted to the backend alongside `repositoryUrl` and `teamId`.**
As of this change, `RegisterProjectRequest` (`services/php`) does not yet have a `name` field — it's being added to the backend concurrently, outside this change. The frontend sends `name` in the `POST /api/projects` payload ahead of that landing. Symfony's serializer ignores unmapped request fields by default, so submitting `name` today is harmless (silently dropped) and starts working the moment the backend field ships — no frontend change needed when it lands. This is the same kind of forward/backward compatibility trade-off as the CORS limitation below: call it out, don't work around it.

**Team context is client-only state, no persistence decision made.**
No backend endpoint lists a user's teams or a team's members, and there's no auth. The active-team switcher and "teams the user belongs to" list are backed by fixture/local state for this change (see Open Questions) — team *registration* is real and returns a real `id`, but nothing here yet reads teams back from the server.

**Sidebar items with no screen (Despliegues, Equipo, Ajustes) render as disabled/non-navigating list items, not dead links.**
Avoids shipping a route that 404s or an empty shell page; keeps the wireframe's visual completeness (all five items always visible) without implying those sections work.

## Risks / Trade-offs

- **Real registration forms will visibly fail in a browser (CORS)** until the separate CORS change ships → mitigate by making the error state honest (show the actual fetch failure, don't fake success) and by not blocking this change on that one; call it out in the PR/demo so it isn't mistaken for a bug.
- **Fixture data can drift from the real future response shape**, making the eventual swap harder than expected → mitigate by shaping every fixture as the *documented* future contract where one exists (e.g. secrets fixtures shaped like `{ id, name, value }` matching `docs/podium-config/podium-yaml-guide.md`'s model) rather than whatever is visually convenient.
- **The backend's `name` field could land with different validation than assumed** (e.g. a max length, uniqueness per team) → the frontend does only basic required-field validation; a backend rejection still surfaces via the existing "Registration fails" error-handling path, so no frontend change is needed if backend validation is stricter than expected.

## Open Questions

- How a user acquires their first team/active-team context in the absence of auth (e.g. a `?teamId=` param, a `localStorage`-remembered id after registration, or always starting from an empty "create your team" state) is left to implementation — it doesn't change any spec requirement, since "register a team" and "switch active team" are both satisfied by any of these.
