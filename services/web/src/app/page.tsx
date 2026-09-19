"use client";

import { AppShell } from "@/components/shell/app-shell";
import { PageHeader } from "@/components/shell/page-header";
import { ProjectCard } from "@/components/projects/project-card";
import { useTeam } from "@/lib/team-context";
import { useProjects } from "@/lib/projects-context";

export default function ProyectosPage() {
  const { activeTeam } = useTeam();
  const { projects } = useProjects();

  const teamProjects = projects.filter(
    (project) => project.teamId === activeTeam?.id,
  );

  return (
    <AppShell>
      <PageHeader
        title="Proyectos"
        subtitle={`${teamProjects.length} proyecto${teamProjects.length === 1 ? "" : "s"} activo${teamProjects.length === 1 ? "" : "s"}`}
      />
      {teamProjects.length === 0 ? (
        <p className="text-muted-foreground">
          {activeTeam?.name ?? "Este equipo"} todavía no tiene proyectos.
        </p>
      ) : (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {teamProjects.map((project) => (
            <ProjectCard key={project.id} project={project} />
          ))}
        </div>
      )}
    </AppShell>
  );
}
