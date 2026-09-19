# Tasks

## 1. Project setup

- [x] 1.1 Scaffold a Next.js (App Router, TypeScript) app at `services/web` and verify `npm run dev` serves a page
- [x] 1.2 Install and configure shadcn/ui with the tokens from `docs/PRD/01_PRD_visual.md` (CSS variables for light/dark, `radius: 0`) and verify a sample shadcn `Button` renders with sharp corners and the documented palette
- [x] 1.3 Install `assistant-ui` as a dependency used only by agent-related components, and verify no non-agent file imports from it (e.g. a lint rule, or a manual grep check)
- [x] 1.4 Wire Plus Jakarta Sans (headings) and Geist Mono (body/UI/code) via `next/font`, and Phosphor Icons, and verify both render correctly on a test page

## 2. App shell

- [x] 2.1 Build the sidebar (Proyectos, Despliegues, Equipo, Secrets, Ajustes) matching the wireframe layout, and verify Proyectos and Secrets are real links while the other three are disabled/non-navigating per the "Sidebar navigation" requirement
- [x] 2.2 Build the topbar (team switcher, page title/breadcrumb slot, "Nuevo proyecto" CTA, ✦ agent entry button) and verify it appears on every page via the shared layout
- [x] 2.3 Wire the root layout (`app/layout.tsx`) combining sidebar + topbar and verify every route in this change renders inside the shell

## 3. Team context and registration

- [x] 3.1 Implement client-side active-team state (mechanism is an implementation choice per design.md's Open Question) and verify the selected team persists across navigation within a session
- [x] 3.2 Build the team registration form calling `POST /api/teams` with `{ name, creatorUserId }` and verify: successful submit sets the new team active, failed submit shows an inline error, per the "Register a new team" requirement

## 4. Project console

- [x] ~~4.1 Add `lib/fixtures/projects.ts`...~~ — superseded by 7.3 once `GET /api/teams/{teamId}/projects` landed; fixture deleted
- [x] 4.2 Build the Proyectos page (`/`) rendering the project list scoped to the active team, and verify switching team changes the rendered cards, per "View project list" / "Switch active team" (originally fixture-backed, now real — see section 7)
- [x] 4.3 Build the "Nuevo proyecto" form (`/projects/new`) with "Nombre del proyecto" and "Repositorio" text fields, calling `POST /api/projects` with `{ name, repositoryUrl, teamId }`, and verify: valid submit navigates to `/projects/[id]`, empty submit of either field is blocked client-side with a per-field message, a backend error renders inline without navigating away — per "Register a new project"
- [x] ~~4.4 Add `lib/fixtures/project-detail.ts`...~~ — superseded by 7.4; the real `Application` has no deployment-timeline/commit/branch/author fields, so that shape was dropped rather than kept as a permanent fixture (see design.md)
- [x] 4.5 Build the project detail page (`/projects/[id]`) rendering the project's identity and its services, per "View project detail" (originally fixture-backed, now real — see section 7)

## 5. Secrets

- [x] 5.1 Add `lib/fixtures/secrets.ts` shaped as `{ id, name, value }` per project, seeded with the 4 wireframe sample credentials (`URL_DB`, `API_KEY`, `JWT_SECRET`, `STRIPE_KEY`)
- [x] 5.2 Build the Secrets page (`/projects/[id]/secrets`) listing credentials masked by default with a per-row reveal toggle, and verify revealing shows the plain value per "View secrets for a project" / "Reveal a secret value"
- [x] 5.3 Build the "Nueva credencial" create form and verify: a new name+value pair is added to the list, and a name matching an existing credential is rejected with an inline message, per "Create a secret"
- [x] 5.4 Build the delete ("Eliminar") action per credential and verify removing one updates the rendered list, per "Delete a secret"

## 6. Agent entry point

- [x] 6.1 Wire a right-docked panel (shadcn `Sheet` for the shell, Assistant UI's `ThreadPrimitive`/`ComposerPrimitive`/`MessagePrimitive` for the chat) to the ✦ topbar button and verify: the panel opens/closes, is positionally stable (no reposition loop), shows the static "¿En qué puedo ayudarte?" welcome state, and makes no network/LLM call — functional scope is explicitly deferred per proposal.md

## 7. Real data integration

`services/php` grew real read endpoints, a `name` field on project registration, and mandatory JWT auth while this change was in flight. This section wires the project console to that real API instead of stopping at fixtures.

- [x] 7.1 Add `nelmio/cors-bundle` to `services/php` (`composer require`) and change the Flex-generated `config/packages/nelmio_cors.yaml`'s `paths` from `'^/': null` (disabled) to `'^/api': ~`, and verify a preflight `OPTIONS` request and a real `GET` both return `Access-Control-Allow-Origin` for `http://localhost:3000`
- [x] 7.2 Add a dev-only auth bridge: `app/api/auth/token/route.ts` (Next.js Route Handler) exchanging the seeded Keycloak `testuser` credentials for a JWT server-side, `lib/api/auth.ts` caching it client-side and exposing `getCurrentUserId()`, and `lib/api/client.ts` attaching `Authorization: Bearer` to every request with a retry-once-on-401, and verify a real request succeeds end-to-end from the browser
- [x] 7.3 Replace `TeamProvider`'s hardcoded teams with a real `GET /api/teams` fetch, and `ProjectsProvider`'s fixture list with `GET /api/teams/{teamId}/projects` per active team, and verify (via a real browser, not just `next build`) that switching teams shows each team's real, distinct project list
- [x] 7.4 Replace the project detail page's fixture lookup with `GET .../projects/{projectId}` + `GET .../applications`, rendering a "Servicios" card per `Application` (service name, state, version, pending-change indicator) instead of the wireframe's deployment-timeline cards, and verify against real seeded data
- [x] 7.5 Update `registerTeam`/`registerProject` callers to use the real authenticated user id (`getCurrentUserId()`) instead of a placeholder, and to fetch the created project by id before adding it to local state (the register response has no `hash`), and verify a real end-to-end project creation navigates to a detail page showing real data
- [x] 7.6 Rebuild the team switcher as a shadcn Combobox (Popover + Command) with search and a "Create team" entry, and verify — via a real browser — that selecting a team from it actually changes the active team (the first pass used `onSelect`, which Base UI's `Menu.Item` ignores; caught by asserting the project list changed, not just that the click didn't error)
- [x] 7.7 Update `services/web/README.md` to describe the real vs. mock boundary and the auth bridge, replacing the now-resolved "CORS will fail" caveat

## 8. Verification

- [x] 8.1 Manually check the sidebar/topbar/shell and all built pages against the wireframes at desktop width for structural fidelity
- [x] 8.2 Run `next build` and verify it completes with no type or build errors
- [x] 8.3 Add a short note in `services/web` documenting the mock/real boundary and how to run the backend so the app has data to show — superseded by 7.7 once CORS/auth landed and the note became "how to actually run this", not "why it won't work"
