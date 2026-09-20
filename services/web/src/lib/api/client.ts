/**
 * Always same-origin — these hit this Next.js app's own `/api/*` Route
 * Handlers, which read the httpOnly session cookie (see lib/auth/session.ts)
 * and proxy to the Symfony API with the Bearer token attached server-side.
 * The JWT never reaches the browser, so there's nothing to attach here and
 * no CORS gap to work around.
 */
export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status?: number,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

async function parseErrorMessage(response: Response): Promise<string> {
  const payload = await response.json().catch(() => null);
  return (
    (payload && typeof payload === "object" && "error" in payload
      ? String((payload as { error: unknown }).error)
      : undefined) ?? `La solicitud falló (${response.status}).`
  );
}

export async function getJson<TResponse>(path: string): Promise<TResponse> {
  let response: Response;
  try {
    response = await fetch(path, { cache: "no-store" });
  } catch {
    throw new ApiError(
      "No se pudo conectar con el servidor. Comprueba que el backend está disponible.",
    );
  }
  if (!response.ok) {
    throw new ApiError(await parseErrorMessage(response), response.status);
  }
  try {
    return (await response.json()) as TResponse;
  } catch {
    throw new ApiError(
      "La API devolvió una respuesta que no es JSON válido.",
      response.status,
    );
  }
}

export async function postJson<TResponse>(
  path: string,
  body: unknown,
): Promise<TResponse> {
  let response: Response;
  try {
    response = await fetch(path, {
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
    throw new ApiError(await parseErrorMessage(response), response.status);
  }
  try {
    return (await response.json()) as TResponse;
  } catch {
    throw new ApiError(
      "La API devolvió una respuesta que no es JSON válido.",
      response.status,
    );
  }
}
