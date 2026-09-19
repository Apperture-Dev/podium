import Link from "next/link";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { CubeFocus } from "@phosphor-icons/react/dist/ssr";
import type { ProjectSummary } from "@/lib/fixtures/projects";
import { formatRelativeTime } from "@/lib/format";

export function ProjectCard({ project }: { project: ProjectSummary }) {
  return (
    <Link href={`/projects/${project.id}`}>
      <Card className="h-full transition-colors hover:bg-accent/40">
        <CardHeader className="flex-row items-start gap-3">
          <div className="flex size-10 shrink-0 items-center justify-center bg-muted">
            <CubeFocus className="size-5" />
          </div>
          <div className="min-w-0">
            <h3 className="text-base font-medium truncate">{project.name}</h3>
            <p className="text-xs text-muted-foreground truncate">
              {project.domain}
            </p>
          </div>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground line-clamp-2 min-h-10">
            {project.description}
          </p>
          <div className="flex items-center justify-between border-t pt-3 mt-3 text-xs">
            <div className="flex items-center gap-1.5">
              <span className="size-2 bg-foreground/70" />
              <span>{project.language}</span>
            </div>
            <span className="text-muted-foreground">
              {project.version}
            </span>
            <span className="text-muted-foreground">
              {formatRelativeTime(project.updatedAt)}
            </span>
          </div>
        </CardContent>
      </Card>
    </Link>
  );
}
