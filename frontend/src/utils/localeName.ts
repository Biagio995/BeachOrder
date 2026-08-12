/** Prefer locale label from i18n map, with sensible fallbacks.
 *  Catalog maps only store primary locales (el/en); other locales come from the API as resolved strings.
 */
export function displayLocalizedName(
  name: string | Record<string, string> | null | undefined,
  locale = 'el',
  fallbackLocales: string[] = ['el', 'en'],
): string {
  if (!name) return ''
  if (typeof name === 'string') return name

  const preferred = name[locale]
  if (preferred) return preferred

  for (const code of fallbackLocales) {
    if (name[code]) return name[code]
  }

  return Object.values(name).find(Boolean) || ''
}
