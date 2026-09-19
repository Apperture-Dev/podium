import { getJson } from "./client";

export type ApplicationState =
  | "Created"
  | "Building"
  | "Built"
  | "Deploying"
  | "Deployed"
  | "BuildFailed"
  | "DeployFailed";

export type Application = {
  serviceName: string;
  projectId: string;
  teamId: string;
  framework: string;
  createdAt: string;
  state: ApplicationState;
  version: string;
  hasPendingSourceChange: boolean;
};

/** Calls the real `GET /api/teams/{teamId}/projects/{projectId}/applications` endpoint. */
export function listApplications(
  teamId: string,
  projectId: string,
): Promise<Application[]> {
  return getJson<Application[]>(
    `/api/teams/${teamId}/projects/${projectId}/applications`,
  );
}
