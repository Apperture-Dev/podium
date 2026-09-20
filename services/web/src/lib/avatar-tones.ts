export type AvatarTone = { bg: string; fg: string; hex: string };

/**
 * Same base hue as the theme (185, teal) plus 5 hues spread around the wheel.
 * `hex` is the sRGB equivalent of `bg` — oklch() can't be sent as a URL query
 * param (e.g. to the DiceBear avatar API), so it's precomputed here.
 */
const AVATAR_TONES: AvatarTone[] = [
  { bg: "oklch(0.93 0.045 185)", fg: "oklch(0.45 0.13 185)", hex: "c7f2ec" }, // teal
  { bg: "oklch(0.93 0.045 264)", fg: "oklch(0.5 0.15 264)", hex: "d9e8ff" }, // indigo
  { bg: "oklch(0.93 0.045 305)", fg: "oklch(0.5 0.15 305)", hex: "efe1ff" }, // violet
  { bg: "oklch(0.93 0.05 20)", fg: "oklch(0.55 0.15 20)", hex: "ffdbda" }, // rose
  { bg: "oklch(0.93 0.06 75)", fg: "oklch(0.55 0.14 75)", hex: "ffe3bc" }, // amber
  { bg: "oklch(0.93 0.05 140)", fg: "oklch(0.5 0.14 140)", hex: "d7f1d1" }, // lime
];

/**
 * Tone by position in the project list (creation order), not a hash of the
 * id — a hash lets two nearby projects land on the same tone by chance.
 * Cycling by index guarantees all 6 tones appear before any repeats.
 */
export function avatarToneFor(index: number): AvatarTone {
  return AVATAR_TONES[index % AVATAR_TONES.length];
}
