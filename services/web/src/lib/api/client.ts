/**
 * Base URL of the Symfony API. No CORS is configured on the backend yet
 * (separate change), so these calls will fail in a browser until it lands —
 * see design.md "Risks / Trade-offs".
 */
export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000";

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status?: number,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export async function postJson<TResponse>(
  path: string,
  body: unknown,
): Promise<TResponse> {
  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
  } catch {
    throw new ApiError(
      "No se pudo conectar con el servidor. Comprueba que el backend está disponible.",
    );
  }

  if (!response.ok) {
    const payload = await response.json().catch(() => null);
    const message =
      (payload && typeof payload === "object" && "error" in payload
        ? String((payload as { error: unknown }).error)
        : undefined) ?? `La solicitud falló (${response.status}).`;
    throw new ApiError(message, response.status);
  }

  return (await response.json()) as TResponse;
}
