import { NextResponse } from "next/server";
import { SYMFONY_API_BASE_URL } from "@/lib/auth/config";
import { getAccessToken } from "@/lib/auth/session";

/** Same-origin proxy to `POST /api/projects` — see app/api/teams/route.ts for why. */
export async function POST(request: Request) {
  const token = await getAccessToken();
  if (!token) {
    return NextResponse.json({ error: "No autenticado." }, { status: 401 });
  }

  const body = (await request.json()) as {
    name?: string;
    repositoryUrl?: string;
    teamId?: string;
  };

  const response = await fetch(`${SYMFONY_API_BASE_URL}/api/projects`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
    cache: "no-store",
  });

  const payload = await response.json().catch(() => null);
  return NextResponse.json(payload, { status: response.status });
}
