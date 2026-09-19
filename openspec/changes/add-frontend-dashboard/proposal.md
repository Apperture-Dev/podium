# Proposal

## Why

Hostium (product-facing name; "Podium" is the repo's internal codename) has no frontend today — only two write-only HTTP endpoints (`POST /api/teams`, `POST /api/projects`) and no way for a team to see their projects, register a project without calling the API by hand, or manage the secrets a deployed service needs. A set of low-fidelity wireframes and a visual PRD (`docs/PRD/01_PRD_visual.md`) already establish the intended information architecture and look, so this change turns that into a working Next.js app.

## What Changes

- Add a new Next.js application at `services/web`, using **shadcn/ui** for all general UI components, styled with the design tokens already defined in `docs/PRD/01_PRD_visual.md` (monochrome palette, `radius: 0`, Plus Jakarta Sans / Geist Mono, Phosphor Icons).
- Add a project console: a team-scoped project list, a "create project" form (project name + repository URL text fields) that registers a project against the real `POST /api/projects` endpoint, and a project detail page showing build/deploy status.
- Add a team registration flow that calls the real `POST /api/teams` endpoint (no wireframe existed for this; it's derived from the existing API and the project-list wireframe's team switcher).
- Add a Secrets screen per project (create/list/delete NAME+VALUE credential pairs, masked by default with a reveal toggle), matching the real domain design documented in `docs/podium-config/podium-yaml-guide.md`.
- Add sidebar navigation matching the wireframes (Proyectos, Despliegues, Equipo, Secrets, Ajustes); Despliegues, Equipo, and Ajustes render as non-interactive/"próximamente" placeholders — no wireframes exist for them yet and they are out of scope for this change.
- Add a minimal scaffold for the agent entry point (the ✦ icon in the topbar), built with the **Assistant UI** library (`AssistantModal` primitive) — the library reserved specifically for anything agent/chat-related, kept separate from the general shadcn/ui components. This is a low-priority, non-blocking task; its functional scope (general platform copilot vs. demo-rehearsal agent) is intentionally left open and not committed by this change.
- The project list, project detail, and secrets screens have no backing read/write API yet (`AppManager` has no HTTP read endpoints; Secrets has no endpoint at all), so they render from static TypeScript fixture data. Project and team registration are real, calling the existing endpoints directly from the browser — client-side calls to the Symfony API will be blocked by CORS until a separate change adds `nelmio/cors-bundle`; that is a known, accepted limitation of this change, not a defect to fix here.

## Capabilities

### New Capabilities
- `web-dashboard/project-console`: the team-scoped project list, project registration (real), team registration (real), and project detail/build-deploy-timeline view (mock data) — the core "Proyectos" experience from the wireframes, plus the sidebar/topbar shell (nav, team switcher, agent entry point) all screens share.
- `web-dashboard/secrets`: per-project Secrets management screen (create, view masked, reveal, delete NAME+VALUE credential pairs), matching the real Secret-storage design in `docs/podium-config/podium-yaml-guide.md` even though the backend endpoint doesn't exist yet.

### Modified Capabilities
None — `openspec/specs/` has no existing capabilities yet; this is the first change to populate it.

## Impact

- **New code**: `services/web` (new Next.js app), sibling to `services/php`.
- **New dependencies**: `shadcn/ui` (general UI components), `assistant-ui` (agent/chat components only).
- **Backend (read-only usage, no backend changes in this change)**: consumes `POST /api/teams` (`{ name, creatorUserId }` → `{ id }`) and `POST /api/projects` (`{ repositoryUrl, teamId }` → `{ id }`), both in `services/php`. A `name` field is being added to `POST /api/projects` concurrently, outside this change; the frontend sends it ahead of that landing (harmless no-op today, per design.md). These calls will not work from a browser until a separate CORS change lands on the Symfony side — out of scope here.
- **No changes** to `services/php` application code, the domain model, or any existing OpenSpec capability (there are none yet).
- **Design system dependency**: relies on `docs/PRD/01_PRD_visual.md` staying the source of truth for visual tokens; this change does not modify that document.
