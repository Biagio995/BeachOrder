import type { Ref } from 'vue'
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

/** Language used when there is no saved choice, no supported browser language and no tenant default. */
export const DEFAULT_LOCALE: AppLocale = 'it'

/** Language explicitly chosen by the user in the language picker. */
export const LOCALE_STORAGE_KEY = 'bo_locale'
/**
 * Marks `bo_locale` as a real user choice. Older builds wrote `bo_locale` on every page load
 * (defaulting to Greek), so a value without this marker is not a choice and is discarded.
 */
const LOCALE_CHOSEN_KEY = 'bo_locale_user'

/**
 * Where the active locale came from. Resolution order on load:
 * 1. `user`: a language the user picked before (persisted);
 * 2. `browser`: the browser's primary language, if supported;
 * 3. `tenant`: the tenant's `default_locale`, applied once tenant info is loaded;
 * 4. `fallback`: {@link DEFAULT_LOCALE}.
 * Only an explicit choice in the language picker is written to localStorage.
 */
export type LocaleSource = 'user' | 'browser' | 'tenant' | 'fallback'

/** Map `it`, `it-IT`, `EN_us`, ... to a supported locale, or `null` if unsupported. */
export function normalizeLocale(value: unknown): AppLocale | null {
  if (typeof value !== 'string') return null
  const base = value.trim().toLowerCase().split(/[-_]/)[0]
  return (SUPPORTED_LOCALES as readonly string[]).includes(base) ? (base as AppLocale) : null
}

function readStoredLocale(): AppLocale | null {
  try {
    const stored = localStorage.getItem(LOCALE_STORAGE_KEY)
    if (stored === null) return null
    if (localStorage.getItem(LOCALE_CHOSEN_KEY) !== '1') {
      // Written automatically by an older build, not chosen by the user.
      localStorage.removeItem(LOCALE_STORAGE_KEY)
      return null
    }
    return normalizeLocale(stored)
  } catch {
    return null
  }
}

/** The browser's primary language (first of `navigator.languages`), if we support it. */
export function detectBrowserLocale(): AppLocale | null {
  if (typeof navigator === 'undefined') return null
  const primary = navigator.languages?.[0] ?? navigator.language
  return normalizeLocale(primary)
}

const storedLocale = readStoredLocale()
const browserLocale = storedLocale ? null : detectBrowserLocale()

let localeSource: LocaleSource = storedLocale ? 'user' : browserLocale ? 'browser' : 'fallback'
const initialLocale: AppLocale = storedLocale ?? browserLocale ?? DEFAULT_LOCALE

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale,
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

function localeRef(): Ref<string> {
  return i18n.global.locale as unknown as Ref<string>
}

/** Current UI locale (also sent to the API as `X-Locale`). */
export function getActiveLocale(): AppLocale {
  return normalizeLocale(localeRef().value) ?? DEFAULT_LOCALE
}

function setActiveLocale(next: AppLocale): void {
  const current = localeRef()
  if (current.value !== next) current.value = next
}

/** Explicit choice from the language picker: applied and persisted. */
export function setUserLocale(code: unknown): AppLocale | null {
  const next = normalizeLocale(code)
  if (!next) return null
  localeSource = 'user'
  setActiveLocale(next)
  try {
    localStorage.setItem(LOCALE_STORAGE_KEY, next)
    localStorage.setItem(LOCALE_CHOSEN_KEY, '1')
  } catch {
    // Storage unavailable (private mode): the choice still applies for this session.
  }
  return next
}

/**
 * Apply the tenant's `default_locale` when neither a saved choice nor a supported browser
 * language decided the locale. Never persisted.
 */
export function applyTenantDefaultLocale(tenantLocale: unknown): void {
  if (localeSource !== 'fallback' && localeSource !== 'tenant') return
  const next = normalizeLocale(tenantLocale)
  if (!next) return
  localeSource = 'tenant'
  setActiveLocale(next)
}
