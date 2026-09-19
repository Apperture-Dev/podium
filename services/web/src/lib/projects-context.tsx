"use client";

import {
  createContext,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { FIXTURE_PROJECTS, type ProjectSummary } from "@/lib/fixtures/projects";
import {
  FIXTURE_PROJECT_DETAILS,
  type ProjectDetail,
} from "@/lib/fixtures/project-detail";

type ProjectsContextValue = {
  projects: ProjectSummary[];
  addProject: (project: ProjectSummary) => void;
  getProjectDetail: (projectId: string) => ProjectDetail;
};

const ProjectsContext = createContext<ProjectsContextValue | null>(null);

/** A freshly-registered project has no mock detail yet — synthesize a minimal one instead of a dead end. */
function synthesizeDetail(project: ProjectSummary): ProjectDetail {
  return {
    id: project.id,
    status: "Construyendo",
    version: "—",
    frameworks: [],
    updatedAt: project.updatedAt,
    primaryDomain: project.domain,
    deployments: [],
  };
}

export function ProjectsProvider({ children }: { children: ReactNode }) {
  const [projects, setProjects] = useState<ProjectSummary[]>(FIXTURE_PROJECTS);
  const [details, setDetails] = useState<Record<string, ProjectDetail>>(
    FIXTURE_PROJECT_DETAILS,
  );

  const value = useMemo<ProjectsContextValue>(
    () => ({
      projects,
      addProject: (project: ProjectSummary) => {
        setProjects((prev) => [project, ...prev]);
        setDetails((prev) => ({
          ...prev,
          [project.id]: synthesizeDetail(project),
        }));
      },
      getProjectDetail: (projectId: string) =>
        details[projectId] ??
        synthesizeDetail(
          projects.find((project) => project.id === projectId) ?? {
            id: projectId,
            teamId: "",
            name: projectId,
            description: "",
            domain: "",
            language: "",
            version: "—",
            updatedAt: new Date().toISOString(),
          },
        ),
    }),
    [projects, details],
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
