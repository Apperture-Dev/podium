"use client";

import { AppShell } from "@/components/shell/app-shell";
import { PageHeader } from "@/components/shell/page-header";
import { ProjectCard } from "@/components/projects/project-card";
import { useTeam } from "@/lib/team-context";
import { useProjects } from "@/lib/projects-context";

export default function ProyectosPage() {
  const { activeTeam, isLoading: isLoadingTeam } = useTeam();
  const {
    projects,
    isLoading: isLoadingProjects,
    error,
  } = useProjects();

  const isLoading = isLoadingTeam || isLoadingProjects;

  return (
    <AppShell>
      <PageHeader
        title="Proyectos"
        subtitle={
          isLoading
            ? "Cargando…"
            : `${projects.length} proyecto${projects.length === 1 ? "" : "s"} activo${projects.length === 1 ? "" : "s"}`
        }
      />
      {error && <p className="text-destructive mb-6">{error}</p>}
      {!isLoading && !error && projects.length === 0 && (
        <p className="text-muted-foreground">
          {activeTeam?.name ?? "Este equipo"} todavía no tiene proyectos.
        </p>
      )}
      {!isLoading && projects.length > 0 && (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {projects.map((project, index) => (
            <ProjectCard key={project.id} project={project} index={index} />
          ))}
        </div>
      )}
    </AppShell>
  );
}
