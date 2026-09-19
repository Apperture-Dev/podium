/**
 * Server-only — never imported from a "use client" file. The browser never
 * talks to Keycloak or to the Symfony API directly; it only ever calls this
 * Next.js app's own `/api/*` routes, which read the session cookie and
 * proxy through with the Bearer token attached (see lib/auth/session.ts and
 * app/api/*\/route.ts). That sidesteps the CORS gap noted in design.md
 * without touching the Symfony side.
 */
export const KEYCLOAK_URL = process.env.KEYCLOAK_URL ?? "http://localhost:8091";
export const KEYCLOAK_REALM = process.env.KEYCLOAK_REALM ?? "podium";
export const KEYCLOAK_CLIENT_ID = process.env.KEYCLOAK_CLIENT_ID ?? "podium-web";
export const SYMFONY_API_BASE_URL =
  process.env.SYMFONY_API_BASE_URL ?? "http://localhost:8090";

export const KEYCLOAK_TOKEN_URL = `${KEYCLOAK_URL}/realms/${KEYCLOAK_REALM}/protocol/openid-connect/token`;
