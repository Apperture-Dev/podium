import { proxyGet } from "@/lib/auth/proxy";

/** Same-origin proxy to `GET /api/teams/{teamId}/projects`. */
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ teamId: string }> },
) {
  const { teamId } = await params;
  return proxyGet(`/api/teams/${teamId}/projects`);
}
