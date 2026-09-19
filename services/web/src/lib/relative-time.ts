/** "hace 2 h", "hace 5 min" — formato compacto, sin dependencias externas. */
export function relativeTimeFromNow(isoDate: string): string {
  const elapsedSeconds = Math.max(
    0,
    (Date.now() - new Date(isoDate).getTime()) / 1000,
  );

  if (elapsedSeconds < 10) return "justo ahora";
  if (elapsedSeconds < 60) return `hace ${Math.floor(elapsedSeconds)} s`;
  if (elapsedSeconds < 3600) return `hace ${Math.floor(elapsedSeconds / 60)} min`;
  if (elapsedSeconds < 86400) return `hace ${Math.floor(elapsedSeconds / 3600)} h`;
  return `hace ${Math.floor(elapsedSeconds / 86400)} d`;
}
