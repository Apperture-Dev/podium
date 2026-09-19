"use client";

import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { listProjects, getProject, type Project } from "@/lib/api/projects";
import { listApplications, type Application } from "@/lib/api/applications";
import { ApiError } from "@/lib/api/client";
import { useTeam } from "@/lib/team-context";

export type ProjectDetail = {
  project: Project;
  applications: Application[];
};

type ProjectsContextValue = {
  projects: Project[];
  /** El primer Application registrado en cada Project — representa la card en el dashboard (ver Figma). */
  primaryApplications: Record<string, Application | undefined>;
  addProject: (project: Project) => void;
  isLoading: boolean;
  error: string | null;
  loadProjectDetail: (
    teamId: string,
    projectId: string,
  ) => Promise<ProjectDetail>;
};

const ProjectsContext = createContext<ProjectsContextValue | null>(null);

export function ProjectsProvider({ children }: { children: ReactNode }) {
  const { activeTeam } = useTeam();
  const [projects, setProjects] = useState<Project[]>([]);
  const [primaryApplications, setPrimaryApplications] = useState<
    Record<string, Application | undefined>
  >({});
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Fetching on team change necessarily calls setState from the effect
    // body — there's no external-system subscription to move this into.
    /* eslint-disable react-hooks/set-state-in-effect */
    if (!activeTeam) {
      setProjects([]);
      setPrimaryApplications({});
      return;
    }
    let cancelled = false;
    setIsLoading(true);
    listProjects(activeTeam.id)
      .then(async (fetched) => {
        if (cancelled) return;
        setProjects(fetched);
        setError(null);

        const entries = await Promise.all(
          fetched.map(async (project): Promise<[string, Application | undefined]> => {
            try {
              const applications = await listApplications(activeTeam.id, project.id);
              return [project.id, applications[0]];
            } catch {
              return [project.id, undefined];
            }
          }),
        );
        if (!cancelled) {
          setPrimaryApplications(Object.fromEntries(entries));
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(
            err instanceof ApiError ? err.message : "No se pudieron cargar los proyectos.",
          );
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });
    /* eslint-enable react-hooks/set-state-in-effect */
    return () => {
      cancelled = true;
    };
  }, [activeTeam]);

  const value = useMemo<ProjectsContextValue>(
    () => ({
      projects,
      primaryApplications,
      addProject: (project: Project) => {
        setProjects((prev) => [project, ...prev]);
      },
      isLoading,
      error,
      loadProjectDetail: async (teamId: string, projectId: string) => {
        const cached = projects.find((p) => p.id === projectId);
        const [project, applications] = await Promise.all([
          cached ? Promise.resolve(cached) : getProject(teamId, projectId),
          listApplications(teamId, projectId),
        ]);
        return { project, applications };
      },
    }),
    [projects, primaryApplications, isLoading, error],
  );

  return (
    <ProjectsContext.Provider value={value}>
      {children}
    </ProjectsContext.Provider>
  );
}

export function useProjects(): ProjectsContextValue {
  const context = useContext(ProjectsContext);
  if (!context) {
    throw new Error("useProjects must be used within a ProjectsProvider");
  }
  return context;
}
