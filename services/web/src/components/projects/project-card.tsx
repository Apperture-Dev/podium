import Link from "next/link";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { CubeFocus } from "@phosphor-icons/react/dist/ssr";
import type { Project } from "@/lib/api/projects";
import { avatarToneFor } from "@/lib/avatar-tones";

/** {hash}.apperture.dev is the real URL convention (see hackathon-plan). No domain field on Project yet. */
function primaryDomain(project: Project): string {
  return `${project.hash}.apperture.dev`;
}

export function ProjectCard({ project }: { project: Project }) {
  const tone = avatarToneFor(project.id);
  return (
    <Link href={`/projects/${project.id}`}>
      <Card className="h-full transition-colors hover:bg-accent/40">
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
      </Card>
    </Link>
  );
}
