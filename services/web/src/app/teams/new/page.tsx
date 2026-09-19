import { AppShell } from "@/components/shell/app-shell";
import { PageBreadcrumb, PageHeader } from "@/components/shell/page-header";
import { TeamForm } from "@/components/teams/team-form";

export default function NuevoEquipoPage() {
  return (
    <AppShell>
      <PageBreadcrumb items={[{ label: "Equipos" }, { label: "Nuevo equipo" }]} />
      <PageHeader
        title="Crear nuevo equipo"
        subtitle="Ponle un nombre a tu equipo para empezar a registrar proyectos."
      />
      <TeamForm />
    </AppShell>
  );
}
