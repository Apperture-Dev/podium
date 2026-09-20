import Link from "next/link";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { CubeFocus } from "@phosphor-icons/react/dist/ssr";
import type { Project } from "@/lib/api/projects";
import { avatarToneFor } from "@/lib/avatar-tones";
import { mockProjectDescription } from "@/lib/mock-project-description";
import { TruncatedText } from "@/components/ui/truncated-text";

/** {hash}.apperture.dev is the real URL convention (see hackathon-plan). No domain field on Project yet. */
function primaryDomain(project: Project): string {
  return `${project.hash}.apperture.dev`;
}

export function ProjectCard({
  project,
  index,
}: {
  project: Project;
  index: number;
}) {
  const tone = avatarToneFor(index);
  return (
    <Link href={`/projects/${project.id}`}>
      <Card className="h-full border border-foreground/20 ring-0 transition-colors hover:bg-accent/40">
        <CardHeader className="flex items-start gap-3">
          <div
            className="flex size-10 shrink-0 items-center justify-center rounded-lg"
            style={{ backgroundColor: tone.bg }}
          >
            <CubeFocus className="size-5" style={{ color: tone.fg }} />
          </div>
          <div className="min-w-0">
            <TruncatedText
              text={project.name}
              render={<h3 className="text-base font-medium truncate" />}
            />
            <TruncatedText
              text={primaryDomain(project)}
              render={<p className="text-sm text-muted-foreground truncate" />}
            />
          </div>
        </CardHeader>
        <CardContent>
          <p className="line-clamp-2 text-xs text-muted-foreground">
            {mockProjectDescription(index)}
          </p>
        </CardContent>
      </Card>
    </Link>
  );
}
