import "server-only";
import { cookies } from "next/headers";

/**
 * The JWT itself is the cookie's value — httpOnly so client JS (and an
 * injected script) can never read it, only this server can. Short-lived by
 * Keycloak's realm default (5 min) with no refresh-token rotation yet; once
 * it expires the user just logs in again — acceptable for the hackathon,
 * revisit if session length becomes a real complaint.
 */
const SESSION_COOKIE = "podium_jwt";

export async function setSessionCookie(accessToken: string, expiresInSeconds: number): Promise<void> {
  const cookieStore = await cookies();
  cookieStore.set(SESSION_COOKIE, accessToken, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: expiresInSeconds,
  });
}

export async function clearSessionCookie(): Promise<void> {
  const cookieStore = await cookies();
  cookieStore.delete(SESSION_COOKIE);
}

export async function getAccessToken(): Promise<string | null> {
  const cookieStore = await cookies();
  return cookieStore.get(SESSION_COOKIE)?.value ?? null;
}

export type JwtClaims = {
  sub: string;
  name?: string;
  preferred_username?: string;
  [claim: string]: unknown;
};

/**
 * Decodes the payload without verifying the signature. Safe here because
 * the only source of this cookie is our own login Server Action, which got
 * the token straight from Keycloak over a server-to-server call — nothing
 * client-controlled ever reaches this cookie. Calls made *as* this token
 * against the Symfony API are verified there (KeycloakAccessTokenHandler),
 * which is the actual trust boundary.
 */
export function decodeJwtClaims(token: string): JwtClaims | null {
  const payload = token.split(".")[1];
  if (!payload) return null;

  try {
    const normalized = payload.replaceAll("-", "+").replaceAll("_", "/");
    const json = Buffer.from(normalized, "base64").toString("utf-8");
    return JSON.parse(json) as JwtClaims;
  } catch {
    return null;
  }
}

export async function getCurrentUser(): Promise<JwtClaims | null> {
  const token = await getAccessToken();
  if (!token) return null;
  return decodeJwtClaims(token);
}
