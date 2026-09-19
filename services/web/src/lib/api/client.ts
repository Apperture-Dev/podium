import { getAccessToken } from "./auth";

export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8090";

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status?: number,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

async function authenticatedFetch(
  path: string,
  init: RequestInit,
  retrying = false,
): Promise<Response> {
  const token = await getAccessToken();
  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      headers: {
        ...init.headers,
        Authorization: `Bearer ${token}`,
      },
    });
  } catch {
    throw new ApiError(
      "No se pudo conectar con el servidor. Comprueba que el backend está disponible.",
    );
  }

  // Token may have expired between requests; refresh once and retry.
  if (response.status === 401 && !retrying) {
    await getAccessToken(true);
    return authenticatedFetch(path, init, true);
  }

  return response;
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
  const response = await authenticatedFetch(path, { method: "GET" });
  if (!response.ok) {
    throw new ApiError(await parseErrorMessage(response), response.status);
  }
  return (await response.json()) as TResponse;
}

export async function postJson<TResponse>(
  path: string,
  body: unknown,
): Promise<TResponse> {
  const response = await authenticatedFetch(path, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  if (!response.ok) {
    throw new ApiError(await parseErrorMessage(response), response.status);
  }
  return (await response.json()) as TResponse;
}
