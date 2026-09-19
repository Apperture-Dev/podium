"use client";

import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { listTeams, type Team } from "@/lib/api/teams";
import { ApiError } from "@/lib/api/client";

const STORAGE_KEY_ACTIVE_TEAM = "hostium.activeTeamId";

type TeamContextValue = {
  teams: Team[];
  activeTeam: Team | null;
  setActiveTeamId: (teamId: string) => void;
  addTeam: (team: Team) => void;
  isLoading: boolean;
  error: string | null;
};

const TeamContext = createContext<TeamContextValue | null>(null);

export function TeamProvider({ children }: { children: ReactNode }) {
  const [teams, setTeams] = useState<Team[]>([]);
  const [activeTeamId, setActiveTeamIdState] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Fetching on mount necessarily calls setState from the effect body —
    // there's no external-system subscription to move this into.
    /* eslint-disable react-hooks/set-state-in-effect */
    let cancelled = false;
    setIsLoading(true);
    listTeams()
      .then((fetchedTeams) => {
        if (cancelled) return;
        setTeams(fetchedTeams);
        setError(null);
        const storedActiveId = (() => {
          try {
            return window.localStorage.getItem(STORAGE_KEY_ACTIVE_TEAM);
          } catch {
            return null;
          }
        })();
        const initialTeam =
          fetchedTeams.find((team) => team.id === storedActiveId) ??
          fetchedTeams[0] ??
          null;
        setActiveTeamIdState(initialTeam?.id ?? null);
      })
      .catch((err) => {
        if (cancelled) return;
        setError(
          err instanceof ApiError ? err.message : "No se pudieron cargar los equipos.",
        );
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });
    /* eslint-enable react-hooks/set-state-in-effect */
    return () => {
      cancelled = true;
    };
  }, []);

  function setActiveTeamId(teamId: string) {
    setActiveTeamIdState(teamId);
    try {
      window.localStorage.setItem(STORAGE_KEY_ACTIVE_TEAM, teamId);
    } catch {
      // Private browsing / blocked storage: active team just won't survive a reload.
    }
  }

  const value = useMemo<TeamContextValue>(
    () => ({
      teams,
      activeTeam: teams.find((team) => team.id === activeTeamId) ?? null,
      setActiveTeamId,
      addTeam: (team: Team) => {
        setTeams((prev) => [...prev, team]);
        setActiveTeamId(team.id);
      },
      isLoading,
      error,
    }),
    [teams, activeTeamId, isLoading, error],
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
