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

/**
 * Admin credentials, used only by the registration Server Action to create
 * a user via Keycloak's Admin REST API — `admin-cli` lives in the `master`
 * realm, not `podium` (see docker/keycloak/podium-realm.json's comments).
 * Dev-only shortcut: the realm's default admin/admin. A real deployment
 * would use a narrower-scoped service account instead of the superuser.
 */
export const KEYCLOAK_ADMIN_USERNAME = process.env.KEYCLOAK_ADMIN_USERNAME ?? "admin";
export const KEYCLOAK_ADMIN_PASSWORD = process.env.KEYCLOAK_ADMIN_PASSWORD ?? "admin";
export const KEYCLOAK_ADMIN_TOKEN_URL = `${KEYCLOAK_URL}/realms/master/protocol/openid-connect/token`;
export const KEYCLOAK_ADMIN_USERS_URL = `${KEYCLOAK_URL}/admin/realms/${KEYCLOAK_REALM}/users`;
