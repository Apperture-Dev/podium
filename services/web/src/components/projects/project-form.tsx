"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { registerProject } from "@/lib/api/projects";
import { ApiError } from "@/lib/api/client";
import { useTeam } from "@/lib/team-context";
import { useProjects } from "@/lib/projects-context";

export function ProjectForm() {
  const router = useRouter();
  const { activeTeam } = useTeam();
  const { addProject } = useProjects();

  const [name, setName] = useState("");
  const [repositoryUrl, setRepositoryUrl] = useState("");
  const [fieldErrors, setFieldErrors] = useState<{
    name?: string;
    repositoryUrl?: string;
  }>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setSubmitError(null);

    const errors: typeof fieldErrors = {};
    if (!name.trim()) errors.name = "El nombre del proyecto es obligatorio.";
    if (!repositoryUrl.trim())
      errors.repositoryUrl = "La URL del repositorio es obligatoria.";
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) return;

    if (!activeTeam) {
      setSubmitError("Selecciona un equipo antes de registrar un proyecto.");
      return;
    }

    setIsSubmitting(true);
    try {
      const { id } = await registerProject({
        name: name.trim(),
        repositoryUrl: repositoryUrl.trim(),
        teamId: activeTeam.id,
      });
      addProject({
        id,
        teamId: activeTeam.id,
        name: name.trim(),
        description: "",
        domain: "pendiente.hostium.app",
        language: "—",
        version: "—",
        updatedAt: new Date().toISOString(),
      });
      router.push(`/projects/${id}`);
    } catch (error) {
      setSubmitError(
        error instanceof ApiError
          ? error.message
          : "No se pudo registrar el proyecto.",
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="max-w-md space-y-6">
      <div className="space-y-2">
        <Label htmlFor="project-name">Nombre del proyecto</Label>
        <Input
          id="project-name"
          value={name}
          onChange={(event) => setName(event.target.value)}
          placeholder="ej. api-gateway"
          aria-invalid={fieldErrors.name ? true : undefined}
        />
        {fieldErrors.name && (
          <p className="text-sm text-destructive">{fieldErrors.name}</p>
        )}
      </div>
      <div className="space-y-2">
        <Label htmlFor="repository-url">Repositorio</Label>
        <Input
          id="repository-url"
          value={repositoryUrl}
          onChange={(event) => setRepositoryUrl(event.target.value)}
          placeholder="Pega aquí la URL de tu repositorio de GitHub"
          aria-invalid={fieldErrors.repositoryUrl ? true : undefined}
        />
        {fieldErrors.repositoryUrl && (
          <p className="text-sm text-destructive">{fieldErrors.repositoryUrl}</p>
        )}
        <p className="text-xs text-muted-foreground">
          Cópiala desde el botón &ldquo;Code&rdquo; de tu repositorio en GitHub y pégala aquí.
        </p>
      </div>
      {submitError && <p className="text-sm text-destructive">{submitError}</p>}
      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting ? "Creando…" : "Crear proyecto"}
      </Button>
    </form>
  );
}
