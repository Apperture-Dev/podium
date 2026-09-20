import "server-only";
import { NextResponse } from "next/server";
import { SYMFONY_API_BASE_URL } from "@/lib/auth/config";
import { getAccessToken } from "@/lib/auth/session";

/**
 * Shared by every `app/api/**\/route.ts` GET proxy: attach the session
 * cookie's JWT as a Bearer header and forward to Symfony, same-origin in,
 * cross-origin out — the browser only ever sees this app's own domain.
 */
export async function proxyGet(symfonyPath: string): Promise<NextResponse> {
  const token = await getAccessToken();
  if (!token) {
    return NextResponse.json({ error: "No autenticado." }, { status: 401 });
  }

  let response: Response;
  try {
    response = await fetch(`${SYMFONY_API_BASE_URL}${symfonyPath}`, {
      headers: { Authorization: `Bearer ${token}` },
      cache: "no-store",
    });
  } catch {
    // La API no responde. Sin esto la excepción se escapa del route handler y
    // Next contesta un 500 sin cuerpo, que el cliente no sabe leer.
    return NextResponse.json(
      { error: "No se pudo contactar con la API." },
      { status: 502 },
    );
  }

  const payload = await response.json().catch(() => null);

  // Una respuesta correcta pero ilegible no puede viajar como éxito: el cliente
  // castea el cuerpo al tipo esperado, así que un `null` acabaría explotando en
  // pleno render, lejos de aquí y fuera de cualquier catch.
  if (response.ok && payload === null) {
    return NextResponse.json(
      { error: "La API devolvió una respuesta vacía o ilegible." },
      { status: 502 },
    );
  }

  return NextResponse.json(payload, { status: response.status });
}
