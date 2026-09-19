# Hostium — web dashboard

Next.js app for the Hostium dashboard ("Podium" is this repo's internal codename — see the root `CLAUDE.md`).

## Real vs. mock

Real, against `services/php`:

- Team list/registration, project list/registration, project detail (via its `Application`s).
- Auth: see below.

Still mock (static fixtures in `src/lib/fixtures/`): the Secrets screen — `services/php` has no Secrets endpoint yet (see `docs/podium-config/podium-yaml-guide.md` for the intended real design).

## Auth (dev-only bridge)

There's no accounts/login concept in Hostium (the hackathon plan explicitly rules it out), but `services/php`'s API now requires a JWT on every route. `src/app/api/auth/token/route.ts` is a Next.js Route Handler that authenticates as the seeded Keycloak `testuser` dev account (password grant) **server-side**, so the OAuth client secret never reaches the browser. The client calls that route, caches the token, and attaches it as `Authorization: Bearer …` to every request — see `src/lib/api/auth.ts` and `src/lib/api/client.ts`.

This is a development convenience, not a real auth flow (no session, no login UI, no refresh tokens). Replacing it with real auth is out of scope for this change.

Configure via env vars if your backend uses different values than the root README's defaults:

```
KEYCLOAK_BASE_URL=http://localhost:8091
KEYCLOAK_REALM=podium
KEYCLOAK_CLIENT_ID=podium-api
KEYCLOAK_CLIENT_SECRET=podium-dev-secret
KEYCLOAK_DEV_USERNAME=testuser
KEYCLOAK_DEV_PASSWORD=testuser
```

## Development

```bash
npm run dev
```

Set `NEXT_PUBLIC_API_BASE_URL` (defaults to `http://localhost:8090`) to point at wherever `services/php` is actually running. Follow the root `README.md`'s "Cómo levantar el backend" section to start it, seed the `Template` catalog, and load fixture data (`app:fixtures:load`) so there's something to see.
