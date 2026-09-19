"use client";

import { use } from "react";
import { CubeFocus } from "@phosphor-icons/react";
import { AppShell } from "@/components/shell/app-shell";
import { PageBreadcrumb } from "@/components/shell/page-header";
import { Badge } from "@/components/ui/badge";
import { DeploymentCard } from "@/components/projects/deployment-card";
import { useProjects } from "@/lib/projects-context";
import { formatRelativeTime } from "@/lib/format";

export default function ProyectoDetallePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const { projects, getProjectDetail } = useProjects();

  const summary = projects.find((project) => project.id === id);
  const detail = getProjectDetail(id);
  const displayName = summary?.name ?? id;

  return (
    <AppShell>
      <PageBreadcrumb
        items={[{ label: "Proyectos", href: "/" }, { label: displayName }]}
      />
      <div className="flex items-start gap-4 mb-8">
        <div className="flex size-14 shrink-0 items-center justify-center bg-muted">
          <CubeFocus className="size-6" />
        </div>
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl">{displayName}</h1>
            <Badge variant="secondary">{detail.status}</Badge>
          </div>
          {summary?.description && (
            <p className="text-muted-foreground mt-1">{summary.description}</p>
          )}
          <p className="text-sm text-muted-foreground mt-1">
            {detail.primaryDomain}
          </p>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-6 border-y py-6 mb-8 sm:grid-cols-4">
        <div>
          <p className="text-xs text-muted-foreground mb-1">Estado</p>
          <p className="text-lg font-medium">{detail.status}</p>
        </div>
        <div>
          <p className="text-xs text-muted-foreground mb-1">Versión</p>
          <p className="text-lg font-medium">{detail.version}</p>
        </div>
        <div>
          <p className="text-xs text-muted-foreground mb-1">Frameworks</p>
          <p className="text-lg font-medium">
            {detail.frameworks.length > 0 ? detail.frameworks.join(", ") : "—"}
          </p>
        </div>
        <div>
          <p className="text-xs text-muted-foreground mb-1">Actualizado</p>
          <p className="text-lg font-medium">
            {formatRelativeTime(detail.updatedAt)}
          </p>
        </div>
      </div>

      <h2 className="text-xl mb-4">Despliegue de producción</h2>
      {detail.deployments.length === 0 ? (
        <p className="text-muted-foreground">
          Todavía no hay despliegues para este proyecto.
        </p>
      ) : (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          {detail.deployments.map((deployment) => (
            <DeploymentCard key={deployment.id} deployment={deployment} />
          ))}
        </div>
      )}
    </AppShell>
  );
}
