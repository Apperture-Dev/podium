# Hostium — web dashboard

Next.js app for the Hostium dashboard ("Podium" is this repo's internal codename — see the root `CLAUDE.md`).

## Known limitation: CORS

`services/php` has no CORS configuration yet. The team/project registration forms
in this app call `POST /api/teams` and `POST /api/projects` directly from the
browser — those requests will fail with a CORS error until a separate change
adds `nelmio/cors-bundle` (or equivalent) to the Symfony app. This is expected,
not a bug in this app: see `design.md` in the `add-frontend-dashboard` OpenSpec
change for the reasoning.

Everything else in this app (project list, project detail, secrets) renders
from static fixture data in `src/lib/fixtures/` — there's no backend to fail
against for those screens yet.

## Development

```bash
npm run dev
```

Set `NEXT_PUBLIC_API_BASE_URL` (defaults to `http://localhost:8000`) to point
at wherever `services/php` is actually running.
