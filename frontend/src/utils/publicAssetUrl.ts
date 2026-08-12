/**
 * Normalize public upload URLs so images load through the same origin
 * as the SPA (Vite/nginx `/storage` proxy), not a hard-coded APP_URL host.
 */
export function resolvePublicAssetUrl(url?: string | null): string | null {
  if (!url?.trim()) return null
  const trimmed = url.trim()

  if (trimmed.startsWith('/storage/') || trimmed.startsWith('data:') || trimmed.startsWith('blob:')) {
    return trimmed
  }

  try {
    const parsed = new URL(trimmed, typeof window !== 'undefined' ? window.location.origin : 'http://localhost')
    if (parsed.pathname.startsWith('/storage/')) {
      return `${parsed.pathname}${parsed.search}`
    }
  } catch {
    // keep original
  }

  return trimmed
}
