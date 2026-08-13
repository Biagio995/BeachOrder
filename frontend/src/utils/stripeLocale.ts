import type { AppLocale } from '@/plugins/i18n'

/** Locales supported by both the app and Stripe Payment Element. */
const STRIPE_LOCALES: AppLocale[] = ['it', 'en', 'el', 'de']

export function stripeLocaleFromApp(locale: string): AppLocale | 'auto' {
  const code = locale.slice(0, 2).toLowerCase()
  if ((STRIPE_LOCALES as readonly string[]).includes(code)) {
    return code as AppLocale
  }
  return 'auto'
}
