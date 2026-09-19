# Design

## Context

See `proposal.md` — `Why` and `What Changes`. Relevant constraints:

- `services/php` now exposes: `GET/POST /api/teams`, `GET /api/teams/{teamId}/projects`, `POST /api/projects` (`{ name, repositoryUrl, teamId }`), `GET /api/teams/{teamId}/projects/{projectId}`, `GET /api/teams/{teamId}/projects/{projectId}/applications[/{serviceName}]`. `Project` has `{ id, name, hash, repositoryUrl, teamId }` — no domain/URL field; the real URL convention is `{hash}.apperture.dev` per the hackathon plan. `Application` has `{ serviceName, projectId, teamId, state, version, hasPendingSourceChange }` — no deployment timeline, commit, branch, author, or timestamp of any kind.
- Every `/api/*` route requires a JWT (Keycloak, realm `podium`, client `podium-api`). There's a seeded dev user (`testuser`/`testuser`) documented in the root README, used purely to unblock frontend development — this product has no accounts/login concept (hackathon plan, explicit).
- CORS is now configured (`nelmio/cors-bundle`, applied to `^/api`), added as part of this change once real data made it a hard blocker for testing anything in a browser.
- Secrets have no backend at all yet, but their intended design is documented in `docs/podium-config/podium-yaml-guide.md`: real values live as native Kubernetes Secrets per project namespace; `podium.yaml` only ever holds a `${SECRET_NAME}` reference.
- Visual tokens (colors, radius, type, icons) are fixed by `docs/PRD/01_PRD_visual.md`.
- Wireframes (Figma file `V32R1h3rGTE8kATd33DIXm`) fix the information architecture for Proyectos, Nuevo proyecto, Detalle de proyecto, and Secrets. No wireframe exists for Despliegues, Equipo, Ajustes, or team registration. The wireframes' project-detail "Despliegue de producción" deployment-history cards don't correspond to anything the real API returns — that section became a "Servicios" list of `Application`s instead (see Decisions).

## Goals / Non-Goals

**Goals:**
- Ship a Next.js app whose project console (teams, projects, project detail) works end-to-end against the real backend, with an honest UI — showing exactly the fields the API returns, not fabricating what the wireframe implied but the backend doesn't have.
- Keep Secrets' mock/real boundary legible: it's the one screen still on fixtures, and that should be obvious from its imports alone.

**Non-Goals:**
- No real login flow — the dev auth bridge (see Decisions) authenticates a fixed seeded user; there's no session, no login UI, no refresh-token handling, and no accounts concept in the product itself.
- No functional agent behavior — the ✦ entry point renders a static welcome state; no LLM call, tool contract, or conversation persistence.
- No new backend endpoints beyond CORS — Secrets still has none, and this change doesn't add one.
- No deployment-history UI — the real `Application` doesn't carry commit/branch/author/timestamp data, so the project detail page doesn't show any, rather than inventing it.

## Decisions

**Next.js App Router, `services/web`, TypeScript.**
Matches the shadcn/ui and Assistant UI ecosystem's default target and the repo's existing per-service folder convention (`services/php` sibling).

**Component libraries kept strictly separated: shadcn/ui for everything general-purpose, Assistant UI only for the agent panel's chat behavior.**
General UI (cards, forms, sidebar, badges, dialogs, the team-switcher combobox) is shadcn/ui; the ✦ panel's message list and composer are built from Assistant UI's headless primitives (`ThreadPrimitive`, `ComposerPrimitive`, `MessagePrimitive`) and nothing else in the app depends on Assistant UI.

The panel's *shell* (open/close state, the right-docked layout, backdrop) is shadcn's `Sheet`, not Assistant UI's own `AssistantModalPrimitive.Root/Trigger/Content`. The first implementation used `AssistantModalPrimitive`, whose positioning is a floating popover (anchored to the trigger via `floating-ui`, recalculated on every layout pass) — with a 600px-tall panel anchored to a small topbar button, this produced a continuous reposition loop (confirmed via a Playwright script polling `boundingClientRect` every 300ms and seeing the panel jump to a different position each check) rather than settling. `Sheet` — already fixed and viewport-docked, not floating — removes the feedback loop and better matches the wireframe's docked-right intent besides.

**Dev-only auth bridge: a Next.js Route Handler does the Keycloak password-grant token exchange server-side.**
The backend's OAuth client (`podium-api`) is confidential — its secret must never reach the browser. `src/app/api/auth/token/route.ts` runs the password grant (fixed `testuser` credentials) server-side using env vars, and returns only the resulting JWT to the client. The client (`lib/api/auth.ts`) caches it in memory, checks its `exp` claim, and `lib/api/client.ts` attaches it to every request and retries once on a 401. This is the one deliberate exception to "no BFF" from the original design: everything else still calls the Symfony API directly from client components.

**Team switcher is a shadcn Combobox (Popover + Command), not a plain dropdown.**
Adds search-by-typing and an inline "Create team" entry at the bottom of the list, requested after the first pass shipped a `DropdownMenu`-based switcher. `DropdownMenuItem` (Base UI's `Menu.Item`) uses `onClick`, not Radix's `onSelect` — the first combobox-less version used `onSelect` and silently did nothing on click; caught via an end-to-end Playwright check that asserted the project list actually changed after a team switch, not just that the click succeeded.

**Team/project/application data is fetched live; only Secrets stays on fixtures.**
`TeamProvider` fetches `GET /api/teams` on mount; `ProjectsProvider` fetches `GET /api/teams/{teamId}/projects` whenever the active team changes, and combines `GET .../projects/{projectId}` + `GET .../applications` for detail. `registerProject`/`registerTeam` still call the real `POST` endpoints; since `POST /api/projects` returns only `{ id }` (no `hash`), the form fetches the created project by id before adding it to local state and navigating, rather than fabricating a `hash` client-side. Secrets keeps its original fixture-only design (`lib/fixtures/secrets.ts`) since no backend exists for it.

**Project detail shows a "Servicios" list of real `Application`s, not the wireframe's deployment-history cards.**
The wireframe's per-deployment cards (subdomain, commit, branch, author, screenshot thumbnail) modeled data the real `Application` doesn't have. Rather than keep shipping that as a permanent fixture indefinitely (which would look "real" but drift from what actually exists), the detail page now renders exactly what `GET .../applications` returns: service name, state (Spanish-labeled), version, and a pending-source-change indicator.

**Sidebar items with no screen (Despliegues, Equipo, Ajustes) render as disabled/non-navigating list items, not dead links.**
Avoids shipping a route that 404s or an empty shell page; keeps the wireframe's visual completeness (all five items always visible) without implying those sections work.

## Risks / Trade-offs

- **The dev auth bridge is a standing shortcut** — anyone running this app authenticates as the same seeded `testuser`, with no way to act as a different user → acceptable for this change (no accounts concept in the product yet); flagged in `services/web/README.md` so it isn't mistaken for real auth.
- **Secrets fixture data can drift from the real future response shape** once that endpoint exists → mitigated by shaping the fixture as the *documented* future contract (`{ id, name, value }`, matching `docs/podium-config/podium-yaml-guide.md`) rather than whatever is visually convenient.
- **`services/php`'s CORS config is dev-oriented** (`CORS_ALLOW_ORIGIN` regex matches any `localhost`/`127.0.0.1` port) → fine for this change's scope; tightening it for a real deployment is a separate concern.

## Open Questions

None outstanding — the original open question (how a user acquires their first team) is resolved: `GET /api/teams` now returns the real list for the authenticated dev user.
