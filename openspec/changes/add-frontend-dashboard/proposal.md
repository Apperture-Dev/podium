# Proposal

## Why

Hostium (product-facing name; "Podium" is the repo's internal codename) had no frontend — only two write-only HTTP endpoints and no way for a team to see their projects, register a project without calling the API by hand, or manage the secrets a deployed service needs. A set of low-fidelity wireframes and a visual PRD (`docs/PRD/01_PRD_visual.md`) already establish the intended information architecture and look, so this change turns that into a working Next.js app.

While this change was in flight, `services/php` grew real read endpoints (`GET /api/teams`, `GET /api/teams/{teamId}/projects`, `GET .../applications`), a `name` field on project registration, and JWT auth (Keycloak) on every route. The scope grew to match: this change now wires the project console to real data end-to-end instead of stopping at fixtures for everything but registration.

## What Changes

- Add a new Next.js application at `services/web`, using **shadcn/ui** for all general UI components, styled with the design tokens already defined in `docs/PRD/01_PRD_visual.md` (monochrome palette, `radius: 0`, Plus Jakarta Sans / Geist Mono, Phosphor Icons).
- Add a project console: a team-scoped project list, a "create project" form (project name + repository URL text fields), and a project detail page showing each project's `Application`s (service name, build/deploy state, version) — all real, backed by `GET /api/teams/{teamId}/projects` and `GET .../applications`.
- Add a team switcher/registration flow backed by the real `GET /api/teams` and `POST /api/teams` endpoints (no wireframe existed for the registration form; it's derived from the existing API and the project-list wireframe's team switcher). The switcher is a searchable shadcn combobox (Popover + Command) with a "Create team" shortcut, not a plain dropdown.
- Add a dev-only auth bridge (`app/api/auth/token`, a Next.js Route Handler) that authenticates as the seeded Keycloak `testuser` account server-side and hands the browser a JWT — necessary because every `/api/*` route now requires one, and doing the token exchange client-side would ship the OAuth client secret to the browser. Not a real login flow; see design.md.
- Add CORS to `services/php` (`nelmio/cors-bundle`, scoped to `^/api`, using the existing `CORS_ALLOW_ORIGIN` env default which already matches `localhost:*`) — without it none of the above works from an actual browser. This was originally scoped as a separate change; doing it here instead once real data made it a hard blocker for testing this change at all.
- Add a Secrets screen per project (create/list/delete NAME+VALUE credential pairs, masked by default with a reveal toggle), matching the real domain design documented in `docs/podium-config/podium-yaml-guide.md`.
- Add sidebar navigation matching the wireframes (Proyectos, Despliegues, Equipo, Secrets, Ajustes); Despliegues, Equipo, and Ajustes render as non-interactive/"próximamente" placeholders — no wireframes exist for them yet and they are out of scope for this change.
- Add a minimal scaffold for the agent entry point (the ✦ icon in the topbar): a shadcn `Sheet` for the docked right panel, with **Assistant UI**'s headless primitives (`Thread`/`Composer`/`Message`) for the chat itself — Assistant UI is reserved specifically for chat rendering, kept separate from the general shadcn/ui components. This is a low-priority, non-blocking task; its functional scope (general platform copilot vs. demo-rehearsal agent) is intentionally left open and not committed by this change.
- The Secrets screen still has no backing API (`services/php` has no Secrets endpoint at all — see `docs/podium-config/podium-yaml-guide.md` for the intended design), so it stays on static TypeScript fixture data. Everything else in the project console is real.

## Capabilities

### New Capabilities
- `web-dashboard/project-console`: the team-scoped project list, project/team registration, team switching, and project detail (all real, against `services/php`) — the core "Proyectos" experience from the wireframes, plus the sidebar/topbar shell (nav, team switcher, agent entry point) all screens share.
- `web-dashboard/secrets`: per-project Secrets management screen (create, view masked, reveal, delete NAME+VALUE credential pairs), matching the real Secret-storage design in `docs/podium-config/podium-yaml-guide.md` even though the backend endpoint doesn't exist yet — still fixture-backed.

### Modified Capabilities
None — `openspec/specs/` has no existing capabilities yet; this is the first change to populate it.

## Impact

- **New code**: `services/web` (new Next.js app), sibling to `services/php`.
- **New dependencies**: `shadcn/ui` (general UI components), `assistant-ui` (agent/chat components only).
- **Backend usage**: consumes `GET/POST /api/teams`, `GET /api/teams/{teamId}/projects`, `POST /api/projects`, `GET /api/teams/{teamId}/projects/{projectId}`, and `GET /api/teams/{teamId}/projects/{projectId}/applications`, all in `services/php`. Every route requires a JWT, obtained via the dev auth bridge described above.
- **Backend change**: `services/php` gains `nelmio/cors-bundle` (`composer require`, Flex-generated `config/packages/nelmio_cors.yaml` adjusted to apply to `^/api`) — the one piece of this change that touches the backend, needed to let a browser call it at all.
- **Design system dependency**: relies on `docs/PRD/01_PRD_visual.md` staying the source of truth for visual tokens; this change does not modify that document.
