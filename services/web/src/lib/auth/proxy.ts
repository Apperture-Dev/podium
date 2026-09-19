import "server-only";
import { NextResponse } from "next/server";
import { SYMFONY_API_BASE_URL } from "@/lib/auth/config";
import { getAccessToken } from "@/lib/auth/session";

/**
 * Shared by every `app/api/**\/route.ts` GET proxy: attach the session
 * cookie's JWT as a Bearer header and forward to Symfony, same-origin in,
 * cross-origin out — the browser only ever sees this app's own domain.
 */
export async function proxyGet(symfonyPath: string): Promise<NextResponse> {
  const token = await getAccessToken();
  if (!token) {
    return NextResponse.json({ error: "No autenticado." }, { status: 401 });
  }

  const response = await fetch(`${SYMFONY_API_BASE_URL}${symfonyPath}`, {
    headers: { Authorization: `Bearer ${token}` },
    cache: "no-store",
  });

  const payload = await response.json().catch(() => null);
  return NextResponse.json(payload, { status: response.status });
}
