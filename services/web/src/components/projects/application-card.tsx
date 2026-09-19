import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import type { Application } from "@/lib/api/applications";

const STATE_LABEL: Record<Application["state"], string> = {
  Registered: "Registrado",
  Building: "Construyendo",
  BuildFailed: "Build fallido",
  Deployed: "Desplegado",
  DeployFailed: "Deploy fallido",
};

export function ApplicationCard({ application }: { application: Application }) {
  return (
    <Card>
      <CardContent className="space-y-3 py-4 text-sm">
        <div className="flex items-center justify-between">
          <p className="font-medium">{application.serviceName}</p>
          <Badge variant="secondary">{STATE_LABEL[application.state]}</Badge>
        </div>
        <div>
          <p className="text-xs text-muted-foreground">Versión</p>
          <p>{application.version}</p>
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
