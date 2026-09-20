import { Card, CardContent, CardFooter } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Clock, Code, Tag } from "@phosphor-icons/react/dist/ssr";
import type { Application } from "@/lib/api/applications";
import { APPLICATION_STATE_TONES } from "@/lib/application-state-tones";
import { avatarToneFor } from "@/lib/avatar-tones";
import { TruncatedText } from "@/components/ui/truncated-text";
import { relativeTimeFromNow } from "@/lib/relative-time";
import { shapeGridAvatarUrl } from "@/lib/dicebear-avatar";

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

export function ApplicationCard({
  application,
  index,
}: {
  application: Application;
  index: number;
}) {
  const stateTone = APPLICATION_STATE_TONES[application.state];
  const avatarTone = avatarToneFor(index);
  return (
    <Card>
      <CardContent className="space-y-3 py-4 text-sm">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2 min-w-0">
            <Avatar size="lg">
              <AvatarImage
                src={shapeGridAvatarUrl(application.serviceName, avatarTone.hex)}
                alt=""
              />
              <AvatarFallback>
                {initialsOf(application.serviceName)}
              </AvatarFallback>
            </Avatar>
            <TruncatedText
              text={application.serviceName}
              render={<p className="text-base font-medium truncate" />}
            />
          </div>
          <Badge style={{ backgroundColor: stateTone.bg, color: stateTone.fg }}>
            {stateTone.label}
          </Badge>
        </div>
        {application.hasPendingSourceChange && (
          <p className="text-xs text-muted-foreground">
            Hay un cambio en el repositorio pendiente de construir.
          </p>
        )}
      </CardContent>
      <CardFooter className="justify-between gap-3 border-t bg-transparent pt-3 text-xs text-muted-foreground">
        <div className="flex min-w-0 items-center gap-3">
          <span className="flex items-center gap-1 truncate">
            <Code className="size-3.5 shrink-0" />
            <span className="truncate">{application.framework}</span>
          </span>
          <span className="flex shrink-0 items-center gap-1">
            <Tag className="size-3.5 shrink-0" />v{application.version}
          </span>
        </div>
        <span className="flex shrink-0 items-center gap-1">
          <Clock className="size-3.5 shrink-0" />
          {relativeTimeFromNow(application.createdAt)}
        </span>
      </CardFooter>
    </Card>
  );
}
