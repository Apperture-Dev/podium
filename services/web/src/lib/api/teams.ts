import { getJson, postJson } from "./client";

export type Team = {
  id: string;
  name: string;
};

export type RegisterTeamInput = {
  name: string;
  creatorUserId: string;
};

export type RegisterTeamResponse = {
  id: string;
};

/** Calls the real `GET /api/teams` endpoint. */
export function listTeams(): Promise<Team[]> {
  return getJson<Team[]>("/api/teams");
}

/** Calls the real `POST /api/teams` endpoint. */
export function registerTeam(
  input: RegisterTeamInput,
): Promise<RegisterTeamResponse> {
  return postJson<RegisterTeamResponse>("/api/teams", input);
}
