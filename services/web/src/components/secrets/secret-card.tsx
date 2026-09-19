"use client";

import { useState } from "react";
import { Eye, EyeSlash } from "@phosphor-icons/react";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import type { Secret } from "@/lib/fixtures/secrets";

export function SecretCard({
  secret,
  onDelete,
}: {
  secret: Secret;
  onDelete: () => void;
}) {
  const [revealed, setRevealed] = useState(false);

  return (
    <div className="border p-4 space-y-4">
      <div className="flex items-center justify-between">
        <Label>NAME</Label>
        <Button variant="ghost" size="sm" onClick={onDelete}>
          ✕ Eliminar
        </Button>
      </div>
      <Input value={secret.name} readOnly />
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <Label>VALUE</Label>
        </div>
        <div className="flex items-center gap-2">
          <Input
            type={revealed ? "text" : "password"}
            value={secret.value}
            readOnly
          />
          <Button
            variant="ghost"
            size="icon"
            aria-label={revealed ? "Ocultar valor" : "Revelar valor"}
            onClick={() => setRevealed((prev) => !prev)}
          >
            {revealed ? <EyeSlash className="size-4" /> : <Eye className="size-4" />}
          </Button>
        </div>
      </div>
    </div>
  );
}
