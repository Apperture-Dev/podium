import { NextResponse } from "next/server";
import { SYMFONY_API_BASE_URL } from "@/lib/auth/config";
import { getAccessToken } from "@/lib/auth/session";
import { proxyGet } from "@/lib/auth/proxy";

/** Same-origin proxy to `GET /api/teams` — see lib/auth/proxy.ts. */
export async function GET() {
  return proxyGet("/api/teams");
}

/**
 * Same-origin proxy to `POST /api/teams` on the Symfony backend.
 *
 * The session JWT is forwarded and the API derives the team's creator from it,
 * so this handler no longer asserts who the caller is — it just proxies. The
 * 401 below is a shortcut for a request with no session at all; the API rejects
 * an invalid token on its own.
 */
export async function POST(request: Request) {
  const token = await getAccessToken();
  if (!token) {
    return NextResponse.json({ error: "No autenticado." }, { status: 401 });
  }

  let body: { name?: string };
  try {
    body = (await request.json()) as { name?: string };
  } catch {
    return NextResponse.json(
      { error: "El cuerpo de la petición no es JSON válido." },
      { status: 400 },
    );
  }

  let response: Response;
  try {
    response = await fetch(`${SYMFONY_API_BASE_URL}/api/teams`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ name: body.name }),
      cache: "no-store",
    });
  } catch {
    return NextResponse.json(
      { error: "No se pudo contactar con la API." },
      { status: 502 },
    );
  }

  const payload = await response.json().catch(() => null);
  return NextResponse.json(payload, { status: response.status });
}
