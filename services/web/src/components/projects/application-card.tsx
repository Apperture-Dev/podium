import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Tag } from "@phosphor-icons/react/dist/ssr";
import type { Application } from "@/lib/api/applications";
import { APPLICATION_STATE_TONES } from "@/lib/application-state-tones";
import { TruncatedText } from "@/components/ui/truncated-text";

/** Dos iniciales: primera letra de las dos primeras palabras, o los dos primeros caracteres si el nombre es una sola palabra (kebab/snake case). */
function initialsOf(serviceName: string): string {
  const parts = serviceName.trim().split(/[\s_-]+/).filter(Boolean);
  if (parts.length === 0) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return parts
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

export function ApplicationCard({ application }: { application: Application }) {
  const tone = APPLICATION_STATE_TONES[application.state];
  return (
    <Card>
      <CardContent className="space-y-3 py-4 text-sm">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2 min-w-0">
            <Avatar size="lg" className="rounded-none after:rounded-none">
              <AvatarFallback className="rounded-none">
                {initialsOf(application.serviceName)}
              </AvatarFallback>
            </Avatar>
            <TruncatedText
              text={application.serviceName}
              render={<p className="text-base font-medium truncate" />}
            />
          </div>
          <Badge style={{ backgroundColor: tone.bg, color: tone.fg }}>
            {tone.label}
          </Badge>
        </div>
        <div>
          <p className="text-xs text-muted-foreground">Versión</p>
          <p className="flex items-center gap-1">
            <Tag className="size-3.5 shrink-0" />v{application.version}
          </p>
        </div>
        {application.hasPendingSourceChange && (
          <p className="text-xs text-muted-foreground">
            Hay un cambio en el repositorio pendiente de construir.
          </p>
        )}
      </CardContent>
    </Card>
  );
}
