"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  FolderSimple,
  Rocket,
  UsersThree,
  Key,
  Gear,
  ArrowLineLeft,
} from "@phosphor-icons/react";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarTrigger,
} from "@/components/ui/sidebar";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { getCurrentUser, type CurrentUser } from "@/lib/api/me";

function displayNameOf(user: CurrentUser | null): string {
  if (!user) return "";
  const fullName = [user.givenName, user.familyName].filter(Boolean).join(" ");
  return user.name || fullName || user.preferredUsername || "";
}

function initialsOf(displayName: string): string {
  const parts = displayName.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "?";
  return parts
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

/** Reads the `[id]` segment out of `/projects/[id]` (and its sub-routes), if present. */
function useActiveProjectId(): string | null {
  const pathname = usePathname();
  const match = pathname.match(/^\/projects\/([^/]+)/);
  if (!match || match[1] === "new") return null;
  return match[1];
}

export function AppSidebar() {
  const pathname = usePathname();
  const activeProjectId = useActiveProjectId();
  const [user, setUser] = useState<CurrentUser | null>(null);

  useEffect(() => {
    let cancelled = false;
    getCurrentUser()
      .then((fetched) => {
        if (!cancelled) setUser(fetched);
      })
      .catch(() => {
        // Not logged in yet, or the fetch raced the login redirect — the
        // proxy will have already sent an unauthenticated visitor to
        // /login regardless, so this is safe to ignore.
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const displayName = displayNameOf(user);

  const secretsHref = activeProjectId
    ? `/projects/${activeProjectId}/secrets`
    : null;

  const navItems = [
    { label: "Proyectos", icon: FolderSimple, href: "/" as string | null },
    { label: "Despliegues", icon: Rocket, href: null },
    { label: "Equipo", icon: UsersThree, href: null },
    { label: "Secrets", icon: Key, href: secretsHref },
    { label: "Ajustes", icon: Gear, href: null },
  ];

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader className="flex-row items-center justify-between px-4 py-4">
        <Link href="/" className="flex items-center gap-2 font-heading font-semibold">
          <span className="flex h-7 w-7 items-center justify-center bg-primary text-primary-foreground text-sm">
            H
          </span>
          <span className="group-data-[collapsible=icon]:hidden">
            Hostium
          </span>
        </Link>
        <SidebarTrigger>
          <ArrowLineLeft className="size-4" />
        </SidebarTrigger>
      </SidebarHeader>
      <SidebarContent className="px-2">
        <SidebarMenu>
          {navItems.map((item) => {
            const isActive = item.href !== null && pathname === item.href;
            const isDisabled = item.href === null;
            return (
              <SidebarMenuItem key={item.label}>
                <SidebarMenuButton
                  isActive={isActive}
                  disabled={isDisabled}
                  aria-disabled={isDisabled}
                  title={isDisabled ? `${item.label} — próximamente` : item.label}
                  className={isDisabled ? "opacity-50 cursor-not-allowed" : undefined}
                  render={isDisabled ? undefined : <Link href={item.href!} />}
                >
                  <item.icon className="size-[18px]" />
                  <span>{item.label}</span>
                </SidebarMenuButton>
              </SidebarMenuItem>
            );
          })}
        </SidebarMenu>
      </SidebarContent>
      <SidebarFooter className="px-4 py-4">
        <div className="flex items-center gap-2 group-data-[collapsible=icon]:hidden">
          <Avatar className="size-9">
            <AvatarFallback>{initialsOf(displayName)}</AvatarFallback>
          </Avatar>
          <div className="text-sm leading-tight">
            <div className="font-medium">{displayName || "…"}</div>
            <div className="text-muted-foreground text-xs">
              {user?.preferredUsername ?? ""}
            </div>
          </div>
        </div>
      </SidebarFooter>
    </Sidebar>
  );
}
