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
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Fetching on team change necessarily calls setState from the effect
    // body — there's no external-system subscription to move this into.
    /* eslint-disable react-hooks/set-state-in-effect */
    if (!activeTeam) {
      setProjects([]);
      return;
    }
    let cancelled = false;
    setIsLoading(true);
    listProjects(activeTeam.id)
      .then((fetched) => {
        if (cancelled) return;
        setProjects(fetched);
        setError(null);
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
      addProject: (project: Project) => {
        setProjects((prev) => [project, ...prev]);
      },
      isLoading,
      error,
      loadProjectDetail: async (teamId: string, projectId: string) => {
        const cached = projects.find((p) => p.id === projectId);
        try {
          const [project, applications] = await Promise.all([
            cached ? Promise.resolve(cached) : getProject(teamId, projectId),
            listApplications(teamId, projectId),
          ]);
          return { project, applications };
        } catch (err) {
          // getProject/listApplications ya lanzan ApiError para los fallos de
          // red reales — esto solo evita que un rechazo se escape sin pasar
          // por la misma convención que usa el resto del módulo, en vez de
          // depender de que cada futuro llamante recuerde poner su propio
          // .catch (hoy solo hay uno, en projects/[id]/page.tsx).
          throw err instanceof ApiError
            ? err
            : new ApiError("No se pudo cargar el proyecto.");
        }
      },
    }),
    [projects, isLoading, error],
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
