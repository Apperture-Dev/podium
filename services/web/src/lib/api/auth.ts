let cachedToken: string | null = null;
let cachedExpiresAt = 0;

function decodeClaims(jwt: string): { exp?: number; sub?: string } {
  try {
    return JSON.parse(atob(jwt.split(".")[1]));
  } catch {
    return {};
  }
}

/** Fetches (and caches) a dev JWT via our own server route — see app/api/auth/token/route.ts. */
export async function getAccessToken(forceRefresh = false): Promise<string> {
  const now = Date.now();
  if (!forceRefresh && cachedToken && now < cachedExpiresAt - 5_000) {
    return cachedToken;
  }

  const response = await fetch("/api/auth/token", { cache: "no-store" });
  if (!response.ok) {
    throw new Error("No se pudo autenticar contra el backend.");
  }
  const { accessToken } = (await response.json()) as { accessToken: string };
  cachedToken = accessToken;
  cachedExpiresAt = (decodeClaims(accessToken).exp ?? 0) * 1000;
  return accessToken;
}

/** The `sub` claim of the current dev JWT — the userId the domain expects (see route.ts). */
export async function getCurrentUserId(): Promise<string> {
  const token = await getAccessToken();
  const { sub } = decodeClaims(token);
  if (!sub) throw new Error("El token no contiene un `sub`.");
  return sub;
}
