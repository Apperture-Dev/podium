# Tasks

Ordered to follow the two-merge rollout in `design.md` — Migration Plan. Group 1 gates
everything: it settles the one assumption the rollout rests on. Groups 3 and 4 ship together as
the second merge.

## 1. Settle the assumption and prepare the harness

- [x] 1.1 Add an optional bearer-token parameter to `postJson()` in
      `services/php/tests/Functional/FunctionalTestCase.php`, mirroring the existing
      `getJson(string $uri, ?string $bearerToken = null)`; verify by making one existing POST test
      pass a token and still pass
- [x] 1.2 Write a functional test that posts a team registration carrying an extra
      `creatorUserId` field that the request object does not declare, and assert the request still
      succeeds — this is the "unmapped attributes are ignored" assumption the rollout depends on.
      Verify with `vendor/bin/phpunit --filter RegisterTeam`. **If it fails**, stop and take the
      fallback in `design.md` — Risks (keep `creatorUserId` declared but unused for one release)
      before continuing

## 2. First merge — the dashboard forwards the token

- [x] 2.1 In `services/web/src/app/api/teams/route.ts`, send `Authorization: Bearer <token>` on the
      upstream POST, keeping `creatorUserId` in the body for now; verify by creating a team from
      the running dashboard against the current API and seeing it appear in the project list
- [x] 2.2 In `services/web/src/app/api/projects/route.ts`, send `Authorization: Bearer <token>` on
      the upstream POST — today it reads the cookie only to check one exists and forwards the body
      with no auth header; verify by creating a project from the dashboard and landing on its
      detail page
- [x] 2.3 Run `npm run lint` and `npx tsc --noEmit` in `services/web` and verify both are clean,
      then merge and let this deploy on its own before starting group 3

## 3. Second merge, API — close the endpoints

- [x] 3.1 Write the failing functional tests for the "Write endpoints require an authenticated
      caller" requirement: a team registration and a project registration with no `Authorization`
      header each answer `401` and create nothing; verify they fail for the right reason (today
      they return `201`)
- [x] 3.2 Write the failing functional tests for "A team's creator is the authenticated caller":
      an authenticated registration makes the caller the team's only member, the created team
      appears in that caller's `GET /api/teams`, and a creator identity supplied in the body does
      not become the owner
- [x] 3.3 Write the failing functional tests for "Registering a project requires membership of the
      target team": a member gets `201`, a non-member gets `403`, an unknown team gets `404`
- [x] 3.4 Remove `creatorUserId` from `RegisterTeamRequest` and take the creator from
      `#[CurrentUser] UserInterface $user` in `RegisterTeamController`, following
      `ListTeamsController`; verify the tests from 3.2 pass
- [x] 3.5 Add `string $requestingUserId` to
      `Project\Application\ApplicationService::registerProject()` and replace the bare
      `teams->get()` with the existing `requireMember()` helper; verify with
      `vendor/bin/phpunit tests/Functional/Project`
- [x] 3.6 In `RegisterProjectController`, take the caller from `#[CurrentUser]` and add
      `catch (AccessDeniedException)` mapping to `403` **above** the existing
      `catch (RuntimeException)` mapping to `404` — it is a subclass, so the wrong order silently
      turns the forbidden case into "team not found"; verify the `403` and `404` tests from 3.3
      both pass and assert those exact codes
- [x] 3.7 Delete the two `PUBLIC_ACCESS` entries for `^/api/teams$` and `^/api/projects$` from
      `services/php/config/packages/security.yaml` so both fall through to the existing
      `IS_AUTHENTICATED_FULLY` rule, and update the stale comment above them; verify the `401`
      tests from 3.1 pass
- [x] 3.8 Update the OpenAPI attributes on both controllers — drop `creatorUserId` from the team
      request model, document the `401` on both and the `403` on project registration; verify at
      `http://localhost:8090/api/doc` that the team request body now shows only `name`
- [x] 3.9 Run the whole suite with `docker compose exec frankenphp vendor/bin/phpunit` and verify
      every pre-existing test still passes, in particular the ones that used to post without a
      token

## 4. Second merge, dashboard — stop asserting identity

- [x] 4.1 Remove `creatorUserId` and the `decodeJwtClaims` call from
      `services/web/src/app/api/teams/route.ts`, leaving it a plain authenticated proxy; verify by
      creating a team end-to-end from the browser against the closed API
- [x] 4.2 Verify the unauthenticated path end-to-end: with the session cookie cleared, a write
      through the BFF returns a JSON `401` rather than an HTML redirect (`src/proxy.ts` already
      excludes `/api` from the redirect matcher, so this should hold — confirm it does)

## 5. Close out

- [x] 5.1 Update the route list in the root `README.md` to state that both write endpoints now
      require a Bearer token and that team registration takes only `name`
- [x] 5.2 Verify the hole is actually closed from outside the app: against a running stack,
      `curl -i -X POST localhost:8090/api/teams -H 'Content-Type: application/json' -d '{"name":"x","creatorUserId":"someone-else"}'`
      returns `401`, and the same call with a valid token creates a team owned by the token's
      subject and by nobody else
