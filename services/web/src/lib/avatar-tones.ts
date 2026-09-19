export type AvatarTone = { bg: string; fg: string };

/** Same base hue as the theme (185, teal) plus 5 hues spread around the wheel. */
const AVATAR_TONES: AvatarTone[] = [
  { bg: "oklch(0.93 0.045 185)", fg: "oklch(0.45 0.13 185)" }, // teal
  { bg: "oklch(0.93 0.045 264)", fg: "oklch(0.5 0.15 264)" }, // indigo
  { bg: "oklch(0.93 0.045 305)", fg: "oklch(0.5 0.15 305)" }, // violet
  { bg: "oklch(0.93 0.05 20)", fg: "oklch(0.55 0.15 20)" }, // rose
  { bg: "oklch(0.93 0.06 75)", fg: "oklch(0.55 0.14 75)" }, // amber
  { bg: "oklch(0.93 0.05 140)", fg: "oklch(0.5 0.14 140)" }, // lime
];

/**
 * Tone by position in the project list (creation order), not a hash of the
 * id — a hash lets two nearby projects land on the same tone by chance.
 * Cycling by index guarantees all 6 tones appear before any repeats.
 */
export function avatarToneFor(index: number): AvatarTone {
  return AVATAR_TONES[index % AVATAR_TONES.length];
}
