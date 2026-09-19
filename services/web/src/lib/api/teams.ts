import { postJson } from "./client";

export type RegisterTeamInput = {
  name: string;
  creatorUserId: string;
};

export type RegisterTeamResponse = {
  id: string;
};

/** Calls the real `POST /api/teams` endpoint. */
export function registerTeam(
  input: RegisterTeamInput,
): Promise<RegisterTeamResponse> {
  return postJson<RegisterTeamResponse>("/api/teams", input);
}
