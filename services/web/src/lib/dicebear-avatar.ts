/**
 * PREVIEW ONLY: "shape-grid" aún no está publicado en @dicebear/collection
 * (llega hasta 9.4.3, sin ese estilo), así que esto usa la API alojada de
 * DiceBear en vez del paquete local. Si el estilo no convence, basta con
 * borrar este archivo y deshacer su uso en application-card.
 *
 * `backgroundHex` es el color plano (sin "#") con el que DiceBear rellena el
 * fondo del SVG — se le pasa el `hex` de `avatarToneFor` para reusar la misma
 * paleta que el resto de avatares de la app.
 */
export function shapeGridAvatarUrl(seed: string, backgroundHex: string): string {
  const params = new URLSearchParams({
    seed,
    backgroundColor: backgroundHex,
  });
  return `https://api.dicebear.com/10.x/shape-grid/svg?${params.toString()}`;
}
