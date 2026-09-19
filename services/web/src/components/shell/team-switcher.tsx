"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { CaretUpDown, Check, Plus } from "@phosphor-icons/react";
import { Button } from "@/components/ui/button";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from "@/components/ui/command";
import { useTeam } from "@/lib/team-context";

export function TeamSwitcher() {
  const router = useRouter();
  const { teams, activeTeam, setActiveTeamId } = useTeam();
  const [open, setOpen] = useState(false);

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger
        render={<Button variant="outline" className="w-[150px] justify-between" />}
      >
        <span className="truncate">{activeTeam?.name ?? "Sin equipo"}</span>
        <CaretUpDown className="size-4 shrink-0 opacity-60" />
      </PopoverTrigger>
      <PopoverContent align="start" className="w-[220px] p-0">
        <Command>
          <CommandInput placeholder="Buscar equipo..." />
          <CommandList>
            <CommandEmpty>Sin resultados.</CommandEmpty>
            <CommandGroup>
              {teams.map((team) => (
                <CommandItem
                  key={team.id}
                  value={team.name}
                  onSelect={() => {
                    setActiveTeamId(team.id);
                    setOpen(false);
                  }}
                >
                  <span className="flex-1 truncate">{team.name}</span>
                  {team.id === activeTeam?.id && <Check className="size-4" />}
                </CommandItem>
              ))}
            </CommandGroup>
            <CommandSeparator />
            <CommandGroup>
              <CommandItem
                onSelect={() => {
                  setOpen(false);
                  router.push("/teams/new");
                }}
              >
                <Plus className="size-4" />
                Create team
              </CommandItem>
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}
