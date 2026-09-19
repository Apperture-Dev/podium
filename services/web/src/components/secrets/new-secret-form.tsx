"use client";

import { useState, type FormEvent } from "react";
import { Plus } from "@phosphor-icons/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { useSecrets } from "@/lib/secrets-context";

export function NewSecretForm({ projectId }: { projectId: string }) {
  const { addSecret } = useSecrets();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [value, setValue] = useState("");
  const [error, setError] = useState<string | null>(null);

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!name.trim() || !value.trim()) {
      setError("Nombre y valor son obligatorios.");
      return;
    }
    const result = addSecret(projectId, { name: name.trim(), value: value.trim() });
    if (!result.ok) {
      setError(result.error);
      return;
    }
    setName("");
    setValue("");
    setError(null);
    setOpen(false);
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button />}>
        <Plus className="size-4" /> Nueva credencial
      </DialogTrigger>
      <DialogContent>
        <form onSubmit={handleSubmit}>
          <DialogHeader>
            <DialogTitle>Nueva credencial</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="secret-name">NAME</Label>
              <Input
                id="secret-name"
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder="ej. API_KEY"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="secret-value">VALUE</Label>
              <Input
                id="secret-value"
                value={value}
                onChange={(event) => setValue(event.target.value)}
                type="password"
              />
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
          </div>
          <DialogFooter>
            <DialogClose render={<Button variant="outline" type="button" />}>
              Cancelar
            </DialogClose>
            <Button type="submit">Guardar</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
