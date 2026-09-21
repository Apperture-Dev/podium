# Design

## Context

See `proposal.md` — Why. The constraints that shape the approach:

- The API already resolves the caller the way this change needs: `GET /api/teams` takes
  `#[CurrentUser] UserInterface $user` and passes `$user->getUserIdentifier()` (the JWT `sub`)
  into the application service. The firewall over `^/api` is `stateless: true` with an
  `access_token` handler, so nothing new is needed to authenticate — only to stop exempting two
  routes.
- `Project\Application\ApplicationService` already has `requireMember()`, used by
  `listProjectsForTeam()` and `getProject()`. `registerProject()` calls `teams->get()` instead,
  which only proves the team exists.
- ⚠️ `App\Shared\Domain\Exception\AccessDeniedException` **extends `RuntimeException`**, and
  `RegisterProjectController` already has a single `catch (RuntimeException)` mapped to `404`.
- There is no global exception listener: all four read controllers map exceptions to status codes
  with their own `catch` blocks.
- `services/php` and `services/web` are separate images, built by separate CI jobs gated on
  `changes:` paths, each committing its own digest into `deploy/overlays/prod`. A single commit
  touching both produces two parallel jobs with **no ordering guarantee** between them.
- The browser-facing client already posts only `{ name }` for a team; it is the BFF route handler
  that adds `creatorUserId`.

## Goals / Non-Goals

**Goals:**

- Establish identity at the API boundary, so the guarantee holds for every caller — not only for
  requests that happen to arrive through the dashboard's BFF.
- Reach the closed state without a window in which creating a team or a project is broken in
  production.
- Reuse the patterns already in the codebase rather than introducing a second way to do the same
  thing.

**Non-Goals:**

- Changing how tokens are obtained, validated or refreshed.
- Revisiting `403`-versus-`404` semantics on the four existing read endpoints.
- Any change to the `Team` or `Project` aggregates. This is an authorization and transport
  concern; no domain rule changes.

## Decisions

**Resolve the caller with `#[CurrentUser]` in the controller, not with a security service injected
into the application service.**
It is the pattern `ListTeamsController` already uses, it keeps the application services taking a
plain `string $requestingUserId` exactly as `listProjectsForTeam()` and `getProject()` do, and it
leaves the domain and application layers unaware that HTTP or JWTs exist. The alternative —
injecting `Security` into the application service — would put a framework dependency in the layer
the repo deliberately keeps clean.

**`Team\Application\ApplicationService::registerTeam(string $name, string $creatorUserId)` keeps
its signature; `registerProject()` gains `string $requestingUserId`.**
The creator still arrives as a string, just from the token instead of the payload, so the service
and the aggregate are untouched. `registerProject()` needs the new parameter because it must now
call `requireMember()`, matching the shape of its sibling read methods.

**Drop `creatorUserId` from the team request payload and rely on unmapped attributes being
ignored.**
The payload mapper silently drops attributes the request object does not declare — behavior this
repo has already observed and written down, when `name` was being sent before
`RegisterProjectRequest` declared it. Keeping that tolerance is what makes the two-step rollout
below possible. The alternative, rejecting unknown fields, is stricter but buys no security
(the value is no longer read by anything) and would force the API and the dashboard to deploy
atomically, which they cannot. This assumption is verified by a test before rollout, not assumed
— see Risks.

**Catch `AccessDeniedException` before `RuntimeException` in `RegisterProjectController`.**
Because it is a subclass, a new `requireMember()` failure would otherwise fall into the existing
`catch (RuntimeException)` and surface as `404 Team not found` — a wrong status and a misleading
message, with nothing failing loudly to reveal it. The four read controllers already order their
catches this way; this one just has to match.

**A non-member gets `403`, not `404`.**
Consistent with every existing read endpoint. It does leak the existence of a team id to an
authenticated user who guesses a UUID — accepted here because changing it would mean changing all
five endpoints at once, which is a separate decision (see Open Questions).

**The BFF forwards the session token instead of asserting identity.**
`src/app/api/teams/route.ts` stops injecting `creatorUserId` and starts sending
`Authorization: Bearer`; `src/app/api/projects/route.ts` starts sending it too — it currently
reads the cookie only to check that one exists, then proxies the body with no `Authorization`
header at all. The BFF stays a proxy; it is no longer the component that decides who you are.

**Extend `postJson()` with an optional bearer token, mirroring `getJson()`.**
`FunctionalTestCase` already has `getJson(string $uri, ?string $bearerToken = null)`, and the test
token handler treats the token value itself as the user id. Adding the same optional parameter to
`postJson()` keeps the two helpers symmetric and lets every new scenario be written as a real HTTP
test.

## Risks / Trade-offs

- **A deploy window where the dashboard cannot create teams or projects** — if the API closes the
  endpoints before the dashboard forwards a token, both actions return `401` in production →
  mitigated by the two-merge rollout in Migration Plan, which makes each side independently
  forward-compatible.
- **The unmapped-attribute assumption could be wrong on this Symfony version**, which would break
  step 1 of that rollout → mitigated by making the spec scenario "A creator identity in the body
  is not honored" a real functional test that runs *before* the API is merged. If the mapper turns
  out to reject unknown attributes, the fallback is to keep `creatorUserId` declared on the
  request object but unused for one release, then remove it.
- **Turning a `403` into a `404` through exception inheritance** — covered by the catch-order
  decision above and by a functional test that asserts `403` specifically, not merely "not 201".
- **Any unknown external caller posting `creatorUserId` breaks** → accepted. The API has been
  public for two days, the only known caller is the dashboard, and leaving a forgeable ownership
  field in place is strictly worse.
- **Membership is creator-only today** (`Team` has no `addMember()`), so in practice the new
  membership check on project registration only ever admits the team's creator. That is correct
  behavior, not a limitation of this change, and it stops being restrictive when invitations land.

## Migration Plan

Two merges, because the two services cannot deploy atomically:

1. **Dashboard first.** Both BFF write routes send `Authorization: Bearer`; the teams route keeps
   sending `creatorUserId`. This works against the API as it is today (the header is simply
   ignored on a public route) and against the API as it will be.
2. **API, then dashboard cleanup.** Remove the two `PUBLIC_ACCESS` entries, derive the creator
   from the token, add the membership check — and in the same merge, drop the now-dead
   `creatorUserId` from the BFF request body.

Rollback: re-adding the two `access_control` lines restores the previous behavior without a
dashboard rollback, since a dashboard that sends a token still works against a public endpoint.
Keep that in mind rather than reverting both services.

## Open Questions

- Should a caller who is not a member of a team receive `404` instead of `403` across the whole
  API, to avoid confirming that a team id exists? Deferrable: it is a consistency decision over
  five endpoints, it does not change this change's specs, approach or tasks, and today's `403` is
  what the rest of the API already does.
