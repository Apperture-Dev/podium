/** Formats a past ISO timestamp as a short Spanish relative time, e.g. "hace 2 h". */
export function formatRelativeTime(iso: string, now: Date = new Date()): string {
  const then = new Date(iso);
  const diffMs = now.getTime() - then.getTime();
  const diffMinutes = Math.floor(diffMs / (60 * 1000));

  if (diffMinutes < 1) return "hace instantes";
  if (diffMinutes < 60) return `hace ${diffMinutes} min`;

  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `hace ${diffHours} h`;

  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 7) return `hace ${diffDays} d`;

  const diffWeeks = Math.floor(diffDays / 7);
  return `hace ${diffWeeks} sem`;
}
