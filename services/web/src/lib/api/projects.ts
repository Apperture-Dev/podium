import { postJson } from "./client";

export type RegisterProjectInput = {
  name: string;
  repositoryUrl: string;
  teamId: string;
};

export type RegisterProjectResponse = {
  id: string;
};

/**
 * Calls the real `POST /api/projects` endpoint. `name` is sent ahead of the
 * backend field landing (see design.md "Project name is a real form field") —
 * today the Symfony serializer silently drops it as an unmapped attribute.
 */
export function registerProject(
  input: RegisterProjectInput,
): Promise<RegisterProjectResponse> {
  return postJson<RegisterProjectResponse>("/api/projects", input);
}
