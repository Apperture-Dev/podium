export type ProjectSummary = {
  id: string;
  teamId: string;
  name: string;
  description: string;
  domain: string;
  language: string;
  version: string;
  updatedAt: string;
};

function hoursAgo(hours: number): string {
  return new Date(Date.now() - hours * 60 * 60 * 1000).toISOString();
}

function daysAgo(days: number): string {
  return hoursAgo(days * 24);
}

export const FIXTURE_PROJECTS: ProjectSummary[] = [
  {
    id: "api-gateway",
    teamId: "team-a",
    name: "api-gateway",
    description: "Servicio central de enrutamiento para las APIs internas.",
    domain: "api-gateway.hostium.app",
    language: "Node.js",
    version: "v2.3.1",
    updatedAt: hoursAgo(2),
  },
  {
    id: "web-dashboard",
    teamId: "team-a",
    name: "web-dashboard",
    description: "Interfaz de administración para clientes.",
    domain: "dashboard.hostium.app",
    language: "TypeScript",
    version: "v1.8.0",
    updatedAt: hoursAgo(5),
  },
  {
    id: "billing-service",
    teamId: "team-a",
    name: "billing-service",
    description: "Procesamiento de pagos y suscripciones.",
    domain: "billing.hostium.app",
    language: "Go",
    version: "v0.9.4",
    updatedAt: daysAgo(1),
  },
  {
    id: "infra-terraform",
    teamId: "team-a",
    name: "infra-terraform",
    description: "Definiciones de infraestructura como código.",
    domain: "infra.hostium.app",
    language: "HCL",
    version: "v3.1.0",
    updatedAt: daysAgo(3),
  },
  {
    id: "design-system",
    teamId: "team-a",
    name: "design-system",
    description: "Componentes UI compartidos entre productos.",
    domain: "ui.hostium.app",
    language: "TypeScript",
    version: "v4.2.0",
    updatedAt: hoursAgo(6),
  },
  {
    id: "docs-site",
    teamId: "team-a",
    name: "docs-site",
    description: "Documentación pública del producto.",
    domain: "docs.hostium.app",
    language: "Markdown",
    version: "v1.0.2",
    updatedAt: daysAgo(7),
  },
  {
    id: "tv-agent-backend",
    teamId: "team-b",
    name: "tv-agent-backend",
    description: "Backend del agente conversacional de recomendación para TV.",
    domain: "tv-agent.hostium.app",
    language: "Python",
    version: "v0.4.0",
    updatedAt: hoursAgo(1),
  },
  {
    id: "vonage-webhook",
    teamId: "team-b",
    name: "vonage-webhook",
    description: "Recibe SMS y llamadas de Vonage y responde.",
    domain: "vonage-webhook.hostium.app",
    language: "Node.js",
    version: "v0.2.1",
    updatedAt: hoursAgo(9),
  },
];
