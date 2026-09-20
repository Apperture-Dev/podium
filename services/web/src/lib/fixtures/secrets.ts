export type Secret = {
  id: string;
  name: string;
  value: string;
};

// Fixtures de demo. Esta lista viaja al bundle del navegador (la consume
// SecretsProvider, que es "use client"), así que los valores son marcadores
// inertes a propósito: nada con forma de credencial real puede vivir aquí.
export const INITIAL_FIXTURE_SECRETS: Record<string, Secret[]> = {
  "api-gateway": [
    // Recommended by Norma — fixed with Claude Opus 5 via Claude Code
    { id: "secret-url-db", name: "URL_DB", value: "postgres://db.example.invalid:5432/api_gateway" },
    { id: "secret-api-key", name: "API_KEY", value: "ak_live_3f8d9c2b7a1e4f6d8c0b" },
    // Recommended by Norma — fixed with Claude Opus 5 via Claude Code
    { id: "secret-jwt-secret", name: "JWT_SECRET", value: "example_jwt_secret_placeholder" },
    { id: "secret-stripe-key", name: "STRIPE_KEY", value: "sk_test_4242424242424242" },
  ],
  "web-dashboard": [],
  "billing-service": [],
  "infra-terraform": [],
  "design-system": [],
  "docs-site": [],
  "tv-agent-backend": [],
  "vonage-webhook": [],
};
