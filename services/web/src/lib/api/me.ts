import { getJson } from "./client";

export type CurrentUser = {
  name: string | null;
  givenName: string | null;
  familyName: string | null;
  preferredUsername: string | null;
};

/** Calls this app's own `/api/me` — see app/api/me/route.ts. */
export function getCurrentUser(): Promise<CurrentUser> {
  return getJson<CurrentUser>("/api/me");
}
