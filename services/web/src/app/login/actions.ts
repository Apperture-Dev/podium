"use server";

import {
  KEYCLOAK_CLIENT_ID,
  KEYCLOAK_TOKEN_URL,
} from "@/lib/auth/config";
import { setSessionCookie } from "@/lib/auth/session";

export type LoginState = {
  error: string | null;
  success: boolean;
};

/**
 * Resource Owner Password Credentials grant against Keycloak, server-side
 * only — the browser never sees the client, the token endpoint, or the
 * token itself in transit. `podium-web` is a public client (no secret to
 * protect) with directAccessGrantsEnabled, same as the podium-api client
 * this whole backend has been tested against all session — see
 * services/php/docker/keycloak/podium-realm.json.
 */
export async function login(_prevState: LoginState, formData: FormData): Promise<LoginState> {
  const email = String(formData.get("email") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  if (!email || !password) {
    return { error: "Email y contraseña son obligatorios.", success: false };
  }

  let response: Response;
  try {
    response = await fetch(KEYCLOAK_TOKEN_URL, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        grant_type: "password",
        client_id: KEYCLOAK_CLIENT_ID,
        // The email itself is the Keycloak username (see register/actions.ts)
        // — Keycloak's grant field is still called `username` regardless of
        // what identifier it actually holds.
        username: email,
        password,
      }),
      cache: "no-store",
    });
  } catch {
    return {
      error: "No se pudo conectar con Keycloak. ¿Está levantado el docker compose?",
      success: false,
    };
  }

  if (!response.ok) {
    return { error: "Email o contraseña incorrectos.", success: false };
  }

  let accessToken: string;
  let expiresIn: number;
  try {
    const parsed = (await response.json()) as {
      access_token: string;
      expires_in: number;
    };
    accessToken = parsed.access_token;
    expiresIn = parsed.expires_in;
  } catch {
    return {
      error: "Keycloak respondió con algo que no es JSON válido.",
      success: false,
    };
  }

  await setSessionCookie(accessToken, expiresIn);

  // Not redirect(): TeamProvider/ProjectsProvider fetch once on mount in the
  // shared root layout, which a client-side router navigation (what
  // redirect() does under a Server Action) never remounts — they'd be
  // stuck showing the pre-login empty/error state. A full navigation forces
  // a fresh mount that picks up the now-set session cookie.
  return { error: null, success: true };
}
