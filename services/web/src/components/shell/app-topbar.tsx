import Link from "next/link";
import { Button } from "@/components/ui/button";
import { TeamSwitcher } from "@/components/shell/team-switcher";
import { AgentEntryPoint } from "@/components/shell/agent-entry-point";

export function AppTopbar() {
  return (
    <header className="flex items-center justify-between gap-4 border-b px-8 py-4">
      <TeamSwitcher />
      <div className="flex items-center gap-3">
        <Button render={<Link href="/projects/new" />} nativeButton={false}>
          Nuevo proyecto
        </Button>
        <AgentEntryPoint />
      </div>
    </header>
  );
}
