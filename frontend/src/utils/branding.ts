type ThemeLike = {
  themes: {
    value: {
      beach: {
        colors: Record<string, string>
      }
    }
  }
}

export type BrandingColors = {
  primary_color?: string
  accent_color?: string
  secondary_color?: string
  [key: string]: string | undefined
}

/** Platform defaults (landing, auth, platform admin). */
export const PLATFORM_PRIMARY = '#0B6E6B'
export const PLATFORM_PRIMARY_DEEP = '#084e4c'
export const PLATFORM_ACCENT = '#E07A5F'
export const PLATFORM_FOAM = '#e8f4f3'
export const PLATFORM_INK = '#143642'

const WHITE = '#FFFFFF'

type Rgb = { r: number; g: number; b: number }

function parseHex(input: string): Rgb | null {
  const raw = input.trim().replace('#', '')
  const hex =
    raw.length === 3
      ? raw
          .split('')
          .map((c) => c + c)
          .join('')
      : raw
  if (!/^[0-9a-fA-F]{6}$/.test(hex)) return null
  return {
    r: parseInt(hex.slice(0, 2), 16),
    g: parseInt(hex.slice(2, 4), 16),
    b: parseInt(hex.slice(4, 6), 16),
  }
}

function toHex({ r, g, b }: Rgb): string {
  const h = (n: number) => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2, '0')
  return `#${h(r)}${h(g)}${h(b)}`
}

function channelLuminance(c: number): number {
  const s = c / 255
  return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
}

/** WCAG relative luminance 0–1. */
export function relativeLuminance(hex: string): number {
  const rgb = parseHex(hex)
  if (!rgb) return 0
  return (
    0.2126 * channelLuminance(rgb.r) +
    0.7152 * channelLuminance(rgb.g) +
    0.0722 * channelLuminance(rgb.b)
  )
}

export function isLightColor(hex: string, threshold = 0.62): boolean {
  return relativeLuminance(hex) >= threshold
}

function mix(a: Rgb, b: Rgb, t: number): Rgb {
  return {
    r: a.r + (b.r - a.r) * t,
    g: a.g + (b.g - a.g) * t,
    b: a.b + (b.b - a.b) * t,
  }
}

function mixHex(a: string, b: string, t: number): string {
  const aa = parseHex(a)
  const bb = parseHex(b)
  if (!aa || !bb) return a
  return toHex(mix(aa, bb, t))
}

/** Darken until the color is safe as text on light surfaces. */
export function ensureReadableOnLight(hex: string, maxLuminance = 0.38): string {
  const parsed = parseHex(hex)
  if (!parsed) return PLATFORM_INK

  let color = toHex(parsed)
  if (relativeLuminance(color) <= maxLuminance) return color

  // Very light / white brands: pull toward ink so headings stay visible.
  for (let t = 0.35; t <= 0.95; t += 0.08) {
    color = mixHex(hex, PLATFORM_INK, t)
    if (relativeLuminance(color) <= maxLuminance) return color
  }
  return PLATFORM_INK
}

function onColor(bg: string): string {
  return isLightColor(bg, 0.55) ? PLATFORM_INK : WHITE
}

/** Soft page wash derived from brand (always stays light). */
function foamFromPrimary(primary: string): string {
  if (isLightColor(primary, 0.85)) return mixHex(primary, WHITE, 0.35)
  return mixHex(primary, WHITE, 0.88)
}

function paintTheme(
  theme: ThemeLike,
  opts: {
    primary: string
    primaryDeep: string
    accent: string
    foam: string
  },
) {
  const { primary, primaryDeep, accent, foam } = opts

  theme.themes.value.beach.colors.primary = primary
  theme.themes.value.beach.colors.secondary = primary
  theme.themes.value.beach.colors.accent = accent
  theme.themes.value.beach.colors['on-primary'] = onColor(primary)
  theme.themes.value.beach.colors['on-secondary'] = onColor(primary)
  theme.themes.value.beach.colors['on-accent'] = onColor(accent)
  theme.themes.value.beach.colors.background = foam
  theme.themes.value.beach.colors.surface = WHITE

  document.documentElement.style.setProperty('--bo-teal', primary)
  document.documentElement.style.setProperty('--bo-teal-deep', primaryDeep)
  document.documentElement.style.setProperty('--bo-coral', accent)
  document.documentElement.style.setProperty('--bo-foam', foam)
  document.documentElement.style.setProperty('--bo-ink', PLATFORM_INK)
}

/** Restore platform look (landing / auth / platform admin). */
export function resetBrandingTheme(theme: ThemeLike) {
  paintTheme(theme, {
    primary: PLATFORM_PRIMARY,
    primaryDeep: PLATFORM_PRIMARY_DEEP,
    accent: PLATFORM_ACCENT,
    foam: PLATFORM_FOAM,
  })
}

/** Apply tenant white-label colors to Vuetify theme + CSS variables. */
export function applyBrandingTheme(
  theme: ThemeLike,
  branding?: BrandingColors | Record<string, string> | null,
) {
  if (!branding) {
    resetBrandingTheme(theme)
    return
  }

  const primary = branding.primary_color || PLATFORM_PRIMARY
  const accent = branding.accent_color || branding.secondary_color || PLATFORM_ACCENT

  // Text/icons on light backgrounds must never inherit pure white/pastel.
  const primaryDeep = ensureReadableOnLight(primary)
  const accentSafe = ensureReadableOnLight(accent, 0.45)
  // Buttons need presence: if brand is white/near-white, use the deep variant.
  const primaryAction = isLightColor(primary, 0.78) ? primaryDeep : primary
  const accentAction = isLightColor(accent, 0.78) ? accentSafe : accent

  paintTheme(theme, {
    primary: primaryAction,
    primaryDeep,
    accent: accentAction,
    foam: foamFromPrimary(primary),
  })
}

import { APP_NAME } from '@/config/brand'

const DEFAULT_FAVICON = '/favicon.svg'
const DEFAULT_TITLE = APP_NAME

/** Set document title and favicon for tenant white-label surfaces. */
export function applyPageBranding(
  name?: string | null,
  faviconUrl?: string | null,
  platformFallback = true,
) {
  document.title = name?.trim() ? name.trim() : platformFallback ? DEFAULT_TITLE : ''

  let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }

  const raw = faviconUrl?.trim() || (platformFallback ? DEFAULT_FAVICON : '')
  if (!raw) return

  // Prefer same-origin /storage paths when the API returned an absolute local URL.
  let href = raw
  try {
    if (!raw.startsWith('/') && !raw.startsWith('data:')) {
      const parsed = new URL(raw)
      if (parsed.pathname.startsWith('/storage/')) {
        href = `${parsed.pathname}${parsed.search}`
      }
    }
  } catch {
    // keep raw
  }

  link.href = href
  link.type = href.endsWith('.svg') ? 'image/svg+xml' : 'image/png'
}

export function resetPageBranding() {
  applyPageBranding(null, null)
}
