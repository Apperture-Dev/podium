export type Deployment = {
  id: string;
  subdomain: string;
  domain: string;
  framework: string;
  status: "Desplegado" | "Construyendo" | "Fallido";
  createdAt: string;
  author: string;
  branch: string;
  commitSha: string;
  commitMessage: string;
};

export type ProjectDetail = {
  id: string;
  status: "Desplegado" | "Construyendo" | "Fallido";
  version: string;
  frameworks: string[];
  updatedAt: string;
  primaryDomain: string;
  deployments: Deployment[];
};

function hoursAgo(hours: number): string {
  return new Date(Date.now() - hours * 60 * 60 * 1000).toISOString();
}

export const FIXTURE_PROJECT_DETAILS: Record<string, ProjectDetail> = {
  "api-gateway": {
    id: "api-gateway",
    status: "Desplegado",
    version: "v2.3.1",
    frameworks: ["Node.js", "PHP"],
    updatedAt: hoursAgo(2),
    primaryDomain: "project-hz852g2z0.app",
    deployments: [
      {
        id: "api-gateway-api",
        subdomain: "api.8f2a91c.hostium.app",
        domain: "api-gateway.hostium.app",
        framework: "PHP",
        status: "Desplegado",
        createdAt: hoursAgo(2),
        author: "peter-parker",
        branch: "main",
        commitSha: "a3f91c2",
        commitMessage: "Merge pull request #48 from feature/rate-limit",
      },
      {
        id: "api-gateway-app",
        subdomain: "app.8f2a91c.hostium.app",
        domain: "api-gateway.hostium.app",
        framework: "Node.js",
        status: "Desplegado",
        createdAt: hoursAgo(2),
        author: "mary-jane",
        branch: "main",
        commitSha: "a3f91c2",
        commitMessage: "Merge pull request #48 from feature/rate-limit",
      },
    ],
  },
  "web-dashboard": {
    id: "web-dashboard",
    status: "Desplegado",
    version: "v1.8.0",
    frameworks: ["TypeScript"],
    updatedAt: hoursAgo(5),
    primaryDomain: "dashboard.hostium.app",
    deployments: [
      {
        id: "web-dashboard-app",
        subdomain: "app.3c71b0e.hostium.app",
        domain: "dashboard.hostium.app",
        framework: "TypeScript",
        status: "Desplegado",
        createdAt: hoursAgo(5),
        author: "tony-stark",
        branch: "main",
        commitSha: "3c71b0e",
        commitMessage: "Fix client-side pagination bug",
      },
    ],
  },
  "billing-service": {
    id: "billing-service",
    status: "Desplegado",
    version: "v0.9.4",
    frameworks: ["Go"],
    updatedAt: hoursAgo(24),
    primaryDomain: "billing.hostium.app",
    deployments: [
      {
        id: "billing-service-app",
        subdomain: "app.71dd402.hostium.app",
        domain: "billing.hostium.app",
        framework: "Go",
        status: "Desplegado",
        createdAt: hoursAgo(24),
        author: "natasha-romanoff",
        branch: "main",
        commitSha: "71dd402",
        commitMessage: "Add Stripe webhook signature verification",
      },
    ],
  },
  "infra-terraform": {
    id: "infra-terraform",
    status: "Desplegado",
    version: "v3.1.0",
    frameworks: ["HCL"],
    updatedAt: hoursAgo(72),
    primaryDomain: "infra.hostium.app",
    deployments: [
      {
        id: "infra-terraform-app",
        subdomain: "app.9b1a44f.hostium.app",
        domain: "infra.hostium.app",
        framework: "HCL",
        status: "Desplegado",
        createdAt: hoursAgo(72),
        author: "bruce-banner",
        branch: "main",
        commitSha: "9b1a44f",
        commitMessage: "Bump node pool to 3 replicas",
      },
    ],
  },
  "design-system": {
    id: "design-system",
    status: "Desplegado",
    version: "v4.2.0",
    frameworks: ["TypeScript"],
    updatedAt: hoursAgo(6),
    primaryDomain: "ui.hostium.app",
    deployments: [
      {
        id: "design-system-app",
        subdomain: "app.5e02ac1.hostium.app",
        domain: "ui.hostium.app",
        framework: "TypeScript",
        status: "Desplegado",
        createdAt: hoursAgo(6),
        author: "wanda-maximoff",
        branch: "main",
        commitSha: "5e02ac1",
        commitMessage: "Publish Button v4 with sharp-corner variant",
      },
    ],
  },
  "docs-site": {
    id: "docs-site",
    status: "Desplegado",
    version: "v1.0.2",
    frameworks: ["Markdown"],
    updatedAt: hoursAgo(168),
    primaryDomain: "docs.hostium.app",
    deployments: [
      {
        id: "docs-site-app",
        subdomain: "app.1a0c88b.hostium.app",
        domain: "docs.hostium.app",
        framework: "Markdown",
        status: "Desplegado",
        createdAt: hoursAgo(168),
        author: "steve-rogers",
        branch: "main",
        commitSha: "1a0c88b",
        commitMessage: "Document the podium.yaml secrets convention",
      },
    ],
  },
  "tv-agent-backend": {
    id: "tv-agent-backend",
    status: "Construyendo",
    version: "v0.4.0",
    frameworks: ["Python"],
    updatedAt: hoursAgo(1),
    primaryDomain: "tv-agent.hostium.app",
    deployments: [
      {
        id: "tv-agent-backend-app",
        subdomain: "app.d40e2f1.hostium.app",
        domain: "tv-agent.hostium.app",
        framework: "Python",
        status: "Construyendo",
        createdAt: hoursAgo(1),
        author: "carol-danvers",
        branch: "main",
        commitSha: "d40e2f1",
        commitMessage: "Wire Token Factory client for recommendations",
      },
    ],
  },
  "vonage-webhook": {
    id: "vonage-webhook",
    status: "Desplegado",
    version: "v0.2.1",
    frameworks: ["Node.js"],
    updatedAt: hoursAgo(9),
    primaryDomain: "vonage-webhook.hostium.app",
    deployments: [
      {
        id: "vonage-webhook-app",
        subdomain: "app.6f3bb9d.hostium.app",
        domain: "vonage-webhook.hostium.app",
        framework: "Node.js",
        status: "Desplegado",
        createdAt: hoursAgo(9),
        author: "sam-wilson",
        branch: "main",
        commitSha: "6f3bb9d",
        commitMessage: "Handle inbound SMS webhook payload",
      },
    ],
  },
};
