import { GitBranch } from "@phosphor-icons/react/dist/ssr";
import { Card, CardContent } from "@/components/ui/card";
import type { Deployment } from "@/lib/fixtures/project-detail";
import { formatRelativeTime } from "@/lib/format";

export function DeploymentCard({ deployment }: { deployment: Deployment }) {
  return (
    <Card className="overflow-hidden py-0">
      <div className="flex h-40 items-center justify-center border-b bg-muted">
        <div className="w-4/5 space-y-2">
          <div className="h-2.5 w-2/3 bg-muted-foreground/20" />
          <div className="h-2 w-1/2 bg-muted-foreground/20" />
        </div>
      </div>
      <CardContent className="space-y-3 py-4 text-sm">
        <div>
          <p className="text-xs text-muted-foreground">Despliegue</p>
          <p className="font-medium">{deployment.subdomain}</p>
        </div>
        <div>
          <p className="text-xs text-muted-foreground">Dominios</p>
          <p>{deployment.domain}</p>
        </div>
        <div>
          <p className="text-xs text-muted-foreground">Framework</p>
          <p>{deployment.framework}</p>
        </div>
        <div className="flex justify-between">
          <div>
            <p className="text-xs text-muted-foreground">Estado</p>
            <div className="flex items-center gap-1.5">
              <span className="size-2 bg-foreground/70" />
              <span>{deployment.status}</span>
            </div>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Creado</p>
            <p>
              {formatRelativeTime(deployment.createdAt)} · {deployment.author}
            </p>
          </div>
        </div>
        <div>
          <p className="text-xs text-muted-foreground">Origen</p>
          <p className="flex items-center gap-1">
            <GitBranch className="size-3.5" /> {deployment.branch}
          </p>
          <p className="text-xs text-muted-foreground truncate">
            {deployment.commitSha} · {deployment.commitMessage}
          </p>
        </div>
      </CardContent>
    </Card>
  );
}
