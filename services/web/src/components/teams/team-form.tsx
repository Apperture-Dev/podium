"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { registerTeam } from "@/lib/api/teams";
import { getCurrentUserId } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/client";
import { useTeam } from "@/lib/team-context";

export function TeamForm() {
  const router = useRouter();
  const { addTeam } = useTeam();
  const [name, setName] = useState("");
  const [nameError, setNameError] = useState<string | null>(null);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setSubmitError(null);

    if (!name.trim()) {
      setNameError("El nombre del equipo es obligatorio.");
      return;
    }
    setNameError(null);

    setIsSubmitting(true);
    try {
      const creatorUserId = await getCurrentUserId();
      const { id } = await registerTeam({
        name: name.trim(),
        creatorUserId,
      });
      addTeam({ id, name: name.trim() });
      router.push("/");
    } catch (error) {
      setSubmitError(
        error instanceof ApiError
          ? error.message
          : "No se pudo registrar el equipo.",
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="max-w-md space-y-6">
      <div className="space-y-2">
        <Label htmlFor="team-name">Nombre del equipo</Label>
        <Input
          id="team-name"
          value={name}
          onChange={(event) => setName(event.target.value)}
          placeholder="ej. team-A"
          aria-invalid={nameError ? true : undefined}
        />
        {nameError && <p className="text-sm text-destructive">{nameError}</p>}
      </div>
      {submitError && <p className="text-sm text-destructive">{submitError}</p>}
      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting ? "Creando…" : "Crear equipo"}
      </Button>
    </form>
  );
}
