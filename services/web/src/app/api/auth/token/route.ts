import { NextResponse } from "next/server";

/**
 * Dev-only token bridge: exchanges a fixed Keycloak dev user for a JWT,
 * server-side, so the OAuth client secret never reaches the browser.
 *
 * There is no login/accounts concept in this product (see the hackathon
 * plan's explicit "no accounts" decision) — the backend's API now requires
 * a JWT on every route regardless, so this route silently authenticates as
 * the seeded `testuser` dev account documented in the root README. This is
 * a development bridge, not a real auth flow (no session, no refresh
 * tokens, no login UI) — replacing it is out of scope for this change.
 */

const KEYCLOAK_BASE_URL = process.env.KEYCLOAK_BASE_URL ?? "http://localhost:8091";
const KEYCLOAK_REALM = process.env.KEYCLOAK_REALM ?? "podium";
const KEYCLOAK_CLIENT_ID = process.env.KEYCLOAK_CLIENT_ID ?? "podium-api";
const KEYCLOAK_CLIENT_SECRET = process.env.KEYCLOAK_CLIENT_SECRET ?? "podium-dev-secret";
const KEYCLOAK_DEV_USERNAME = process.env.KEYCLOAK_DEV_USERNAME ?? "testuser";
const KEYCLOAK_DEV_PASSWORD = process.env.KEYCLOAK_DEV_PASSWORD ?? "testuser";

export async function GET() {
  const response = await fetch(
    `${KEYCLOAK_BASE_URL}/realms/${KEYCLOAK_REALM}/protocol/openid-connect/token`,
    {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        grant_type: "password",
        client_id: KEYCLOAK_CLIENT_ID,
        client_secret: KEYCLOAK_CLIENT_SECRET,
        username: KEYCLOAK_DEV_USERNAME,
        password: KEYCLOAK_DEV_PASSWORD,
      }),
      cache: "no-store",
    },
  );

  if (!response.ok) {
    return NextResponse.json(
      { error: "No se pudo obtener un token de Keycloak." },
      { status: 502 },
    );
  }

  const payload = (await response.json()) as { access_token: string };
  return NextResponse.json({ accessToken: payload.access_token });
}
