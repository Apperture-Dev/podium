import { NextResponse } from "next/server";
import { getAccessToken, decodeJwtClaims } from "@/lib/auth/session";

/** The claims of the currently logged-in user — the browser gets these, never the JWT itself. */
export async function GET() {
  const token = await getAccessToken();
  if (!token) {
    return NextResponse.json({ error: "No autenticado." }, { status: 401 });
  }

  const claims = decodeJwtClaims(token);
  if (!claims) {
    return NextResponse.json({ error: "Token inválido." }, { status: 401 });
  }

  return NextResponse.json({
    name: (claims.name as string | undefined) ?? null,
    givenName: (claims.given_name as string | undefined) ?? null,
    familyName: (claims.family_name as string | undefined) ?? null,
    preferredUsername: (claims.preferred_username as string | undefined) ?? null,
  });
}
