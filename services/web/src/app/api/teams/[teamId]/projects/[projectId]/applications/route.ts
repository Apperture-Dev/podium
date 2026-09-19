import { proxyGet } from "@/lib/auth/proxy";

/** Same-origin proxy to `GET /api/teams/{teamId}/projects/{projectId}/applications`. */
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ teamId: string; projectId: string }> },
) {
  const { teamId, projectId } = await params;
  return proxyGet(`/api/teams/${teamId}/projects/${projectId}/applications`);
}
