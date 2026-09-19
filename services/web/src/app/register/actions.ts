"use server";

import {
  KEYCLOAK_ADMIN_PASSWORD,
  KEYCLOAK_ADMIN_TOKEN_URL,
  KEYCLOAK_ADMIN_USERNAME,
  KEYCLOAK_ADMIN_USERS_URL,
  KEYCLOAK_CLIENT_ID,
  KEYCLOAK_TOKEN_URL,
} from "@/lib/auth/config";
import { setSessionCookie } from "@/lib/auth/session";

export type RegisterState = {
  error: string | null;
  success: boolean;
};

/** `admin-cli` in the `master` realm — see the comment on the admin config constants. */
async function getAdminToken(): Promise<string | null> {
  const response = await fetch(KEYCLOAK_ADMIN_TOKEN_URL, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      grant_type: "password",
      client_id: "admin-cli",
      username: KEYCLOAK_ADMIN_USERNAME,
      password: KEYCLOAK_ADMIN_PASSWORD,
    }),
    cache: "no-store",
  });
  if (!response.ok) return null;
  const { access_token: accessToken } = (await response.json()) as { access_token: string };
  return accessToken;
}

/** Cheap format check — no verification link, no confirmation email, just shape. */
function looksLikeAnEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

/**
 * Creates the user via Keycloak's Admin REST API (firstName/lastName are
 * real Keycloak user fields, not custom attributes — they come back as the
 * JWT's `given_name`/`family_name` claims once the user logs in), then logs
 * them in immediately with the same credentials so registration doesn't
 * dead-end at a second form.
 *
 * The email doubles as the Keycloak username — one field, one identifier,
 * no separate "choose a username" step. It's taken at face value (format
 * checked, never actually verified by sending mail — there's no mail
 * sending in this app at all).
 */
export async function register(_prevState: RegisterState, formData: FormData): Promise<RegisterState> {
  const firstName = String(formData.get("firstName") ?? "").trim();
  const lastName = String(formData.get("lastName") ?? "").trim();
  const email = String(formData.get("email") ?? "").trim();
  const password = String(formData.get("password") ?? "");
  const confirmPassword = String(formData.get("confirmPassword") ?? "");

  if (!firstName || !lastName || !email || !password) {
    return { error: "Todos los campos son obligatorios.", success: false };
  }
  if (!looksLikeAnEmail(email)) {
    return { error: "Introduce un email válido.", success: false };
  }
  if (password !== confirmPassword) {
    return { error: "Las contraseñas no coinciden.", success: false };
  }

  const adminToken = await getAdminToken();
  if (!adminToken) {
    return {
      error: "No se pudo conectar con Keycloak. ¿Está levantado el docker compose?",
      success: false,
    };
  }

  let createResponse: Response;
  try {
    createResponse = await fetch(KEYCLOAK_ADMIN_USERS_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${adminToken}`,
      },
      body: JSON.stringify({
        username: email,
        email,
        firstName,
        lastName,
        enabled: true,
        // Not a lie by omission the usual way: there's no verification
        // email being skipped, because this app never sends mail at all.
        // Keycloak 26's User Profile validation also requires this flag
        // (or an actual verify-email flow) before password-grant login
        // works — without it, login fails afterward with
        // "Account is not fully set up" even though nothing in the user
        // record looks like a pending required action.
        emailVerified: true,
        credentials: [{ type: "password", value: password, temporary: false }],
      }),
      cache: "no-store",
    });
  } catch {
    return { error: "No se pudo conectar con Keycloak.", success: false };
  }

  if (createResponse.status === 409) {
    return { error: "Ya existe una cuenta con ese email.", success: false };
  }
  if (!createResponse.ok) {
    return { error: "No se pudo crear el usuario.", success: false };
  }

  const tokenResponse = await fetch(KEYCLOAK_TOKEN_URL, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      grant_type: "password",
      client_id: KEYCLOAK_CLIENT_ID,
      username: email,
      password,
    }),
    cache: "no-store",
  });

  if (!tokenResponse.ok) {
    return {
      error: "Usuario creado, pero no se pudo iniciar sesión automáticamente. Entra manualmente.",
      success: false,
    };
  }

  const { access_token: accessToken, expires_in: expiresIn } = (await tokenResponse.json()) as {
    access_token: string;
    expires_in: number;
  };
  await setSessionCookie(accessToken, expiresIn);

  return { error: null, success: true };
}
