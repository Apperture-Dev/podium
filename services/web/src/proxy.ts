import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

/**
 * `middleware.ts` is deprecated in Next.js 16, renamed to `proxy.ts` — same
 * behavior, new file/export name (see next/dist/docs § file-conventions/proxy).
 *
 * Gates every page behind a session cookie; `/login`, `/register`, and the
 * Next.js/static internals are excluded via the matcher below.
 */
export function proxy(request: NextRequest) {
  const hasSession = request.cookies.has("podium_jwt");

  if (!hasSession) {
    const loginUrl = new URL("/login", request.url);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  // /api excluded: those routes check the cookie themselves and return a
  // clean 401 JSON on a missing/expired session — a fetch() call redirected
  // to the /login HTML page instead would fail response.json() parsing.
  matcher: ["/((?!api|login|register|_next/static|_next/image|favicon.ico|.*\\.svg$).*)"],
};
