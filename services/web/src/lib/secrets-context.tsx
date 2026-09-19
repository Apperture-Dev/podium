"use client";

import {
  createContext,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { INITIAL_FIXTURE_SECRETS, type Secret } from "@/lib/fixtures/secrets";

type SecretsContextValue = {
  getSecrets: (projectId: string) => Secret[];
  addSecret: (projectId: string, secret: Omit<Secret, "id">) => { ok: true } | { ok: false; error: string };
  removeSecret: (projectId: string, secretId: string) => void;
};

const SecretsContext = createContext<SecretsContextValue | null>(null);

export function SecretsProvider({ children }: { children: ReactNode }) {
  const [secretsByProject, setSecretsByProject] = useState<
    Record<string, Secret[]>
  >(INITIAL_FIXTURE_SECRETS);

  const value = useMemo<SecretsContextValue>(
    () => ({
      getSecrets: (projectId: string) => secretsByProject[projectId] ?? [],
      addSecret: (projectId: string, secret: Omit<Secret, "id">) => {
        const existing = secretsByProject[projectId] ?? [];
        const duplicate = existing.some(
          (candidate) =>
            candidate.name.toLowerCase() === secret.name.toLowerCase(),
        );
        if (duplicate) {
          return {
            ok: false as const,
            error: `Ya existe una credencial llamada "${secret.name}" en este proyecto.`,
          };
        }
        setSecretsByProject((prev) => ({
          ...prev,
          [projectId]: [
            ...existing,
            { ...secret, id: `secret-${crypto.randomUUID()}` },
          ],
        }));
        return { ok: true as const };
      },
      removeSecret: (projectId: string, secretId: string) => {
        setSecretsByProject((prev) => ({
          ...prev,
          [projectId]: (prev[projectId] ?? []).filter(
            (secret) => secret.id !== secretId,
          ),
        }));
      },
    }),
    [secretsByProject],
  );

  return (
    <SecretsContext.Provider value={value}>
      {children}
    </SecretsContext.Provider>
  );
}

export function useSecrets(): SecretsContextValue {
  const context = useContext(SecretsContext);
  if (!context) {
    throw new Error("useSecrets must be used within a SecretsProvider");
  }
  return context;
}
