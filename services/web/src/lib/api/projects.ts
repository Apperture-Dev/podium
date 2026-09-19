import { getJson, postJson } from "./client";

export type Project = {
  id: string;
  name: string;
  hash: string;
  repositoryUrl: string;
  teamId: string;
  createdAt: string;
};

export type RegisterProjectInput = {
  name: string;
  repositoryUrl: string;
  teamId: string;
};

export type RegisterProjectResponse = {
  id: string;
};

/** Calls the real `GET /api/teams/{teamId}/projects` endpoint. */
export function listProjects(teamId: string): Promise<Project[]> {
  return getJson<Project[]>(`/api/teams/${teamId}/projects`);
}

/** Calls the real `GET /api/teams/{teamId}/projects/{projectId}` endpoint. */
export function getProject(
  teamId: string,
  projectId: string,
): Promise<Project> {
  return getJson<Project>(`/api/teams/${teamId}/projects/${projectId}`);
}

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
