export type Secret = {
  id: string;
  name: string;
  value: string;
};

export const INITIAL_FIXTURE_SECRETS: Record<string, Secret[]> = {
  "api-gateway": [
    { id: "secret-url-db", name: "URL_DB", value: "postgres://hostium:s3cr3t@10.0.4.12:5432/api_gateway" },
    { id: "secret-api-key", name: "API_KEY", value: "ak_live_3f8d9c2b7a1e4f6d8c0b" },
    { id: "secret-jwt-secret", name: "JWT_SECRET", value: "sk_live_9f2a7c31d8e4b6a0c2f5b8e2x91b" },
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
