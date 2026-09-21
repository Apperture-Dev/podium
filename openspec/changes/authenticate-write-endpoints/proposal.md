# Proposal

## Why

`POST /api/teams` and `POST /api/projects` are declared `PUBLIC_ACCESS` in
`services/php/config/packages/security.yaml:26-27`, and `POST /api/teams` takes the owner's
identity — `creatorUserId` — **from the request body**. An unauthenticated caller can therefore
create a team owned by any user id they choose, and then attach a project to any `teamId` they
know. That is not a gap at the edge: it makes the `requireMember()` authorization on every read
endpoint decorative, because the membership it checks can be forged by anyone with `curl`.

Now, because every later feature is built on top of "who owns this" — editing and deleting
projects, inviting members, per-project secrets — and because the fix **changes the HTTP request
contract**. Fixing it after the dashboard is reworked means reworking the dashboard twice.

## What Changes

- **BREAKING** — `POST /api/teams` no longer accepts `creatorUserId`. The request body becomes
  `{ name }` only, and the creator is the authenticated user (JWT `sub`), exactly as
  `ListTeamsController` already resolves the current user for `GET /api/teams`.
- **BREAKING** — `POST /api/teams` and `POST /api/projects` now require a valid Bearer token and
  answer `401` without one. Their two `PUBLIC_ACCESS` entries are removed from `access_control`,
  so they fall through to the existing `IS_AUTHENTICATED_FULLY` rule for `^/api`.
- `POST /api/projects` now rejects with `403` a caller who is not a member of the target team.
  Today it only checks that the team *exists*. `Project\Application\ApplicationService` already
  has the `requireMember()` helper this needs — `registerProject()` simply does not call it.
- The dashboard's BFF write routes forward the session JWT to Symfony. Today
  `services/web/src/app/api/projects/route.ts` reads the cookie only to check that one exists and
  then proxies the body **with no `Authorization` header at all**, and
  `services/web/src/app/api/teams/route.ts` injects `creatorUserId` instead of authenticating.
  Neither works once the endpoints are closed.
- Functional tests gain the ability to send a Bearer token on a POST: `FunctionalTestCase` today
  has `getJson($uri, $bearerToken)` but a `postJson($uri, $payload)` with no token parameter.

Not in scope: refresh-token rotation, the Keycloak master-realm registration shortcut, and team
invitations. They are separate concerns on the roadmap (`docs/roadmap/plan-continuidad-2026-q4.md`,
Fases 0 and 6) and none of them is needed to close this hole.

## Capabilities

### New Capabilities

- `platform-api/write-authorization`: who may call the API's write endpoints, and whose identity a
  write is recorded under. Covers authentication of `POST /api/teams` and `POST /api/projects`,
  derivation of the team creator from the authenticated principal rather than from client input,
  and the team-membership check required to register a project.

### Modified Capabilities

None. `openspec/specs/` is currently empty — the `add-frontend-dashboard` change was implemented
but never synced, so no capability spec exists yet to modify. The dashboard's observable behavior
does not change either: the "Register a new team" requirement it declared already states that the
creator is the current user; only the layer that establishes that fact moves, from the BFF to the
API.

## Impact

**API (`services/php`)** — `Team/Infrastructure/Http/RegisterTeamController.php` and its
`RegisterTeamRequest`; `Project/Infrastructure/Http/RegisterProjectController.php`;
`Project/Application/ApplicationService::registerProject()`;
`config/packages/security.yaml`. `Team\Application\ApplicationService::registerTeam()` keeps its
signature — the creator id still arrives as a string, just from the token instead of the payload.

**Dashboard (`services/web`)** — `src/app/api/teams/route.ts` and `src/app/api/projects/route.ts`.
The browser-facing client (`src/lib/api/teams.ts`) already sends only `{ name }`, so neither it
nor the forms change.

**Tests** — `tests/Functional/FunctionalTestCase.php`, `tests/Functional/Team/RegisterTeamTest.php`
and `tests/Functional/Project/RegisterProjectTest.php`, all of which currently POST without a
token and, for teams, with a `creatorUserId` in the payload.

**Consumers** — any caller outside this repo that posts `creatorUserId` breaks. The only known
caller is the dashboard BFF, updated here. The seeded dev fixtures
(`app:fixtures:load`) call the application service directly, not HTTP, and are unaffected.

**Docs** — the root `README.md` lists the available routes and should note that both writes now
require a Bearer token.
