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

- [x] 4.1 Add `lib/fixtures/projects.ts` shaped as the documented future list-response, and verify it renders the 6 sample cards from the wireframe (name, description, domain, language, last-updated)
- [x] 4.2 Build the Proyectos page (`/`) rendering the fixture list scoped to the active team, and verify switching team changes the rendered cards, per "View project list" / "Switch active team"
- [x] 4.3 Build the "Nuevo proyecto" form (`/projects/new`) with "Nombre del proyecto" and "Repositorio" text fields, calling `POST /api/projects` with `{ name, repositoryUrl, teamId }` (the `name` field is landing on the backend concurrently — see design.md), and verify: valid submit navigates to `/projects/[id]`, empty submit of either field is blocked client-side with a per-field message, a backend error renders inline without navigating away — per "Register a new project"
- [x] 4.4 Add `lib/fixtures/project-detail.ts` shaped after the `Application` + `ApplicationHistoryLog` fields named in design.md (status, version, frameworks, updated time, deployment timeline entries with domain/branch/commit/author/time), and verify it covers at least one project with two deployments (matching the wireframe)
- [x] 4.5 Build the project detail page (`/projects/[id]`) rendering status, version, frameworks, updated time, and the deployment timeline from the fixture, per "View project detail"

## 5. Secrets

- [x] 5.1 Add `lib/fixtures/secrets.ts` shaped as `{ id, name, value }` per project, seeded with the 4 wireframe sample credentials (`URL_DB`, `API_KEY`, `JWT_SECRET`, `STRIPE_KEY`)
- [x] 5.2 Build the Secrets page (`/projects/[id]/secrets`) listing credentials masked by default with a per-row reveal toggle, and verify revealing shows the plain value per "View secrets for a project" / "Reveal a secret value"
- [x] 5.3 Build the "Nueva credencial" create form and verify: a new name+value pair is added to the list, and a name matching an existing credential is rejected with an inline message, per "Create a secret"
- [x] 5.4 Build the delete ("Eliminar") action per credential and verify removing one updates the rendered list, per "Delete a secret"

## 6. Agent entry point

- [x] 6.1 Wire a right-docked panel (shadcn `Sheet` for the shell, Assistant UI's `ThreadPrimitive`/`ComposerPrimitive`/`MessagePrimitive` for the chat) to the ✦ topbar button and verify: the panel opens/closes, is positionally stable (no reposition loop), shows the static "¿En qué puedo ayudarte?" welcome state, and makes no network/LLM call — functional scope is explicitly deferred per proposal.md

## 7. Verification

- [x] 7.1 Manually check the sidebar/topbar/shell and all built pages against the wireframes at desktop width for structural fidelity
- [x] 7.2 Run `next build` and verify it completes with no type or build errors
- [x] 7.3 Add a short note in `services/web` (README or top-level comment) documenting that the real registration forms will fail in-browser until the separate CORS change lands, so this isn't mistaken for a bug during review/demo
