"use client";

import { use, useEffect, useState } from "react";
import { CubeFocus } from "@phosphor-icons/react";
import { AppShell } from "@/components/shell/app-shell";
import { PageBreadcrumb } from "@/components/shell/page-header";
import { ApplicationCard } from "@/components/projects/application-card";
import { useTeam } from "@/lib/team-context";
import { useProjects, type ProjectDetail } from "@/lib/projects-context";
import { ApiError } from "@/lib/api/client";

export default function ProyectoDetallePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const { activeTeam } = useTeam();
  const { loadProjectDetail } = useProjects();

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

  return (
    <AppShell>
      <PageBreadcrumb
        items={[{ label: "Proyectos", href: "/" }, { label: displayName }]}
      />

      {error && <p className="text-destructive">{error}</p>}
      {!error && !detail && <p className="text-muted-foreground">Cargando…</p>}

      {detail && (
        <>
          <div className="flex items-start gap-4 mb-8">
            <div className="flex size-14 shrink-0 items-center justify-center bg-muted">
              <CubeFocus className="size-6" />
            </div>
            <div>
              <h1 className="text-2xl">{detail.project.name}</h1>
              <p className="text-sm text-muted-foreground mt-1">
                {detail.project.repositoryUrl}
              </p>
              <p className="text-sm text-muted-foreground">
                {detail.project.hash}.apperture.dev
              </p>
            </div>
          </div>

          <h2 className="text-xl mb-4">Servicios</h2>
          {detail.applications.length === 0 ? (
            <p className="text-muted-foreground">
              Todavía no hay servicios registrados para este proyecto.
            </p>
          ) : (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {detail.applications.map((application) => (
                <ApplicationCard
                  key={application.serviceName}
                  application={application}
                />
              ))}
            </div>
          )}
        </>
      )}
    </AppShell>
  );
}
