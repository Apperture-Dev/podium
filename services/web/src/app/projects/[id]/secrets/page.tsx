"use client";

import { use } from "react";
import { LockKey } from "@phosphor-icons/react";
import { AppShell } from "@/components/shell/app-shell";
import { PageBreadcrumb, PageHeader } from "@/components/shell/page-header";
import { SecretCard } from "@/components/secrets/secret-card";
import { NewSecretForm } from "@/components/secrets/new-secret-form";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { useProjects } from "@/lib/projects-context";
import { useSecrets } from "@/lib/secrets-context";

export default function SecretsPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const { projects } = useProjects();
  const { getSecrets, removeSecret } = useSecrets();

  const projectName = projects.find((project) => project.id === id)?.name ?? id;
  const secrets = getSecrets(id);

  return (
    <AppShell>
      <PageBreadcrumb
        items={[
          { label: "Proyectos", href: "/" },
          { label: projectName, href: `/projects/${id}` },
          { label: "Secrets" },
        ]}
      />
      <PageHeader
        title="Secrets"
        subtitle={`Credenciales y variables sensibles de ${projectName}.`}
        action={<NewSecretForm projectId={id} />}
      />
      {secrets.length === 0 ? (
        <Empty>
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <LockKey />
            </EmptyMedia>
            <EmptyTitle>Sin credenciales</EmptyTitle>
            <EmptyDescription>
              Este proyecto todavía no tiene credenciales. Añade una para
              empezar a usarla en tus builds y despliegues.
            </EmptyDescription>
          </EmptyHeader>
          <EmptyContent>
            <NewSecretForm projectId={id} />
          </EmptyContent>
        </Empty>
      ) : (
        <div className="max-w-2xl space-y-4">
          {secrets.map((secret) => (
            <SecretCard
              key={secret.id}
              secret={secret}
              onDelete={() => removeSecret(id, secret.id)}
            />
          ))}
        </div>
      )}
    </AppShell>
  );
}
