import type { ApplicationState } from "@/lib/api/applications";

export type ApplicationStateTone = { label: string; bg: string; fg: string };

/** Same oklch approach as avatar-tones.ts; hue chosen per state, not reused from the avatar palette. */
export const APPLICATION_STATE_TONES: Record<ApplicationState, ApplicationStateTone> = {
  Created: { label: "Creado", bg: "oklch(0.93 0.07 95)", fg: "oklch(0.45 0.13 95)" }, // amarillo
  Building: { label: "Construyendo", bg: "oklch(0.93 0.045 240)", fg: "oklch(0.5 0.15 240)" }, // azul claro
  Built: { label: "Construido", bg: "oklch(0.35 0.12 240)", fg: "oklch(0.97 0.01 240)" }, // azul oscuro
  Deploying: { label: "Desplegando", bg: "oklch(0.93 0.05 150)", fg: "oklch(0.45 0.13 150)" }, // verde claro
  Deployed: { label: "Desplegado", bg: "oklch(0.72 0.14 150)", fg: "oklch(0.25 0.1 150)" }, // verde
  BuildFailed: { label: "Build fallido", bg: "oklch(0.2 0 0)", fg: "oklch(0.98 0 0)" }, // negro
  DeployFailed: { label: "Deploy fallido", bg: "oklch(0.58 0.19 25)", fg: "oklch(0.98 0.02 25)" }, // rojo
};
