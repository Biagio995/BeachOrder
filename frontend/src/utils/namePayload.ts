/** Build DB name map from EL/EN admin inputs (at least one required by UI). */
export function buildNamePayload(nameEl: string, nameEn: string): Record<string, string> {
  const name: Record<string, string> = {}
  const el = nameEl.trim()
  const en = nameEn.trim()
  if (el) name.el = el
  if (en) name.en = en
  return name
}

export function hasPrimaryName(nameEl: string, nameEn: string): boolean {
  return nameEl.trim().length > 0 || nameEn.trim().length > 0
}

/** Prefill edit form from stored i18n map. */
export function pickNameFields(name?: Record<string, string> | string | null): {
  name_el: string
  name_en: string
} {
  if (!name || typeof name === 'string') {
    return { name_el: typeof name === 'string' ? name : '', name_en: '' }
  }
  return {
    name_el: name.el || '',
    name_en: name.en || '',
  }
}
