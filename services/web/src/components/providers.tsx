"use client";

import type { ReactNode } from "react";
import { TeamProvider } from "@/lib/team-context";
import { ProjectsProvider } from "@/lib/projects-context";
import { SecretsProvider } from "@/lib/secrets-context";
import { TooltipProvider } from "@/components/ui/tooltip";

export function Providers({ children }: { children: ReactNode }) {
  return (
    <TooltipProvider>
      <TeamProvider>
        <ProjectsProvider>
          <SecretsProvider>{children}</SecretsProvider>
        </ProjectsProvider>
      </TeamProvider>
    </TooltipProvider>
  );
}
