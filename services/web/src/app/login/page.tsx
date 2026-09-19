"use client";

import { useActionState, useEffect, useState } from "react";
import Link from "next/link";
import { EyeIcon, EyeSlashIcon } from "@phosphor-icons/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/components/ui/input-group";
import { login, type LoginState } from "./actions";

const initialState: LoginState = { error: null, success: false };

export default function LoginPage() {
  const [state, formAction, pending] = useActionState(login, initialState);
  const [showPassword, setShowPassword] = useState(false);

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
    <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
      <div className="flex w-full max-w-sm flex-col gap-6 md:max-w-4xl">
        <Link href="/" className="flex items-center gap-2 self-center font-heading font-semibold">
          <img src="/logo-mark.svg" alt="" className="h-7 w-7 shrink-0" />
          <img src="/logo-wordmark.svg" alt="Hostium" className="h-[22.6px] w-auto" />
        </Link>

        <Card className="overflow-hidden p-0">
          <CardContent className="grid p-0 md:grid-cols-2">
            <form action={formAction} className="p-6 md:p-8">
              <FieldGroup>
                <div className="flex flex-col items-center gap-2 text-center">
                  <h1 className="text-2xl font-bold">Bienvenido de nuevo</h1>
                  <p className="text-balance text-muted-foreground">
                    Inicia sesión con tu email.
                  </p>
                </div>

                <Field>
                  <FieldLabel htmlFor="email">Email</FieldLabel>
                  <Input
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="email"
                    placeholder="tu@email.com"
                    required
                  />
                </Field>

                <Field>
                  <FieldLabel htmlFor="password">Contraseña</FieldLabel>
                  <InputGroup>
                    <InputGroupInput
                      id="password"
                      name="password"
                      type={showPassword ? "text" : "password"}
                      autoComplete="current-password"
                      required
                    />
                    <InputGroupAddon align="inline-end">
                      <InputGroupButton
                        type="button"
                        size="icon-sm"
                        aria-label={showPassword ? "Ocultar contraseña" : "Mostrar contraseña"}
                        onClick={() => setShowPassword((prev) => !prev)}
                      >
                        {showPassword ? <EyeSlashIcon className="size-4" /> : <EyeIcon className="size-4" />}
                      </InputGroupButton>
                    </InputGroupAddon>
                  </InputGroup>
                </Field>

                {state.error && <p className="text-sm text-destructive">{state.error}</p>}

                <Field>
                  <Button type="submit" disabled={pending}>
                    {pending ? "Entrando…" : "Entrar"}
                  </Button>
                  <FieldDescription className="text-center">
                    ¿No tienes cuenta? <Link href="/register">Regístrate</Link>
                  </FieldDescription>
                </Field>
              </FieldGroup>
            </form>

            <div className="relative hidden flex-col items-center justify-center gap-6 overflow-hidden bg-primary p-10 md:flex">
              <div
                aria-hidden
                className="absolute inset-0 opacity-[0.12]"
                style={{
                  backgroundImage: "url(/logo-glyph.svg)",
                  backgroundSize: "64px 64px",
                  backgroundRepeat: "repeat",
                }}
              />
              <img src="/logo-mark.svg" alt="" className="relative h-20 w-20 shrink-0 rounded-lg shadow-lg" />
              <p className="relative max-w-80 text-balance text-center text-base font-bold text-primary-foreground">
                Despliega cualquier repo con una URL pública en segundos.
              </p>
            </div>
          </CardContent>
        </Card>

        <FieldDescription className="px-6 text-center">
          Hostium — la plataforma de despliegue del hackathon.
        </FieldDescription>
      </div>
    </div>
  );
}
