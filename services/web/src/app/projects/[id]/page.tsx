"use client";

import { use, useEffect, useState } from "react";
import { ArrowUpRight, CubeFocus } from "@phosphor-icons/react";
import { AppShell } from "@/components/shell/app-shell";
import { PageBreadcrumb } from "@/components/shell/page-header";
import { ApplicationCard } from "@/components/projects/application-card";
import { useTeam } from "@/lib/team-context";
import { useProjects, type ProjectDetail } from "@/lib/projects-context";
import { ApiError } from "@/lib/api/client";
import { avatarToneFor } from "@/lib/avatar-tones";
import { mockProjectDescription } from "@/lib/mock-project-description";

export default function ProyectoDetallePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const { activeTeam } = useTeam();
  const { projects, loadProjectDetail } = useProjects();

  const [detail, setDetail] = useState<ProjectDetail | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Fetching on id/team change necessarily calls setState from the effect
    // body — there's no external-system subscription to move this into.
    /* eslint-disable react-hooks/set-state-in-effect */
    if (!activeTeam) return;
    let cancelled = false;
    setDetail(null);
    setError(null);
    loadProjectDetail(activeTeam.id, id)
      .then((result) => {
        if (!cancelled) setDetail(result);
      })
      .catch((err) => {
        if (!cancelled) {
          setError(
            err instanceof ApiError ? err.message : "No se pudo cargar el proyecto.",
          );
        }
      });
    /* eslint-enable react-hooks/set-state-in-effect */
    return () => {
      cancelled = true;
    };
  }, [activeTeam, id, loadProjectDetail]);

  const displayName = detail?.project.name ?? id;
  const projectIndex = projects.findIndex((p) => p.id === id);
  const tone = avatarToneFor(projectIndex === -1 ? 0 : projectIndex);

  return (
    <AppShell>
      <PageBreadcrumb
        items={[{ label: "Proyectos", href: "/" }, { label: displayName }]}
      />

      {error && <p className="text-destructive">{error}</p>}
      {!error && !detail && <p className="text-muted-foreground">Cargando…</p>}

      {detail && (
        <>
          <div className="mb-8">
            <div className="flex items-start gap-4">
              <div
                className="flex size-14 shrink-0 items-center justify-center"
                style={{ backgroundColor: tone.bg }}
              >
                <CubeFocus className="size-6" style={{ color: tone.fg }} />
              </div>
              <div>
                <h1 className="text-2xl">{detail.project.name}</h1>
                <p className="flex items-center gap-1 text-base mt-1">
                  {detail.project.hash}.apperture.dev
                  <ArrowUpRight className="size-4" />
                </p>
              </div>
            </div>
            <p className="text-base text-muted-foreground mt-4">
              {mockProjectDescription(projectIndex === -1 ? 0 : projectIndex)}
            </p>
            <p className="inline-flex items-center gap-2 text-xs text-muted-foreground bg-sidebar border border-foreground/20 rounded-md px-3 py-1.5 mt-4">
              <span className="size-1.5 shrink-0 rounded-full bg-muted-foreground" />
              {detail.project.repositoryUrl}
            </p>
          </div>

          <h2 className="text-xl mb-4">Aplicaciones</h2>
          {detail.applications.length === 0 ? (
            <p className="text-muted-foreground">
              Todavía no hay aplicaciones registradas para este proyecto.
            </p>
          ) : (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {detail.applications.map((application, index) => (
                <ApplicationCard
                  key={application.serviceName}
                  application={application}
                  index={index}
                  projectHash={detail.project.hash}
                />
              ))}
            </div>
          )}
        </>
      )}
    </AppShell>
  );
}
