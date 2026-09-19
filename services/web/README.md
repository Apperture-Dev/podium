# Hostium — web dashboard

Next.js app for the Hostium dashboard ("Podium" is this repo's internal codename — see the root `CLAUDE.md`).

## Real vs. mock

Real, against `services/php`:

- Team list/registration, project list/registration, project detail (via its `Application`s).
- Auth: see below.

Still mock (static fixtures in `src/lib/fixtures/`): the Secrets screen — `services/php` has no Secrets endpoint yet (see `docs/podium-config/podium-yaml-guide.md` for the intended real design).

## Auth

Real login against Keycloak — `/login` is a username/password form (any user in the `podium` realm, e.g. the seeded `testuser`/`testuser`). The form action (`src/app/login/actions.ts`) does a password-grant exchange against Keycloak **server-side**, using the public `podium-web` client (no secret — see `services/php/docker/keycloak/podium-realm.json`), and stores the resulting JWT as an **httpOnly cookie** (`src/lib/auth/session.ts`). `src/proxy.ts` (the renamed `middleware.ts` — Next 16) redirects any request without that cookie to `/login`.

The browser never sees the JWT and never calls `services/php` directly: every `GET`/`POST` under `src/app/api/**` is a same-origin Route Handler that reads the cookie, attaches `Authorization: Bearer …`, and forwards to Symfony (`src/lib/auth/proxy.ts` for `GET`, per-route for `POST` where the backend also needs the authenticated `sub`, e.g. `creatorUserId` on team registration). This is also why `services/php`'s CORS setup (`nelmio_cors.yaml`) doesn't matter for this app — nothing here is a cross-origin request.

"Cerrar sesión" in the topbar clears the cookie (`src/app/logout/actions.ts`) and redirects to `/login`. Sessions last as long as the Keycloak access token (5 min by realm default) — there's no refresh-token rotation yet, so re-login is the way back in once it expires; fine for a hackathon, revisit if that TTL becomes an actual complaint.

Configure via env vars if your backend/Keycloak use different values than the root README's defaults — see `.env.example`:

```
KEYCLOAK_URL=http://localhost:8091
KEYCLOAK_REALM=podium
KEYCLOAK_CLIENT_ID=podium-web
SYMFONY_API_BASE_URL=http://localhost:8090
```

## Development

```bash
npm run dev
```

Follow the root `README.md`'s "Cómo levantar el backend" section to start `services/php`, seed the `Template` catalog, and load fixture data (`app:fixtures:load <sub>`) so there's something to see once you log in — the `<sub>` it asks for is exactly the `sub` claim of whichever Keycloak user you'll log in as here.
