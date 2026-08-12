import { createI18n } from 'vue-i18n'
import el from '@/locales/el.json'
import en from '@/locales/en.json'
import it from '@/locales/it.json'
import de from '@/locales/de.json'

/**
 * Locale architecture
 * - `el.json` = reference key tree (canonical structure)
 * - `en.json` = complete fallback (every key must exist here)
 * - `it.json` / `de.json` = optional overrides; missing keys resolve to English
 *
 * Workflow: add new UI copy only in el + en. Other languages pick up `en` until translated.
 */
export const SUPPORTED_LOCALES = ['el', 'en', 'it', 'de'] as const
export type AppLocale = (typeof SUPPORTED_LOCALES)[number]

export const REFERENCE_LOCALE: AppLocale = 'el'
export const FALLBACK_LOCALE: AppLocale = 'en'

type MessageTree = Record<string, unknown>

function isObject(value: unknown): value is MessageTree {
  return !!value && typeof value === 'object' && !Array.isArray(value)
}

/** Deep-merge so partial locale files inherit English automatically. */
export function deepMergeMessages(base: MessageTree, overlay: MessageTree): MessageTree {
  const out: MessageTree = { ...base }
  for (const [key, value] of Object.entries(overlay)) {
    if (isObject(value) && isObject(out[key])) {
      out[key] = deepMergeMessages(out[key] as MessageTree, value)
    } else {
      out[key] = value
    }
  }
  return out
}

const savedRaw = localStorage.getItem('bo_locale') || REFERENCE_LOCALE
const saved = (SUPPORTED_LOCALES as readonly string[]).includes(savedRaw)
  ? (savedRaw as AppLocale)
  : REFERENCE_LOCALE

// Keep API X-Locale in sync with the UI locale (i18n may default before localStorage is set).
if (localStorage.getItem('bo_locale') !== saved) {
  localStorage.setItem('bo_locale', saved)
}

export const i18n = createI18n({
  legacy: false,
  locale: saved,
  fallbackLocale: FALLBACK_LOCALE,
  missingWarn: import.meta.env.DEV,
  fallbackWarn: false,
  messages: {
    // Reference locale inherits English for any unfinished Greek keys.
    el: deepMergeMessages(en as MessageTree, el as MessageTree),
    en: en as MessageTree,
    // Partials inherit English for any key not yet translated.
    it: deepMergeMessages(en as MessageTree, it as MessageTree),
    de: deepMergeMessages(en as MessageTree, de as MessageTree),
  },
} as any)
