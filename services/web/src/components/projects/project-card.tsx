import Link from "next/link";
import { Card, CardContent, CardFooter, CardHeader } from "@/components/ui/card";
import { Clock, Code, CubeFocus, Tag } from "@phosphor-icons/react/dist/ssr";
import type { Project } from "@/lib/api/projects";
import type { Application } from "@/lib/api/applications";
import { avatarToneFor } from "@/lib/avatar-tones";
import { relativeTimeFromNow } from "@/lib/relative-time";

/** {hash}.apperture.dev is the real URL convention (see hackathon-plan). No domain field on Project yet. */
function primaryDomain(project: Project): string {
  return `${project.hash}.apperture.dev`;
}

export function ProjectCard({
  project,
  application,
  index,
}: {
  project: Project;
  /** Primer Application del Project — representa framework y versión en la card (ver Figma). */
  application?: Application;
  index: number;
}) {
  const tone = avatarToneFor(index);
  return (
    <Link href={`/projects/${project.id}`}>
      <Card className="h-full border border-dashed border-foreground/20 ring-0 transition-colors hover:bg-accent/40">
        <CardHeader className="flex-row items-start gap-3">
          <div
            className="flex size-10 shrink-0 items-center justify-center"
            style={{ backgroundColor: tone.bg }}
          >
            <CubeFocus className="size-5" style={{ color: tone.fg }} />
          </div>
          <div className="min-w-0">
            <h3 className="text-base font-medium truncate">{project.name}</h3>
            <p className="text-xs text-muted-foreground truncate">
              {primaryDomain(project)}
            </p>
          </div>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground truncate">
            {project.repositoryUrl.replace(/^https?:\/\//, "")}
          </p>
        </CardContent>
        <CardFooter className="justify-between gap-3 border-t bg-transparent pt-3 text-xs text-muted-foreground">
          <div className="flex min-w-0 items-center gap-3">
            {application && (
              <>
                <span className="flex items-center gap-1 truncate">
                  <Code className="size-3.5 shrink-0" />
                  <span className="truncate">{application.framework}</span>
                </span>
                <span className="flex shrink-0 items-center gap-1">
                  <Tag className="size-3.5 shrink-0" />v{application.version}
                </span>
              </>
            )}
          </div>
          <span className="flex shrink-0 items-center gap-1">
            <Clock className="size-3.5 shrink-0" />
            {relativeTimeFromNow(project.createdAt)}
          </span>
        </CardFooter>
      </Card>
    </Link>
  );
}
