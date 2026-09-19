"use client";

import { useActionState, useEffect } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { login, type LoginState } from "./actions";

const initialState: LoginState = { error: null, success: false };

export default function LoginPage() {
  const [state, formAction, pending] = useActionState(login, initialState);

  useEffect(() => {
    // Full navigation, not router.push(): see the comment in actions.ts —
    // the providers that fetch teams/projects live in the shared root
    // layout and only fetch once, on mount.
    if (state.success) {
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- intentional hard reload, see comment above
      window.location.href = "/";
    }
  }, [state.success]);

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <form action={formAction} className="w-full max-w-sm space-y-6">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight">Hostium</h1>
          <p className="text-sm text-muted-foreground">
            Inicia sesión con tu email.
          </p>
        </div>

        <div className="space-y-2">
          <Label htmlFor="email">Email</Label>
          <Input id="email" name="email" type="email" autoComplete="email" required />
        </div>

        <div className="space-y-2">
          <Label htmlFor="password">Contraseña</Label>
          <Input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            required
          />
        </div>

        {state.error && <p className="text-sm text-destructive">{state.error}</p>}

        <Button type="submit" disabled={pending} className="w-full">
          {pending ? "Entrando…" : "Entrar"}
        </Button>

        <p className="text-center text-sm text-muted-foreground">
          ¿No tienes cuenta?{" "}
          <Link href="/register" className="text-foreground underline underline-offset-4">
            Regístrate
          </Link>
        </p>
      </form>
    </div>
  );
}
