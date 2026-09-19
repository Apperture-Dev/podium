import { AppShell } from "@/components/shell/app-shell";
import { PageBreadcrumb, PageHeader } from "@/components/shell/page-header";
import { ProjectForm } from "@/components/projects/project-form";

export default function NuevoProyectoPage() {
  return (
    <AppShell>
      <PageBreadcrumb
        items={[{ label: "Proyectos", href: "/" }, { label: "Nuevo proyecto" }]}
      />
      <PageHeader
        title="Crear nuevo proyecto"
        subtitle="Ponle un nombre y conecta el repositorio que quieres desplegar."
      />
      <ProjectForm />
    </AppShell>
  );
}
