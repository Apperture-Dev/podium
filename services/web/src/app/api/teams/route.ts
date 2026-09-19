import { NextResponse } from "next/server";
import { SYMFONY_API_BASE_URL } from "@/lib/auth/config";
import { getAccessToken, decodeJwtClaims } from "@/lib/auth/session";
import { proxyGet } from "@/lib/auth/proxy";

/** Same-origin proxy to `GET /api/teams` — see lib/auth/proxy.ts. */
export async function GET() {
  return proxyGet("/api/teams");
}

/**
 * Same-origin proxy to `POST /api/teams` on the Symfony backend. `creatorUserId`
 * is taken from the session cookie's `sub` claim here, not from whatever the
 * client sent, since this is the one place that actually knows who's logged in.
 */
export async function POST(request: Request) {
  const token = await getAccessToken();
  if (!token) {
    return NextResponse.json({ error: "No autenticado." }, { status: 401 });
  }

  const claims = decodeJwtClaims(token);
  if (!claims?.sub) {
    return NextResponse.json({ error: "Token inválido." }, { status: 401 });
  }

  const body = (await request.json()) as { name?: string };

  const response = await fetch(`${SYMFONY_API_BASE_URL}/api/teams`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name: body.name, creatorUserId: claims.sub }),
    cache: "no-store",
  });

  const payload = await response.json().catch(() => null);
  return NextResponse.json(payload, { status: response.status });
}
