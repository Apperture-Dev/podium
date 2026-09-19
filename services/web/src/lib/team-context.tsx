"use client";

import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

export type Team = {
  id: string;
  name: string;
};

const STORAGE_KEY_TEAMS = "hostium.teams";
const STORAGE_KEY_ACTIVE_TEAM = "hostium.activeTeamId";

const DEFAULT_TEAMS: Team[] = [
  { id: "team-a", name: "team-A" },
  { id: "team-b", name: "team-B" },
];

type TeamContextValue = {
  teams: Team[];
  activeTeam: Team | null;
  setActiveTeamId: (teamId: string) => void;
  addTeam: (team: Team) => void;
};

const TeamContext = createContext<TeamContextValue | null>(null);

export function TeamProvider({ children }: { children: ReactNode }) {
  const [teams, setTeams] = useState<Team[]>(DEFAULT_TEAMS);
  const [activeTeamId, setActiveTeamIdState] = useState<string>(
    DEFAULT_TEAMS[0].id,
  );
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    // One-time hydration from localStorage after mount, so server and first
    // client render match; the extra re-render this causes is the standard,
    // unavoidable trade-off for reading browser storage in a Next.js app.
    /* eslint-disable react-hooks/set-state-in-effect */
    try {
      const storedTeams = window.localStorage.getItem(STORAGE_KEY_TEAMS);
      const storedActiveId = window.localStorage.getItem(
        STORAGE_KEY_ACTIVE_TEAM,
      );
      if (storedTeams) setTeams(JSON.parse(storedTeams) as Team[]);
      if (storedActiveId) setActiveTeamIdState(storedActiveId);
    } catch {
      // Private browsing / blocked storage: fall back to defaults silently.
    } finally {
      setHydrated(true);
    }
    /* eslint-enable react-hooks/set-state-in-effect */
  }, []);

  useEffect(() => {
    if (!hydrated) return;
    try {
      window.localStorage.setItem(STORAGE_KEY_TEAMS, JSON.stringify(teams));
      window.localStorage.setItem(STORAGE_KEY_ACTIVE_TEAM, activeTeamId);
    } catch {
      // Ignore storage failures; state still works for this session.
    }
  }, [teams, activeTeamId, hydrated]);

  const value = useMemo<TeamContextValue>(
    () => ({
      teams,
      activeTeam: teams.find((team) => team.id === activeTeamId) ?? null,
      setActiveTeamId: setActiveTeamIdState,
      addTeam: (team: Team) => {
        setTeams((prev) => [...prev, team]);
        setActiveTeamIdState(team.id);
      },
    }),
    [teams, activeTeamId],
  );

  return (
    <TeamContext.Provider value={value}>{children}</TeamContext.Provider>
  );
}

export function useTeam(): TeamContextValue {
  const context = useContext(TeamContext);
  if (!context) {
    throw new Error("useTeam must be used within a TeamProvider");
  }
  return context;
}
