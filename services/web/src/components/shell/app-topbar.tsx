import Link from "next/link";
import { Button } from "@/components/ui/button";
import { TeamSwitcher } from "@/components/shell/team-switcher";
import { AgentEntryPoint } from "@/components/shell/agent-entry-point";
import { logout } from "@/app/logout/actions";

// Stays a plain, non-async component: some pages that render AppShell (and
// therefore this) are Client Components (e.g. app/projects/[id]/secrets),
// which would drag any server-only import (cookies(), etc.) into the
// browser bundle and fail the build. `logout` is a Server Action, which is
// the one thing safe to import/call from either side of that boundary.
export function AppTopbar() {
  return (
    <header className="flex items-center justify-between gap-4 border-b px-8 py-4">
      <TeamSwitcher />
      <div className="flex items-center gap-3">
        <Button render={<Link href="/projects/new" />} nativeButton={false}>
          Nuevo proyecto
        </Button>
        <AgentEntryPoint />
        <form action={logout}>
          <Button type="submit" variant="ghost">
            Cerrar sesión
          </Button>
        </form>
      </div>
    </header>
  );
}
